<?php
namespace Book100\Repository;

use Book100\Core\Database;
use PDO;

final class RegistrationRepository
{
    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $stmt = Database::pdo()->prepare(
            'INSERT INTO registrations
             (form_id, content_page_id, event_id, source_label, person_name, email, phone,
              data_json, status, admin_note, consent_at, created_at, updated_at)
             VALUES
             (:form_id, :content_page_id, :event_id, :source_label, :person_name, :email, :phone,
              :data_json, :status, :admin_note, :consent_at, :created_at, :updated_at)'
        );
        $stmt->execute([
            ':form_id' => (int)$data['form_id'],
            ':content_page_id' => !empty($data['content_page_id']) ? (int)$data['content_page_id'] : null,
            ':event_id' => !empty($data['event_id']) ? (int)$data['event_id'] : null,
            ':source_label' => trim((string)($data['source_label'] ?? '')) ?: null,
            ':person_name' => trim((string)($data['person_name'] ?? '')) ?: null,
            ':email' => trim((string)($data['email'] ?? '')) ?: null,
            ':phone' => trim((string)($data['phone'] ?? '')) ?: null,
            ':data_json' => json_encode($data['values'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':status' => in_array(($data['status'] ?? ''), ['new', 'confirmed', 'cancelled'], true)
                ? (string)$data['status']
                : 'new',
            ':admin_note' => trim((string)($data['admin_note'] ?? '')) ?: null,
            ':consent_at' => $data['consent_at'] ?? null,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        return (int)Database::pdo()->lastInsertId();
    }

    public function forEvent(int $eventId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT r.*, f.name AS form_name
             FROM registrations r
             LEFT JOIN registration_forms f ON f.id = r.form_id
             WHERE r.event_id = ?
             ORDER BY r.created_at DESC, r.id DESC'
        );
        $stmt->execute([$eventId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function forForm(int $formId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT r.*, e.title AS event_title, p.title AS page_title
             FROM registrations r
             LEFT JOIN events e ON e.id = r.event_id
             LEFT JOIN content_pages p ON p.id = r.content_page_id
             WHERE r.form_id = ?
             ORDER BY r.created_at DESC, r.id DESC'
        );
        $stmt->execute([$formId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM registrations WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function update(int $id, string $status, string $note): void
    {
        $status = in_array($status, ['new', 'confirmed', 'cancelled'], true) ? $status : 'new';
        Database::pdo()->prepare(
            'UPDATE registrations SET status = ?, admin_note = ?, updated_at = ? WHERE id = ?'
        )->execute([$status, trim($note) ?: null, date('Y-m-d H:i:s'), $id]);
    }
}
