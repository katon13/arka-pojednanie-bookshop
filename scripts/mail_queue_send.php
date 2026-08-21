<?php
require __DIR__ . '/../app/bootstrap.php';
\Book100\Core\Env::load(__DIR__ . '/../.env');
$limit = 50;
$dry = in_array('--dry-run', $argv, true);
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--limit=')) $limit = max(1, (int)substr($arg, 8));
}
$salesSchedule = ['status'=>'not_run'];
if (!$dry) {
    try {
        $salesSchedule = (new \Book100\Services\Sales\SalesReportScheduler())->run();
    } catch (\Throwable $exception) {
        $salesSchedule = ['status'=>'failed', 'message'=>$exception->getMessage()];
    }
}

$mail = (new \Book100\Services\Mail\Mailer())->processQueue($limit, $dry);
$salesStatusSync = 0;
if (!$dry) {
    try {
        $salesStatusSync = (new \Book100\Repository\SalesReportRepository())->syncAllEmailStatuses();
    } catch (\Throwable $exception) {
        $salesSchedule['status_sync_error'] = $exception->getMessage();
    }
}

echo json_encode([
    'sales_report'=>$salesSchedule,
    'sales_reports_synced'=>$salesStatusSync,
    'mail'=>$mail,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
