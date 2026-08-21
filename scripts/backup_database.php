<?php
require __DIR__ . '/../app/bootstrap.php';
\Book100\Core\Env::load(__DIR__ . '/../.env');

$env = [
    'connection' => \Book100\Core\Env::get('DB_CONNECTION', 'mysql'),
    'host' => \Book100\Core\Env::get('DB_HOST', '127.0.0.1'),
    'port' => \Book100\Core\Env::get('DB_PORT', '3306'),
    'database' => \Book100\Core\Env::get('DB_DATABASE', 'arka_shop'),
    'username' => \Book100\Core\Env::get('DB_USERNAME', 'root'),
];
$outDir = dirname(__DIR__) . '/storage/backups';
if (!is_dir($outDir)) mkdir($outDir, 0775, true);
$file = $outDir . '/arka_backup_' . date('Ymd_His') . '.sql';

if ($env['connection'] !== 'mysql') {
    fwrite(STDERR, "Backup SQL automatyczny jest przygotowany dla MySQL/Laragon. Aktualne DB_CONNECTION={$env['connection']}\n");
    exit(2);
}

$cmd = 'mysqldump --default-character-set=utf8mb4 -h ' . escapeshellarg($env['host']) . ' -P ' . escapeshellarg($env['port']) . ' -u ' . escapeshellarg($env['username']) . ' ' . escapeshellarg($env['database']) . ' > ' . escapeshellarg($file);
echo "ARKA — backup bazy\n";
echo "Komenda:\n$cmd\n";
if (in_array('--print-only', $argv, true)) {
    echo "Tryb print-only. Nie wykonuję backupu.\n";
    exit(0);
}
system($cmd, $code);
if ($code !== 0) {
    fwrite(STDERR, "Backup nie powiódł się. Sprawdź mysqldump w PATH Laragona.\n");
    exit($code);
}
echo "Backup zapisany: storage/backups/" . basename($file) . "\n";
