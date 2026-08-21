<?php
namespace Book100\Services\Homepage;

use Book100\Core\Database;
use Book100\Repository\BookRepository;
use Book100\Repository\ContentPageRepository;
use Book100\Repository\EventRepository;
use Book100\Repository\SettingsRepository;
use Book100\Services\Media\ImageOptimizer;
use Book100\Services\Books\BookSaleState;
use PDO;
use RuntimeException;

final class HomepageSettingsService
{
    public function adminState(): array
    {
        $books = (new BookRepository())->all();
        $pages = (new ContentPageRepository())->all();
        $events = (new EventRepository())->all();
        $settings = (new SettingsRepository())->allKeyed();
        $defaults = $this->defaultFeatured($books);
        $featuredIds = [
            1 => array_key_exists('home_featured_1_book_id', $settings)
                ? $this->positiveId($settings['home_featured_1_book_id'])
                : (int)($defaults[1]['book_id'] ?? 0),
            2 => array_key_exists('home_featured_2_book_id', $settings)
                ? $this->positiveId($settings['home_featured_2_book_id'])
                : (int)($defaults[2]['book_id'] ?? 0),
        ];
        $featuredImages = [
            1 => array_key_exists('home_featured_1_image', $settings)
                ? (string)$settings['home_featured_1_image']
                : (string)($defaults[1]['image'] ?? ''),
            2 => array_key_exists('home_featured_2_image', $settings)
                ? (string)$settings['home_featured_2_image']
                : (string)($defaults[2]['image'] ?? ''),
        ];
        $featuredTargets = [];
        foreach ([1, 2] as $slot) {
            $type = trim((string)($settings['home_featured_' . $slot . '_target_type'] ?? ''));
            $pageId = $this->positiveId($settings['home_featured_' . $slot . '_page_id'] ?? 0);
            $eventId = $this->positiveId($settings['home_featured_' . $slot . '_event_id'] ?? 0);
            $bookId = (int)$featuredIds[$slot];
            if ($type === 'event' && $eventId > 0) {
                $featuredTargets[$slot] = ['type' => 'event', 'id' => $eventId];
            } elseif ($type === 'page' && $pageId > 0) {
                $featuredTargets[$slot] = ['type' => 'page', 'id' => $pageId];
            } elseif ($type === 'book' && $bookId > 0) {
                $featuredTargets[$slot] = ['type' => 'book', 'id' => $bookId];
            } elseif ($pageId > 0) {
                $featuredTargets[$slot] = ['type' => 'page', 'id' => $pageId];
            } elseif ($bookId > 0) {
                $featuredTargets[$slot] = ['type' => 'book', 'id' => $bookId];
            } else {
                $featuredTargets[$slot] = ['type' => '', 'id' => 0];
            }
        }
        $order = $this->idList($settings['home_catalog_order'] ?? '');
        $hidden = $this->idList($settings['home_catalog_hidden'] ?? '');
        $orderMap = array_flip($order);
        usort($books, static function (array $left, array $right) use ($orderMap): int {
            $leftPosition = $orderMap[(int)$left['id']] ?? PHP_INT_MAX;
            $rightPosition = $orderMap[(int)$right['id']] ?? PHP_INT_MAX;
            return $leftPosition <=> $rightPosition ?: strcmp((string)$left['title'], (string)$right['title']);
        });

        return [
            'books' => $books,
            'pages' => $pages,
            'events' => $events,
            'featured_ids' => $featuredIds,
            'featured_targets' => $featuredTargets,
            'featured_images' => $featuredImages,
            'hidden_ids' => $hidden,
            'show_how_it_works' => ($settings['home_show_how_it_works'] ?? '1') !== '0',
            'hero' => $this->heroState($settings),
        ];
    }

