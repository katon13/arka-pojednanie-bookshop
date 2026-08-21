<?php
namespace Book100\Services\Logs;

use Book100\Core\Database;

final class WebhookLogService
{
    public static function log(string $provider, string $eventType, string $payload, array $headers, string $status, ?int $orderId = null, ?string $message = null): void
    {
        try {
            foreach ($headers as $name => $value) {
                if (in_array(strtolower((string)$name), ['authorization','cookie','proxy-authorization'], true)) {
                    $headers[$name] = '[ukryto]';
                }
            }
            $stmt = Database::pdo()->prepare('INSERT INTO webhook_logs (provider, event_type, order_id, payload_json, headers_json, status, message, created_at) VALUES (:provider,:event_type,:order_id,:payload_json,:headers_json,:status,:message,:created_at)');
            $stmt->execute([
                ':provider' => $provider,
                ':event_type' => $eventType,
                ':order_id' => $orderId,
                ':payload_json' => $payload,
                ':headers_json' => json_encode($headers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':status' => $status,
                ':message' => $message,
                ':created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            error_log('[webhook_log_failed] ' . $e->getMessage());
        }
    }
}
