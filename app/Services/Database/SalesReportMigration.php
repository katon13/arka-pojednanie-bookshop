<?php
namespace Book100\Services\Database;

use Book100\Core\Database;
use Book100\Core\Paths;
use PDO;
use RuntimeException;

final class SalesReportMigration
{
    /** @return array{ok:bool,driver:string,columns:int,indexes:int,settings:int} */
    public static function migrate(): array
    {
        $directory = Paths::projectRoot() . '/storage/reports';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Nie można utworzyć chronionego katalogu raportów.');
        }

        $pdo = Database::pdo();
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (!in_array($driver, ['mysql', 'sqlite'], true)) {
            throw new RuntimeException('Nieobsługiwany silnik bazy dla migracji raportów.');
        }
        $auto = $driver === 'mysql' ? 'INTEGER PRIMARY KEY AUTO_INCREMENT' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $json = $driver === 'mysql' ? 'JSON' : 'TEXT';
        $pdo->exec(self::tableSql($auto));

        $columns = [
            'orders' => [
                'vat_rate'=>'DECIMAL(5,2) NULL',
                'subtotal_net'=>'DECIMAL(10,2) NULL',
                'subtotal_vat'=>'DECIMAL(10,2) NULL',
                'discount_net'=>'DECIMAL(10,2) NULL',
                'discount_vat'=>'DECIMAL(10,2) NULL',
                'shipping_net'=>'DECIMAL(10,2) NULL',
                'shipping_vat'=>'DECIMAL(10,2) NULL',
                'total_net'=>'DECIMAL(10,2) NULL',
                'total_vat'=>'DECIMAL(10,2) NULL',
            ],
            'order_items' => [
                'vat_rate'=>'DECIMAL(5,2) NULL',
                'unit_price_net'=>'DECIMAL(10,2) NULL',
                'unit_vat'=>'DECIMAL(10,2) NULL',
                'total_net'=>'DECIMAL(10,2) NULL',
                'total_vat'=>'DECIMAL(10,2) NULL',
            ],
            'email_logs' => [
                'attachments_json'=>$json . ' NULL',
            ],
        ];
        $columnCount = 0;
        foreach ($columns as $table => $definitions) {
            foreach ($definitions as $column => $definition) {
                if (!self::columnExists($pdo, $driver, $table, $column)) {
                    $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
                }
                if (!self::columnExists($pdo, $driver, $table, $column)) {
                    throw new RuntimeException("Migracja nie utworzyła pola {$table}.{$column}.");
                }
                $columnCount++;
            }
        }

        $indexes = [
            'idx_orders_paid_at'=>['orders', 'paid_at'],
            'idx_orders_refunded_at'=>['orders', 'refunded_at'],
            'idx_sales_reports_email_log_id'=>['sales_reports', 'email_log_id'],
        ];
        foreach ($indexes as $name => [$table, $column]) {
            if (!self::indexExists($pdo, $driver, $table, $name)) {
                $pdo->exec("CREATE INDEX {$name} ON {$table} ({$column})");
            }
            if (!self::indexExists($pdo, $driver, $table, $name)) {
                throw new RuntimeException("Migracja nie utworzyła indeksu {$name}.");
            }
        }

        $defaults = [
            'seller_legal_name'=>'Agencja ARKA',
            'seller_owner_name'=>'Maciej Karwacki-Niecewicz',
            'seller_street'=>'ul. Św. Wawrzyńca 38/10',
            'seller_post_code'=>'31-052',
            'seller_city'=>'Kraków',
            'seller_nip'=>'7791563475',
            'sales_vat_rate'=>'5.00',
            'sales_report_enabled'=>'0',
            'sales_report_day'=>'5',
            'sales_report_email'=>'',
        ];
        $select = $pdo->prepare('SELECT id FROM settings WHERE name=? LIMIT 1');
        $insert = $pdo->prepare('INSERT INTO settings (name, value, is_secret, updated_at) VALUES (?, ?, 0, ?)');
        $inserted = 0;
        foreach ($defaults as $name => $value) {
            $select->execute([$name]);
            if ($select->fetchColumn()) continue;
            $insert->execute([$name, $value, date('Y-m-d H:i:s')]);
            $inserted++;
        }

        return [
            'ok'=>true,
            'driver'=>$driver,
            'columns'=>$columnCount,
            'indexes'=>count($indexes),
            'settings'=>$inserted,
        ];
    }

    private static function tableSql(string $auto): string
    {
        return "CREATE TABLE IF NOT EXISTS sales_reports (id $auto, period_year INTEGER NOT NULL, period_month INTEGER NOT NULL, period_start DATE NOT NULL, period_end DATE NOT NULL, file_path VARCHAR(255) NULL, status VARCHAR(30) NOT NULL DEFAULT 'generating', recipient_email VARCHAR(190) NULL, send_status VARCHAR(30) NOT NULL DEFAULT 'not_sent', email_log_id INTEGER NULL, orders_count INTEGER NOT NULL DEFAULT 0, units_count INTEGER NOT NULL DEFAULT 0, sales_net DECIMAL(12,2) NOT NULL DEFAULT 0.00, sales_vat DECIMAL(12,2) NOT NULL DEFAULT 0.00, sales_gross DECIMAL(12,2) NOT NULL DEFAULT 0.00, shipping_net DECIMAL(12,2) NOT NULL DEFAULT 0.00, shipping_vat DECIMAL(12,2) NOT NULL DEFAULT 0.00, shipping_gross DECIMAL(12,2) NOT NULL DEFAULT 0.00, discount_gross DECIMAL(12,2) NOT NULL DEFAULT 0.00, refund_net DECIMAL(12,2) NOT NULL DEFAULT 0.00, refund_vat DECIMAL(12,2) NOT NULL DEFAULT 0.00, refund_gross DECIMAL(12,2) NOT NULL DEFAULT 0.00, final_net DECIMAL(12,2) NOT NULL DEFAULT 0.00, final_vat DECIMAL(12,2) NOT NULL DEFAULT 0.00, final_gross DECIMAL(12,2) NOT NULL DEFAULT 0.00, last_error TEXT NULL, generated_at DATETIME NULL, sent_at DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NULL, UNIQUE(period_year, period_month))";
    }

    private static function columnExists(PDO $pdo, string $driver, string $table, string $column): bool
    {
        if ($driver === 'mysql') {
            $statement = $pdo->prepare(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?'
            );
            $statement->execute([$table, $column]);
            return (int)$statement->fetchColumn() > 0;
        }
        foreach ($pdo->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC) as $definition) {
            if (($definition['name'] ?? '') === $column) return true;
        }
        return false;
    }

    private static function indexExists(PDO $pdo, string $driver, string $table, string $index): bool
    {
        if ($driver === 'mysql') {
            $statement = $pdo->prepare(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?'
            );
            $statement->execute([$table, $index]);
            return (int)$statement->fetchColumn() > 0;
        }
        foreach ($pdo->query("PRAGMA index_list({$table})")->fetchAll(PDO::FETCH_ASSOC) as $definition) {
            if (($definition['name'] ?? '') === $index) return true;
        }
        return false;
    }
}
