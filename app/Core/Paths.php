<?php
namespace Book100\Core;

final class Paths
{
    public static function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    public static function publicRoot(): string
    {
        $configured = trim((string)Env::get('PUBLIC_ROOT', 'public'));
        if ($configured === '') {
            $configured = 'public';
        }
        if (
            str_starts_with($configured, '/')
            || preg_match('/^[a-z]:[\\\\\/]/i', $configured) === 1
        ) {
            return rtrim(str_replace('\\', '/', $configured), '/');
        }
        return self::projectRoot() . '/' . trim(str_replace('\\', '/', $configured), '/');
    }
}
