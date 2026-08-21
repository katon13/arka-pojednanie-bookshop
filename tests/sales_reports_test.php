<?php
declare(strict_types=1);

use Book100\Core\Database;
use Book100\Core\View;
use Book100\Repository\OrderRepository;
use Book100\Repository\EmailLogRepository;
use Book100\Repository\SalesReportRepository;
use Book100\Repository\SettingsRepository;
use Book100\Services\Database\Installer;
use Book100\Services\Database\SalesReportMigration;
use Book100\Services\Mail\Mailer;
use Book100\Services\Sales\Money;
use Book100\Services\Sales\SalesReportScheduler;
use Book100\Services\Sales\SalesReportService;
use Book100\Services\Storefront\StorefrontSettingsService;

require dirname(__DIR__) . '/app/bootstrap.php';

$root = dirname(__DIR__);
$database = $root . '/storage/test-sales-reports.sqlite';
$keepArtifacts = in_array('--keep-artifacts', $argv, true);
@unlink($database);
$_ENV['DB_CONNECTION'] = 'sqlite';
$_ENV['DB_DATABASE'] = 'storage/test-sales-reports.sqlite';
$_ENV['ADMIN_EMAIL'] = 'admin@example.test';
$_ENV['ADMIN_PASSWORD_CHANGE_ME'] = 'test-password-only';
$_ENV['APP_URL'] = 'https://example.test';
$_ENV['MAIL_TRANSPORT'] = 'log';
$_ENV['MAIL_FROM'] = 'sklep@example.test';
$_ENV['MAIL_FROM_NAME'] = 'ARKA TEST';
$_ENV['MAIL_REPLY_TO'] = 'sklep@example.test';
$_ENV['ADMIN_BASE_PATH'] = '/admin';
$_SERVER['REQUEST_URI'] = '/admin/sales?year=2025&month=8';
$_SERVER['HTTP_HOST'] = 'example.test';
$_SERVER['HTTPS'] = 'on';

$createdFiles = [];
$assertions = 0;

function check(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) throw new RuntimeException('TEST FAILED: ' . $message);
}

function newestFile(array $paths): ?string
{
    if ($paths === []) return null;
    usort($paths, static fn(string $left, string $right): int => filemtime($right) <=> filemtime($left));
    return $paths[0];
}

function insertOrder(PDO $pdo, array $order, array $item): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO orders
        (order_number,status,customer_email,customer_name,delivery_method,subtotal_gross,discount_gross,shipping_gross,total_gross,
         currency,payment_provider,payment_status,shipment_status,stock_state,created_at,updated_at,paid_at,refunded_at,
         vat_rate,subtotal_net,subtotal_vat,discount_net,discount_vat,shipping_net,shipping_vat,total_net,total_vat)
        VALUES
        (:order_number,:status,:customer_email,:customer_name,:delivery_method,:subtotal_gross,0,:shipping_gross,:total_gross,
         :currency,:payment_provider,:payment_status,:shipment_status,:stock_state,:created_at,:updated_at,:paid_at,:refunded_at,
         :vat_rate,:subtotal_net,:subtotal_vat,0,0,:shipping_net,:shipping_vat,:total_net,:total_vat)'
    );
    $stmt->execute($order);
    $orderId = (int)$pdo->lastInsertId();
    $itemStmt = $pdo->prepare(
        'INSERT INTO order_items
         (order_id,sku,title,quantity,unit_price_gross,total_gross,vat_rate,unit_price_net,unit_vat,total_net,total_vat)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)'
    );
    $itemStmt->execute([
        $orderId, $item['sku'], $item['title'], $item['quantity'], $item['unit_price_gross'], $item['total_gross'],
        $item['vat_rate'], $item['unit_price_net'], $item['unit_vat'], $item['total_net'], $item['total_vat'],
    ]);
    return $orderId;
}

