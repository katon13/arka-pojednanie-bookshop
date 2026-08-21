<?php
namespace Book100\Core;

use Book100\Repository\SettingsRepository;
use Throwable;

final class StoreUrl
{
    public static function base(): string
    {
        $fallback = rtrim((string)Env::get('APP_URL', 'http://localhost:8000'), '/');

        try {
            $configured = trim((new SettingsRepository())->get('shop_url', $fallback));
        } catch (Throwable) {
            $configured = $fallback;
        }

        return self::normalize($configured) ?? $fallback;
    }

    public static function to(string $path = '/'): string
    {
        return self::base() . '/' . ltrim($path, '/');
    }

    public static function normalize(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (!preg_match('#^https?://#i', $value)) {
            $value = 'https://' . $value;
        }

        $parts = parse_url($value);
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = strtolower((string)($parts['host'] ?? ''));
        $port = isset($parts['port']) ? (int)$parts['port'] : null;
        $path = (string)($parts['path'] ?? '');
        if (
            !in_array($scheme, ['http', 'https'], true)
            || !preg_match('/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/i', $host)
            || ($path !== '' && $path !== '/')
            || isset($parts['query'])
            || isset($parts['fragment'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            return null;
        }

        return $scheme . '://' . $host . ($port !== null ? ':' . $port : '');
    }
}
