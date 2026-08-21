<?php
namespace Book100\Core;

final class AdminUrl
{
    public static function base(): string
    {
        $base = trim((string)Env::get('ADMIN_BASE_PATH', ''));
        if ($base === '' || $base === '/') {
            return '';
        }
        return '/' . trim($base, '/');
    }

    public static function publicOrigin(): string
    {
        $canonicalOrigin = StoreUrl::base();
        if (self::base() === '') {
            return $canonicalOrigin;
        }

        $forwardedHost = trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ''))[0]);
        $host = $forwardedHost !== '' ? $forwardedHost : trim((string)($_SERVER['HTTP_HOST'] ?? ''));
        if (
            $host === ''
            || !preg_match('/^(?:[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?|\[[0-9a-f:]+\])(?::\d{1,5})?$/i', $host)
        ) {
            return $canonicalOrigin;
        }

        $forwardedProto = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
        if (in_array($forwardedProto, ['http', 'https'], true)) {
            $scheme = $forwardedProto;
        } elseif (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
            $scheme = 'https';
        } else {
            $requestScheme = strtolower((string)($_SERVER['REQUEST_SCHEME'] ?? ''));
            $scheme = in_array($requestScheme, ['http', 'https'], true) ? $requestScheme : 'http';
        }

        return $scheme . '://' . $host;
    }

    public static function route(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return $path;
        }
        if (
            !str_starts_with($path, '/')
            || str_starts_with($path, '//')
            || preg_match('#^[a-z][a-z0-9+.-]*:#i', $path)
        ) {
            return $path;
        }

        $base = self::base();
        if ($base === '' || $path === $base || str_starts_with($path, $base . '/')) {
            return $path;
        }
        return $base . ($path === '/' ? '/' : $path);
    }

    public static function stripRequestUri(string $requestUri): string
    {
        $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
        $base = self::base();
        if ($base === '') {
            return $path;
        }
        if ($path === $base || $path === $base . '/') {
            return '/';
        }
        if (str_starts_with($path, $base . '/')) {
            return substr($path, strlen($base)) ?: '/';
        }
        return $path;
    }

    public static function rewriteHtml(string $html): string
    {
        if (self::base() === '' || $html === '') {
            return $html;
        }
        return (string)preg_replace_callback(
            '/\b(href|action|src|data-rich-upload-url)="(\/(?!\/)[^"]*)"/i',
            static function (array $match): string {
                $path = (string)$match[2];
                if (str_starts_with($path, '/uploads/') || str_starts_with($path, '/wp-content/')) {
                    return $match[1] . '="' . $path . '"';
                }
                return $match[1] . '="' . self::route($path) . '"';
            },
            $html
        );
    }
}
