<?php
namespace Book100\Core;

final class Redirect
{
    public static function to(string $path): void
    {
        if (defined('BOOK100_ADMIN_REQUEST') && BOOK100_ADMIN_REQUEST === true) {
            $path = AdminUrl::route($path) ?? $path;
        }
        if (strcasecmp((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''), 'XMLHttpRequest') === 0) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'ok' => true,
                'redirect' => $path,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        header('Location: ' . $path);
        exit;
    }
}
