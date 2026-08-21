<?php
namespace Book100\Services\Media;

use Book100\Core\Database;
use Book100\Repository\SettingsRepository;
use PDO;
use RuntimeException;
use Throwable;

final class AdminAssetRemovalService
{
    /** @return array{scope:string,asset:string,id:int,removed_url:string,file_trashed:bool,message:string} */
    public function remove(string $scope, string $asset, int $id = 0): array
    {
        $scope = strtolower(trim($scope));
        $asset = strtolower(trim($asset));
        $removedUrl = '';
        $message = 'Obraz został usunięty z tego miejsca.';

        $setting = $this->settingName($scope, $asset);
        if ($setting !== null) {
            $settings = new SettingsRepository();
            $removedUrl = trim($settings->get($setting, ''));
            $settings->saveValues([$setting => '']);
        } else {
            $entity = $this->entity($scope, $asset);
            if ($entity === null || $id <= 0) {
                throw new RuntimeException('Nieprawidłowy rodzaj zasobu do usunięcia.');
            }

            [$table, $column, $noun] = $entity;
            $pdo = Database::pdo();
            $select = $pdo->prepare("SELECT {$column} FROM {$table} WHERE id = ? LIMIT 1");
            $select->execute([$id]);
            $current = $select->fetch(PDO::FETCH_ASSOC);
            if (!$current) {
                throw new RuntimeException('Nie znaleziono elementu, z którego ma zostać usunięty plik.');
            }

            $removedUrl = trim((string)($current[$column] ?? ''));
            $update = $pdo->prepare("UPDATE {$table} SET {$column} = NULL, updated_at = ? WHERE id = ?");
            $update->execute([date('Y-m-d H:i:s'), $id]);
            $message = $noun . ' został odłączony od tego elementu.';
        }

        $fileTrashed = $this->trashUnusedImage($removedUrl);

        return [
            'scope' => $scope,
            'asset' => $asset,
            'id' => $id,
            'removed_url' => $removedUrl,
            'file_trashed' => $fileTrashed,
            'message' => $removedUrl === ''
                ? 'Ten plik był już usunięty.'
                : $message,
        ];
    }

    private function settingName(string $scope, string $asset): ?string
    {
        return match ($scope . ':' . $asset) {
            'settings:site_logo' => 'site_logo',
            'settings:site_icon' => 'site_icon',
            'settings:seo_default_og_image' => 'seo_default_og_image',
            'homepage:hero_image' => 'home_hero_image',
            'homepage:featured_1_image' => 'home_featured_1_image',
            'homepage:featured_2_image' => 'home_featured_2_image',
            default => null,
        };
    }

    /** @return array{string,string,string}|null */
    private function entity(string $scope, string $asset): ?array
    {
        return match ($scope . ':' . $asset) {
            'author:photo' => ['authors', 'photo', 'Zdjęcie'],
            'page:featured_image' => ['content_pages', 'featured_image', 'Obraz'],
            'event:featured_image' => ['events', 'featured_image', 'Grafika'],
            'book:ebook_file_path' => ['books', 'ebook_file_path', 'Plik e-booka'],
            default => null,
        };
    }

    private function trashUnusedImage(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || !str_starts_with('/' . ltrim($path, '/'), '/uploads/')) {
            return false;
        }

        try {
            $media = new MediaLibraryService();
            if ($media->usages($url) !== []) {
                return false;
            }
            $media->delete($url);
            return true;
        } catch (Throwable) {
            // Odpięcie jest ważniejsze od porządkowania pliku. Brakujący lub współdzielony
            // obraz może bezpiecznie pozostać w bibliotece i zostać usunięty później.
            return false;
        }
    }
}
