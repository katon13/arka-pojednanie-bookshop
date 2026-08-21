<?php
namespace Book100\Repository;

use Book100\Core\Database;
use PDO;

final class EventRepository
{
    public function all(): array
    {
        return Database::pdo()->query(
            $this->selectSql()
            . " ORDER BY e.status = 'published' DESC, e.starts_at DESC, e.title ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function allPublished(): array
    {
        return Database::pdo()->query(
            $this->selectSql(true)
            . " WHERE e.status = 'published' ORDER BY e.starts_at ASC, e.title ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function allPublic(): array
    {
        return Database::pdo()->query(
            $this->selectSql(true)
            . " WHERE e.status IN ('published', 'archived') ORDER BY e.starts_at DESC, e.title ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare($this->selectSql() . ' WHERE e.id = ? LIMIT 1');
        $stmt->execute([$id]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        return $event ?: null;
    }

    public function findPublishedBySlug(string $slug): ?array
    {
        $stmt = Database::pdo()->prepare(
            $this->selectSql(true) . " WHERE e.slug = ? AND e.status = 'published' LIMIT 1"
        );
        $stmt->execute([$slug]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        return $event ?: null;
    }

    public function findPublicBySlug(string $slug): ?array
    {
        $stmt = Database::pdo()->prepare(
            $this->selectSql(true)
            . " WHERE e.slug = ? AND e.status IN ('published', 'archived') LIMIT 1"
        );
        $stmt->execute([$slug]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        return $event ?: null;
    }

    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $stmt = Database::pdo()->prepare(
            'INSERT INTO events
             (slug, title, author_id, excerpt, content, starts_at, ends_at, location, organizer,
              featured_image, registration_form_id, status, seo_title, seo_description, created_at, updated_at)
             VALUES
             (:slug, :title, :author_id, :excerpt, :content, :starts_at, :ends_at, :location, :organizer,
              :featured_image, :registration_form_id, :status, :seo_title, :seo_description, :created_at, :updated_at)'
        );
        $stmt->execute($this->params($data, $now));
        return (int)Database::pdo()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $params = $this->params($data, date('Y-m-d H:i:s'));
        unset($params[':created_at']);
        $params[':id'] = $id;
        Database::pdo()->prepare(
            'UPDATE events SET slug=:slug, title=:title, author_id=:author_id, excerpt=:excerpt,
             content=:content, starts_at=:starts_at, ends_at=:ends_at, location=:location,
             organizer=:organizer, featured_image=:featured_image,
             registration_form_id=:registration_form_id, status=:status, seo_title=:seo_title,
             seo_description=:seo_description, updated_at=:updated_at WHERE id=:id'
        )->execute($params);
    }

    public function archive(int $id): void
    {
        Database::pdo()->prepare(
            "UPDATE events SET status = 'archived', updated_at = ? WHERE id = ?"
        )->execute([date('Y-m-d H:i:s'), $id]);
    }

    public function delete(int $id): array
    {
        $pdo = Database::pdo();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $event = $this->find($id);
            if (!$event) {
                throw new \RuntimeException('Wydarzenie już nie istnieje.');
            }

            $count = $pdo->prepare('SELECT COUNT(*) FROM registrations WHERE event_id = ?');
            $count->execute([$id]);
            $registrationsDetached = (int)$count->fetchColumn();

            $pdo->prepare('UPDATE registrations SET event_id = NULL, updated_at = ? WHERE event_id = ?')
                ->execute([date('Y-m-d H:i:s'), $id]);
            $pdo->prepare('DELETE FROM events WHERE id = ?')->execute([$id]);
            (new SettingsRepository())->forgetHomepageTarget('event', $id);

            if ($ownsTransaction) {
                $pdo->commit();
            }

            return [
                'event_deleted' => true,
                'registrations_detached' => $registrationsDetached,
            ];
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function selectSql(bool $activeAuthor = false): string
    {
        return 'SELECT e.*, a.name AS author, a.slug AS author_slug, a.photo AS author_photo,
                a.short_bio AS author_bio, a.publications_url AS author_publications_url,
                f.name AS registration_form_name,
                (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id) AS registrations_count
                FROM events e
                LEFT JOIN authors a ON a.id = e.author_id'
                . ($activeAuthor ? " AND a.status = 'active'" : '')
                . ' LEFT JOIN registration_forms f ON f.id = e.registration_form_id';
    }

    private function params(array $data, string $now): array
    {
        return [
            ':slug' => trim((string)($data['slug'] ?? '')),
            ':title' => trim((string)($data['title'] ?? '')),
            ':author_id' => !empty($data['author_id']) ? (int)$data['author_id'] : null,
            ':excerpt' => trim((string)($data['excerpt'] ?? '')) ?: null,
            ':content' => trim((string)($data['content'] ?? '')) ?: null,
            ':starts_at' => trim((string)($data['starts_at'] ?? '')),
            ':ends_at' => trim((string)($data['ends_at'] ?? '')) ?: null,
            ':location' => trim((string)($data['location'] ?? '')) ?: null,
            ':organizer' => trim((string)($data['organizer'] ?? '')) ?: null,
            ':featured_image' => trim((string)($data['featured_image'] ?? '')) ?: null,
            ':registration_form_id' => !empty($data['registration_form_id']) ? (int)$data['registration_form_id'] : null,
            ':status' => in_array(($data['status'] ?? ''), ['draft', 'published', 'archived'], true)
                ? (string)$data['status']
                : 'draft',
            ':seo_title' => trim((string)($data['seo_title'] ?? '')) ?: null,
            ':seo_description' => trim((string)($data['seo_description'] ?? '')) ?: null,
            ':created_at' => $now,
            ':updated_at' => $now,
        ];
    }
}
