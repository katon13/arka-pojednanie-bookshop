<?php
namespace Book100\Services\InPost;

use Book100\Core\Env;
use Book100\Core\StoreUrl;

final class InPostClient
{
    private const PARCEL_TEMPLATES = ['small', 'medium', 'large'];
    private const SENDING_METHODS = ['parcel_locker', 'branch', 'dispatch_order', 'pop', 'any_point'];
    private const LABEL_FORMATS = ['Pdf', 'Zpl', 'Epl'];
    private const LABEL_TYPES = ['normal', 'A6'];

    public function isConfigured(): bool
    {
        return trim((string)Env::get('INPOST_API_TOKEN', '')) !== ''
            && trim((string)Env::get('INPOST_ORGANIZATION_ID', '')) !== '';
    }

    public function configuration(): array
    {
        $mode = strtolower((string)Env::get('INPOST_MODE', 'sandbox')) === 'production'
            ? 'production'
            : 'sandbox';
        $organizationId = trim((string)Env::get('INPOST_ORGANIZATION_ID', ''));
        $webhookSecret = trim((string)Env::get('INPOST_WEBHOOK_SECRET', ''));
        $appUrl = StoreUrl::base();
        $webhookUrl = $appUrl !== '' && $webhookSecret !== ''
            ? $appUrl . '/?shipx_webhook=' . rawurlencode($webhookSecret)
            : '';
        $host = strtolower((string)parse_url($webhookUrl, PHP_URL_HOST));
        $publicWebhook = $webhookUrl !== ''
            && strtolower((string)parse_url($webhookUrl, PHP_URL_SCHEME)) === 'https'
            && !in_array($host, ['localhost', '127.0.0.1', 'arka.test'], true)
            && !str_ends_with($host, '.test');

        return [
            'configured' => $this->isConfigured(),
            'mode' => $mode,
            'organization_id' => $organizationId,
            'token_configured' => trim((string)Env::get('INPOST_API_TOKEN', '')) !== '',
            'geowidget_configured' => trim((string)Env::get('INPOST_GEO_WIDGET_TOKEN', '')) !== '',
            'webhook_configured' => $webhookSecret !== '',
            'webhook_url' => $webhookUrl,
            'public_webhook' => $publicWebhook,
            'courier_enabled' => $this->courierEnabled(),
            'default_parcel_template' => $this->parcelTemplate((string)Env::get('INPOST_DEFAULT_PARCEL_TEMPLATE', 'small')),
            'default_sending_method' => $this->sendingMethod((string)Env::get('INPOST_DEFAULT_SENDING_METHOD', 'any_point')),
            'label_format' => $this->labelFormat((string)Env::get('INPOST_LABEL_FORMAT', 'Pdf')),
            'label_type' => $this->labelType((string)Env::get('INPOST_LABEL_TYPE', 'A6')),
        ];
    }

