<?php
namespace Book100\Services\Payments;

use Book100\Core\Env;
use Book100\Core\StoreUrl;
use Book100\Repository\OrderRepository;
use Book100\Repository\SettingsRepository;
use Book100\Services\Http\SimpleHttpClient;

final class StripeGateway implements PaymentGatewayInterface
{
    public function createSession(array $order): array
    {
        $secret = trim((string)Env::get('STRIPE_SECRET_KEY', ''));
        $publishableKey = trim((string)Env::get('STRIPE_PUBLISHABLE_KEY', ''));
        if ($secret === '' || $publishableKey === '') {
            return ['status' => 'not_configured', 'message' => 'Brak kompletnej konfiguracji Stripe.'];
        }
        $amount = (int)round(((float)$order['total_gross']) * 100);
        $shopName = (new SettingsRepository())->get('shop_name', 'ARKA');
        $title = 'Zamówienie ' . $order['order_number'] . ' — ' . $shopName;
        $metadata = [
            'order_number' => (string)$order['order_number'],
            'order_id' => (string)$order['id'],
            'customer_name' => (string)($order['customer_name'] ?? ''),
            'customer_email' => (string)($order['customer_email'] ?? ''),
            'customer_phone' => (string)($order['customer_phone'] ?? ''),
            'delivery_method' => (string)($order['delivery_method'] ?? ''),
            'inpost_point' => (string)($order['inpost_point'] ?? ''),
        ];
        $itemSummary = [];
        foreach (($order['items'] ?? []) as $item) {
            $itemSummary[] = max(1, (int)($item['quantity'] ?? 1)) . ' × ' . trim((string)($item['title'] ?? 'Książka'));
        }
        $metadata['items'] = mb_substr(implode(' | ', $itemSummary), 0, 500);

        $customerPayload = [
            'email' => (string)($order['customer_email'] ?? ''),
            'name' => (string)($order['customer_name'] ?? ''),
            'description' => $title,
        ];
        if (trim((string)($order['customer_phone'] ?? '')) !== '') {
            $customerPayload['phone'] = (string)$order['customer_phone'];
        }
        foreach ($metadata as $key => $value) {
            $customerPayload["metadata[{$key}]"] = $value;
        }
        $customerResponse = (new SimpleHttpClient())->postForm(
            'https://api.stripe.com/v1/customers',
            $customerPayload,
            ['Idempotency-Key: arka-customer-' . (int)$order['id']],
            $secret
        );
        $customerId = (string)($customerResponse['json']['id'] ?? '');
        if (!$customerResponse['ok'] || !str_starts_with($customerId, 'cus_')) {
            return ['status' => 'error', 'message' => 'Stripe nie utworzył danych klienta.'];
        }

        $payload = [
            'amount' => (string)$amount,
            'currency' => strtolower((string)($order['currency'] ?? 'pln')),
            'customer' => $customerId,
            'description' => $title,
            'automatic_payment_methods[enabled]' => 'true',
        ];
        foreach ($metadata as $key => $value) {
            $payload["metadata[{$key}]"] = $value;
        }
        $paymentConfiguration = trim((string)Env::get('STRIPE_PAYMENT_METHOD_CONFIGURATION', ''));
        if (preg_match('/^pmc_[A-Za-z0-9]+$/', $paymentConfiguration)) {
            $payload['payment_method_configuration'] = $paymentConfiguration;
        } else {
            unset($payload['automatic_payment_methods[enabled]']);
            $payload['payment_method_types[0]'] = 'blik';
            $payload['payment_method_types[1]'] = 'card';
        }

        $response = (new SimpleHttpClient())->postForm(
            'https://api.stripe.com/v1/payment_intents',
            $payload,
            ['Idempotency-Key: arka-intent-' . (int)$order['id']],
            $secret
        );
        $json = $response['json'] ?? [];
        $intentId = (string)($json['id'] ?? '');
        if (!$response['ok'] || !str_starts_with($intentId, 'pi_') || empty($json['client_secret'])) {
            return ['status' => 'error', 'message' => 'Stripe nie utworzył płatności.'];
        }
        return [
            'status' => (string)($json['status'] ?? 'requires_payment_method'),
            'provider' => 'stripe',
            'session_id' => $intentId,
            'payment_id' => $intentId,
            'redirect_url' => StoreUrl::to('/platnosc/' . rawurlencode((string)$order['order_token'])),
            'raw' => [
                'id' => $intentId,
                'object' => 'payment_intent',
                'status' => (string)($json['status'] ?? 'requires_payment_method'),
                'customer' => $customerId,
                'amount' => $amount,
                'currency' => strtolower((string)($order['currency'] ?? 'pln')),
            ],
        ];
    }

