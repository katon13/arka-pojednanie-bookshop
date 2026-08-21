<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.save_path', sys_get_temp_dir());
}

$path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$publicFile = dirname(__DIR__) . '/public' . $path;

if ($path !== '/' && is_file($publicFile)) {
    return false;
}

require dirname(__DIR__) . '/public/index.php';
