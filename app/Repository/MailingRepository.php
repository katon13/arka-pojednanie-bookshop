<?php
namespace Book100\Repository;

use Book100\Core\Database;
use Book100\Core\Utf8Sanitizer;
use Book100\Services\Mail\EmailTemplate;
use Book100\Services\Seo\SeoBuilder;
use PDO;

final class MailingRepository
{
    public function createCampaign(string $subject, string $body, array $subscribers): int
    {
        $pdo = Database::pdo();
        $now = date('Y-m-d H:i:s');
        $pdo->beginTransaction();
        try {
            $subject = Utf8Sanitizer::normalize($subject);
            $body = Utf8Sanitizer::normalize($body);
            $stmt = $pdo->prepare('INSERT INTO mailing_campaigns (subject, body, status, recipients_count, created_at) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$subject, $body, 'queued', count($subscribers), $now]);
            $campaignId = (int)$pdo->lastInsertId();
            $rec = $pdo->prepare('INSERT INTO mailing_recipients (campaign_id, subscriber_id, email, status, created_at) VALUES (?, ?, ?, ?, ?)');
            $log = $pdo->prepare('INSERT INTO email_logs (to_email, subject, template, body, status, created_at) VALUES (?, ?, ?, ?, ?, ?)');
            $emailTemplate = new EmailTemplate();
            foreach ($subscribers as $s) {
                $unsubscribeToken = strtolower(trim((string)($s['unsubscribe_token'] ?? '')));
                if (!preg_match('/^[a-f0-9]{40}$/', $unsubscribeToken)) {
                    $unsubscribeToken = bin2hex(random_bytes(20));
                    $pdo->prepare('UPDATE subscribers SET unsubscribe_token = ? WHERE id = ?')
                        ->execute([$unsubscribeToken, (int)$s['id']]);
                }
                $unsubscribeUrl = SeoBuilder::url('/newsletter/wypisz/' . rawurlencode($unsubscribeToken));
                $recipientBody = $emailTemplate->newsletter($subject, $body, $unsubscribeUrl);
                $rec->execute([$campaignId, $s['id'], $s['email'], 'queued', $now]);
                $log->execute([$s['email'], $subject, 'mailing_campaign_'.$campaignId, $recipientBody, 'queued', $now]);
            }
            $pdo->prepare('UPDATE mailing_campaigns SET status = ? WHERE id = ?')->execute(['queued', $campaignId]);
            $pdo->commit();
            return $campaignId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function latest(int $limit = 50): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM mailing_campaigns ORDER BY created_at DESC, id DESC LIMIT ?');
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
