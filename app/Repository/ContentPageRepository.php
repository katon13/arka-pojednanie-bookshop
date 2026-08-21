<?php
namespace Book100\Repository;

use Book100\Core\Database;
use PDO;

final class ContentPageRepository
{
    private ?bool $authorColumnAvailable = null;

    public function all(): array
    {
        return Database::pdo()
            ->query(
                $this->selectWithAuthor()
                . " ORDER BY p.status = 'published' DESC, p.updated_at DESC, p.title ASC"
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function allPublished(): array
    {
        $stmt = Database::pdo()->query(
            $this->selectWithAuthor(true)
            . " WHERE p.status = 'published' ORDER BY p.title ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            $this->selectWithAuthor() . ' WHERE p.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $page = $stmt->fetch(PDO::FETCH_ASSOC);
        return $page ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = Database::pdo()->prepare(
            $this->selectWithAuthor() . ' WHERE p.slug = ? LIMIT 1'
        );
        $stmt->execute([$slug]);
        $page = $stmt->fetch(PDO::FETCH_ASSOC);
        return $page ?: null;
    }

    public function findPublishedBySlug(string $slug): ?array
    {
        $stmt = Database::pdo()->prepare(
            $this->selectWithAuthor(true)
            . " WHERE p.slug = ? AND p.status = 'published' LIMIT 1"
        );
        $stmt->execute([$slug]);
        $page = $stmt->fetch(PDO::FETCH_ASSOC);
        return $page ?: null;
    }

    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $params = $this->params($data, $now);
        if ($this->hasAuthorColumn()) {
            $stmt = Database::pdo()->prepare(
            'INSERT INTO content_pages (old_wp_id, slug, title, author_id, registration_form_id, excerpt, content, status, featured_image, seo_title, seo_description, canonical_url, created_at, updated_at)
                 VALUES (:old_wp_id, :slug, :title, :author_id, :registration_form_id, :excerpt, :content, :status, :featured_image, :seo_title, :seo_description, :canonical_url, :created_at, :updated_at)'
            );
        } else {
            unset($params[':author_id']);
            $stmt = Database::pdo()->prepare(
                'INSERT INTO content_pages (old_wp_id, slug, title, registration_form_id, excerpt, content, status, featured_image, seo_title, seo_description, canonical_url, created_at, updated_at)
                 VALUES (:old_wp_id, :slug, :title, :registration_form_id, :excerpt, :content, :status, :featured_image, :seo_title, :seo_description, :canonical_url, :created_at, :updated_at)'
            );
        }
        $stmt->execute($params);
        return (int)Database::pdo()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $params = $this->params($data, date('Y-m-d H:i:s'));
        unset($params[':created_at']);
        $params[':id'] = $id;
        if ($this->hasAuthorColumn()) {
            $stmt = Database::pdo()->prepare(
                'UPDATE content_pages SET old_wp_id=:old_wp_id, slug=:slug, title=:title, author_id=:author_id, registration_form_id=:registration_form_id, excerpt=:excerpt,
                 content=:content, status=:status, featured_image=:featured_image, seo_title=:seo_title,
                 seo_description=:seo_description, canonical_url=:canonical_url, updated_at=:updated_at WHERE id=:id'
            );
        } else {
            unset($params[':author_id']);
            $stmt = Database::pdo()->prepare(
                'UPDATE content_pages SET old_wp_id=:old_wp_id, slug=:slug, title=:title, registration_form_id=:registration_form_id, excerpt=:excerpt,
                 content=:content, status=:status, featured_image=:featured_image, seo_title=:seo_title,
                 seo_description=:seo_description, canonical_url=:canonical_url, updated_at=:updated_at WHERE id=:id'
            );
        }
        $stmt->execute($params);
    }

    public function upsertLegacy(array $data): int
    {
        $existing = null;
        if (!empty($data['old_wp_id'])) {
            $stmt = Database::pdo()->prepare('SELECT * FROM content_pages WHERE old_wp_id = ? LIMIT 1');
            $stmt->execute([(int)$data['old_wp_id']]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        $existing ??= $this->findBySlug((string)$data['slug']);
        if ($existing) {
            $data['featured_image'] = $data['featured_image'] ?? $existing['featured_image'] ?? null;
            $data['author_id'] = $data['author_id'] ?? $existing['author_id'] ?? null;
            $this->update((int)$existing['id'], $data);
            return (int)$existing['id'];
        }
        return $this->create($data);
    }

    public function archive(int $id): void
    {
        Database::pdo()->prepare("UPDATE content_pages SET status = 'hidden', updated_at = ? WHERE id = ?")
            ->execute([date('Y-m-d H:i:s'), $id]);
    }

    public function delete(int $id): array
    {
        $pdo = Database::pdo();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $page = $this->find($id);
            if (!$page) {
                throw new \RuntimeException('Strona już nie istnieje.');
            }

            $count = $pdo->prepare('SELECT COUNT(*) FROM registrations WHERE content_page_id = ?');
            $count->execute([$id]);
            $registrationsDetached = (int)$count->fetchColumn();

            $pdo->prepare('UPDATE registrations SET content_page_id = NULL, updated_at = ? WHERE content_page_id = ?')
                ->execute([date('Y-m-d H:i:s'), $id]);
            $pdo->prepare('DELETE FROM content_pages WHERE id = ?')->execute([$id]);
            (new SettingsRepository())->forgetHomepageTarget('page', $id);

            if ($ownsTransaction) {
                $pdo->commit();
            }

            return [
                'page_deleted' => true,
                'registrations_detached' => $registrationsDetached,
            ];
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function params(array $data, string $now): array
    {
        return [
            ':old_wp_id' => ($data['old_wp_id'] ?? null) ?: null,
            ':slug' => trim((string)($data['slug'] ?? '')),
            ':title' => trim((string)($data['title'] ?? '')),
            ':author_id' => !empty($data['author_id']) ? (int)$data['author_id'] : null,
            ':registration_form_id' => !empty($data['registration_form_id']) ? (int)$data['registration_form_id'] : null,
            ':excerpt' => trim((string)($data['excerpt'] ?? '')) ?: null,
            ':content' => trim((string)($data['content'] ?? '')) ?: null,
            ':status' => (string)($data['status'] ?? 'draft'),
            ':featured_image' => trim((string)($data['featured_image'] ?? '')) ?: null,
            ':seo_title' => trim((string)($data['seo_title'] ?? '')) ?: null,
            ':seo_description' => trim((string)($data['seo_description'] ?? '')) ?: null,
            ':canonical_url' => trim((string)($data['canonical_url'] ?? '')) ?: null,
            ':created_at' => $now,
            ':updated_at' => $now,
        ];
    }

    private function selectWithAuthor(bool $activeOnly = false): string
    {
        if (!$this->hasAuthorColumn()) {
            return 'SELECT p.*, NULL AS author_id, NULL AS author, NULL AS author_slug,
                    NULL AS author_photo, NULL AS author_bio, NULL AS author_publications_url
                    FROM content_pages p';
        }
        return 'SELECT p.*, a.name AS author, a.slug AS author_slug, a.photo AS author_photo,
                a.short_bio AS author_bio, a.publications_url AS author_publications_url
                FROM content_pages p
                LEFT JOIN authors a ON a.id = p.author_id'
                . ($activeOnly ? " AND a.status = 'active'" : '');
    }

    private function hasAuthorColumn(): bool
    {
        if ($this->authorColumnAvailable !== null) {
            return $this->authorColumnAvailable;
        }
        $pdo = Database::pdo();
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $columns = $pdo->query('PRAGMA table_info(content_pages)')->fetchAll(PDO::FETCH_ASSOC);
            return $this->authorColumnAvailable = count(array_filter(
                $columns,
                static fn(array $column): bool => ($column['name'] ?? '') === 'author_id'
            )) > 0;
        }
        $stmt = $pdo->query("SHOW COLUMNS FROM content_pages LIKE 'author_id'");
        return $this->authorColumnAvailable = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }
}
