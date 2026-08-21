<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$options = getopt('', ['source::', 'output-dir::', 'name::']);
$sourcePath = (string)($options['source'] ?? ($root . '/storage/database.sqlite'));
$outputDir = (string)($options['output-dir'] ?? ($root . '/storage/exports'));
$exportName = trim((string)($options['name'] ?? ('bookshop-server-' . date('Ymd-His'))));

$productionTables = [
    'admins',
    'authors',
    'books',
    'content_pages',
    'book_images',
    'orders',
    'order_items',
    'payments',
    'shipments',
    'subscribers',
    'mailing_campaigns',
    'mailing_recipients',
    'email_logs',
    'webhook_logs',
    'settings',
];

try {
    if (!extension_loaded('pdo_sqlite')) {
        throw new RuntimeException('Brak rozszerzenia PDO SQLite.');
    }

    $sourceRealPath = realpath($sourcePath);
    if ($sourceRealPath === false || !is_file($sourceRealPath) || !is_readable($sourceRealPath)) {
        throw new RuntimeException('Nie można odczytać źródłowej bazy SQLite: ' . $sourcePath);
    }
    if (!preg_match('/^[a-z0-9][a-z0-9._-]*$/i', $exportName)) {
        throw new RuntimeException('Nazwa eksportu może zawierać tylko litery, cyfry, kropkę, myślnik i podkreślenie.');
    }
    if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
        throw new RuntimeException('Nie można utworzyć katalogu eksportu: ' . $outputDir);
    }
    $outputRealPath = realpath($outputDir);
    if ($outputRealPath === false || !is_writable($outputRealPath)) {
        throw new RuntimeException('Katalog eksportu nie jest zapisywalny: ' . $outputDir);
    }

    $sqlitePath = $outputRealPath . DIRECTORY_SEPARATOR . $exportName . '.sqlite';
    $mysqlPath = $outputRealPath . DIRECTORY_SEPARATOR . $exportName . '.mysql.sql';
    $manifestPath = $outputRealPath . DIRECTORY_SEPARATOR . $exportName . '.manifest.json';
    foreach ([$sqlitePath, $mysqlPath, $manifestPath] as $targetPath) {
        if (file_exists($targetPath)) {
            throw new RuntimeException('Plik eksportu już istnieje: ' . $targetPath);
        }
    }

    $source = sqliteConnection($sourceRealPath);
    $sourceIntegrity = integrityCheck($source);
    if ($sourceIntegrity !== 'ok') {
        throw new RuntimeException('Źródłowa baza nie przeszła kontroli integralności: ' . $sourceIntegrity);
    }

    $sourceTables = tableNames($source);
    $missingTables = array_values(array_diff($productionTables, $sourceTables));
    if ($missingTables !== []) {
        throw new RuntimeException('Źródłowa baza nie zawiera wymaganych tabel: ' . implode(', ', $missingTables));
    }

    $sourceCounts = tableCounts($source, $productionTables);
    $excludedTables = array_values(array_diff($sourceTables, $productionTables));
    $excludedCounts = tableCounts($source, $excludedTables);

    $source->exec('VACUUM INTO ' . $source->quote($sqlitePath));
    $snapshot = sqliteConnection($sqlitePath);
    $snapshot->beginTransaction();
    foreach ($excludedTables as $table) {
        $snapshot->exec('DROP TABLE IF EXISTS ' . sqliteIdentifier($table));
    }
    $snapshot->commit();
    $snapshot->exec('VACUUM');

    $snapshotIntegrity = integrityCheck($snapshot);
    if ($snapshotIntegrity !== 'ok') {
        throw new RuntimeException('Kopia SQLite nie przeszła kontroli integralności: ' . $snapshotIntegrity);
    }
    $snapshotCounts = tableCounts($snapshot, $productionTables);
    if ($snapshotCounts !== $sourceCounts) {
        throw new RuntimeException('Liczba rekordów w kopii nie zgadza się ze źródłem.');
    }

    $sql = mysqlDump($snapshot, $productionTables, basename($sourceRealPath));
    if (file_put_contents($mysqlPath, $sql, LOCK_EX) === false) {
        throw new RuntimeException('Nie udało się zapisać eksportu MySQL/MariaDB.');
    }

    $manifest = [
        'generated_at' => date(DATE_ATOM),
        'source' => [
            'file' => $sourceRealPath,
            'integrity' => $sourceIntegrity,
            'size_bytes' => filesize($sourceRealPath),
        ],
        'production_tables' => $productionTables,
        'row_counts' => $snapshotCounts,
        'excluded_runtime_and_migration_tables' => $excludedCounts,
        'exports' => [
            'sqlite' => fileMetadata($sqlitePath),
            'mysql_mariadb' => fileMetadata($mysqlPath),
        ],
        'verification' => [
            'sqlite_integrity' => $snapshotIntegrity,
            'source_and_snapshot_counts_equal' => true,
        ],
        'import_note' => 'Plik MySQL/MariaDB odtwarza tabele produkcyjne w pustej, wybranej bazie. Zawiera konta administratorów i dane klientów — przechowuj go prywatnie.',
    ];
    if (file_put_contents(
        $manifestPath,
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        LOCK_EX
    ) === false) {
        throw new RuntimeException('Nie udało się zapisać manifestu eksportu.');
    }

    echo 'SQLite: ' . $sqlitePath . PHP_EOL;
    echo 'MySQL/MariaDB: ' . $mysqlPath . PHP_EOL;
    echo 'Manifest: ' . $manifestPath . PHP_EOL;
    echo 'Integralność: OK' . PHP_EOL;
    foreach ($snapshotCounts as $table => $count) {
        echo $table . ': ' . $count . PHP_EOL;
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'BŁĄD: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

function sqliteConnection(string $path): PDO
{
    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ]);
    $pdo->exec('PRAGMA busy_timeout = 10000');
    return $pdo;
}