    public function publicState(): array
    {
        $state = $this->adminState();
        $catalogBooks = array_values(array_filter(
            $state['books'],
            static fn(array $book): bool => BookSaleState::isPublic($book)
        ));
        $visibleBooks = array_values(array_filter(
            $catalogBooks,
            static fn(array $book): bool => !in_array((int)$book['id'], $state['hidden_ids'], true)
        ));
        $booksById = [];
        foreach ($catalogBooks as $book) {
            $booksById[(int)$book['id']] = $book;
        }
        $pagesById = [];
        foreach ($state['pages'] as $page) {
            if (($page['status'] ?? '') === 'published') {
                $pagesById[(int)$page['id']] = $page;
            }
        }
        $eventsById = [];
        foreach ($state['events'] as $event) {
            if (($event['status'] ?? '') === 'published') {
                $eventsById[(int)$event['id']] = $event;
            }
        }

        $featured = [];
        foreach ([1, 2] as $slot) {
            $target = $state['featured_targets'][$slot] ?? ['type' => '', 'id' => 0];
            $type = (string)($target['type'] ?? '');
            $id = (int)($target['id'] ?? 0);
            $image = trim((string)($state['featured_images'][$slot] ?? ''));
            if ($type === 'book' && isset($booksById[$id])) {
                $book = $booksById[$id];
                $featured[] = [
                    'type' => 'book',
                    'item' => $book,
                    'book' => $book,
                    'title' => (string)$book['title'],
                    'href' => '/book/' . rawurlencode((string)$book['slug']) . '/',
                    'image' => $image !== '' ? $image : (string)($book['cover_image'] ?? ''),
                ];
            } elseif ($type === 'page' && isset($pagesById[$id])) {
                $page = $pagesById[$id];
                $featured[] = [
                    'type' => 'page',
                    'item' => $page,
                    'page' => $page,
                    'title' => (string)$page['title'],
                    'href' => '/' . rawurlencode((string)$page['slug']),
                    'image' => $image !== '' ? $image : (string)($page['featured_image'] ?? ''),
                ];
            } elseif ($type === 'event' && isset($eventsById[$id])) {
                $event = $eventsById[$id];
                $featured[] = [
                    'type' => 'event',
                    'item' => $event,
                    'event' => $event,
                    'title' => (string)$event['title'],
                    'href' => '/wydarzenia/' . rawurlencode((string)$event['slug']),
                    'image' => $image !== '' ? $image : (string)($event['featured_image'] ?? ''),
                ];
            }
        }

        return [
            'books' => $visibleBooks,
            'featured' => $featured,
            'show_how_it_works' => $state['show_how_it_works'],
            'hero' => $state['hero'],
        ];
    }

