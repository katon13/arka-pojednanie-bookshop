<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
\Book100\Core\Env::load(__DIR__ . '/../.env');

$root = dirname(__DIR__);
$publicDirectory = is_dir($root . '/public') ? 'public' : 'www';
$requiredFiles = [
    '.env.example',
    'admin/index.php',
    'app/Controllers/AdminController.php',
    'app/Controllers/ApiController.php',
    'app/Controllers/PublicController.php',
    'app/Core/Database.php',
    'app/Services/Database/Installer.php',
    $publicDirectory . '/index.php',
    'scripts/export_server_database.php',
    'scripts/install.php',
    'scripts/mail_queue_send.php',
];
$requiredDirectories = [
    'admin',
    'app',
    $publicDirectory,
    $publicDirectory . '/uploads',
    'resources/views',
    'storage',
    'storage/cache',
    'storage/labels',
    'storage/logs',
];
$errors = [];
$warnings = [];
$phpFiles = [];

foreach ($requiredFiles as $file) {
    if (!is_file($root . '/' . $file)) {
        $errors[] = 'Brak pliku: ' . $file;
    }
}
foreach ($requiredDirectories as $directory) {
    $path = $root . '/' . $directory;
    if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
        $errors[] = 'Brak katalogu: ' . $directory;
    } elseif (!is_writable($path)) {
        $errors[] = 'Brak prawa zapisu: ' . $directory;
    }
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }
    $phpFiles[] = $file->getPathname();
    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname()) . ' 2>&1';
    exec($command, $output, $code);
    if ($code !== 0) {
        $errors[] = 'PHP lint: ' . str_replace($root . DIRECTORY_SEPARATOR, '', $file->getPathname())
            . ' — ' . implode(' ', $output);
    }
    $output = [];
}

if (!extension_loaded('pdo')) {
    $errors[] = 'Brak rozszerzenia PDO.';
}
if (!extension_loaded('curl')) {
    $warnings[] = 'Brak cURL — Przelewy24, Stripe i InPost wymagają sprawnego klienta HTTP.';
}
if (!is_file($root . '/.env')) {
    $warnings[] = 'Brak lokalnego .env. Na serwerze utwórz go z .env.example i wpisz sekrety.';
}

try {
    $pdo = \Book100\Core\Database::pdo();
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $integrity = strtolower((string)$pdo->query('PRAGMA integrity_check')->fetchColumn());
        if ($integrity !== 'ok') {
            $errors[] = 'Baza SQLite nie przeszła kontroli integralności: ' . $integrity;
        }
    }
    foreach (['admins', 'books', 'orders', 'order_items', 'payments', 'settings'] as $table) {
        $pdo->query('SELECT 1 FROM ' . $table . ' LIMIT 1');
    }
} catch (Throwable $exception) {
    $errors[] = 'Kontrola bazy: ' . $exception->getMessage();
}

try {
    $integrations = (new \Book100\Services\Integrations\IntegrationHealthChecker())->check();
    foreach ($integrations['errors'] ?? [] as $error) {
        $warnings[] = 'Integracje: ' . $error;
    }
    foreach ($integrations['warnings'] ?? [] as $warning) {
        $warnings[] = 'Integracje: ' . $warning;
    }
} catch (Throwable $exception) {
    $warnings[] = 'Nie udało się wykonać kontroli integracji: ' . $exception->getMessage();
}

$report = [
    'ok' => $errors === [],
    'errors' => array_values(array_unique($errors)),
    'warnings' => array_values(array_unique($warnings)),
    'php_files_checked' => count($phpFiles),
    'generated_at' => date(DATE_ATOM),
];
$reportDirectory = $root . '/storage/logs';
if (!is_dir($reportDirectory)) {
    mkdir($reportDirectory, 0775, true);
}
file_put_contents(
    $reportDirectory . '/predeploy-report.json',
    json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
    LOCK_EX
);
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($report['ok'] ? 0 : 1);
