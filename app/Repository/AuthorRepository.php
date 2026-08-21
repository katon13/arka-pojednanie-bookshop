<?php
namespace Book100\Repository;

use Book100\Core\Database;
use PDO;

final class AuthorRepository
{
    public function all(): array
    {
        $stmt = Database::pdo()->query(
            'SELECT a.*, COUNT(DISTINCT b.id) AS books_count, COUNT(DISTINCT p.id) AS pages_count
             FROM authors a
             LEFT JOIN books b ON b.author_id = a.id
             LEFT JOIN content_pages p ON p.author_id = a.id
             GROUP BY a.id
             ORDER BY a.status = "active" DESC, a.name ASC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function active(): array
    {
        $stmt = Database::pdo()->query(
            "SELECT * FROM authors WHERE status = 'active' ORDER BY name ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM authors WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $author = $stmt->fetch(PDO::FETCH_ASSOC);
        return $author ?: null;
    }

    public function findByName(string $name): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM authors WHERE LOWER(name) = LOWER(?) LIMIT 1');
        $stmt->execute([trim($name)]);
        $author = $stmt->fetch(PDO::FETCH_ASSOC);
        return $author ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM authors WHERE slug = ? LIMIT 1');
        $stmt->execute([trim($slug)]);
        $author = $stmt->fetch(PDO::FETCH_ASSOC);
        return $author ?: null;
    }

    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $stmt = Database::pdo()->prepare(
            'INSERT INTO authors (name, slug, photo, short_bio, publications_url, status, created_at, updated_at)
             VALUES (:name, :slug, :photo, :short_bio, :publications_url, :status, :created_at, :updated_at)'
        );
        $stmt->execute($this->params($data, $now));
        return (int)Database::pdo()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $params = $this->params($data, date('Y-m-d H:i:s'));
        unset($params[':created_at']);
        $params[':id'] = $id;
        $stmt = Database::pdo()->prepare(
            'UPDATE authors SET name=:name, slug=:slug, photo=:photo, short_bio=:short_bio,
             publications_url=:publications_url, status=:status, updated_at=:updated_at WHERE id=:id'
        );
        $stmt->execute($params);

        Database::pdo()->prepare('UPDATE books SET author = ?, updated_at = ? WHERE author_id = ?')
            ->execute([trim((string)$data['name']), date('Y-m-d H:i:s'), $id]);
    }

    public function archive(int $id): void
    {
        Database::pdo()->prepare(
            "UPDATE authors SET status = 'hidden', updated_at = ? WHERE id = ?"
        )->execute([date('Y-m-d H:i:s'), $id]);
    }

    public function assignLegacyAuthors(): void
    {
        $pdo = Database::pdo();
        $rows = $pdo->query(
            "SELECT DISTINCT TRIM(author) AS name FROM books
             WHERE author IS NOT NULL AND TRIM(author) <> ''
             ORDER BY name"
        )->fetchAll(PDO::FETCH_COLUMN);

        foreach ($rows as $name) {
            $name = trim((string)$name);
            if ($name === '') continue;
            $author = $this->findByName($name);
            if (!$author) {
                $id = $this->create([
                    'name' => $name,
                    'slug' => BookRepository::slugify($name),
                    'photo' => '',
                    'short_bio' => '',
                    'publications_url' => '',
                    'status' => 'active',
                ]);
            } else {
                $id = (int)$author['id'];
            }
            $pdo->prepare(
                'UPDATE books SET author_id = ? WHERE author_id IS NULL AND LOWER(TRIM(author)) = LOWER(?)'
            )->execute([$id, $name]);
        }
    }

    private function params(array $data, string $now): array
    {
        $name = trim((string)($data['name'] ?? ''));
        return [
            ':name' => $name,
            ':slug' => trim((string)($data['slug'] ?? '')) ?: BookRepository::slugify($name),
            ':photo' => trim((string)($data['photo'] ?? '')) ?: null,
            ':short_bio' => trim((string)($data['short_bio'] ?? '')) ?: null,
            ':publications_url' => trim((string)($data['publications_url'] ?? '')) ?: null,
            ':status' => in_array(($data['status'] ?? ''), ['active', 'hidden'], true)
                ? (string)$data['status']
                : 'active',
            ':created_at' => $now,
            ':updated_at' => $now,
        ];
    }
}
