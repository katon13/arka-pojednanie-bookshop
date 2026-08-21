<?php
namespace Book100\Repository;

use Book100\Core\Database;
use DateTimeImmutable;
use PDO;
use PDOException;

final class SalesReportRepository
{
    public function ordersForPeriod(string $start, string $end): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM orders
             WHERE (paid_at IS NOT NULL AND paid_at >= ? AND paid_at < ?)
                OR (refunded_at IS NOT NULL AND refunded_at >= ? AND refunded_at < ?)
             ORDER BY COALESCE(refunded_at, paid_at) ASC, id ASC'
        );
        $stmt->execute([$start, $end, $start, $end]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($orders === []) return [];

        $ids = array_map(static fn(array $order): int => (int)$order['id'], $orders);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $items = Database::pdo()->prepare(
            'SELECT * FROM order_items WHERE order_id IN (' . $placeholders . ') ORDER BY order_id ASC, id ASC'
        );
        $items->execute($ids);
        $byOrder = [];
        foreach ($items->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $byOrder[(int)$item['order_id']][] = $item;
        }
        foreach ($orders as &$order) $order['items'] = $byOrder[(int)$order['id']] ?? [];
        unset($order);
        return $orders;
    }

    public function recent(int $limit = 24): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM sales_reports ORDER BY period_year DESC, period_month DESC LIMIT ?');
        $stmt->bindValue(1, max(1, min(120, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM sales_reports WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findPeriod(int $year, int $month): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM sales_reports WHERE period_year = ? AND period_month = ? LIMIT 1');
        $stmt->execute([$year, $month]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array{report:array,claimed:bool} */
    public function claim(int $year, int $month, string $start, string $end, string $recipient): array
    {
        $existing = $this->findPeriod($year, $month);
        if ($existing) {
            if (($existing['status'] ?? '') === 'failed') {
                Database::pdo()->prepare(
                    "UPDATE sales_reports SET status='generating', last_error=NULL, recipient_email=?, updated_at=? WHERE id=?"
                )->execute([$recipient ?: null, date('Y-m-d H:i:s'), (int)$existing['id']]);
                return ['report'=>$this->find((int)$existing['id']) ?? $existing, 'claimed'=>true];
            }
            return ['report'=>$existing, 'claimed'=>false];
        }

        $now = date('Y-m-d H:i:s');
        try {
            $stmt = Database::pdo()->prepare(
                "INSERT INTO sales_reports
                 (period_year, period_month, period_start, period_end, status, recipient_email, send_status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, 'generating', ?, 'not_sent', ?, ?)"
            );
            $periodEnd = (new DateTimeImmutable($end))->modify('-1 day')->format('Y-m-d');
            $stmt->execute([$year, $month, substr($start, 0, 10), $periodEnd, $recipient ?: null, $now, $now]);
            $id = (int)Database::pdo()->lastInsertId();
            return ['report'=>$this->find($id) ?? ['id'=>$id], 'claimed'=>true];
        } catch (PDOException $exception) {
            $existing = $this->findPeriod($year, $month);
            if (!$existing) throw $exception;
            return ['report'=>$existing, 'claimed'=>false];
        }
    }

    public function markGenerated(int $id, string $path, array $summary): void
    {
        $now = date('Y-m-d H:i:s');
        Database::pdo()->prepare(
            "UPDATE sales_reports SET file_path=?, status='generated', orders_count=?, units_count=?,
             sales_net=?, sales_vat=?, sales_gross=?, shipping_net=?, shipping_vat=?, shipping_gross=?, discount_gross=?,
             refund_net=?, refund_vat=?, refund_gross=?, final_net=?, final_vat=?, final_gross=?,
             generated_at=?, last_error=NULL, updated_at=? WHERE id=?"
        )->execute([
            $path,
            (int)$summary['paid_orders'], (int)$summary['units'],
            $summary['sales_net'], $summary['sales_vat'], $summary['sales_gross'],
            $summary['shipping_net'], $summary['shipping_vat'], $summary['shipping_gross'], $summary['discount_gross'],
            $summary['refund_net'], $summary['refund_vat'], $summary['refund_gross'],
            $summary['final_net'], $summary['final_vat'], $summary['final_gross'],
            $now, $now, $id,
        ]);
    }

    public function markFailed(int $id, string $message): void
    {
        Database::pdo()->prepare(
            "UPDATE sales_reports SET status='failed', last_error=?, updated_at=? WHERE id=?"
        )->execute([mb_substr($message, 0, 4000), date('Y-m-d H:i:s'), $id]);
    }

    public function attachEmail(int $id, int $emailLogId, string $recipient): void
    {
        Database::pdo()->prepare(
            "UPDATE sales_reports SET email_log_id=?, recipient_email=?, send_status='queued', last_error=NULL, updated_at=? WHERE id=?"
        )->execute([$emailLogId, $recipient, date('Y-m-d H:i:s'), $id]);
    }

    public function clearEmailForResend(int $id): void
    {
        Database::pdo()->prepare(
            "UPDATE sales_reports SET email_log_id=NULL, send_status='not_sent', sent_at=NULL, last_error=NULL, updated_at=? WHERE id=?"
        )->execute([date('Y-m-d H:i:s'), $id]);
    }

    public function syncEmailStatus(int $id): ?array
    {
        $report = $this->find($id);
        if (!$report || empty($report['email_log_id'])) return $report;
        $stmt = Database::pdo()->prepare('SELECT status, sent_at, last_error FROM email_logs WHERE id=? LIMIT 1');
        $stmt->execute([(int)$report['email_log_id']]);
        $email = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$email) return $report;
        $sendStatus = match ((string)$email['status']) {
            'sent' => 'sent',
            'failed_retry' => 'failed',
            default => 'queued',
        };
        Database::pdo()->prepare(
            'UPDATE sales_reports SET send_status=?, sent_at=?, last_error=?, updated_at=? WHERE id=?'
        )->execute([
            $sendStatus,
            $sendStatus === 'sent' ? ($email['sent_at'] ?? date('Y-m-d H:i:s')) : null,
            $sendStatus === 'failed' ? mb_substr((string)($email['last_error'] ?? ''), 0, 4000) : null,
            date('Y-m-d H:i:s'),
            $id,
        ]);
        return $this->find($id);
    }

    public function syncAllEmailStatuses(): int
    {
        $ids = Database::pdo()->query('SELECT id FROM sales_reports WHERE email_log_id IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) $this->syncEmailStatus((int)$id);
        return count($ids);
    }
}