    public function paymentElementState(array $order): array
    {
        $publishableKey = trim((string)Env::get('STRIPE_PUBLISHABLE_KEY', ''));
        $payment = $order['payment'] ?? (new OrderRepository())->paymentForOrder((int)$order['id']);
        $intentId = (string)($payment['provider_payment_id'] ?? $payment['provider_session_id'] ?? '');
        if (!str_starts_with($publishableKey, 'pk_') || !str_starts_with($intentId, 'pi_')) {
            return ['ok'=>false, 'status'=>'not_configured'];
        }
        $verified = $this->retrieveVerifiedIntent($order, $intentId);
        if (empty($verified['ok'])) return $verified;
        $intent = $verified['intent'];
        if (($intent['status'] ?? '') === 'succeeded') {
            (new OrderRepository())->markPaid(
                (int)$order['id'],
                'stripe',
                $intentId,
                ['type'=>'payment_intent.return.confirmed', 'data'=>['object'=>$this->safeIntent($intent)]]
            );
        }
        return [
            'ok'=>true,
            'status'=>(string)($intent['status'] ?? ''),
            'publishable_key'=>$publishableKey,
            'client_secret'=>(string)($intent['client_secret'] ?? ''),
            'payment_intent_id'=>$intentId,
            'return_url'=>StoreUrl::to('/dziekujemy/' . rawurlencode((string)$order['order_token']) . '?payment=stripe_intent'),
            'cancel_url'=>StoreUrl::to('/platnosc/anulowana/' . rawurlencode((string)$order['order_token'])),
        ];
    }

    public function confirmIntent(array $order, string $intentId): array
    {
        $verified = $this->retrieveVerifiedIntent($order, $intentId);
        if (empty($verified['ok'])) return $verified;
        $intent = $verified['intent'];
        if (($intent['status'] ?? '') !== 'succeeded') {
            return ['ok'=>false, 'status'=>(string)($intent['status'] ?? 'not_paid')];
        }
        (new OrderRepository())->markPaid(
            (int)$order['id'],
            'stripe',
            $intentId,
            ['type'=>'payment_intent.return.confirmed', 'data'=>['object'=>$this->safeIntent($intent)]]
        );
        return ['ok'=>true, 'status'=>'paid'];
    }

    public function cancelUnpaid(array $order): void
    {
        $secret = trim((string)Env::get('STRIPE_SECRET_KEY', ''));
        $payment = $order['payment'] ?? (new OrderRepository())->paymentForOrder((int)$order['id']);
        $intentId = (string)($payment['provider_payment_id'] ?? $payment['provider_session_id'] ?? '');
        if ($secret !== '' && str_starts_with($intentId, 'pi_')) {
            (new SimpleHttpClient())->postForm(
                'https://api.stripe.com/v1/payment_intents/' . rawurlencode($intentId) . '/cancel',
                [],
                ['Idempotency-Key: arka-cancel-' . (int)$order['id']],
                $secret
            );
        }
    }

