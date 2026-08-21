<?php
namespace Book100\Services\Books;

use Book100\Core\Paths;
use Book100\Services\Media\ImageOptimizer;
use RuntimeException;

final class BookAssetService
{
    private string $root;

    public function __construct()
    {
        $this->root = dirname(__DIR__, 3);
    }

    public function saveCover(array $file, string $slug, ?string $current = null): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return $current;
        $this->assertUpload($file, 10 * 1024 * 1024);
        $safeSlug = $this->safeSlug($slug);
        $relativeDir = 'uploads/products/' . $safeSlug;
        $targetDir = Paths::publicRoot() . '/' . $relativeDir;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Nie można utworzyć katalogu okładki.');
        }
        $filename = 'cover-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
        (new ImageOptimizer())->optimize(
            (string)$file['tmp_name'],
            $targetDir . '/' . $filename,
            1600,
            2200,
            85
        );
        return '/uploads/products/' . $safeSlug . '/' . $filename . '.webp';
    }

    public function saveEbook(array $file, string $slug, ?string $current = null): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return $current;
        $this->assertUpload($file, 100 * 1024 * 1024);
        $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($extension, ['pdf','epub','mobi'], true)) {
            throw new RuntimeException('Ebook musi być plikiem PDF, EPUB albo MOBI.');
        }
        $safeSlug = $this->safeSlug($slug);
        $relativeDir = 'storage/ebooks/' . $safeSlug;
        $targetDir = $this->root . '/' . $relativeDir;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Nie można utworzyć katalogu ebooka.');
        }
        $filename = 'ebook-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.' . $extension;
        if (!move_uploaded_file((string)$file['tmp_name'], $targetDir . '/' . $filename)) {
            throw new RuntimeException('Nie udało się zapisać ebooka.');
        }
        return $relativeDir . '/' . $filename;
    }

    public function saveDescriptionImage(array $file, string $slug): string
    {
        $this->assertUpload($file, 12 * 1024 * 1024);
        $safeSlug = $this->safeSlug($slug);
        $relativeDir = 'uploads/products/' . $safeSlug . '/description';
        $targetDir = Paths::publicRoot() . '/' . $relativeDir;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Nie można utworzyć katalogu grafik opisu.');
        }
        $filename = 'image-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4));
        (new ImageOptimizer())->optimize(
            (string)$file['tmp_name'],
            $targetDir . '/' . $filename,
            1800,
            1800,
            84
        );
        return '/uploads/products/' . $safeSlug . '/description/' . $filename . '.webp';
    }

    private function assertUpload(array $file, int $maxBytes): void
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Wgrywanie pliku zakończyło się błędem.');
        }
        if ((int)($file['size'] ?? 0) <= 0 || (int)$file['size'] > $maxBytes) {
            throw new RuntimeException('Plik ma nieprawidłowy rozmiar.');
        }
        if (!is_uploaded_file((string)($file['tmp_name'] ?? ''))) {
            throw new RuntimeException('Nieprawidłowe źródło wgrywanego pliku.');
        }
    }

    private function safeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug) ?: 'ksiazka';
        return trim($slug, '-') ?: 'ksiazka';
    }
}
