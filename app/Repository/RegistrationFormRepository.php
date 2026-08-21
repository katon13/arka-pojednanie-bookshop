<?php
namespace Book100\Repository;

use Book100\Core\Database;
use PDO;

final class RegistrationFormRepository
{
    public function all(): array
    {
        return Database::pdo()->query(
            'SELECT f.*, (SELECT COUNT(*) FROM registrations r WHERE r.form_id = f.id) AS registrations_count
             FROM registration_forms f
             ORDER BY CASE WHEN f.status = \'active\' THEN 0 ELSE 1 END, f.name ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function active(): array
    {
        return Database::pdo()->query(
            "SELECT * FROM registration_forms WHERE status = 'active' ORDER BY name ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT f.*, (SELECT COUNT(*) FROM registrations r WHERE r.form_id = f.id) AS registrations_count
             FROM registration_forms f
             WHERE f.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $form = $stmt->fetch(PDO::FETCH_ASSOC);
        return $form ?: null;
    }

    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $stmt = Database::pdo()->prepare(
            'INSERT INTO registration_forms
             (name, recipient_email, email_subject, intro_text, submit_label, success_message, fields_json, status, created_at, updated_at)
             VALUES (:name, :recipient_email, :email_subject, :intro_text, :submit_label, :success_message, :fields_json, :status, :created_at, :updated_at)'
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
            'UPDATE registration_forms SET name=:name, recipient_email=:recipient_email,
             email_subject=:email_subject, intro_text=:intro_text, submit_label=:submit_label,
             success_message=:success_message, fields_json=:fields_json, status=:status,
             updated_at=:updated_at WHERE id=:id'
        )->execute($params);
    }

    public function archive(int $id): void
    {
        Database::pdo()->prepare(
            "UPDATE registration_forms SET status = 'hidden', updated_at = ? WHERE id = ?"
        )->execute([date('Y-m-d H:i:s'), $id]);
    }

    public static function fields(array $form): array
    {
        $decoded = json_decode((string)($form['fields_json'] ?? '[]'), true);
        return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
    }

    private function params(array $data, string $now): array
    {
        return [
            ':name' => trim((string)($data['name'] ?? '')),
            ':recipient_email' => trim((string)($data['recipient_email'] ?? '')),
            ':email_subject' => trim((string)($data['email_subject'] ?? '')) ?: null,
            ':intro_text' => trim((string)($data['intro_text'] ?? '')) ?: null,
            ':submit_label' => trim((string)($data['submit_label'] ?? '')) ?: 'Wyślij zgłoszenie',
            ':success_message' => trim((string)($data['success_message'] ?? '')) ?: 'Dziękujemy. Zgłoszenie zostało przyjęte.',
            ':fields_json' => (string)($data['fields_json'] ?? '[]'),
            ':status' => ($data['status'] ?? '') === 'hidden' ? 'hidden' : 'active',
            ':created_at' => $now,
            ':updated_at' => $now,
        ];
    }
}
