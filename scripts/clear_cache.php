<?php
require __DIR__ . '/../app/bootstrap.php';
$count = \Book100\Services\Cache\PublicCache::clear();
echo "Cache publiczny wyczyszczony. Usunięto plików: {$count}\n";
