<?php
namespace Book100\Services\Orders;

use Book100\Core\Env;
use Book100\Services\Payments\PaymentService;

final class CheckoutValidator
{
    public static function validate(array $book, array $data): array
    {
        $book['checkout_quantity'] = $data['quantity'] ?? 1;
        return self::validateItems([$book], $data);
    }

    public static function validateItems(array $books, array $data): array
    {
        $errors = [];
        $name = trim((string)($data['customer_name'] ?? ''));
        $email = trim((string)($data['customer_email'] ?? ''));
        $phone = trim((string)($data['customer_phone'] ?? ''));
        $delivery = (string)($data['delivery_method'] ?? 'inpost_locker');
        $payment = (string)($data['payment_provider'] ?? Env::get('PAYMENT_PRIMARY', 'przelewy24'));
        $hasPaper = false;
        $hasEbook = false;

        if ($name === '') $errors[] = 'Podaj imię i nazwisko.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Podaj poprawny e-mail.';

        if ($books === []) {
            $errors[] = 'Dodaj co najmniej jedną książkę do zamówienia.';
        }
        foreach ($books as $book) {
            $isEbook = ($book['product_type'] ?? 'paper') === 'ebook';
            $hasEbook = $hasEbook || $isEbook;
            $hasPaper = $hasPaper || !$isEbook;
            $quantity = filter_var($book['checkout_quantity'] ?? 1, FILTER_VALIDATE_INT);
            if ($quantity === false || $quantity < 1 || $quantity > 20) {
                $errors[] = 'Ilość dla „' . ($book['title'] ?? 'książki') . '” musi mieścić się w zakresie 1–20.';
                continue;
            }
            if ($isEbook && (int)$quantity !== 1) {
                $errors[] = 'E-book „' . ($book['title'] ?? '') . '” jest sprzedawany jako jedna licencja na zamówienie.';
            }
            if (!$isEbook && ($book['manage_stock'] ?? 1) && (int)($book['stock_qty'] ?? 0) < (int)$quantity) {
                $errors[] = 'Brak wystarczającej liczby egzemplarzy: ' . ($book['title'] ?? 'książka') . '.';
            }
        }

        if ($hasPaper && $phone === '') $errors[] = 'Podaj telefon.';
        if (!in_array($delivery, ['inpost_locker','inpost_courier','pickup','ebook'], true)) $errors[] = 'Nieprawidłowa dostawa.';
        if ($hasPaper && $delivery === 'ebook') $errors[] = 'Dla książki papierowej wybierz Paczkomat, kuriera albo odbiór osobisty.';
        if (!$hasPaper && $hasEbook && $delivery !== 'ebook') $errors[] = 'E-book jest dostarczany wyłącznie elektronicznie.';
        if ($hasPaper && $delivery === 'inpost_locker') {
            $point = strtoupper(trim((string)($data['inpost_point'] ?? '')));
            if ($point === '') $errors[] = 'Wybierz Paczkomat / punkt InPost.';
            elseif (!preg_match('/^[A-Z0-9-]{4,20}$/', $point)) $errors[] = 'Kod punktu InPost ma nieprawidłowy format.';
        }
        if ($hasPaper && $delivery === 'inpost_courier') {
            if (strtolower((string)Env::get('INPOST_COURIER_ENABLED', 'false')) !== 'true') {
                $errors[] = 'Dostawa kurierem InPost nie jest obecnie dostępna. Wybierz Paczkomat albo odbiór osobisty.';
            }
            foreach (['street'=>'ulicę', 'building_number'=>'numer budynku', 'city'=>'miasto', 'post_code'=>'kod pocztowy'] as $field => $label) {
                if (trim((string)($data[$field] ?? '')) === '') $errors[] = 'Dla kuriera InPost podaj ' . $label . '.';
            }
        }
        if (!array_key_exists($payment, PaymentService::availableProviders())) {
            $errors[] = 'Wybrana płatność nie jest obecnie dostępna.';
        }
        if (empty($data['terms'])) $errors[] = 'Musisz zaakceptować regulamin.';
        if ($hasEbook && empty($data['digital_content_consent'])) {
            $errors[] = 'Aby otrzymać e-book od razu po płatności, potwierdź żądanie natychmiastowego dostarczenia i utratę prawa odstąpienia po rozpoczęciu dostarczania.';
        }

        return array_values(array_unique($errors));
    }
}