    public function save(array $input, array $files): void
    {
        $books = (new BookRepository())->all();
        $pages = (new ContentPageRepository())->all();
        $events = (new EventRepository())->all();
        $bookIds = array_map(static fn(array $book): int => (int)$book['id'], $books);
        $featuredBookIds = array_map(
            static fn(array $book): int => (int)$book['id'],
            array_values(array_filter($books, static fn(array $book): bool => BookSaleState::isPublic($book)))
        );
        $catalogIds = array_map(
            static fn(array $book): int => (int)$book['id'],
            array_values(array_filter(
                $books,
                static fn(array $book): bool => BookSaleState::isPublic($book)
            ))
        );
        $featuredPageIds = array_map(
            static fn(array $page): int => (int)$page['id'],
            array_values(array_filter($pages, static fn(array $page): bool => ($page['status'] ?? '') === 'published'))
        );
        $featuredEventIds = array_map(
            static fn(array $event): int => (int)$event['id'],
            array_values(array_filter($events, static fn(array $event): bool => ($event['status'] ?? '') === 'published'))
        );
        $current = $this->adminState();

        $featured = [];
        foreach ([1, 2] as $slot) {
            if (array_key_exists('featured_' . $slot . '_target', $input)) {
                $target = $this->parseFeaturedTarget($input['featured_' . $slot . '_target']);
            } else {
                $legacyId = $this->positiveId($input['featured_' . $slot . '_book_id'] ?? '');
                $target = ['type' => $legacyId > 0 ? 'book' : '', 'id' => $legacyId];
            }
            if ($target['type'] === 'book' && !in_array($target['id'], $featuredBookIds, true)) {
                throw new RuntimeException('Promowana książka musi być widoczna w sklepie.');
            }
            if ($target['type'] === 'page' && !in_array($target['id'], $featuredPageIds, true)) {
                throw new RuntimeException('Promowana strona musi być opublikowana.');
            }
            if ($target['type'] === 'event' && !in_array($target['id'], $featuredEventIds, true)) {
                throw new RuntimeException('Promowane wydarzenie musi być opublikowane.');
            }
            $featured[$slot] = $target;
        }
        if (
            $featured[1]['id'] > 0
            && $featured[1]['type'] === $featured[2]['type']
            && $featured[1]['id'] === $featured[2]['id']
        ) {
            throw new RuntimeException('Wybierz dwie różne treści promowane.');
        }

        $orderValues = is_array($input['catalog_order'] ?? null) ? $input['catalog_order'] : [];
        usort($bookIds, static function (int $left, int $right) use ($orderValues): int {
            $leftOrder = max(0, (int)($orderValues[$left] ?? 9999));
            $rightOrder = max(0, (int)($orderValues[$right] ?? 9999));
            return $leftOrder <=> $rightOrder ?: $left <=> $right;
        });
        $visible = is_array($input['catalog_visible'] ?? null)
            ? array_map('intval', array_keys($input['catalog_visible']))
            : [];
        $visible = array_values(array_intersect($visible, $catalogIds));
        $hidden = array_values(array_filter(
            $bookIds,
            static fn(int $id): bool => !in_array($id, $visible, true)
        ));

        $values = [
            'home_featured_1_target_type' => $featured[1]['type'],
            'home_featured_1_book_id' => (string)($featured[1]['type'] === 'book' ? $featured[1]['id'] : 0),
            'home_featured_1_page_id' => (string)($featured[1]['type'] === 'page' ? $featured[1]['id'] : 0),
            'home_featured_1_event_id' => (string)($featured[1]['type'] === 'event' ? $featured[1]['id'] : 0),
            'home_featured_2_target_type' => $featured[2]['type'],
            'home_featured_2_book_id' => (string)($featured[2]['type'] === 'book' ? $featured[2]['id'] : 0),
            'home_featured_2_page_id' => (string)($featured[2]['type'] === 'page' ? $featured[2]['id'] : 0),
            'home_featured_2_event_id' => (string)($featured[2]['type'] === 'event' ? $featured[2]['id'] : 0),
            'home_catalog_order' => json_encode($bookIds, JSON_UNESCAPED_SLASHES),
            'home_catalog_hidden' => json_encode($hidden, JSON_UNESCAPED_SLASHES),
            'home_show_how_it_works' => !empty($input['show_how_it_works']) ? '1' : '0',
        ];

        $hero = $current['hero'];
        $textFields = [
            'hero_eyebrow' => ['eyebrow', 120],
            'hero_title' => ['title', 160],
            'hero_text' => ['text', 600],
            'hero_primary_label' => ['primary_label', 80],
            'hero_secondary_label' => ['secondary_label', 80],
            'hero_image_alt' => ['image_alt', 160],
        ];
        foreach ($textFields as $inputName => [$stateName, $maximumLength]) {
            if (array_key_exists($inputName, $input)) {
                $hero[$stateName] = $this->cleanText($input[$inputName], $maximumLength);
            }
        }
        foreach ([
            'hero_primary_url' => 'primary_url',
            'hero_secondary_url' => 'secondary_url',
            'hero_image_url' => 'image_url',
        ] as $inputName => $stateName) {
            if (array_key_exists($inputName, $input)) {
                $hero[$stateName] = $this->safeLink($input[$inputName]);
            }
        }
        if ($hero['title'] === '') {
            throw new RuntimeException('Nagłówek banera strony głównej nie może być pusty.');
        }
        foreach ([
            'pierwszego' => ['primary_label', 'primary_url'],
            'drugiego' => ['secondary_label', 'secondary_url'],
        ] as $buttonName => [$labelKey, $urlKey]) {
            if (($hero[$labelKey] === '') !== ($hero[$urlKey] === '')) {
                throw new RuntimeException('Uzupełnij zarówno tekst, jak i adres ' . $buttonName . ' przycisku albo pozostaw oba pola puste.');
            }
        }

        $heroImage = (string)$hero['image'];
        if (!empty($input['remove_hero_image'])) {
            $heroImage = '';
        }
        $hero['image'] = $this->saveHeroImage(
            is_array($files['hero_image'] ?? null) ? $files['hero_image'] : [],
            $heroImage
        );
        foreach ($hero as $name => $value) {
            $values['home_hero_' . $name] = $value;
        }

        foreach ([1, 2] as $slot) {
            $currentTarget = $current['featured_targets'][$slot] ?? ['type' => '', 'id' => 0];
            $currentImage = (string)($current['featured_images'][$slot] ?? '');
            if (
                (string)($currentTarget['type'] ?? '') !== $featured[$slot]['type']
                || (int)($currentTarget['id'] ?? 0) !== $featured[$slot]['id']
            ) {
                $currentImage = '';
            }
            if (!empty($input['remove_featured_' . $slot . '_image'])) {
                $currentImage = '';
            }
            $values['home_featured_' . $slot . '_image'] = $this->saveFeaturedImage(
                is_array($files['featured_' . $slot . '_image'] ?? null) ? $files['featured_' . $slot . '_image'] : [],
                $slot,
                $currentImage
            );
        }

        $this->saveValues($values);
    }

