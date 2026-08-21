<?php
namespace Book100\Services\Media;

use Book100\Core\Database;
use Book100\Core\Paths;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class MediaLibraryService
{
    private string $root;
    private string $publicRoot;

    public function __construct()
    {
        $this->root = dirname(__DIR__, 3);
        $this->publicRoot = Paths::publicRoot();
    }

    public function save(array $file): array
    {
        $this->assertUpload($file, 12 * 1024 * 1024);

        $relativeDir = 'uploads/media/' . date('Y/m');
        $targetDir = $this->publicRoot . '/' . $relativeDir;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Nie można utworzyć katalogu biblioteki mediów.');
        }

        $baseName = $this->safeName((string)($file['name'] ?? 'grafika'));
        $filename = $baseName . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4));
        $target = (new ImageOptimizer())->optimize(
            (string)$file['tmp_name'],
            $targetDir . '/' . $filename,
            2000,
            2000,
            84
        );

        return $this->describe($target, 'Media');
    }

    public function all(int $limit = 600): array
    {
        $roots = [
            $this->publicRoot . '/uploads/media',
            $this->publicRoot . '/uploads/products',
            $this->publicRoot . '/uploads/pages',
            $this->publicRoot . '/uploads/events',
            $this->publicRoot . '/uploads/authors',
        ];
        $files = [];

        foreach ($roots as $root) {
            if (!is_dir($root)) continue;
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file instanceof SplFileInfo || !$file->isFile()) continue;
                if (!in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png', 'webp'], true)) continue;
                $files[] = [
                    'path' => $file->getPathname(),
                    'mtime' => $file->getMTime(),
                ];
            }
        }

        usort($files, static fn(array $a, array $b): int => $b['mtime'] <=> $a['mtime']);
        $items = [];
        foreach (array_slice($files, 0, max(1, $limit)) as $file) {
            $path = (string)$file['path'];
            $normalized = str_replace('\\', '/', $path);
            $origin = str_contains($normalized, '/uploads/products/')
                ? 'Książka'
                : (str_contains($normalized, '/uploads/pages/')
                    ? 'Strona'
                    : (str_contains($normalized, '/uploads/events/')
                        ? 'Wydarzenie'
                        : (str_contains($normalized, '/uploads/authors/') ? 'Autor' : 'Media')));
            $items[] = $this->describe($path, $origin);
        }
        return $items;
    }

    public function usages(string $url): array
    {
        $url = $this->normalizeUrl($url);
        $needle = '%' . $url . '%';
        $pdo = Database::pdo();
        $usages = [];

        $books = $pdo->prepare(
            'SELECT id, title, cover_image, description
             FROM books
             WHERE cover_image = :url OR description LIKE :needle'
        );
        $books->execute([':url' => $url, ':needle' => $needle]);
        foreach ($books->fetchAll(\PDO::FETCH_ASSOC) as $book) {
            if ((string)($book['cover_image'] ?? '') === $url) {
                $usages[] = 'Okładka książki „' . (string)$book['title'] . '”';
            }
            if (str_contains((string)($book['description'] ?? ''), $url)) {
                $usages[] = 'Opis książki „' . (string)$book['title'] . '”';
            }
        }

        $pages = $pdo->prepare(
            'SELECT id, title, featured_image, content
             FROM content_pages
             WHERE featured_image = :url OR content LIKE :needle'
        );
        $pages->execute([':url' => $url, ':needle' => $needle]);
        foreach ($pages->fetchAll(\PDO::FETCH_ASSOC) as $page) {
            if ((string)($page['featured_image'] ?? '') === $url) {
                $usages[] = 'Grafika wyróżniająca strony „' . (string)$page['title'] . '”';
            }
            if (str_contains((string)($page['content'] ?? ''), $url)) {
                $usages[] = 'Treść strony „' . (string)$page['title'] . '”';
            }
        }

        $events = $pdo->prepare(
            'SELECT id, title, featured_image, content
             FROM events
             WHERE featured_image = :url OR content LIKE :needle'
        );
        $events->execute([':url' => $url, ':needle' => $needle]);
        foreach ($events->fetchAll(\PDO::FETCH_ASSOC) as $event) {
            if ((string)($event['featured_image'] ?? '') === $url) {
                $usages[] = 'Grafika wydarzenia „' . (string)$event['title'] . '”';
            }
            if (str_contains((string)($event['content'] ?? ''), $url)) {
                $usages[] = 'Opis wydarzenia „' . (string)$event['title'] . '”';
            }
        }

        $authors = $pdo->prepare('SELECT id, name FROM authors WHERE photo = :url');
        $authors->execute([':url' => $url]);
        foreach ($authors->fetchAll(\PDO::FETCH_ASSOC) as $author) {
            $usages[] = 'Zdjęcie autora „' . (string)$author['name'] . '”';
        }

        $settings = $pdo->prepare('SELECT name FROM settings WHERE value LIKE :needle');
        $settings->execute([':needle' => $needle]);
        foreach ($settings->fetchAll(\PDO::FETCH_ASSOC) as $setting) {
            $usages[] = 'Ustawienie sklepu „' . (string)$setting['name'] . '”';
        }

        $bookImages = $pdo->prepare('SELECT book_id, type FROM book_images WHERE path = :url');
        $bookImages->execute([':url' => $url]);
        foreach ($bookImages->fetchAll(\PDO::FETCH_ASSOC) as $image) {
            $usages[] = 'Galeria książki #' . (int)$image['book_id'] . ' (' . (string)$image['type'] . ')';
        }

        return array_values(array_unique($usages));
    }

    public function delete(string $url): string
    {
        $url = $this->normalizeUrl($url);
        $uploadsRoot = realpath($this->publicRoot . '/uploads');
        $file = realpath($this->publicRoot . '/' . ltrim($url, '/'));
        if ($uploadsRoot === false || $file === false || !is_file($file)) {
            throw new RuntimeException('Nie odnaleziono grafiki.');
        }
        $uploadsPrefix = rtrim(str_replace('\\', '/', $uploadsRoot), '/') . '/';
        $normalizedFile = str_replace('\\', '/', $file);
        if (!str_starts_with($normalizedFile, $uploadsPrefix)) {
            throw new RuntimeException('Nie można usunąć pliku spoza biblioteki.');
        }
        if (!in_array(strtolower(pathinfo($normalizedFile, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new RuntimeException('Wybrany plik nie jest obsługiwaną grafiką.');
        }
        $trashDir = $this->root . '/storage/media-trash/' . date('Y-m');
        if (!is_dir($trashDir) && !mkdir($trashDir, 0775, true) && !is_dir($trashDir)) {
            throw new RuntimeException('Nie udało się przygotować bezpiecznego usunięcia grafiki.');
        }
        $trashFile = $trashDir . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '-' . basename($file);
        if (!rename($file, $trashFile)) {
            throw new RuntimeException('Nie udało się usunąć grafiki z biblioteki.');
        }
        return $trashFile;
    }

    private function describe(string $path, string $origin): array
    {
        $realPublic = realpath($this->publicRoot);
        $realFile = realpath($path);
        if ($realPublic === false || $realFile === false) {
            throw new RuntimeException('Nie odnaleziono zapisanego obrazu.');
        }
        $publicPrefix = rtrim(str_replace('\\', '/', $realPublic), '/') . '/';
        $normalizedFile = str_replace('\\', '/', $realFile);
        if (!str_starts_with($normalizedFile, $publicPrefix)) {
            throw new RuntimeException('Obraz znajduje się poza katalogiem publicznym.');
        }

        $url = '/' . ltrim(substr($normalizedFile, strlen($publicPrefix)), '/');
        $dimensions = @getimagesize($realFile);
        return [
            'id' => hash('sha256', $url),
            'url' => $url,
            'name' => pathinfo($realFile, PATHINFO_FILENAME),
            'origin' => $origin,
            'width' => (int)($dimensions[0] ?? 0),
            'height' => (int)($dimensions[1] ?? 0),
            'bytes' => (int)(filesize($realFile) ?: 0),
            'created_at' => date('Y-m-d H:i:s', (int)(filemtime($realFile) ?: time())),
        ];
    }

    private function assertUpload(array $file, int $maxBytes): void
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Wgrywanie pliku zakończyło się błędem.');
        }
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > $maxBytes) {
            throw new RuntimeException('Grafika jest pusta albo przekracza 12 MB.');
        }
        if (!is_uploaded_file((string)($file['tmp_name'] ?? ''))) {
            throw new RuntimeException('Nieprawidłowe źródło wgrywanego pliku.');
        }
    }

    private function safeName(string $name): string
    {
        $name = pathinfo($name, PATHINFO_FILENAME);
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $name = strtolower($converted !== false ? $converted : $name);
        $name = preg_replace('/[^a-z0-9]+/', '-', $name) ?: 'grafika';
        return trim($name, '-') ?: 'grafika';
    }

    private function normalizeUrl(string $url): string
    {
        $path = parse_url(trim($url), PHP_URL_PATH);
        $url = '/' . ltrim(is_string($path) ? $path : '', '/');
        if (!str_starts_with($url, '/uploads/') || str_contains($url, '..')) {
            throw new RuntimeException('Nieprawidłowy adres grafiki.');
        }
        return $url;
    }
}
