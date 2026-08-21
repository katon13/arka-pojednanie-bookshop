<?php
namespace Book100\Core;

final class AdminPresenter
{
    public static function orderId(array $order): string
    {
        $legacyId = (int)($order['old_wp_id'] ?? $order['legacy_order_id'] ?? 0);
        if ($legacyId > 0) return (string)$legacyId;
        $number = trim((string)($order['order_number'] ?? ''));
        if ($number !== '') return $number;
        return (string)(int)($order['id'] ?? $order['order_id'] ?? 0);
    }

    public static function orderStatus(string $status): string
    {
        return [
            'pending' => 'Oczekuje',
            'payment_pending' => 'Czeka na płatność',
            'payment_failed' => 'Płatność nieudana',
            'payment_expired' => 'Płatność wygasła',
            'failed' => 'Nieudane',
            'paid' => 'Opłacone',
            'paid_waiting_for_shipment' => 'Do wysłania',
            'paid_stock_problem' => 'Brak towaru',
            'shipment_created' => 'Etykieta gotowa',
            'shipped' => 'Wysłane',
            'completed' => 'Zrealizowane',
            'refund_pending' => 'Zwrot w toku',
            'cancelled' => 'Anulowane',
            'refunded' => 'Zwrócone',
            'archived' => 'Archiwalne',
        ][$status] ?? ($status !== '' ? $status : 'Brak statusu');
    }

    public static function paymentStatus(string $status): string
    {
        return [
            'created' => 'Rozpoczęta',
            'pending' => 'Oczekuje',
            'paid' => 'Zapłacono',
            'failed' => 'Nieudana',
            'expired' => 'Wygasła',
            'cancelled' => 'Anulowana',
            'refund_pending' => 'Zwrot w toku',
            'refunded' => 'Zwrócona',
            'archived' => 'Archiwalna',
        ][$status] ?? ($status !== '' ? $status : 'Brak');
    }

    public static function shipmentStatus(string $status): string
    {
        return [
            'not_created' => 'Brak etykiety',
            'created' => 'Tworzenie etykiety',
            'offers_prepared' => 'Oferta przygotowana',
            'offer_selected' => 'Oferta wybrana',
            'confirmed' => 'Etykieta gotowa',
            'sent' => 'Wysłana',
            'dispatched_by_sender' => 'Nadana przez sprzedawcę',
            'collected_from_sender' => 'Odebrana od nadawcy',
            'taken_by_courier' => 'Odebrana przez kuriera',
            'adopted_at_source_branch' => 'W oddziale nadawczym',
            'sent_from_source_branch' => 'W drodze',
            'sent_from_transit_branch' => 'W drodze',
            'adopted_at_sorting_center' => 'W sortowni',
            'sent_from_sorting_center' => 'Opuściła sortownię',
            'out_for_delivery' => 'W doręczeniu',
            'ready_to_pickup' => 'Gotowa do odbioru',
            'pickup_reminder_sent' => 'Przypomnienie o odbiorze',
            'pickup_time_expired' => 'Minął czas odbioru',
            'returned_to_sender' => 'Wraca do nadawcy',
            'canceled' => 'Anulowana',
            'delivered' => 'Doręczona',
            'not_required' => 'Nie wymaga wysyłki',
            'archived' => 'Archiwalna',
        ][$status] ?? ($status !== '' ? $status : 'Brak etykiety');
    }

    public static function delivery(string $method): string
    {
        return [
            'inpost_locker' => 'Paczkomat InPost',
            'inpost_courier' => 'Kurier InPost',
            'ebook' => 'E-book',
            'pickup' => 'Odbiór osobisty',
        ][$method] ?? ($method !== '' ? $method : 'Nie podano');
    }

    public static function tone(string $status): string
    {
        if (in_array($status, ['paid', 'paid_waiting_for_shipment', 'shipment_created', 'shipped', 'completed', 'confirmed', 'sent', 'delivered', 'ready_to_pickup', 'active'], true)) {
            return 'success';
        }
        if (in_array($status, ['pending', 'payment_pending', 'created', 'offers_prepared', 'offer_selected', 'out_for_delivery', 'refund_pending', 'draft', 'preorder'], true)) {
            return 'warning';
        }
        if (in_array($status, ['failed', 'payment_failed', 'payment_expired', 'paid_stock_problem', 'cancelled', 'canceled', 'returned_to_sender', 'refunded', 'sold_out'], true)) {
            return 'danger';
        }
        if ($status === 'announced') return 'info';
        return 'neutral';
    }

    public static function date(?string $value, bool $withTime = true): string
    {
        if (!$value) return '—';
        $timestamp = strtotime($value);
        if ($timestamp === false) return $value;
        return date($withTime ? 'd.m.Y, H:i' : 'd.m.Y', $timestamp);
    }

    public static function money(float|int|string|null $value, string $currency = 'PLN'): string
    {
        return number_format((float)$value, 2, ',', ' ') . ' ' . $currency;
    }

    public static function publicAsset(?string $path): string
    {
        $path = trim((string)$path);
        if ($path === '' || preg_match('#^(?:https?:)?//#i', $path)) return $path;
        return AdminUrl::publicOrigin() . '/' . ltrim($path, '/');
    }

    public static function address(?string $json): array
    {
        $data = json_decode((string)$json, true);
        return is_array($data) ? $data : [];
    }

    public static function addressLines(array $address): array
    {
        $street = trim(implode(' ', array_filter([
            trim((string)($address['street'] ?? '')),
            trim((string)($address['building_number'] ?? '')),
            trim((string)($address['apartment_number'] ?? '')),
        ])));
        $city = trim(implode(' ', array_filter([
            trim((string)($address['post_code'] ?? '')),
            trim((string)($address['city'] ?? '')),
        ])));
        return array_values(array_filter([$street, $city]));
    }
}
