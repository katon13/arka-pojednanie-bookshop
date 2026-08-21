<?php
namespace Book100\Controllers;

use Book100\Core\Env;
use Book100\Repository\ShipmentRepository;
use Book100\Repository\BookRepository;
use Book100\Services\Logs\WebhookLogService;
use Book100\Services\Payments\StripeGateway;
use Book100\Services\Payments\Przelewy24Gateway;
use Book100\Services\InPost\InPostClient;
use Book100\Services\Books\BookSaleState;

final class ApiController
{
    public function checkoutBooks(): void
    {
        $books = array_map(static function (array $book): array {
            return [
                'id' => (int)$book['id'],
                'title' => (string)$book['title'],
                'author' => (string)($book['author'] ?? ''),
                'price' => (float)$book['price_gross'],
                'currency' => (string)($book['currency'] ?? 'PLN'),
                'cover' => (string)($book['cover_image'] ?? ''),
                'type' => (string)($book['product_type'] ?? 'paper'),
                'sale_mode' => (string)($book['status'] ?? 'active'),
                'release_date' => BookSaleState::releaseDate($book),
                'release_label' => BookSaleState::releaseMessage($book),
            ];
        }, (new BookRepository())->allPurchasable());
        $this->json(['ok'=>true, 'books'=>$books], 200);
    }

    public function stripeWebhook(): void
    {
        $payload = file_get_contents('php://input') ?: '';
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $result = (new StripeGateway())->handleWebhook($payload, $headers);
        WebhookLogService::log('stripe', (string)($result['event_type'] ?? $result['status'] ?? 'webhook'), $payload, $headers, $result['status'] ?? 'unknown', $result['order_id'] ?? null, $result['message'] ?? null);
        http_response_code(($result['ok'] ?? false) ? 200 : 400);
        header('Content-Type: application/json');
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    public function p24Notify(): void
    {
        $payload = file_get_contents('php://input') ?: '';
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $result = (new Przelewy24Gateway())->handleWebhook($payload, $headers);
        WebhookLogService::log(
            'przelewy24',
            (string)($result['status'] ?? 'notify'),
            $payload,
            $headers,
            $result['status'] ?? 'unknown',
            $result['order_id'] ?? null,
            $result['message'] ?? null
        );
        $this->json($result, ($result['ok'] ?? false) ? 200 : 400);
    }

    public function p24RefundNotify(): void
    {
        $payload = file_get_contents('php://input') ?: '';
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $result = (new Przelewy24Gateway())->handleRefundWebhook($payload, $headers);
        WebhookLogService::log(
            'przelewy24_refund',
            (string)($result['status'] ?? 'notify'),
            $payload,
            $headers,
            $result['status'] ?? 'unknown',
            $result['order_id'] ?? null,
            $result['message'] ?? null
        );
        $this->json($result, ($result['ok'] ?? false) ? 200 : 400);
    }

    public function inpostPoints(): void
    {
        $query = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 80);
        header('Content-Type: application/json');
        if (mb_strlen($query) < 2) {
            echo json_encode(['status' => 'ok', 'points' => []], JSON_UNESCAPED_UNICODE);
            return;
        }
        echo json_encode(['status' => 'ok', 'points' => (new InPostClient())->points($query)], JSON_UNESCAPED_UNICODE);
    }

    public function inpostWebhookHealth(string $secret): void
    {
        if (!$this->validInPostWebhookSecret($secret)) {
            $this->json(['ok' => false, 'status' => 'not_found'], 404);
            return;
        }

        $this->json(['ok' => true, 'status' => 'ready'], 200);
    }

    public function inpostWebhook(string $secret): void
    {
        $payloadRaw = file_get_contents('php://input') ?: '';
        $headers = function_exists('getallheaders') ? getallheaders() : [];

        if (!$this->validInPostWebhookSecret($secret)) {
            WebhookLogService::log('inpost', 'invalid_secret', $payloadRaw, $headers, 'rejected', null, 'Niepoprawny adres webhooka.');
            $this->json(['ok' => false, 'status' => 'not_found'], 404);
            return;
        }

        if (!$this->validInPostWebhookIp()) {
            WebhookLogService::log('inpost', 'invalid_ip', $payloadRaw, $headers, 'rejected', null, 'Źródłowy adres IP jest spoza zakresu InPost.');
            $this->json(['ok' => false, 'status' => 'forbidden'], 403);
            return;
        }

        $data = json_decode($payloadRaw, true);
        if (!is_array($data)) {
            WebhookLogService::log('inpost', 'invalid_json', $payloadRaw, $headers, 'rejected', null, 'Niepoprawny JSON.');
            $this->json(['ok' => false, 'status' => 'invalid_json'], 400);
            return;
        }

        $organizationId = trim((string)Env::get('INPOST_ORGANIZATION_ID', ''));
        $incomingOrganizationId = trim((string)($data['organization_id'] ?? ''));
        if ($organizationId !== '' && $incomingOrganizationId !== '' && !hash_equals($organizationId, $incomingOrganizationId)) {
            WebhookLogService::log('inpost', (string)($data['event'] ?? 'unknown'), $payloadRaw, $headers, 'rejected', null, 'Zdarzenie dla innej organizacji.');
            $this->json(['ok' => false, 'status' => 'organization_mismatch'], 403);
            return;
        }

        $event = trim((string)($data['event'] ?? ''));
        $payload = is_array($data['payload'] ?? null) ? $data['payload'] : [];
        if (!in_array($event, ['shipment_confirmed', 'shipment_status_changed'], true)) {
            WebhookLogService::log('inpost', $event !== '' ? $event : 'unknown', $payloadRaw, $headers, 'ignored', null, 'Zdarzenie nie zmienia statusu przesyłki.');
            $this->json(['ok' => true, 'status' => 'ignored'], 200);
            return;
        }

        $providerShipmentId = trim((string)($payload['shipment_id'] ?? ''));
        $trackingNumber = trim((string)($payload['tracking_number'] ?? ''));
        $status = $event === 'shipment_confirmed'
            ? 'confirmed'
            : trim((string)($payload['status'] ?? ''));
        if ($providerShipmentId === '' || $status === '') {
            WebhookLogService::log('inpost', $event, $payloadRaw, $headers, 'rejected', null, 'Brak ID przesyłki albo statusu.');
            $this->json(['ok' => false, 'status' => 'invalid_payload'], 400);
            return;
        }

        $result = (new ShipmentRepository())->applyInPostWebhook(
            $providerShipmentId,
            $trackingNumber !== '' ? $trackingNumber : null,
            $status,
            $data
        );
        WebhookLogService::log(
            'inpost',
            $event,
            $payloadRaw,
            $headers,
            (string)($result['status'] ?? 'received'),
            isset($result['order_id']) ? (int)$result['order_id'] : null,
            (string)($result['message'] ?? '')
        );
        $this->json($result, 200);
    }

    private function validInPostWebhookSecret(string $provided): bool
    {
        $configured = trim((string)Env::get('INPOST_WEBHOOK_SECRET', ''));
        return $configured !== '' && hash_equals($configured, trim($provided));
    }

    private function validInPostWebhookIp(): bool
    {
        if (strtolower((string)Env::get('INPOST_WEBHOOK_ENFORCE_IP', 'false')) !== 'true') {
            return true;
        }
        $remoteIp = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        return str_starts_with($remoteIp, '91.216.25.');
    }

    private function json(array $data, int $status): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