    public function confirmSession(array $order, string $sessionId): array
    {
        $secret = trim((string)Env::get('STRIPE_SECRET_KEY', ''));
        $sessionId = trim($sessionId);
        if ($secret === '' || !str_starts_with($sessionId, 'cs_')) {
            return ['ok'=>false, 'status'=>'invalid_session'];
        }
        $response = (new SimpleHttpClient())->get(
            'https://api.stripe.com/v1/checkout/sessions/' . rawurlencode($sessionId),
            [],
            $secret
        );
        $session = $response['json'] ?? [];
        if (!$response['ok'] || !is_array($session)) {
            return ['ok'=>false, 'status'=>'stripe_error'];
        }
        $reference = (string)($session['client_reference_id'] ?? '');
        $amount = (int)($session['amount_total'] ?? 0);
        $currency = strtolower((string)($session['currency'] ?? ''));
        if ($reference !== (string)$order['order_number']
            || $amount !== (int)round((float)$order['total_gross'] * 100)
            || $currency !== strtolower((string)$order['currency'])) {
            return ['ok'=>false, 'status'=>'order_mismatch'];
        }
        if (($session['payment_status'] ?? '') !== 'paid') {
            return ['ok'=>false, 'status'=>'not_paid'];
        }
        (new OrderRepository())->markPaid(
            (int)$order['id'],
            'stripe',
            $session['payment_intent'] ?? $sessionId,
            ['type'=>'checkout.return.confirmed', 'data'=>['object'=>$session]]
        );
        return ['ok'=>true, 'status'=>'paid'];
    }