try {
    Installer::install(false);
    $pdo = Database::pdo();
    check($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite', 'test uses isolated SQLite');
    $migration = SalesReportMigration::migrate();
    check($migration['ok'] && $migration['columns'] === 15 && $migration['indexes'] === 3, 'narrow report migration verifies its schema');
    check(SalesReportMigration::migrate()['settings'] === 0, 'report migration is idempotent');

    $split = Money::splitGross(4200, '5.00');
    check($split === ['net'=>4000, 'vat'=>200, 'gross'=>4200], '42.00 gross splits to 40.00 net and 2.00 VAT');
    $shippingSplit = Money::splitGross(1200, '5.00');
    check($shippingSplit['net'] === 1143 && $shippingSplit['vat'] === 57, 'shipping rounding is stable');
    check(Money::normalizedRate('5') === '5.00', 'VAT rate normalization');

    (new SettingsRepository())->saveValues([
        'seller_legal_name'=>'Agencja ARKA',
        'seller_owner_name'=>'Maciej Karwacki-Niecewicz',
        'seller_street'=>'ul. Św. Wawrzyńca 38/10',
        'seller_post_code'=>'31-052',
        'seller_city'=>'Kraków',
        'seller_nip'=>'7791563475',
        'sales_vat_rate'=>'5.00',
        'sales_report_enabled'=>'1',
        'sales_report_day'=>'5',
        'sales_report_email'=>'ksiegowosc@example.test',
    ]);

    $createdOrder = (new OrderRepository())->createForBook([
        'id'=>null,
        'old_wp_id'=>null,
        'sku'=>'VAT-SNAPSHOT-TEST',
        'title'=>'Test zapisu VAT',
        'price_gross'=>'42.00',
        'product_type'=>'paper',
        'status'=>'active',
        'manage_stock'=>0,
        'release_date'=>null,
    ], [
        'quantity'=>1,
        'customer_name'=>'Test VAT',
        'customer_email'=>'vat@example.test',
        'customer_phone'=>'500000000',
        'delivery_method'=>'pickup',
        'payment_provider'=>'przelewy24',
    ]);
    check(Money::cents((string)($createdOrder['subtotal_gross'] ?? '')) === 4200, 'existing gross order total is preserved');
    check(Money::cents((string)($createdOrder['subtotal_net'] ?? '')) === 4000 && Money::cents((string)($createdOrder['subtotal_vat'] ?? '')) === 200, 'order stores VAT snapshot');
    check(Money::cents((string)($createdOrder['items'][0]['unit_price_net'] ?? '')) === 4000 && Money::cents((string)($createdOrder['items'][0]['unit_vat'] ?? '')) === 200, 'order item stores VAT snapshot');
    $createdOrderId = (int)$createdOrder['id'];
    $pdo->prepare('DELETE FROM payments WHERE order_id = ?')->execute([$createdOrderId]);
    $pdo->prepare('DELETE FROM order_items WHERE order_id = ?')->execute([$createdOrderId]);
    $pdo->prepare('DELETE FROM orders WHERE id = ?')->execute([$createdOrderId]);

    insertOrder($pdo, [
        ':order_number'=>'ARKA-2025-000001', ':status'=>'paid_waiting_for_shipment', ':customer_email'=>'customer@example.test',
        ':customer_name'=>'Klient Testowy', ':delivery_method'=>'inpost_locker', ':subtotal_gross'=>'42.00', ':shipping_gross'=>'12.00',
        ':total_gross'=>'54.00', ':currency'=>'PLN', ':payment_provider'=>'przelewy24', ':payment_status'=>'paid',
        ':shipment_status'=>'not_created', ':stock_state'=>'committed', ':created_at'=>'2025-08-10 09:00:00',
        ':updated_at'=>'2025-08-10 09:05:00', ':paid_at'=>'2025-08-10 09:05:00', ':refunded_at'=>null,
        ':vat_rate'=>'5.00', ':subtotal_net'=>'40.00', ':subtotal_vat'=>'2.00', ':shipping_net'=>'11.43', ':shipping_vat'=>'0.57',
        ':total_net'=>'51.43', ':total_vat'=>'2.57',
    ], [
        'sku'=>'BOOK-001', 'title'=>'Książka testowa', 'quantity'=>2, 'unit_price_gross'=>'21.00', 'total_gross'=>'42.00',
        'vat_rate'=>'5.00', 'unit_price_net'=>'20.00', 'unit_vat'=>'1.00', 'total_net'=>'40.00', 'total_vat'=>'2.00',
    ]);
    insertOrder($pdo, [
        ':order_number'=>'ARKA-2025-000002', ':status'=>'refunded', ':customer_email'=>'other@example.test',
        ':customer_name'=>'Drugi Klient', ':delivery_method'=>'ebook', ':subtotal_gross'=>'21.00', ':shipping_gross'=>'0.00',
        ':total_gross'=>'21.00', ':currency'=>'PLN', ':payment_provider'=>'przelewy24', ':payment_status'=>'refunded',
        ':shipment_status'=>'not_required', ':stock_state'=>'not_required', ':created_at'=>'2025-07-20 11:00:00',
        ':updated_at'=>'2025-08-12 12:00:00', ':paid_at'=>'2025-07-20 11:05:00', ':refunded_at'=>'2025-08-12 12:00:00',
        ':vat_rate'=>'5.00', ':subtotal_net'=>'20.00', ':subtotal_vat'=>'1.00', ':shipping_net'=>'0.00', ':shipping_vat'=>'0.00',
        ':total_net'=>'20.00', ':total_vat'=>'1.00',
    ], [
        'sku'=>'EBOOK-001', 'title'=>'E-book testowy', 'quantity'=>1, 'unit_price_gross'=>'21.00', 'total_gross'=>'21.00',
        'vat_rate'=>'5.00', 'unit_price_net'=>'20.00', 'unit_vat'=>'1.00', 'total_net'=>'20.00', 'total_vat'=>'1.00',
    ]);

    $service = new SalesReportService();
    $incompleteBlocked = false;
    try {
        $service->generateStored((int)date('Y'), (int)date('n'), 'ksiegowosc@example.test');
    } catch (RuntimeException) {
        $incompleteBlocked = true;
    }
    check($incompleteBlocked, 'incomplete current month cannot be archived');
    $dataset = $service->dataset(2025, 8);
    $summary = $dataset['summary'];
    check($summary['paid_orders'] === 1, 'paid orders are counted by paid_at');
    check($summary['units'] === 2, 'sold units are counted');
    check($summary['sales_net'] === '40.00' && $summary['sales_vat'] === '2.00' && $summary['sales_gross'] === '42.00', 'product totals agree');
    check($summary['shipping_net'] === '11.43' && $summary['shipping_vat'] === '0.57' && $summary['shipping_gross'] === '12.00', 'shipping totals agree');
    check($summary['refund_net'] === '20.00' && $summary['refund_vat'] === '1.00' && $summary['refund_gross'] === '21.00', 'refund is posted in refund month');
    check($summary['final_net'] === '31.43' && $summary['final_vat'] === '1.57' && $summary['final_gross'] === '33.00', 'final totals agree');
    check(count($dataset['rows']) === 2 && $dataset['rows'][0]['type'] === 'SPRZEDAŻ' && $dataset['rows'][1]['type'] === 'ZWROT', 'sale and refund rows are present');
    check($service->exportFilename($dataset, 'xlsx') === 'sprzedaz-2025-08.xlsx', 'monthly report has a clear filename');

    $yearDataset = $service->datasetForRange('year', 2025);
    check($yearDataset['period']['start_date'] === '2025-01-01' && $yearDataset['period']['end_date'] === '2025-12-31', 'yearly report covers the full selected year');
    check($yearDataset['summary']['paid_orders'] === 2 && $yearDataset['summary']['units'] === 3 && $yearDataset['summary']['returned_units'] === 1, 'yearly report combines all monthly events');
    check($yearDataset['summary']['final_net'] === '51.43' && $yearDataset['summary']['final_vat'] === '2.57' && $yearDataset['summary']['final_gross'] === '54.00', 'yearly report totals agree');
    check(count($yearDataset['rows']) === 3 && $service->exportFilename($yearDataset, 'xlsx') === 'sprzedaz-2025.xlsx', 'yearly report rows and filename are correct');

    $invalidRange = false;
    try {
        $service->datasetForRange('all', 2025);
    } catch (RuntimeException) {
        $invalidRange = true;
    }
    check($invalidRange, 'only monthly and yearly report ranges are accepted');

    $csv = $service->csv($dataset);
    check(str_contains($csv, 'ARKA-2025-000001') && str_contains($csv, 'ARKA-2025-000002'), 'CSV contains report rows');
    check(!str_contains($csv, 'customer@example.test') && !str_contains($csv, 'other@example.test'), 'CSV contains no customer email');

    $xlsxPath = $root . '/storage/reports/test-sales-report.xlsx';
    $service->writeXlsx($dataset, $xlsxPath);
    $createdFiles[] = $xlsxPath;
    check(is_file($xlsxPath) && filesize($xlsxPath) > 3000, 'XLSX is generated');
    check(substr((string)file_get_contents($xlsxPath), 0, 2) === 'PK', 'XLSX is a ZIP package');
    $yearXlsxPath = $root . '/storage/reports/test-sales-report-year.xlsx';
    $service->writeXlsx($yearDataset, $yearXlsxPath);
    $createdFiles[] = $yearXlsxPath;
    check(is_file($yearXlsxPath) && filesize($yearXlsxPath) > 3000, 'yearly XLSX is generated');

    $report = $service->generateStored(2025, 8, 'ksiegowosc@example.test');
    $createdFiles[] = $root . '/' . $report['file_path'];
    check(($report['status'] ?? '') === 'generated', 'stored report is generated');
    $sameReport = $service->generateStored(2025, 8, 'ksiegowosc@example.test');
    check((int)$sameReport['id'] === (int)$report['id'], 'same monthly report is not duplicated');

    $sentReport = $service->queueReport($report, 'ksiegowosc@example.test', true);
    check(($sentReport['send_status'] ?? '') === 'sent', 'Mailer processes XLSX attachment');
    $emailCount = (int)$pdo->query("SELECT COUNT(*) FROM email_logs WHERE template='sales_report_monthly'")->fetchColumn();
    check($emailCount === 1, 'one report email was queued');
    $reportEmailId = (int)($sentReport['email_log_id'] ?? 0);
    $reportEml = newestFile(glob($root . '/storage/logs/mail/mail-*-' . $reportEmailId . '.eml') ?: []);
    $reportRaw = $reportEml === null ? '' : (string)file_get_contents($reportEml);
    check(str_contains($reportRaw, 'multipart/mixed') && str_contains($reportRaw, 'multipart/alternative'), 'report EML has nested mixed and alternative MIME parts');
    check(str_contains($reportRaw, 'sprzedaz-2025-08.xlsx') && str_contains($reportRaw, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'), 'EML contains typed XLSX attachment');

    $plainEmailId = (new EmailLogRepository())->queueCustom(
        'customer@example.test',
        'Test wiadomości bez załącznika',
        'Dotychczasowa poczta działa bez zmian.',
        'transactional_regression'
    );
    (new Mailer())->processOne($plainEmailId);
    $plainEml = newestFile(glob($root . '/storage/logs/mail/mail-*-' . $plainEmailId . '.eml') ?: []);
    $plainRaw = $plainEml === null ? '' : (string)file_get_contents($plainEml);
    check(str_contains($plainRaw, 'multipart/alternative') && !str_contains($plainRaw, 'multipart/mixed'), 'existing email without attachments keeps the original MIME shape');

    $schedulerExisting = (new SalesReportScheduler())->run(new DateTimeImmutable('2025-09-05 08:00:00'));
    check(($schedulerExisting['status'] ?? '') === 'already_queued', 'scheduler does not duplicate an existing monthly email');
    check((int)$pdo->query("SELECT COUNT(*) FROM email_logs WHERE template='sales_report_monthly'")->fetchColumn() === 1, 'duplicate scheduler run creates no email');

    $schedulerNew = (new SalesReportScheduler())->run(new DateTimeImmutable('2025-10-05 08:00:00'));
    check(($schedulerNew['status'] ?? '') === 'queued', 'scheduler queues previous complete month');
    $schedulerAgain = (new SalesReportScheduler())->run(new DateTimeImmutable('2025-10-06 08:00:00'));
    check(($schedulerAgain['status'] ?? '') === 'already_queued', 'scheduler is idempotent after configured day');
    check((int)$pdo->query("SELECT COUNT(*) FROM sales_reports WHERE period_year=2025 AND period_month=9")->fetchColumn() === 1, 'only one September report exists');

    $state = (new StorefrontSettingsService())->state();
    $invalid = false;
    try {
        (new StorefrontSettingsService())->save(array_merge($state, [
            'sales_report_enabled'=>'1', 'sales_report_day'=>'29', 'sales_report_email'=>'bad-address',
        ]), []);
    } catch (RuntimeException) {
        $invalid = true;
    }
    check($invalid, 'invalid report day and email are rejected');

    $salesHtml = View::capture('admin/sales/index', [
        'dataset'=>$dataset,
        'reports'=>$service->reports(),
        'reportSettings'=>$state,
        'user'=>['email'=>'admin@example.test'],
    ]);
    check(str_contains($salesHtml, 'Najważniejsze kwoty sprzedaży') && str_contains($salesHtml, 'action="/admin/sales/export-xlsx"'), 'sales panel renders with the configured admin base path');
    check(str_contains($salesHtml, 'Wygeneruj raport miesięczny') && str_contains($salesHtml, 'Wygeneruj raport roczny'), 'sales panel clearly offers monthly and yearly reports');
    check(str_contains($salesHtml, 'name="range" value="month"') && str_contains($salesHtml, 'name="range" value="year"'), 'report forms send an explicit safe range');
    check(!str_contains($salesHtml, 'Zapis po zakończeniu miesiąca'), 'sales panel contains no ambiguous save-after-month action');
    check(strpos($salesHtml, 'sales-report-panel') > strpos($salesHtml, 'sales-items-panel'), 'report generation is placed below the sales details');

    $settingsHtml = View::capture('admin/settings/index', [
        'settings'=>(new SettingsRepository())->allKeyed(),
        'storefront'=>$state,
        'envStatus'=>[],
        'user'=>['email'=>'admin@example.test'],
    ]);
    check(str_contains($settingsHtml, 'name="sales_vat_rate"') && str_contains($settingsHtml, 'value="5.00"'), 'settings panel shows editable 5% VAT rate');
    check(str_contains($settingsHtml, 'name="sales_report_email"') && str_contains($settingsHtml, 'name="sales_report_day"'), 'settings panel renders cyclical email and day fields');
    check(str_contains($settingsHtml, 'brand-upload-preview--logo'), 'settings panel uses the contained logo preview');
    $adminCss = (string)file_get_contents($root . '/admin/assets/style.css');
    check(preg_match('/\.brand-upload-preview--social img\s*\{[^}]*object-fit:\s*contain;/s', $adminCss) === 1, 'social sharing image preview contains the complete image');

    (new SalesReportRepository())->syncAllEmailStatuses();
    echo json_encode([
        'ok'=>true,
        'assertions'=>$assertions,
        'xlsx'=>$xlsxPath,
        'csv_bytes'=>strlen($csv),
        'summary'=>[
            'net'=>$summary['final_net'], 'vat'=>$summary['final_vat'], 'gross'=>$summary['final_gross'],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    if (!$keepArtifacts) {
        foreach (array_unique($createdFiles) as $file) if (is_file($file)) @unlink($file);
        foreach (glob($root . '/storage/logs/mail/mail-*.eml') ?: [] as $file) @unlink($file);
        @unlink($database);
    }
}
