<?php
require __DIR__ . '/../app/bootstrap.php';
\Book100\Core\Env::load(__DIR__ . '/../.env');
$limit = 50;
$dry = in_array('--dry-run', $argv, true);
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--limit=')) $limit = max(1, (int)substr($arg, 8));
}
$report = (new \Book100\Services\Mail\Mailer())->processQueue($limit, $dry);
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