    public function handleWebhook(string $payload, array $headers): array
    {
        $secret = Env::get('STRIPE_WEBHOOK_SECRET', '');
        if (!$secret) {
            return ['ok'=>false, 'status'=>'not_configured', 'message'=>'Brak STRIPE_WEBHOOK_SECRET.'];
        }
        if (!$this->verifySignature($payload, $headers['stripe-signature'] ?? $headers['Stripe-Signature'] ?? '', $secret)) {
            return ['ok' => false, 'status' => 'bad_signature', 'message' => 'Niepoprawny podpis Stripe-Signature.'];
        }
        $event = json_decode($payload, true);
        if (!is_array($event)) return ['ok' => false, 'status' => 'bad_payload', 'message' => 'Niepoprawny JSON Stripe.'];
        $type = (string)($event['type'] ?? 'unknown');
        $object = $event['data']['object'] ?? [];
        $repo = new OrderRepository();
        if ($type === 'payment_intent.succeeded') {
            $intentId = (string)($object['id'] ?? '');
            $orderId = (int)($object['metadata']['order_id'] ?? 0);
            $order = $orderId ? $repo->find($orderId) : null;
            if (!$order && $intentId !== '') {
                $order = $repo->findByProviderPaymentId('stripe', $intentId);
            }
            if (!$order) {
                return ['ok'=>false, 'status'=>'order_not_found', 'message'=>'Nie znaleziono zamówienia Stripe.'];
            }
            if (!$this->intentMatchesOrder($object, $order)) {
                return ['ok'=>false, 'status'=>'amount_mismatch', 'message'=>'Kwota lub waluta Stripe nie zgadza się z zamówieniem.'];
            }
            $repo->markPaid((int)$order['id'], 'stripe', $intentId, $event);
            return ['ok'=>true, 'status'=>'paid', 'order_id'=>(int)$order['id'], 'event_type'=>$type];
        }
        if ($type === 'payment_intent.canceled') {
            $intentId = (string)($object['id'] ?? '');
            $order = $intentId !== '' ? $repo->findByProviderPaymentId('stripe', $intentId) : null;
            if (!$order && !empty($object['metadata']['order_id'])) {
                $order = $repo->find((int)$object['metadata']['order_id']);
            }
            if ($order && !in_array((string)($order['payment_status'] ?? ''), ['paid','refund_pending','refunded'], true)) {
                $repo->deleteUnpaidOrder((int)$order['id']);
            }
            return ['ok'=>true, 'status'=>'cancelled', 'event_type'=>$type];
        }
        if (in_array($type, ['payment_intent.processing','payment_intent.payment_failed'], true)) {
            $intentId = (string)($object['id'] ?? '');
            $order = $intentId !== '' ? $repo->findByProviderPaymentId('stripe', $intentId) : null;
            if (!$order && !empty($object['metadata']['order_id'])) {
                $order = $repo->find((int)$object['metadata']['order_id']);
            }
            if ($order) {
                $repo->recordPaymentStatus(
                    (int)$order['id'],
                    'stripe',
                    $type === 'payment_intent.processing' ? 'processing' : 'requires_payment_method',
                    $event
                );
            }
            return [
                'ok'=>true,
                'status'=>$type === 'payment_intent.processing' ? 'processing' : 'payment_failed',
                'event_type'=>$type,
            ];
        }
        if ($type === 'checkout.session.completed' || $type === 'checkout.session.async_payment_succeeded') {
            $sessionId = (string)($object['id'] ?? '');
            $orderNumber = (string)($object['client_reference_id'] ?? ($object['metadata']['order_number'] ?? ''));
            $order = $sessionId ? $repo->findByPaymentSession('stripe', $sessionId) : null;
            if (!$order && $orderNumber) $order = $repo->findByOrderNumber($orderNumber);
            if (!$order) return ['ok' => false, 'status' => 'order_not_found', 'message' => 'Nie znaleziono zamówienia Stripe.'];
            if (($object['payment_status'] ?? '') !== 'paid') {
                return ['ok'=>false, 'status'=>'not_paid', 'message'=>'Stripe nie potwierdził statusu paid.'];
            }
            $amount = (int)($object['amount_total'] ?? 0);
            $currency = strtolower((string)($object['currency'] ?? ''));
            if ($amount !== (int)round((float)$order['total_gross'] * 100)
                || $currency !== strtolower((string)$order['currency'])) {
                return ['ok'=>false, 'status'=>'amount_mismatch', 'message'=>'Kwota lub waluta Stripe nie zgadza się z zamówieniem.'];
            }
            $repo->markPaid((int)$order['id'], 'stripe', $object['payment_intent'] ?? $sessionId, $event);
            return ['ok'=>true, 'status'=>'paid', 'order_id'=>(int)$order['id'], 'event_type'=>$type];
        }
        if (in_array($type, ['checkout.session.async_payment_failed','checkout.session.expired'], true)) {
            $sessionId = (string)($object['id'] ?? '');
            $orderNumber = (string)($object['client_reference_id'] ?? ($object['metadata']['order_number'] ?? ''));
            $order = str_starts_with($sessionId, 'cs_') ? $repo->findByPaymentSession('stripe', $sessionId) : null;
            if (!$order && $orderNumber) $order = $repo->findByOrderNumber($orderNumber);
            if ($order) $repo->markPaymentFailed((int)$order['id'], 'stripe', 'failed', $event);
            return ['ok' => true, 'status' => 'failed'];
        }
        if (in_array($type, ['refund.updated', 'refund.failed'], true)) {
            $orderId = (int)($object['metadata']['order_id'] ?? 0);
            $order = $orderId ? $repo->find($orderId) : null;
            if (!$order && !empty($object['payment_intent'])) {
                $order = $repo->findByProviderPaymentId('stripe', (string)$object['payment_intent']);
            }
            if (!$order) {
                return ['ok'=>false, 'status'=>'order_not_found', 'message'=>'Nie znaleziono zamówienia zwrotu Stripe.'];
            }
            $status = (string)($object['status'] ?? '');
            if ($status === 'succeeded') {
                $repo->markRefunded(
                    (int)$order['id'],
                    (string)($object['id'] ?? ''),
                    $event,
                    $repo->requestedRefundRestock((int)$order['id'])
                );
                return ['ok'=>true, 'status'=>'refunded', 'order_id'=>(int)$order['id'], 'event_type'=>$type];
            }
            if (in_array($status, ['failed','canceled'], true)) {
                $repo->markRefundRejected((int)$order['id'], $event);
                return ['ok'=>true, 'status'=>'refund_rejected', 'order_id'=>(int)$order['id'], 'event_type'=>$type];
            }
            return ['ok'=>true, 'status'=>'refund_pending', 'order_id'=>(int)$order['id'], 'event_type'=>$type];
        }
        return ['ok' => true, 'status' => 'ignored', 'event_type' => $type];
    }

