<?php
namespace Book100\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_set_cookie_params([
            'lifetime'=>0,
            'path'=>'/',
            'secure'=>self::isHttps(),
            'httponly'=>true,
            'samesite'=>'Lax',
        ]);
        session_start();
    }

    private static function isHttps(): bool
    {
        if (($_SERVER['HTTPS'] ?? '') === 'on') return true;
        return strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }
}
