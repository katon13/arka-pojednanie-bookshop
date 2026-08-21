<?php
namespace Book100\Repository;

use Book100\Core\Database;
use Book100\Core\Env;
use PDO;

final class SettingsRepository
{
    /** @return array<string,string> */
    public function allKeyed(): array
    {
        try {
            $rows = Database::pdo()->query('SELECT name, value FROM settings ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) $out[(string)$row['name']] = (string)($row['value'] ?? '');
        return $out;
    }

    public function get(string $name, string $default = ''): string
    {
        $settings = $this->allKeyed();
        return $settings[$name] ?? Env::get(strtoupper($name), $default);
    }

    public function savePublicSettings(array $data): void
    {
        $allowed = [
            'shop_name','shop_email','shop_phone','shop_address','seo_home_title','seo_home_description',
            'shipping_default_gross','shipping_inpost_locker_gross','shipping_inpost_courier_gross',
            'currency','newsletter_footer_text','contact_text','terms_text','privacy_text'
        ];
        $email = trim((string)($data['shop_email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Podaj poprawny e-mail sklepu.');
        }
        foreach (['shipping_default_gross','shipping_inpost_locker_gross','shipping_inpost_courier_gross'] as $priceKey) {
            $raw = str_replace(',', '.', trim((string)($data[$priceKey] ?? '0')));
            if ($raw !== '' && (!is_numeric($raw) || (float)$raw < 0)) {
                throw new \RuntimeException('Koszt dostawy musi być liczbą równą lub większą od zera.');
            }
            $data[$priceKey] = number_format(max(0, (float)$raw), 2, '.', '');
        }
        $currency = strtoupper(trim((string)($data['currency'] ?? 'PLN')));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new \RuntimeException('Waluta musi mieć trzyliterowy kod, np. PLN.');
        }
        $data['currency'] = $currency;
        $values = [];
        foreach ($allowed as $name) {
            $values[$name] = trim((string)($data[$name] ?? ''));
        }
        $this->saveValues($values);
    }

    /** @param array<string,string|int|float|null> $values */
    public function saveValues(array $values): void
    {
        if ($values === []) return;

        $pdo = Database::pdo();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $now = date('Y-m-d H:i:s');
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) $pdo->beginTransaction();

        try {
            foreach ($values as $name => $value) {
                if (!preg_match('/^[a-z0-9_]{2,120}$/', (string)$name)) {
                    throw new \RuntimeException('Nieprawidłowa nazwa ustawienia.');
                }
                $value = (string)($value ?? '');
                if ($driver === 'mysql') {
                    $stmt = $pdo->prepare(
                        'INSERT INTO settings (name, value, is_secret, updated_at)
                         VALUES (?, ?, 0, ?)
                         ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = VALUES(updated_at)'
                    );
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT OR REPLACE INTO settings (name, value, is_secret, updated_at)
                         VALUES (?, ?, 0, ?)'
                    );
                }
                $stmt->execute([(string)$name, $value, $now]);
            }
            if ($ownsTransaction) $pdo->commit();
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }

    public function forgetHomepageTarget(string $type, int $id): void
    {
        if (!in_array($type, ['page', 'event'], true) || $id <= 0) {
            return;
        }

        $settings = $this->allKeyed();
        $idKeySuffix = $type === 'page' ? 'page_id' : 'event_id';
        $values = [];
        foreach ([1, 2] as $slot) {
            $prefix = 'home_featured_' . $slot . '_';
            $targetType = trim((string)($settings[$prefix . 'target_type'] ?? ''));
            $targetId = (int)($settings[$prefix . $idKeySuffix] ?? 0);
            if ($targetId !== $id || ($targetType !== '' && $targetType !== $type)) {
                continue;
            }

            $values[$prefix . 'target_type'] = '';
            $values[$prefix . 'book_id'] = '0';
            $values[$prefix . 'page_id'] = '0';
            $values[$prefix . 'event_id'] = '0';
            $values[$prefix . 'image'] = '';
        }

        $this->saveValues($values);
    }

    public function shippingCost(string $method): float
    {
        if (in_array($method, ['ebook', 'pickup'], true)) return 0.0;
        $key = match ($method) {
            'inpost_courier' => 'shipping_inpost_courier_gross',
            default => 'shipping_inpost_locker_gross',
        };
        $fallback = $this->get('shipping_default_gross', '0.00');
        $value = str_replace(',', '.', $this->get($key, $fallback));
        return max(0.0, round((float)$value, 2));
    }

    /** @return array<string,string> */
    public function envStatus(): array
    {
        $keys = [
            'DB_CONNECTION','DB_HOST','DB_DATABASE','ADMIN_EMAIL',
            'P24_MERCHANT_ID','P24_POS_ID','P24_API_KEY','P24_CRC',
            'STRIPE_SECRET_KEY','STRIPE_WEBHOOK_SECRET',
            'INPOST_API_TOKEN','INPOST_ORGANIZATION_ID',
            'MAIL_FROM','APP_URL'
        ];
        $out = [];
        foreach ($keys as $key) {
            $val = Env::get($key, '');
            $out[$key] = $val === '' ? 'BRAK' : ($this->isSecretKey($key) ? 'USTAWIONE / UKRYTE' : $val);
        }
        return $out;
    }

    private function isSecretKey(string $key): bool
    {
        return str_contains($key, 'KEY') || str_contains($key, 'TOKEN') || str_contains($key, 'SECRET') || str_contains($key, 'CRC');
    }
}
