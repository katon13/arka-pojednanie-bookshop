<?php
namespace Book100\Repository;

use Book100\Core\Database;
use PDO;

final class SubscriberRepository
{
    public function subscribe(string $email, ?string $name = null, string $source = 'footer'): void
    {
        $email = trim(strtolower($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return;
        $now = date('Y-m-d H:i:s');
        $pdo = Database::pdo();
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $stmt = $pdo->prepare('INSERT INTO subscribers (email, name, source, consent_marketing, consent_date, status, unsubscribe_token, created_at) VALUES (:email,:name,:source,1,:consent_date,:status,:token,:created_at) ON DUPLICATE KEY UPDATE name=VALUES(name), source=VALUES(source), consent_marketing=1, consent_date=VALUES(consent_date), status=VALUES(status), unsubscribe_token=VALUES(unsubscribe_token)');
        } else {
            $stmt = $pdo->prepare('INSERT INTO subscribers (email, name, source, consent_marketing, consent_date, status, unsubscribe_token, created_at) VALUES (:email,:name,:source,1,:consent_date,:status,:token,:created_at) ON CONFLICT(email) DO UPDATE SET name=excluded.name, source=excluded.source, consent_marketing=1, consent_date=excluded.consent_date, status=excluded.status, unsubscribe_token=excluded.unsubscribe_token');
        }
        $stmt->execute([':email'=>$email, ':name'=>$name, ':source'=>$source, ':consent_date'=>$now, ':status'=>'active', ':token'=>bin2hex(random_bytes(20)), ':created_at'=>$now]);
    }

    public function unsubscribe(string $token): bool
    {
        $token = strtolower(trim($token));
        if (!preg_match('/^[a-f0-9]{40}$/', $token)) return false;
        return $this->deleteMatching('unsubscribe_token', $token);
    }

    public function deleteById(int $id): bool
    {
        if ($id < 1) return false;
        return $this->deleteMatching('id', $id);
    }

    public function all(int $limit = 500): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM subscribers ORDER BY created_at DESC, id DESC LIMIT ?');
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function activeEmails(): array
    {
        $stmt = Database::pdo()->query("SELECT * FROM subscribers WHERE status = 'active' AND consent_marketing = 1 ORDER BY email ASC");
        $subscribers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $update = null;
        foreach ($subscribers as &$subscriber) {
            $token = strtolower(trim((string)($subscriber['unsubscribe_token'] ?? '')));
            if (!preg_match('/^[a-f0-9]{40}$/', $token)) {
                $token = bin2hex(random_bytes(20));
                $update ??= Database::pdo()->prepare('UPDATE subscribers SET unsubscribe_token = ? WHERE id = ?');
                $update->execute([$token, (int)$subscriber['id']]);
            }
            $subscriber['unsubscribe_token'] = $token;
        }
        unset($subscriber);
        return $subscribers;
    }

    private function deleteMatching(string $column, string|int $value): bool
    {
        if (!in_array($column, ['id', 'unsubscribe_token'], true)) return false;
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT id, email FROM subscribers WHERE ' . $column . ' = ? LIMIT 1');
            $stmt->execute([$value]);
            $subscriber = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$subscriber) {
                $pdo->rollBack();
                return false;
            }

            $email = strtolower(trim((string)$subscriber['email']));
            $subscriberId = (int)$subscriber['id'];
            $pdo->prepare(
                "UPDATE email_logs
                 SET status = 'cancelled_unsubscribed', last_error = 'Odbiorca zrezygnował z newslettera.'
                 WHERE LOWER(to_email) = ?
                   AND template LIKE 'mailing_campaign_%'
                   AND status IN ('queued', 'failed_retry')"
            )->execute([$email]);
            $pdo->prepare(
                "UPDATE mailing_recipients SET status = 'unsubscribed'
                 WHERE subscriber_id = ? AND status = 'queued'"
            )->execute([$subscriberId]);
            $deleted = $pdo->prepare('DELETE FROM subscribers WHERE id = ?');
            $deleted->execute([$subscriberId]);
            $pdo->commit();
            return $deleted->rowCount() > 0;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }
}
