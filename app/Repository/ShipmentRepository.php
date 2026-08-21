<?php
namespace Book100\Repository;

use Book100\Core\Database;
use PDO;

final class ShipmentRepository
{
    public function findByOrderId(int $orderId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM shipments WHERE order_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$orderId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM shipments WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByProviderShipmentId(string $providerShipmentId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM shipments WHERE provider = ? AND provider_shipment_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute(['inpost', trim($providerShipmentId)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByTrackingNumber(string $trackingNumber): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM shipments WHERE provider = ? AND tracking_number = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute(['inpost', trim($trackingNumber)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createOrUpdateFromInPost(array $order, array $result): array
    {
        $pdo = Database::pdo();
        $now = date('Y-m-d H:i:s');
        $existing = $this->findByOrderId((int)$order['id']);
        $providerShipmentId = (string)($result['provider_shipment_id'] ?? $result['id'] ?? '');
        $tracking = $result['tracking_number'] ?? $result['tracking'] ?? null;
        $status = (string)($result['status'] ?? 'created');
        $labelPath = $result['label_path'] ?? null;
        $raw = json_encode($result['raw'] ?? $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $method = (string)($order['delivery_method'] ?? 'inpost_locker');
        $point = $order['inpost_point'] ?? null;

        if ($existing) {
            $stmt = $pdo->prepare('UPDATE shipments SET provider_shipment_id = :provider_shipment_id, tracking_number = :tracking_number, status = :status, label_path = COALESCE(:label_path, label_path), inpost_point = :inpost_point, method = :method, receiver_name = :receiver_name, receiver_email = :receiver_email, receiver_phone = :receiver_phone, raw_response_json = :raw, updated_at = :updated_at WHERE id = :id');
            $stmt->execute([
                ':provider_shipment_id' => $providerShipmentId ?: ($existing['provider_shipment_id'] ?? null),
                ':tracking_number' => $tracking ?: ($existing['tracking_number'] ?? null),
                ':status' => $status,
                ':label_path' => $labelPath,
                ':inpost_point' => $point,
                ':method' => $method,
                ':receiver_name' => $order['customer_name'] ?? '',
                ':receiver_email' => $order['customer_email'] ?? '',
                ':receiver_phone' => $order['customer_phone'] ?? '',
                ':raw' => $raw,
                ':updated_at' => $now,
                ':id' => (int)$existing['id'],
            ]);
            return $this->find((int)$existing['id']) ?: $existing;
        }

        $stmt = $pdo->prepare('INSERT INTO shipments (order_id, provider, provider_shipment_id, tracking_number, status, label_path, inpost_point, method, receiver_name, receiver_email, receiver_phone, raw_response_json, created_at, updated_at) VALUES (:order_id,:provider,:provider_shipment_id,:tracking_number,:status,:label_path,:inpost_point,:method,:receiver_name,:receiver_email,:receiver_phone,:raw,:created_at,:updated_at)');
        $stmt->execute([
            ':order_id' => (int)$order['id'],
            ':provider' => 'inpost',
            ':provider_shipment_id' => $providerShipmentId ?: null,
            ':tracking_number' => $tracking,
            ':status' => $status,
            ':label_path' => $labelPath,
            ':inpost_point' => $point,
            ':method' => $method,
            ':receiver_name' => $order['customer_name'] ?? '',
            ':receiver_email' => $order['customer_email'] ?? '',
            ':receiver_phone' => $order['customer_phone'] ?? '',
            ':raw' => $raw,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        return $this->find((int)$pdo->lastInsertId()) ?: [];
    }

    public function markSent(int $shipmentId): void
    {
        $pdo = Database::pdo();
        $shipment = $this->find($shipmentId);
        if (!$shipment) return;
        $now = date('Y-m-d H:i:s');
        $pdo->prepare('UPDATE shipments SET status = ?, sent_at = ?, updated_at = ? WHERE id = ?')->execute(['sent', $now, $now, $shipmentId]);
        $pdo->prepare('UPDATE orders SET status = ?, shipment_status = ?, shipped_at = ?, updated_at = ? WHERE id = ?')->execute(['shipped', 'sent', $now, $now, (int)$shipment['order_id']]);
        (new OrderRepository())->notifyShipmentSent(
            (int)$shipment['order_id'],
            (string)($shipment['tracking_number'] ?? '')
        );
    }

    public function updateFromProvider(int $shipmentId, array $result): ?array
    {
        $shipment = $this->find($shipmentId);
        if (!$shipment) return null;

        return $this->applyStatusUpdate(
            $shipment,
            (string)($result['status'] ?? $shipment['status'] ?? 'created'),
            $result['tracking_number'] ?? ($shipment['tracking_number'] ?? null),
            $result['raw'] ?? $result
        );
    }

    public function applyInPostWebhook(
        string $providerShipmentId,
        ?string $trackingNumber,
        string $status,
        array $payload
    ): array {
        $providerShipmentId = trim($providerShipmentId);
        $trackingNumber = trim((string)$trackingNumber);
        $status = trim($status);

        $shipment = $providerShipmentId !== ''
            ? $this->findByProviderShipmentId($providerShipmentId)
            : null;
        if (!$shipment && $trackingNumber !== '') {
            $shipment = $this->findByTrackingNumber($trackingNumber);
        }
        if (!$shipment) {
            return [
                'ok' => true,
                'matched' => false,
                'status' => 'ignored',
                'message' => 'Zdarzenie dotyczy przesyłki nieznanej w tym sklepie.',
                'order_id' => null,
            ];
        }

        $updated = $this->applyStatusUpdate(
            $shipment,
            $status !== '' ? $status : (string)($shipment['status'] ?? 'created'),
            $trackingNumber !== '' ? $trackingNumber : ($shipment['tracking_number'] ?? null),
            ['webhook' => $payload]
        );

        return [
            'ok' => true,
            'matched' => true,
            'status' => (string)($updated['status'] ?? $status),
            'message' => 'Status przesyłki został zapisany.',
            'order_id' => (int)$shipment['order_id'],
            'shipment_id' => (int)$shipment['id'],
        ];
    }

    public function listForOrders(array $orders): array
    {
        $ids = array_values(array_filter(array_map(fn($o) => (int)($o['id'] ?? 0), $orders)));
        if (!$ids) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::pdo()->prepare("SELECT * FROM shipments WHERE order_id IN ($placeholders) ORDER BY id DESC");
        $stmt->execute($ids);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(int)$row['order_id']] = $row;
        }
        return $out;
    }

    private function applyStatusUpdate(
        array $shipment,
        string $status,
        ?string $trackingNumber,
        array $raw
    ): array {
        $pdo = Database::pdo();
        $now = date('Y-m-d H:i:s');
        $status = trim($status) !== '' ? trim($status) : (string)($shipment['status'] ?? 'created');
        $trackingNumber = trim((string)$trackingNumber);
        $rawJson = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $sentStatuses = [
            'dispatched_by_sender',
            'collected_from_sender',
            'taken_by_courier',
            'adopted_at_source_branch',
            'sent_from_source_branch',
            'sent_from_transit_branch',
            'adopted_at_sorting_center',
            'sent_from_sorting_center',
            'ready_to_pickup',
            'out_for_delivery',
            'pickup_reminder_sent',
            'avizo',
            'claimed',
            'pickup_time_expired',
            'stack_parcel_pickup_time_expired',
            'redirect_to_box',
            'canceled_redirect_to_box',
            'returned_to_sender',
        ];
        $isDelivered = $status === 'delivered';
        $isSent = $isDelivered || in_array($status, $sentStatuses, true);
        $sentAt = $isSent ? ((string)($shipment['sent_at'] ?? '') ?: $now) : ($shipment['sent_at'] ?? null);
        $deliveredAt = $isDelivered ? ((string)($shipment['delivered_at'] ?? '') ?: $now) : ($shipment['delivered_at'] ?? null);

        $stmt = $pdo->prepare(
            'UPDATE shipments
             SET tracking_number = COALESCE(NULLIF(:tracking_number, \'\'), tracking_number),
                 status = :status,
                 raw_response_json = :raw,
                 sent_at = :sent_at,
                 delivered_at = :delivered_at,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $stmt->execute([
            ':tracking_number' => $trackingNumber,
            ':status' => $status,
            ':raw' => $rawJson,
            ':sent_at' => $sentAt,
            ':delivered_at' => $deliveredAt,
            ':updated_at' => $now,
            ':id' => (int)$shipment['id'],
        ]);

        $orderId = (int)$shipment['order_id'];
        if ($isDelivered) {
            $pdo->prepare(
                "UPDATE orders
                 SET status = CASE WHEN status IN ('cancelled','refunded','archived') THEN status ELSE 'completed' END,
                     shipment_status = ?, completed_at = COALESCE(completed_at, ?),
                     shipped_at = COALESCE(shipped_at, ?), updated_at = ?
                 WHERE id = ?"
            )->execute([$status, $now, $now, $now, $orderId]);
        } elseif ($isSent) {
            $pdo->prepare(
                "UPDATE orders
                 SET status = CASE WHEN status IN ('cancelled','refunded','archived') THEN status ELSE 'shipped' END,
                     shipment_status = ?, shipped_at = COALESCE(shipped_at, ?), updated_at = ?
                 WHERE id = ?"
            )->execute([$status, $now, $now, $orderId]);
        } else {
            $pdo->prepare(
                "UPDATE orders
                 SET status = CASE
                     WHEN status IN ('paid','paid_waiting_for_shipment') THEN 'shipment_created'
                     ELSE status
                 END,
                 shipment_status = ?, updated_at = ?
                 WHERE id = ?"
            )->execute([$status, $now, $orderId]);
        }

        if ($isSent && empty($shipment['sent_at'])) {
            (new OrderRepository())->notifyShipmentSent($orderId, $trackingNumber);
        }

        return $this->find((int)$shipment['id']) ?: $shipment;
    }
}