    public function heroDefaults(): array
    {
        return [
            'eyebrow' => 'Wydawnictwo Katolickie ARKA',
            'title' => 'Słowo · Wiara · Życie',
            'text' => 'Przestrzeń dla książek, które prowadzą ku wierze, prawdzie, nadziei i pojednaniu.',
            'primary_label' => 'Poznaj ARKĘ',
            'primary_url' => '/idea-znaku-arka',
            'secondary_label' => 'Rekolekcje Pojednania',
            'secondary_url' => '/rekolekcje-pojednania',
            'image' => '',
            'image_url' => '/idea-znaku-arka',
            'image_alt' => 'ARKA',
        ];
    }

    private function saveFeaturedImage(array $file, int $slot, string $current): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return $current;
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Nie udało się wgrać grafiki promowanej.');
        }
        if ((int)($file['size'] ?? 0) <= 0 || (int)$file['size'] > 12 * 1024 * 1024) {
            throw new RuntimeException('Grafika promowana może mieć maksymalnie 12 MB.');
        }
        if (!is_uploaded_file((string)($file['tmp_name'] ?? ''))) {
            throw new RuntimeException('Nieprawidłowe źródło grafiki promowanej.');
        }
        $root = dirname(__DIR__, 3);
        $directory = $root . '/public/uploads/home';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Nie można utworzyć katalogu grafik strony głównej.');
        }
        $filename = 'featured-' . $slot . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
        (new ImageOptimizer())->optimize(
            (string)$file['tmp_name'],
            $directory . '/' . $filename,
            2400,
            1800,
            84
        );
        return '/uploads/home/' . $filename . '.webp';
    }

    private function saveHeroImage(array $file, string $current): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return $current;
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Nie udało się wgrać grafiki głównego banera.');
        }
        if ((int)($file['size'] ?? 0) <= 0 || (int)$file['size'] > 12 * 1024 * 1024) {
            throw new RuntimeException('Grafika głównego banera może mieć maksymalnie 12 MB.');
        }
        if (!is_uploaded_file((string)($file['tmp_name'] ?? ''))) {
            throw new RuntimeException('Nieprawidłowe źródło grafiki głównego banera.');
        }
        $root = dirname(__DIR__, 3);
        $directory = $root . '/public/uploads/home';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Nie można utworzyć katalogu grafik strony głównej.');
        }
        $filename = 'hero-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
        (new ImageOptimizer())->optimize(
            (string)$file['tmp_name'],
            $directory . '/' . $filename,
            2400,
            1800,
            86
        );
        return '/uploads/home/' . $filename . '.webp';
    }

    private function saveValues(array $values): void
    {
        $pdo = Database::pdo();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $now = date('Y-m-d H:i:s');
        foreach ($values as $name => $value) {
            if ($driver === 'mysql') {
                $stmt = $pdo->prepare(
                    'INSERT INTO settings (name, value, is_secret, updated_at)
                     VALUES (?, ?, 0, ?)
                     ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = VALUES(updated_at)'
                );
            } else {
                $stmt = $pdo->prepare(
                    'INSERT OR REPLACE INTO settings (name, value, is_secret, updated_at)
                     VALUES (?, ?, 0, ?)'
                );
            }
            $stmt->execute([$name, (string)$value, $now]);
        }
    }

    private function defaultFeatured(array $books): array
    {
        $defaults = [
            1 => [
                'slug' => 'grzechy-przeciwne-nadziei',
                'image' => '/uploads/products/grzechy-przeciwne-nadziei/cover.jpg',
            ],
            2 => ['slug' => '', 'image' => ''],
        ];
        foreach ($defaults as $slot => $default) {
            $defaults[$slot]['book_id'] = 0;
            foreach ($books as $book) {
                if (($book['slug'] ?? '') === $default['slug']) {
                    $defaults[$slot]['book_id'] = (int)$book['id'];
                    break;
                }
            }
        }
        return $defaults;
    }

    private function positiveId(mixed $value): int
    {
        return max(0, (int)$value);
    }

    /** @return array{type:string,id:int} */
    private function parseFeaturedTarget(mixed $value): array
    {
        $value = trim((string)$value);
        if ($value === '' || $value === '0') {
            return ['type' => '', 'id' => 0];
        }
        if (!preg_match('/^(book|page|event):([1-9]\d*)$/', $value, $matches)) {
            throw new RuntimeException('Nieprawidłowy wybór promowanej treści.');
        }
        return ['type' => $matches[1], 'id' => (int)$matches[2]];
    }

    private function idList(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) return [];
        return array_values(array_unique(array_filter(array_map('intval', $decoded), static fn(int $id): bool => $id > 0)));
    }

    private function heroState(array $settings): array
    {
        $hero = $this->heroDefaults();
        foreach ($hero as $name => $default) {
            $key = 'home_hero_' . $name;
            if (array_key_exists($key, $settings)) {
                $hero[$name] = (string)$settings[$key];
            }
        }
        return $hero;
    }

    private function cleanText(mixed $value, int $maximumLength): string
    {
        $text = trim((string)$value);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\R{3,}/u', "\n\n", $text) ?? $text;
        if (mb_strlen($text) > $maximumLength) {
            throw new RuntimeException('Jedno z pól banera jest za długie.');
        }
        return $text;
    }

    private function safeLink(mixed $value): string
    {
        $url = trim((string)$value);
        if ($url === '') return '';
        if ((str_starts_with($url, '/') && !str_starts_with($url, '//')) || str_starts_with($url, '#')) {
            return $url;
        }
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
            if (in_array($scheme, ['http', 'https'], true)) {
                return $url;
            }
        }
        throw new RuntimeException('Link banera musi być ścieżką zaczynającą się od / lub pełnym adresem http(s).');
    }
}