    public function refund(array $order, array $payment): array
    {
        $secret = (string)Env::get('STRIPE_SECRET_KEY', '');
        $paymentIntent = (string)($payment['provider_payment_id'] ?? '');
        if ($secret === '' || $paymentIntent === '') {
            return ['ok'=>false, 'message'=>'Brak klucza Stripe albo identyfikatora PaymentIntent.'];
        }
        $response = (new SimpleHttpClient())->postForm(
            'https://api.stripe.com/v1/refunds',
            [
                'payment_intent'=>$paymentIntent,
                'metadata[order_id]'=>(string)$order['id'],
                'metadata[order_number]'=>(string)$order['order_number'],
            ],
            ['Idempotency-Key: arka-refund-' . (int)$order['id']],
            $secret
        );
        $status = (string)($response['json']['status'] ?? '');
        $accepted = $response['ok'] && in_array($status, ['succeeded','pending','requires_action'], true);
        return [
            'ok'=>$accepted,
            'finalized'=>$status === 'succeeded',
            'refund_id'=>(string)($response['json']['id'] ?? ''),
            'message'=>$status === 'succeeded'
                ? 'Zwrot Stripe został wykonany.'
                : ($accepted ? 'Zwrot Stripe został przyjęty i oczekuje na końcowe potwierdzenie.' : 'Stripe nie przyjął zwrotu.'),
            'raw'=>$response,
        ];
    }

    private function retrieveVerifiedIntent(array $order, string $intentId): array
    {
        $secret = trim((string)Env::get('STRIPE_SECRET_KEY', ''));
        $intentId = trim($intentId);
        $payment = $order['payment'] ?? (new OrderRepository())->paymentForOrder((int)$order['id']);
        $expectedIntentId = (string)($payment['provider_payment_id'] ?? $payment['provider_session_id'] ?? '');
        if ($secret === '' || !str_starts_with($intentId, 'pi_') || !hash_equals($expectedIntentId, $intentId)) {
            return ['ok'=>false, 'status'=>'invalid_intent'];
        }
        $response = (new SimpleHttpClient())->get(
            'https://api.stripe.com/v1/payment_intents/' . rawurlencode($intentId),
            [],
            $secret
        );
        $intent = $response['json'] ?? [];
        if (!$response['ok'] || !is_array($intent)) {
            return ['ok'=>false, 'status'=>'stripe_error'];
        }
        if (!$this->intentMatchesOrder($intent, $order)) {
            return ['ok'=>false, 'status'=>'order_mismatch'];
        }
        return ['ok'=>true, 'intent'=>$intent];
    }

    private function intentMatchesOrder(array $intent, array $order): bool
    {
        return (string)($intent['metadata']['order_id'] ?? '') === (string)$order['id']
            && (string)($intent['metadata']['order_number'] ?? '') === (string)$order['order_number']
            && (int)($intent['amount'] ?? 0) === (int)round((float)$order['total_gross'] * 100)
            && strtolower((string)($intent['currency'] ?? '')) === strtolower((string)$order['currency']);
    }

    private function safeIntent(array $intent): array
    {
        return [
            'id'=>(string)($intent['id'] ?? ''),
            'object'=>'payment_intent',
            'status'=>(string)($intent['status'] ?? ''),
            'amount'=>(int)($intent['amount'] ?? 0),
            'amount_received'=>(int)($intent['amount_received'] ?? 0),
            'currency'=>(string)($intent['currency'] ?? ''),
            'customer'=>(string)($intent['customer'] ?? ''),
            'payment_method_types'=>array_values((array)($intent['payment_method_types'] ?? [])),
            'metadata'=>(array)($intent['metadata'] ?? []),
        ];
    }

    private function verifySignature(string $payload, string $signatureHeader, string $secret): bool
    {
        if ($signatureHeader === '') return false;
        $parts = [];
        foreach (explode(',', $signatureHeader) as $piece) {
            [$k, $v] = array_pad(explode('=', trim($piece), 2), 2, '');
            $parts[$k][] = $v;
        }
        $timestamp = $parts['t'][0] ?? '';
        $signatures = $parts['v1'] ?? [];
        if (!$timestamp || !$signatures || abs(time() - (int)$timestamp) > 300) return false;
        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) return true;
        }
        return false;
    }
}