function integrityCheck(PDO $pdo): string
{
    $result = $pdo->query('PRAGMA integrity_check')->fetchColumn();
    return is_string($result) ? strtolower(trim($result)) : 'unknown';
}

/** @return list<string> */
function tableNames(PDO $pdo): array
{
    $statement = $pdo->query(
        "SELECT name FROM sqlite_master
         WHERE type = 'table' AND name NOT LIKE 'sqlite_%'
         ORDER BY name"
    );
    return array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
}

/** @param list<string> $tables
 *  @return array<string,int>
 */
function tableCounts(PDO $pdo, array $tables): array
{
    $counts = [];
    foreach ($tables as $table) {
        $counts[$table] = (int)$pdo->query(
            'SELECT COUNT(*) FROM ' . sqliteIdentifier($table)
        )->fetchColumn();
    }
    return $counts;
}

function sqliteIdentifier(string $identifier): string
{
    return '"' . str_replace('"', '""', $identifier) . '"';
}

function mysqlIdentifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

/** @param list<string> $tables */
function mysqlDump(PDO $pdo, array $tables, string $sourceName): string
{
    $lines = [
        '-- Bookshop — spójny eksport produkcyjny',
        '-- Źródło: ' . $sourceName,
        '-- Wygenerowano: ' . date(DATE_ATOM),
        '-- Importuj do pustej bazy MySQL 8+ albo MariaDB 10.4+.',
        '',
        'SET NAMES utf8mb4;',
        "SET time_zone = '+00:00';",
        "SET SESSION sql_mode = 'NO_BACKSLASH_ESCAPES';",
        'SET FOREIGN_KEY_CHECKS = 0;',
        'START TRANSACTION;',
        '',
    ];

    foreach (array_reverse($tables) as $table) {
        $lines[] = 'DROP TABLE IF EXISTS ' . mysqlIdentifier($table) . ';';
    }
    $lines[] = '';

    foreach ($tables as $table) {
        $columns = sqliteColumns($pdo, $table);
        if ($columns === []) {
            throw new RuntimeException('Nie można odczytać kolumn tabeli: ' . $table);
        }
        $definitions = [];
        foreach ($columns as $column) {
            $name = (string)$column['name'];
            $definition = mysqlIdentifier($name) . ' ' . mysqlColumnType($name, (string)$column['type'], (int)$column['pk'] > 0);
            if ((int)$column['pk'] > 0) {
                $definition .= ' NOT NULL AUTO_INCREMENT PRIMARY KEY';
            } else {
                $definition .= (int)$column['notnull'] === 1 ? ' NOT NULL' : ' NULL';
                if ($column['dflt_value'] !== null) {
                    $definition .= ' DEFAULT ' . mysqlDefault((string)$column['dflt_value']);
                }
            }
            $definitions[] = '  ' . $definition;
        }

        $indexes = sqliteIndexes($pdo, $table);
        $knownIndexColumns = [];
        foreach ($indexes as $index) {
            $knownIndexColumns[implode('|', $index['columns'])] = true;
        }
        foreach (recommendedIndexes($table, array_column($columns, 'name')) as $index) {
            $signature = implode('|', $index['columns']);
            if (!isset($knownIndexColumns[$signature])) {
                $indexes[] = $index;
                $knownIndexColumns[$signature] = true;
            }
        }
        foreach ($indexes as $index) {
            $indexColumns = array_map('mysqlIdentifier', $index['columns']);
            if ($indexColumns === []) {
                continue;
            }
            $definitions[] = sprintf(
                '  %sKEY %s (%s)',
                $index['unique'] ? 'UNIQUE ' : '',
                mysqlIdentifier($index['name']),
                implode(', ', $indexColumns)
            );
        }

        $lines[] = 'CREATE TABLE ' . mysqlIdentifier($table) . " (\n"
            . implode(",\n", $definitions)
            . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $lines[] = '';
        appendTableData($pdo, $table, $columns, $lines);
    }

    $lines[] = 'COMMIT;';
    $lines[] = 'SET FOREIGN_KEY_CHECKS = 1;';
    $lines[] = '';
    return implode("\n", $lines);
}

