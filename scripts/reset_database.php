<?php
require __DIR__ . '/../app/bootstrap.php';
\Book100\Core\Env::load(__DIR__ . '/../.env');

if (!in_array('--yes', $argv, true)) {
    fwrite(STDERR, "To skasuje wszystkie tabele lokalnej księgarni i odtworzy czystą bazę ARKA. Dodaj --yes\n");
    exit(1);
}

$result = \Book100\Services\Database\Installer::resetAndInstall(true);
\Book100\Services\Cache\PublicCache::clear();
echo "OK: utworzono czystą bazę ARKA.\n";
echo "Driver: {$result['driver']}\n";
echo "Admin: {$result['admin']}\n";
echo "Książki startowe: {$result['books_seeded']}\n";
echo "Strony startowe: {$result['pages_seeded']}\n";
