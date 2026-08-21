<?php
require __DIR__ . '/../app/bootstrap.php';
\Book100\Core\Env::load(__DIR__ . '/../.env');

$result = \Book100\Services\Database\Installer::install(true);
echo "ARKA — instalacja czystej bazy\n";
echo "DB: {$result['driver']}\n";
echo "Admin: {$result['admin']}\n";
echo "Książki dodane: {$result['books_seeded']}\n";
echo "Strony dodane: {$result['pages_seeded']}\n";
echo "Gotowe. Baza nie zawiera danych poprzedniej księgarni.\n";
