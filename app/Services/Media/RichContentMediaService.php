<?php
namespace Book100\Services\Media;

use Book100\Core\Paths;
use RuntimeException;

final class RichContentMediaService
{
    public function saveInlineImage(array $file, string $scope, string $slug): string
    {
        $this->assertUpload($file, 12 * 1024 * 1024);
        $scope = in_array($scope, ['pages', 'events'], true) ? $scope : 'products';
        $slug = $this->safeSlug($slug);
        $relativeDir = 'uploads/' . $scope . '/' . $slug . '/description';
        return $this->saveOptimized($file, $relativeDir, 'image', 1800, 1800, 84);
    }

    public function savePageFeaturedImage(array $file, string $slug, ?string $current = null): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $current;
        }
        $this->assertUpload($file, 12 * 1024 * 1024);
        $relativeDir = 'uploads/pages/' . $this->safeSlug($slug);
        return $this->saveOptimized($file, $relativeDir, 'featured', 1800, 1200, 85);
    }

    public function saveEventFeaturedImage(array $file, string $slug, ?string $current = null): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $current;
        }
        $this->assertUpload($file, 12 * 1024 * 1024);
        $relativeDir = 'uploads/events/' . $this->safeSlug($slug);
        return $this->saveOptimized($file, $relativeDir, 'featured', 1800, 1200, 85);
    }

    private function saveOptimized(
        array $file,
        string $relativeDir,
        string $prefix,
        int $maxWidth,
        int $maxHeight,
        int $quality
    ): string {
        $targetDir = Paths::publicRoot() . '/' . $relativeDir;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Nie można utworzyć katalogu grafiki.');
        }
        $filename = $prefix . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4));
        (new ImageOptimizer())->optimize(
            (string)$file['tmp_name'],
            $targetDir . '/' . $filename,
            $maxWidth,
            $maxHeight,
            $quality
        );
        return '/' . $relativeDir . '/' . $filename . '.webp';
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
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug) ?: 'strona';
        return trim($slug, '-') ?: 'strona';
    }
}
