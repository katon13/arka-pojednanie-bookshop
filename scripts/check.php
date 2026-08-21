<?php
$root = dirname(__DIR__);
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$errors = 0;
foreach ($rii as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $cmd = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname()) . ' 2>&1';
        exec($cmd, $out, $code);
        if ($code !== 0) { echo implode("\n", $out) . "\n"; $errors++; }
        $out = [];
    }
}
echo $errors ? "Błędy PHP: {$errors}\n" : "PHP syntax OK\n";
exit($errors ? 1 : 0);
