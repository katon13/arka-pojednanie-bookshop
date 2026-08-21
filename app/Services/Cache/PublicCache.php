<?php
namespace Book100\Services\Cache;

final class PublicCache
{
    public static function remember(string $key, int $ttlSeconds, callable $callback): string
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return (string)$callback();
        if (!empty($_SERVER['QUERY_STRING'])) {
            if (!headers_sent()) header('Cache-Control: private, no-store');
            return (string)$callback();
        }
        $path = self::path($key);
        if (is_file($path) && (time() - filemtime($path)) < $ttlSeconds) {
            return self::withHeaders((string)file_get_contents($path), 'HIT');
        }
        $html = (string)$callback();
        $dir = dirname($path);
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        file_put_contents($path, $html);
        return self::withHeaders($html, 'MISS');
    }

    public static function clear(): int
    {
        $dir = dirname(__DIR__, 3) . '/storage/cache';
        $count = 0;
        if (!is_dir($dir)) return 0;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $file) {
            if ($file->isFile()) { unlink($file->getPathname()); $count++; }
            elseif ($file->isDir()) { @rmdir($file->getPathname()); }
        }
        return $count;
    }

    private static function path(string $key): string
    {
        $safe = sha1($key);
        return dirname(__DIR__, 3) . '/storage/cache/public/' . substr($safe, 0, 2) . '/' . $safe . '.html';
    }

    private static function withHeaders(string $html, string $state): string
    {
        if (headers_sent()) return $html;
        $etag = '"' . sha1($html) . '"';
        header('Cache-Control: public, max-age=0, must-revalidate');
        header('ETag: ' . $etag);
        header('X-Arka-Cache: ' . $state);
        $requestedEtags = explode(',', (string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
        foreach ($requestedEtags as $requestedEtag) {
            $normalized = trim(str_replace('W/', '', trim($requestedEtag)), "\" ");
            if ($normalized === '*' || hash_equals(trim($etag, '"'), $normalized)) {
                http_response_code(304);
                return '';
            }
        }
        return $html;
    }
}
