<?php
namespace Book100\Repository;

use Book100\Core\Database;
use Book100\Core\Utf8Sanitizer;
use Book100\Services\Mail\EmailTemplate;
use PDO;

final class EmailLogRepository
{
    public function queueCustom(
        string $to,
        string $subject,
        string $body,
        string $template,
        string $customerName = '',
        string $replyTo = '',
        array $attachments = []
    ): int {
        $subject = Utf8Sanitizer::normalize($subject);
        $body = Utf8Sanitizer::normalize($body);
        $stmt = Database::pdo()->prepare(
            'INSERT INTO email_logs
             (to_email, reply_to, subject, template, customer_name, body, attachments_json, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            trim($to),
            filter_var($replyTo, FILTER_VALIDATE_EMAIL) ? trim($replyTo) : null,
            trim($subject),
            trim($template),
            trim($customerName) ?: null,
            $body,
            $attachments === [] ? null : json_encode($attachments, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'queued',
            date('Y-m-d H:i:s'),
        ]);
        return (int)Database::pdo()->lastInsertId();
    }

    public function search(string $query = '', string $status = '', string $template = '', int $page = 1, int $perPage = 40): array
    {
        $where = [];
        $params = [];
        if ($query !== '') {
            $where[] = '(to_email LIKE :q OR customer_name LIKE :q OR subject LIKE :q)';
            $params[':q'] = '%' . $query . '%';
        }
        if ($status !== '') {
            $where[] = 'status = :status';
            $params[':status'] = $status;
        }
        if ($template !== '') {
            $where[] = 'template = :template';
            $params[':template'] = $template;
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $pdo = Database::pdo();
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM email_logs' . $whereSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $page = max(1, $page);
        $perPage = max(10, min(100, $perPage));
        $stmt = $pdo->prepare(
            'SELECT * FROM email_logs' . $whereSql . ' ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) $stmt->bindValue($key, $value);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();
        return [
            'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int)ceil($total / $perPage)),
            'stats' => $this->stats(),
        ];
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM email_logs WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function queueTest(string $email): int
    {
        $subject = 'Test poczty ze sklepu ARKA';
        $body = Utf8Sanitizer::normalize((new EmailTemplate())->generic(
            $subject,
            'To jest wiadomość kontrolna. SMTP, wersja HTML i tekstowa oraz uwierzytelnienie domeny działają, jeśli ten mail dotarł poprawnie.'
        ));
        $stmt = Database::pdo()->prepare(
            'INSERT INTO email_logs (to_email, subject, template, body, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$email, $subject, 'system_test', $body, 'queued', date('Y-m-d H:i:s')]);
        return (int)Database::pdo()->lastInsertId();
    }

    public function retry(int $id): void
    {
        Database::pdo()->prepare(
            "UPDATE email_logs SET status='queued', last_error=NULL WHERE id=?"
        )->execute([$id]);
    }

    private function stats(): array
    {
        $row = Database::pdo()->query(
            "SELECT COUNT(*) AS total,
             SUM(CASE WHEN status='sent' THEN 1 ELSE 0 END) AS sent,
             SUM(CASE WHEN status='queued' THEN 1 ELSE 0 END) AS waiting,
             SUM(CASE WHEN status='failed_retry' THEN 1 ELSE 0 END) AS failed
             FROM email_logs"
        )->fetch(PDO::FETCH_ASSOC) ?: [];
        return array_map('intval', $row);
    }
}