    public function geoWidgetConfiguration(): array
    {
        $token = trim((string)Env::get('INPOST_GEO_WIDGET_TOKEN', ''));
        $production = strtolower((string)Env::get('INPOST_MODE', 'sandbox')) === 'production';
        $baseUrl = $production
            ? 'https://geowidget.inpost.pl'
            : 'https://sandbox-easy-geowidget-sdk.easypack24.net';

        return [
            'enabled' => $token !== '',
            'token' => $token,
            'script_url' => $baseUrl . '/inpost-geowidget.js',
            'style_url' => $baseUrl . '/inpost-geowidget.css',
            'config' => 'parcelCollect',
        ];
    }

    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return [
                'ok' => false,
                'status' => 'not_configured',
                'message' => 'Uzupełnij token ShipX i ID organizacji.',
            ];
        }

        $organizationId = trim((string)Env::get('INPOST_ORGANIZATION_ID', ''));
        $response = $this->requestRaw(
            'GET',
            $this->baseUrl() . '/v1/organizations/' . rawurlencode($organizationId)
        );
        if (!$response['ok']) {
            return [
                'ok' => false,
                'status' => 'connection_error',
                'message' => $this->errorMessage($response, 'InPost odrzucił połączenie. Sprawdź token, ID organizacji i tryb API.'),
            ];
        }

        $returnedId = (string)($response['json']['id'] ?? '');
        if ($returnedId !== '' && $returnedId !== $organizationId) {
            return [
                'ok' => false,
                'status' => 'organization_mismatch',
                'message' => 'Token działa, ale zwrócił inną organizację.',
            ];
        }

        return [
            'ok' => true,
            'status' => 'connected',
            'message' => 'Połączenie z InPost ShipX działa.',
        ];
    }

    public function courierEnabled(): bool
    {
        return strtolower(trim((string)Env::get('INPOST_COURIER_ENABLED', 'false'))) === 'true';
    }

    public function createShipment(array $order, array $options = []): array
    {
        if (!$this->isConfigured()) {
            return [
                'ok' => false,
                'status' => 'not_configured',
                'message' => 'Brak tokenu ShipX albo ID organizacji. Przesyłka nie została utworzona.',
            ];
        }
        if (($order['delivery_method'] ?? '') === 'ebook') {
            return [
                'ok' => false,
                'status' => 'ebook_no_shipment',
                'message' => 'E-book nie wymaga przesyłki InPost.',
            ];
        }
        if (($order['delivery_method'] ?? '') === 'inpost_courier' && !$this->courierEnabled()) {
            return [
                'ok' => false,
                'status' => 'courier_disabled',
                'message' => 'Usługa InPost Kurier nie jest aktywna dla tego konta. Wybierz Paczkomat albo włącz Kuriera po rozszerzeniu umowy.',
            ];
        }

        $validation = $this->validateOrder($order);
        if ($validation !== null) {
            return ['ok' => false, 'status' => 'invalid_order', 'message' => $validation];
        }

        $organizationId = rawurlencode(trim((string)Env::get('INPOST_ORGANIZATION_ID', '')));
        $payload = $this->payload($order, $options);
        $response = $this->request(
            'POST',
            $this->baseUrl() . "/v1/organizations/{$organizationId}/shipments",
            $payload
        );
        if (!$response['ok']) {
            return [
                'ok' => false,
                'status' => 'api_error',
                'message' => $this->errorMessage($response, 'InPost ShipX nie utworzył przesyłki.'),
                'raw' => $response['json'] ?? null,
            ];
        }

        $json = $response['json'] ?? [];
        $shipmentId = (string)($json['id'] ?? '');
        if ($shipmentId === '') {
            return [
                'ok' => false,
                'status' => 'invalid_response',
                'message' => 'InPost nie zwrócił ID nowej przesyłki.',
                'raw' => $json,
            ];
        }

        return [
            'ok' => true,
            'status' => (string)($json['status'] ?? 'created'),
            'provider_shipment_id' => $shipmentId,
            'tracking_number' => $json['tracking_number'] ?? ($json['trackingNumber'] ?? null),
            'raw' => $json,
            'message' => 'Przesyłka InPost została utworzona.',
        ];
    }

    public function shipment(string $providerShipmentId): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'status' => 'not_configured', 'message' => 'Brak konfiguracji InPost.'];
        }
        if (trim($providerShipmentId) === '') {
            return ['ok' => false, 'status' => 'missing_shipment_id', 'message' => 'Brak ID przesyłki InPost.'];
        }

        $response = $this->requestRaw(
            'GET',
            $this->baseUrl() . '/v1/shipments/' . rawurlencode(trim($providerShipmentId))
        );
        if (!$response['ok']) {
            return [
                'ok' => false,
                'status' => 'shipment_error',
                'message' => $this->errorMessage($response, 'Nie udało się pobrać stanu przesyłki.'),
                'raw' => $response['json'] ?? null,
            ];
        }

        $json = $response['json'] ?? [];
        return [
            'ok' => true,
            'status' => (string)($json['status'] ?? 'created'),
            'provider_shipment_id' => (string)($json['id'] ?? $providerShipmentId),
            'tracking_number' => $json['tracking_number'] ?? null,
            'raw' => $json,
        ];
    }

    public function fetchLabel(string $providerShipmentId, ?string $format = null, ?string $type = null): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'status' => 'not_configured', 'message' => 'Brak konfiguracji InPost.'];
        }
        $providerShipmentId = trim($providerShipmentId);
        if ($providerShipmentId === '') {
            return ['ok' => false, 'status' => 'missing_shipment_id', 'message' => 'Brak ID przesyłki InPost.'];
        }

        $format = $this->labelFormat($format ?? (string)Env::get('INPOST_LABEL_FORMAT', 'Pdf'));
        $type = $this->labelType($type ?? (string)Env::get('INPOST_LABEL_TYPE', 'A6'));
        $url = $this->baseUrl()
            . '/v1/shipments/' . rawurlencode($providerShipmentId)
            . '/label?format=' . rawurlencode($format)
            . '&type=' . rawurlencode($type);

        $lastResponse = null;
        $latestShipment = null;
        for ($attempt = 0; $attempt < 9; $attempt++) {
            $lastResponse = $this->requestRaw('GET', $url);
            if ($lastResponse['ok'] && $lastResponse['body'] !== '') {
                return [
                    'ok' => true,
                    'status' => (string)($latestShipment['status'] ?? 'confirmed'),
                    'body' => $lastResponse['body'],
                    'format' => $format,
                    'type' => $type,
                    'provider_shipment_id' => $providerShipmentId,
                    'tracking_number' => $latestShipment['tracking_number'] ?? null,
                    'raw' => $latestShipment['raw'] ?? null,
                ];
            }

            $latestShipment = $this->shipment($providerShipmentId);
            if (($latestShipment['ok'] ?? false) && in_array(
                (string)($latestShipment['status'] ?? ''),
                ['canceled', 'rejected', 'returned_to_sender'],
                true
            )) {
                break;
            }
            if ($attempt < 8) {
                usleep(400000);
            }
        }

        $status = (string)($latestShipment['status'] ?? 'label_error');
        return [
            'ok' => false,
            'status' => $status,
            'message' => $this->errorMessage(
                is_array($lastResponse) ? $lastResponse : [],
                'Etykieta jest jeszcze przygotowywana przez InPost. Spróbuj ponownie za chwilę.'
            ),
            'provider_shipment_id' => $providerShipmentId,
            'tracking_number' => $latestShipment['tracking_number'] ?? null,
            'raw' => $latestShipment['raw'] ?? null,
        ];
    }

    public function points(string $query = ''): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }
        $url = $this->pointsUrl() . '?type=parcel_locker&per_page=10&query=' . rawurlencode($query);
        $response = $this->requestRaw('GET', $url, false);
        $items = $response['json']['items'] ?? [];
        $out = [];
        foreach ($items as $item) {
            $out[] = [
                'name' => $item['name'] ?? '',
                'label' => trim(($item['name'] ?? '') . ' — ' . ($item['address']['line1'] ?? '') . ' ' . ($item['address']['line2'] ?? '')),
            ];
        }
        return $out;
    }

    private function payload(array $order, array $options): array
    {
        $service = ($order['delivery_method'] ?? 'inpost_locker') === 'inpost_courier'
            ? 'inpost_courier_standard'
            : 'inpost_locker_standard';
        $template = $this->parcelTemplate((string)($options['parcel_template'] ?? Env::get('INPOST_DEFAULT_PARCEL_TEMPLATE', 'small')));
        $sendingMethod = $this->sendingMethod((string)($options['sending_method'] ?? Env::get('INPOST_DEFAULT_SENDING_METHOD', 'any_point')));
        $weight = (float)($options['weight_kg'] ?? Env::get('INPOST_DEFAULT_WEIGHT_KG', '1'));
        $weight = $weight > 0 && $weight <= 25 ? $weight : 1.0;
        $reference = $this->reference($order, (string)($options['reference'] ?? ''));

        $receiver = [
            'first_name' => $this->firstName((string)($order['customer_name'] ?? '')),
            'last_name' => $this->lastName((string)($order['customer_name'] ?? '')),
            'email' => trim((string)($order['customer_email'] ?? '')),
            'phone' => preg_replace('/\s+/', '', (string)($order['customer_phone'] ?? '')),
        ];
        $payload = [
            'receiver' => $receiver,
            'parcels' => [[
                'template' => $template,
                'weight' => ['amount' => $weight, 'unit' => 'kg'],
            ]],
            'service' => $service,
            'reference' => $reference,
            'comments' => mb_substr((new \Book100\Repository\SettingsRepository())->get('shop_name', 'ARKA') . ' — ' . $reference, 0, 100),
            'only_choice_of_offer' => false,
            'custom_attributes' => ['sending_method' => $sendingMethod],
        ];

        $insurance = str_replace(',', '.', trim((string)($options['insurance'] ?? Env::get('INPOST_DEFAULT_INSURANCE', '0'))));
        if (is_numeric($insurance) && (float)$insurance > 0) {
            $payload['insurance'] = ['amount' => round((float)$insurance, 2), 'currency' => 'PLN'];
        }

        if ($service === 'inpost_locker_standard') {
            $payload['custom_attributes']['target_point'] = strtoupper(trim((string)($order['inpost_point'] ?? '')));
        } else {
            $payload['receiver']['address'] = $this->addressFromOrder($order);
        }

        return $payload;
    }

    private function validateOrder(array $order): ?string
    {
        if (trim((string)($order['customer_name'] ?? '')) === '') {
            return 'W zamówieniu brakuje imienia i nazwiska odbiorcy.';
        }
        if (!filter_var(trim((string)($order['customer_email'] ?? '')), FILTER_VALIDATE_EMAIL)) {
            return 'W zamówieniu brakuje poprawnego adresu e-mail odbiorcy.';
        }
        if (strlen(preg_replace('/\D+/', '', (string)($order['customer_phone'] ?? ''))) < 9) {
            return 'W zamówieniu brakuje poprawnego numeru telefonu odbiorcy.';
        }

        if (($order['delivery_method'] ?? 'inpost_locker') === 'inpost_locker') {
            if (trim((string)($order['inpost_point'] ?? '')) === '') {
                return 'W zamówieniu nie wybrano Paczkomatu.';
            }
            return null;
        }

        $address = $this->addressFromOrder($order);
        foreach (['street', 'building_number', 'city', 'post_code'] as $field) {
            if (trim((string)($address[$field] ?? '')) === '') {
                return 'Adres dostawy kurierskiej jest niepełny.';
            }
        }
        return null;
    }

    private function addressFromOrder(array $order): array
    {
        $json = json_decode((string)($order['shipping_address_json'] ?? ''), true) ?: [];
        return [
            'street' => $json['street'] ?? Env::get('INPOST_FALLBACK_STREET', ''),
            'building_number' => $json['building_number'] ?? Env::get('INPOST_FALLBACK_BUILDING', ''),
            'city' => $json['city'] ?? Env::get('INPOST_FALLBACK_CITY', ''),
            'post_code' => $json['post_code'] ?? Env::get('INPOST_FALLBACK_POST_CODE', ''),
            'country_code' => 'PL',
        ];
    }

    private function reference(array $order, string $custom): string
    {
        $custom = trim($custom);
        if ($custom !== '') {
            return mb_substr($custom, 0, 100);
        }
        $displayId = (int)($order['old_wp_id'] ?? 0);
        if ($displayId < 1) {
            $displayId = (int)($order['id'] ?? 0);
        }
        return mb_substr('Zamówienie ' . ($displayId > 0 ? $displayId : (string)($order['order_number'] ?? 'ARKA')), 0, 100);
    }

    private function firstName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        return $parts[0] ?? $name;
    }

    private function lastName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        return count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '-';
    }

    private function parcelTemplate(string $value): string
    {
        $value = strtolower(trim($value));
        return in_array($value, self::PARCEL_TEMPLATES, true) ? $value : 'small';
    }

    private function sendingMethod(string $value): string
    {
        $value = strtolower(trim($value));
        return in_array($value, self::SENDING_METHODS, true) ? $value : 'any_point';
    }

    private function labelFormat(string $value): string
    {
        foreach (self::LABEL_FORMATS as $format) {
            if (strcasecmp($format, trim($value)) === 0) return $format;
        }
        return 'Pdf';
    }

    private function labelType(string $value): string
    {
        foreach (self::LABEL_TYPES as $type) {
            if (strcasecmp($type, trim($value)) === 0) return $type;
        }
        return 'A6';
    }

    private function baseUrl(): string
    {
        return strtolower((string)Env::get('INPOST_MODE', 'sandbox')) === 'production'
            ? 'https://api-shipx-pl.easypack24.net'
            : 'https://sandbox-api-shipx-pl.easypack24.net';
    }

    private function pointsUrl(): string
    {
        return (string)Env::get('INPOST_POINTS_URL', 'https://api-pl-points.easypack24.net/v1/points');
    }

    private function request(string $method, string $url, array $payload): array
    {
        return $this->requestRaw(
            $method,
            $url,
            true,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    private function requestRaw(string $method, string $url, bool $auth = true, ?string $body = null): array
    {
        if (!function_exists('curl_init')) {
            return [
                'ok' => false,
                'status' => 0,
                'json' => null,
                'body' => '',
                'error' => 'Brak rozszerzenia cURL w PHP.',
            ];
        }

        $headers = ['Accept: application/json'];
        if ($body !== null) $headers[] = 'Content-Type: application/json';
        if ($auth) $headers[] = 'Authorization: Bearer ' . Env::get('INPOST_API_TOKEN', '');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ]);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        $bodyString = is_string($response) ? $response : '';
        $decoded = json_decode($bodyString, true);
        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'json' => is_array($decoded) ? $decoded : null,
            'body' => $bodyString,
            'content_type' => $contentType,
            'error' => $error ?: null,
        ];
    }

    private function errorMessage(array $response, string $fallback): string
    {
        $json = is_array($response['json'] ?? null) ? $response['json'] : [];
        $message = trim((string)($json['description'] ?? $json['message'] ?? $response['error'] ?? ''));
        if ($message === '' && isset($json['details'])) {
            $message = trim((string)json_encode($json['details'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        if ($message === '') return $fallback;
        return $fallback . ' ' . mb_substr(strip_tags($message), 0, 320);
    }
}
