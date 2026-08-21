<?php
namespace Book100\Services\Media;

use GdImage;
use RuntimeException;

final class ImageOptimizer
{
    private const MAX_SOURCE_PIXELS = 40_000_000;

    /**
     * Optymalizuje JPG, PNG lub WEBP do wersji WEBP bez metadanych.
     * Zwraca pełną ścieżkę zapisanego pliku.
     */
    public function optimize(
        string $source,
        string $targetWithoutExtension,
        int $maxWidth,
        int $maxHeight,
        int $quality = 84
    ): string {
        if (!extension_loaded('gd') || !function_exists('imagewebp')) {
            throw new RuntimeException('Serwer nie ma włączonej obsługi optymalizacji WEBP (GD).');
        }

        $info = @getimagesize($source);
        $type = (int)($info[2] ?? 0);
        $width = (int)($info[0] ?? 0);
        $height = (int)($info[1] ?? 0);
        if ($width < 1 || $height < 1 || $width * $height > self::MAX_SOURCE_PIXELS) {
            throw new RuntimeException('Obraz ma nieprawidłowe albo zbyt duże wymiary.');
        }

        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
            IMAGETYPE_PNG => @imagecreatefrompng($source),
            IMAGETYPE_WEBP => @imagecreatefromwebp($source),
            default => false,
        };
        if (!$image instanceof GdImage) {
            throw new RuntimeException('Nie udało się odczytać obrazu. Użyj JPG, PNG albo WEBP.');
        }

        try {
            if ($type === IMAGETYPE_JPEG) {
                $image = $this->orientJpeg($image, $source);
            }

            $sourceWidth = imagesx($image);
            $sourceHeight = imagesy($image);
            $scale = min(1, $maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
            $targetWidth = max(1, (int)round($sourceWidth * $scale));
            $targetHeight = max(1, (int)round($sourceHeight * $scale));

            $optimized = imagecreatetruecolor($targetWidth, $targetHeight);
            if (!$optimized instanceof GdImage) {
                throw new RuntimeException('Nie udało się przygotować zoptymalizowanego obrazu.');
            }

            imagealphablending($optimized, false);
            imagesavealpha($optimized, true);
            $transparent = imagecolorallocatealpha($optimized, 0, 0, 0, 127);
            imagefilledrectangle($optimized, 0, 0, $targetWidth, $targetHeight, $transparent);

            if (!imagecopyresampled(
                $optimized,
                $image,
                0,
                0,
                0,
                0,
                $targetWidth,
                $targetHeight,
                $sourceWidth,
                $sourceHeight
            )) {
                imagedestroy($optimized);
                throw new RuntimeException('Nie udało się zmniejszyć obrazu.');
            }

            $target = $targetWithoutExtension . '.webp';
            $temporary = $target . '.tmp-' . bin2hex(random_bytes(4));
            if (!imagewebp($optimized, $temporary, max(55, min(95, $quality)))) {
                imagedestroy($optimized);
                throw new RuntimeException('Nie udało się zapisać zoptymalizowanego obrazu.');
            }
            imagedestroy($optimized);

            if (!@rename($temporary, $target)) {
                @unlink($temporary);
                throw new RuntimeException('Nie udało się zakończyć zapisu zoptymalizowanego obrazu.');
            }

            return $target;
        } finally {
            imagedestroy($image);
        }
    }

    private function orientJpeg(GdImage $image, string $source): GdImage
    {
        if (!function_exists('exif_read_data')) return $image;
        $exif = @exif_read_data($source);
        $orientation = (int)($exif['Orientation'] ?? 1);

        if (in_array($orientation, [2, 4, 5, 7], true)) {
            $mode = in_array($orientation, [2, 5], true)
                ? IMG_FLIP_HORIZONTAL
                : IMG_FLIP_VERTICAL;
            imageflip($image, $mode);
        }

        $angle = match ($orientation) {
            3, 4 => 180,
            5, 6 => -90,
            7, 8 => 90,
            default => 0,
        };
        if ($angle === 0) return $image;

        $rotated = imagerotate($image, $angle, 0);
        if (!$rotated instanceof GdImage) return $image;
        imagedestroy($image);
        return $rotated;
    }
}
