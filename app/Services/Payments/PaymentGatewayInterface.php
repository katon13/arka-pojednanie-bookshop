<?php
namespace Book100\Services\Payments;

interface PaymentGatewayInterface
{
    public function createSession(array $order): array;
    public function handleWebhook(string $payload, array $headers): array;
    public function refund(array $order, array $payment): array;
}
