<?php
namespace Book100\Services\Payments;

use Book100\Core\Env;
use Book100\Repository\OrderRepository;
use RuntimeException;

final class PaymentService
{
    private static function isP24Configured(): bool
    {
        $merchantId = trim((string)Env::get('P24_MERCHANT_ID', ''));
        $apiKey = trim((string)Env::get('P24_API_KEY', ''));
        $crc = trim((string)Env::get('P24_CRC', ''));
        return $merchantId !== '' && $apiKey !== '' && $crc !== '';
    }

    public static function gateway(string $provider): PaymentGatewayInterface
    {
        return match ($provider) {
            'stripe', 'stripe_p24' => new StripeGateway(),
            'przelewy24', 'p24' => new Przelewy24Gateway(),
            default => throw new RuntimeException('Nieobslugiwany operator platnosci: ' . $provider),
        };
    }

    public static function availableProviders(): array
    {
        $providers = [];
        if (self::isP24Configured()) {
            $providers['przelewy24'] = 'Przelewy24 - BLIK i szybki przelew';
        }
        if (self::filled(['STRIPE_PUBLISHABLE_KEY','STRIPE_SECRET_KEY','STRIPE_WEBHOOK_SECRET'])) {
            $label = trim((string)Env::get('STRIPE_CHECKOUT_LABEL', 'Karta przez Stripe'));
            $providers['stripe'] = $label !== '' ? mb_substr($label, 0, 80) : 'Karta przez Stripe';
        }
        return $providers;
    }

    public static function startForOrder(array $order): array
    {
        $provider = (string)($order['payment_provider'] ?? Env::get('PAYMENT_PRIMARY', 'przelewy24'));
        $result = self::gateway($provider)->createSession($order);
        if (!empty($result['session_id'])) {
            (new OrderRepository())->savePaymentSession(
                (int)$order['id'],
                $provider,
                (string)$result['session_id'],
                $result['payment_id'] ?? null,
                $result['status'] ?? 'redirected',
                $result
            );
        } elseif (in_array((string)($result['status'] ?? ''), ['not_configured','error'], true)) {
            (new OrderRepository())->deleteUnpaidOrder((int)$order['id']);
            $message = trim((string)($result['message'] ?? ''));
            throw new RuntimeException(
                'Nie udalo sie otworzyc bezpiecznej platnosci. Sprobuj ponownie.' . ($message !== '' ? ' (' . $message . ')' : '')
            );
        }
        return $result;
    }

    public static function confirmReturn(array $order, string $sessionId): array
    {
        $provider = (string)($order['payment_provider'] ?? '');
        if (!in_array($provider, ['stripe','stripe_p24'], true)) {
            return ['ok'=>false, 'status'=>'unsupported_provider'];
        }
        $gateway = new StripeGateway();
        return str_starts_with($sessionId, 'pi_')
            ? $gateway->confirmIntent($order, $sessionId)
            : $gateway->confirmSession($order, $sessionId);
    }

    public static function isRedirectProvider(string $provider): bool
    {
        return in_array($provider, ['przelewy24', 'p24'], true);
    }

    public static function paymentElementState(array $order): array
    {
        $provider = (string)($order['payment_provider'] ?? '');
        if (!in_array($provider, ['stripe','stripe_p24'], true)) {
            return ['ok'=>false, 'status'=>'unsupported_provider'];
        }
        return (new StripeGateway())->paymentElementState($order);
    }

    public static function cancelUnpaid(array $order): void
    {
        $provider = (string)($order['payment_provider'] ?? '');
        if (in_array($provider, ['stripe','stripe_p24'], true)) {
            (new StripeGateway())->cancelUnpaid($order);
        }
    }

    public static function refundOrder(int $orderId, bool $restock = false): array
    {
        $repo = new OrderRepository();
        $order = $repo->find($orderId);
        if (!$order) throw new RuntimeException('Nie znaleziono zamowienia.');
        if (($order['payment_status'] ?? '') !== 'paid') {
            return ['ok'=>false, 'message'=>'Zwrot jest dostepny tylko dla oplaconego zamowienia.'];
        }
        $payment = $repo->paymentForOrder($orderId);
        if (!$payment) return ['ok'=>false, 'message'=>'Brak rekordu platnosci dla zamowienia.'];
        $result = self::gateway((string)$payment['provider'])->refund($order, $payment);
        if (!empty($result['ok'])) {
            if (!empty($result['finalized'])) {
                $repo->markRefunded(
                    $orderId,
                    (string)($result['refund_id'] ?? ''),
                    $result,
                    $restock
                );
            } else {
                $repo->markRefundPending(
                    $orderId,
                    (string)($result['refund_id'] ?? ''),
                    $result,
                    $restock
                );
            }
        }
        return $result;
    }

    private static function filled(array $keys): bool
    {
        foreach ($keys as $key) {
            if (trim((string)Env::get($key, '')) === '') return false;
        }
        return true;
    }
}