/** @return list<array<string,mixed>> */
function sqliteColumns(PDO $pdo, string $table): array
{
    return $pdo->query('PRAGMA table_info(' . sqliteIdentifier($table) . ')')->fetchAll();
}

/** @return list<array{name:string,unique:bool,columns:list<string>}> */
function sqliteIndexes(PDO $pdo, string $table): array
{
    $indexes = [];
    foreach ($pdo->query('PRAGMA index_list(' . sqliteIdentifier($table) . ')')->fetchAll() as $index) {
        $origin = (string)($index['origin'] ?? '');
        if ($origin === 'pk') {
            continue;
        }
        $name = (string)$index['name'];
        $columns = [];
        foreach ($pdo->query('PRAGMA index_info(' . sqliteIdentifier($name) . ')')->fetchAll() as $column) {
            if (isset($column['name'])) {
                $columns[] = (string)$column['name'];
            }
        }
        if ($columns === []) {
            continue;
        }
        $indexes[] = [
            'name' => preg_replace('/^sqlite_autoindex_/', 'uq_', $name) ?: ('idx_' . $table),
            'unique' => (int)($index['unique'] ?? 0) === 1,
            'columns' => $columns,
        ];
    }
    return $indexes;
}

/**
 * @param list<string> $availableColumns
 * @return list<array{name:string,unique:bool,columns:list<string>}>
 */
function recommendedIndexes(string $table, array $availableColumns): array
{
    $map = [
        'books' => [['status'], ['product_type'], ['old_wp_id'], ['sku']],
        'content_pages' => [['status']],
        'book_images' => [['book_id'], ['book_id', 'type']],
        'orders' => [['old_wp_id'], ['status'], ['created_at'], ['payment_status'], ['shipment_status'], ['customer_email']],
        'order_items' => [['order_id'], ['book_id']],
        'payments' => [['order_id'], ['provider_session_id'], ['provider_payment_id'], ['status']],
        'shipments' => [['order_id'], ['provider_shipment_id'], ['tracking_number'], ['status']],
        'subscribers' => [['status']],
        'mailing_recipients' => [['campaign_id'], ['status']],
        'email_logs' => [['status'], ['order_id'], ['to_email']],
        'webhook_logs' => [['provider'], ['order_id'], ['status']],
    ];
    $result = [];
    foreach ($map[$table] ?? [] as $columns) {
        if (array_diff($columns, $availableColumns) !== []) {
            continue;
        }
        $result[] = [
            'name' => 'idx_' . $table . '_' . implode('_', $columns),
            'unique' => false,
            'columns' => $columns,
        ];
    }
    return $result;
}

