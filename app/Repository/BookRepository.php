<?php
namespace Book100\Repository;

use Book100\Core\Database;
use Book100\Services\Books\BookSaleState;
use PDO;

final class BookRepository
{
    public function allPublic(): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn(array $book): bool => BookSaleState::isPublic($book)
        ));
    }

    public function allPurchasable(): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn(array $book): bool => BookSaleState::isPurchasable($book)
        ));
    }

    public function findPurchasableByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
        if ($ids === []) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::pdo()->prepare(
            "SELECT b.*, a.slug AS author_slug, a.photo AS author_photo, a.short_bio AS author_bio,
                    a.publications_url AS author_publications_url
             FROM books b
             LEFT JOIN authors a ON a.id = b.author_id AND a.status = 'active'
             WHERE b.id IN ($placeholders) AND b.status IN ('active','preorder') ORDER BY b.title ASC"
        );
        $stmt->execute($ids);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $byId = [];
        foreach ($rows as $row) {
            if (!BookSaleState::isPurchasable($row)) {
                continue;
            }
            $byId[(int)$row['id']] = $row;
        }
        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) $ordered[] = $byId[$id];
        }
        return $ordered;
    }

    public function all(): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->query(
            "SELECT b.*, a.slug AS author_slug, a.photo AS author_photo, a.short_bio AS author_bio,
                    a.publications_url AS author_publications_url
             FROM books b
             LEFT JOIN authors a ON a.id = b.author_id AND a.status = 'active'
             ORDER BY b.status IN ('active','preorder') DESC, b.title ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT b.*, a.slug AS author_slug, a.photo AS author_photo, a.short_bio AS author_bio,
                    a.publications_url AS author_publications_url
             FROM books b
             LEFT JOIN authors a ON a.id = b.author_id AND a.status = 'active'
             WHERE b.slug = ? LIMIT 1"
        );
        $stmt->execute([$slug]);
        $book = $stmt->fetch(PDO::FETCH_ASSOC);
        return $book ?: null;
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT b.*, a.slug AS author_slug, a.photo AS author_photo, a.short_bio AS author_bio,
                    a.publications_url AS author_publications_url
             FROM books b
             LEFT JOIN authors a ON a.id = b.author_id
             WHERE b.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $book = $stmt->fetch(PDO::FETCH_ASSOC);
        return $book ?: null;
    }

    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $stmt = Database::pdo()->prepare('INSERT INTO books (old_wp_id, sku, slug, title, author_id, author, short_description, description, price_gross, currency, product_type, status, release_date, stock_qty, manage_stock, weight_kg, length_cm, width_cm, height_cm, isbn, publisher, publication_year, pages, format, attributes_json, cover_image, ebook_file_path, seo_title, seo_description, seo_keywords, canonical_url, created_at, updated_at) VALUES (:old_wp_id,:sku,:slug,:title,:author_id,:author,:short_description,:description,:price_gross,:currency,:product_type,:status,:release_date,:stock_qty,:manage_stock,:weight_kg,:length_cm,:width_cm,:height_cm,:isbn,:publisher,:publication_year,:pages,:format,:attributes_json,:cover_image,:ebook_file_path,:seo_title,:seo_description,:seo_keywords,:canonical_url,:created_at,:updated_at)');
        $stmt->execute($this->params($data, $now));
        return (int)Database::pdo()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $params = $this->params($data, date('Y-m-d H:i:s'));
        $params[':id'] = $id;
        unset($params[':created_at']);
        $stmt = Database::pdo()->prepare('UPDATE books SET old_wp_id=:old_wp_id, sku=:sku, slug=:slug, title=:title, author_id=:author_id, author=:author, short_description=:short_description, description=:description, price_gross=:price_gross, currency=:currency, product_type=:product_type, status=:status, release_date=:release_date, stock_qty=:stock_qty, manage_stock=:manage_stock, weight_kg=:weight_kg, length_cm=:length_cm, width_cm=:width_cm, height_cm=:height_cm, isbn=:isbn, publisher=:publisher, publication_year=:publication_year, pages=:pages, format=:format, attributes_json=:attributes_json, cover_image=:cover_image, ebook_file_path=:ebook_file_path, seo_title=:seo_title, seo_description=:seo_description, seo_keywords=:seo_keywords, canonical_url=:canonical_url, updated_at=:updated_at WHERE id=:id');
        $stmt->execute($params);
    }

    public function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM books WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function deletePermanentlyWithSalesHistory(int $id): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $orderStmt = $pdo->prepare('SELECT DISTINCT order_id FROM order_items WHERE book_id = ?');
            $orderStmt->execute([$id]);
            $orderIds = array_values(array_filter(
                array_map('intval', $orderStmt->fetchAll(PDO::FETCH_COLUMN)),
                static fn(int $orderId): bool => $orderId > 0
            ));

            $deletedOrders = 0;
            $deletedOrderItems = 0;
            if ($orderIds !== []) {
                $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
                foreach (['payments', 'shipments', 'webhook_logs', 'email_logs'] as $table) {
                    if (!$this->tableExists($pdo, $table)) continue;
                    $pdo->prepare("DELETE FROM {$table} WHERE order_id IN ({$placeholders})")->execute($orderIds);
                }
                $itemsDelete = $pdo->prepare("DELETE FROM order_items WHERE order_id IN ({$placeholders})");
                $itemsDelete->execute($orderIds);
                $deletedOrderItems = $itemsDelete->rowCount();

                $ordersDelete = $pdo->prepare("DELETE FROM orders WHERE id IN ({$placeholders})");
                $ordersDelete->execute($orderIds);
                $deletedOrders = $ordersDelete->rowCount();
            }

            foreach (['book_category_links', 'book_images'] as $table) {
                if (!$this->tableExists($pdo, $table)) continue;
                $pdo->prepare("DELETE FROM {$table} WHERE book_id = ?")->execute([$id]);
            }

            $pdo->prepare(
                "UPDATE settings SET value = '', updated_at = ?
                 WHERE name IN ('home_featured_1_book_id', 'home_featured_2_book_id') AND value = ?"
            )->execute([date('Y-m-d H:i:s'), (string)$id]);

            $bookDelete = $pdo->prepare('DELETE FROM books WHERE id = ?');
            $bookDelete->execute([$id]);
            if ($bookDelete->rowCount() !== 1) {
                throw new \RuntimeException('Książka nie istnieje albo została już usunięta.');
            }

            $pdo->commit();
            return [
                'book_deleted' => true,
                'orders_deleted' => $deletedOrders,
                'order_items_deleted' => $deletedOrderItems,
            ];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1");
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        }

        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    }

    public static function slugify(string $text): string
    {
        $map = ['ą'=>'a','ć'=>'c','ę'=>'e','ł'=>'l','ń'=>'n','ó'=>'o','ś'=>'s','ż'=>'z','ź'=>'z','Ą'=>'a','Ć'=>'c','Ę'=>'e','Ł'=>'l','Ń'=>'n','Ó'=>'o','Ś'=>'s','Ż'=>'z','Ź'=>'z'];
        $text = strtr($text, $map);
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?: 'ksiazka';
        return trim($text, '-') ?: 'ksiazka';
    }

    private function params(array $data, string $now): array
    {
        return [
            ':old_wp_id' => ($data['old_wp_id'] ?? null) ?: null,
            ':sku' => trim((string)($data['sku'] ?? '')) ?: null,
            ':slug' => trim((string)($data['slug'] ?? '')) ?: self::slugify((string)$data['title']),
            ':title' => trim((string)$data['title']),
            ':author_id' => !empty($data['author_id']) ? (int)$data['author_id'] : null,
            ':author' => trim((string)($data['author'] ?? '')) ?: null,
            ':short_description' => trim((string)($data['short_description'] ?? '')) ?: null,
            ':description' => trim((string)($data['description'] ?? '')) ?: null,
            ':price_gross' => (float)str_replace(',', '.', (string)($data['price_gross'] ?? 0)),
            ':currency' => $data['currency'] ?? 'PLN',
            ':product_type' => $data['product_type'] ?? 'paper',
            ':status' => $data['status'] ?? 'draft',
            ':release_date' => trim((string)($data['release_date'] ?? '')) ?: null,
            ':stock_qty' => (int)($data['stock_qty'] ?? 0),
            ':manage_stock' => !empty($data['manage_stock']) ? 1 : 0,
            ':weight_kg' => ($data['weight_kg'] ?? '') !== '' ? $data['weight_kg'] : null,
            ':length_cm' => ($data['length_cm'] ?? '') !== '' ? $data['length_cm'] : null,
            ':width_cm' => ($data['width_cm'] ?? '') !== '' ? $data['width_cm'] : null,
            ':height_cm' => ($data['height_cm'] ?? '') !== '' ? $data['height_cm'] : null,
            ':isbn' => trim((string)($data['isbn'] ?? '')) ?: null,
            ':publisher' => trim((string)($data['publisher'] ?? '')) ?: null,
            ':publication_year' => ($data['publication_year'] ?? '') !== '' ? (int)$data['publication_year'] : null,
            ':pages' => ($data['pages'] ?? '') !== '' ? (int)$data['pages'] : null,
            ':format' => trim((string)($data['format'] ?? '')) ?: null,
            ':attributes_json' => $this->normalizeAttributes($data['attributes_json'] ?? null),
            ':cover_image' => trim((string)($data['cover_image'] ?? '')) ?: null,
            ':ebook_file_path' => trim((string)($data['ebook_file_path'] ?? '')) ?: null,
            ':seo_title' => trim((string)($data['seo_title'] ?? '')) ?: null,
            ':seo_description' => trim((string)($data['seo_description'] ?? '')) ?: null,
            ':seo_keywords' => trim((string)($data['seo_keywords'] ?? '')) ?: null,
            ':canonical_url' => trim((string)($data['canonical_url'] ?? '')) ?: null,
            ':created_at' => $now,
            ':updated_at' => $now,
        ];
    }

    private function normalizeAttributes(mixed $attributes): string
    {
        if (is_array($attributes)) {
            return json_encode($attributes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
        }

        $decoded = json_decode((string)$attributes, true);
        return is_array($decoded)
            ? (json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]')
            : '[]';
    }
}
