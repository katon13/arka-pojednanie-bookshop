<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.save_path', sys_get_temp_dir());
}

$path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$projectRoot = dirname(__DIR__);
$adminRoot = realpath($projectRoot . '/admin');
$adminBase = '';
$envPath = $projectRoot . '/.env';
if (is_file($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim((string)$line);
        if (str_starts_with($line, 'ADMIN_BASE_PATH=')) {
            $adminBase = trim(substr($line, strlen('ADMIN_BASE_PATH=')), " \t\n\r\0\x0B\"'");
            break;
        }
    }
}
$adminBase = $adminBase === '' || $adminBase === '/' ? '' : '/' . trim($adminBase, '/');

if ($adminBase !== '') {
    if ($path === '/') {
        header('Location: ' . $adminBase . '/login', true, 302);
        return;
    }
    if ($path === $adminBase || $path === $adminBase . '/') {
        $path = '/';
    } elseif (str_starts_with($path, $adminBase . '/')) {
        $path = substr($path, strlen($adminBase)) ?: '/';
    }
}

$adminFile = $adminRoot ? realpath($adminRoot . '/' . ltrim($path, '/')) : false;
if ($path !== '/' && $adminRoot && $adminFile && is_file($adminFile)
    && str_starts_with(strtolower($adminFile), strtolower($adminRoot . DIRECTORY_SEPARATOR))) {
    $contentTypes = [
        'css' => 'text/css; charset=UTF-8',
        'js' => 'application/javascript; charset=UTF-8',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
    ];
    $extension = strtolower(pathinfo($adminFile, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($contentTypes[$extension] ?? 'application/octet-stream'));
    header('Content-Length: ' . (string)filesize($adminFile));
    readfile($adminFile);
    return;
}

$query = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY);
$_SERVER['REQUEST_URI'] = $path . ($query !== null && $query !== '' ? '?' . $query : '');
require $projectRoot . '/admin/index.php';