function mysqlColumnType(string $column, string $sqliteType, bool $primary): string
{
    if ($primary) {
        return 'BIGINT UNSIGNED';
    }
    $type = strtoupper(trim($sqliteType));
    if (str_ends_with($column, '_json') || in_array($column, ['description', 'content', 'body', 'terms_snapshot'], true)) {
        return 'LONGTEXT';
    }
    if (in_array($column, ['manage_stock', 'consent_marketing', 'is_secret'], true)) {
        return 'TINYINT(1)';
    }
    if (preg_match('/^VARCHAR\(\d+\)$/', $type) || preg_match('/^CHAR\(\d+\)$/', $type)) {
        return $type;
    }
    if (preg_match('/^DECIMAL\(\d+,\d+\)$/', $type)) {
        return $type;
    }
    if (str_contains($type, 'INT')) {
        return 'INT';
    }
    if (str_contains($type, 'REAL') || str_contains($type, 'FLOAT') || str_contains($type, 'DOUBLE')) {
        return 'DOUBLE';
    }
    if (str_contains($type, 'BLOB')) {
        return 'LONGBLOB';
    }
    if (str_contains($type, 'DATE') || str_contains($type, 'TIME')) {
        return 'DATETIME';
    }
    return 'TEXT';
}

function mysqlDefault(string $default): string
{
    $trimmed = trim($default);
    if (strcasecmp($trimmed, 'NULL') === 0) {
        return 'NULL';
    }
    if (preg_match('/^-?\d+(?:\.\d+)?$/', $trimmed)) {
        return $trimmed;
    }
    if (in_array(strtoupper($trimmed), ['CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP()'], true)) {
        return 'CURRENT_TIMESTAMP';
    }
    if (
        (str_starts_with($trimmed, "'") && str_ends_with($trimmed, "'"))
        || (str_starts_with($trimmed, '"') && str_ends_with($trimmed, '"'))
    ) {
        return mysqlString(substr($trimmed, 1, -1));
    }
    return mysqlString($trimmed);
}

/**
 * @param list<array<string,mixed>> $columns
 * @param list<string> $lines
 */
function appendTableData(PDO $pdo, string $table, array $columns, array &$lines): void
{
    $columnNames = array_map(static fn(array $column): string => (string)$column['name'], $columns);
    $columnTypes = [];
    foreach ($columns as $column) {
        $columnTypes[(string)$column['name']] = strtoupper((string)$column['type']);
    }
    $order = in_array('id', $columnNames, true) ? ' ORDER BY ' . sqliteIdentifier('id') : '';
    $statement = $pdo->query('SELECT * FROM ' . sqliteIdentifier($table) . $order);
    $batch = [];
    while ($row = $statement->fetch()) {
        $values = [];
        foreach ($columnNames as $columnName) {
            $values[] = mysqlValue($row[$columnName] ?? null, $columnTypes[$columnName] ?? '');
        }
        $batch[] = '(' . implode(', ', $values) . ')';
        if (count($batch) >= 100) {
            appendInsertBatch($table, $columnNames, $batch, $lines);
            $batch = [];
        }
    }
    if ($batch !== []) {
        appendInsertBatch($table, $columnNames, $batch, $lines);
    }
    $lines[] = '';
}

/** @param list<string> $columns
 *  @param list<string> $batch
 *  @param list<string> $lines
 */
function appendInsertBatch(string $table, array $columns, array $batch, array &$lines): void
{
    $lines[] = 'INSERT INTO ' . mysqlIdentifier($table)
        . ' (' . implode(', ', array_map('mysqlIdentifier', $columns)) . ") VALUES\n"
        . implode(",\n", $batch) . ';';
}

function mysqlValue(mixed $value, string $declaredType): string
{
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string)$value;
    }
    $string = (string)$value;
    if (
        preg_match('/(?:INT|DECIMAL|NUMERIC|REAL|FLOAT|DOUBLE)/', $declaredType)
        && is_numeric($string)
    ) {
        return $string;
    }
    if (str_contains($declaredType, 'BLOB') || str_contains($string, "\0")) {
        return "X'" . bin2hex($string) . "'";
    }
    return mysqlString($string);
}

function mysqlString(string $value): string
{
    return "'" . str_replace("'", "''", $value) . "'";
}

/** @return array{file:string,size_bytes:int,sha256:string} */
function fileMetadata(string $path): array
{
    return [
        'file' => $path,
        'size_bytes' => (int)filesize($path),
        'sha256' => (string)hash_file('sha256', $path),
    ];
}
