<?php
namespace Book100\Services\Integrations;

use Book100\Core\Database;
use Book100\Core\Env;
use Book100\Core\StoreUrl;
use Book100\Core\Paths;
use PDO;
use Throwable;

final class IntegrationHealthChecker
{
    public function check(): array
    {
        $report = [
            'title' => 'Kontrola integracji sklepu',
            'generated_at' => date('Y-m-d H:i:s'),
            'sections' => [],
            'errors' => [],
            'warnings' => [],
            'ok' => false,
        ];

        $root = dirname(__DIR__, 3);
        $appUrl = StoreUrl::base();
        $this->add($report, 'Środowisko', 'Adres sklepu', $appUrl !== '', $appUrl ?: 'BRAK');

        $p24 = $this->filled(['P24_MERCHANT_ID', 'P24_POS_ID', 'P24_API_KEY', 'P24_CRC']);
        $stripe = $this->filled(['STRIPE_PUBLISHABLE_KEY', 'STRIPE_SECRET_KEY', 'STRIPE_WEBHOOK_SECRET']);
        $inpost = $this->filled(['INPOST_API_TOKEN', 'INPOST_ORGANIZATION_ID']);
        $tawk = TawkWidget::configuration();
        $analytics = GoogleAnalytics::configuration();
        $this->add($report, 'Płatności', 'Przelewy24 — główny operator', $p24['ok'], $p24['summary']);
        $this->add($report, 'Płatności', 'Stripe — moduł opcjonalny', $stripe['ok'], $stripe['summary'], false);
        $this->add($report, 'Wysyłka', 'InPost ShipX', $inpost['ok'], $inpost['summary'], false);
        $this->add(
            $report,
            'Kontakt',
            'Tawk.to',
            empty($tawk['requested_enabled']) || !empty($tawk['configured']),
            !empty($tawk['enabled']) ? 'WŁĄCZONY' : (!empty($tawk['configured']) ? 'SKONFIGUROWANY, WYŁĄCZONY' : 'WYŁĄCZONY'),
            false
        );
        $this->add(
            $report,
            'Analityka',
            'Google Analytics 4',
            empty($analytics['requested_enabled']) || !empty($analytics['configured']),
            !empty($analytics['enabled'])
                ? 'WŁĄCZONY — ' . (string)$analytics['measurement_id']
                : (!empty($analytics['configured']) ? 'SKONFIGUROWANY, WYŁĄCZONY' : 'WYŁĄCZONY'),
            false
        );
        $this->add($report, 'Płatności', 'Webhook Przelewy24', $appUrl !== '', $appUrl ? $appUrl . '/api/webhooks/przelewy24' : 'BRAK APP_URL');
        $this->add($report, 'Płatności', 'Webhook zwrotów Przelewy24', $appUrl !== '', $appUrl ? $appUrl . '/api/webhooks/przelewy24/refund' : 'BRAK APP_URL', false);
        $this->add($report, 'Płatności', 'Webhook Stripe', $appUrl !== '', $appUrl ? $appUrl . '/api/webhooks/stripe' : 'BRAK APP_URL', false);
        $this->add($report, 'Wysyłka', 'Wyszukiwarka punktów InPost', $appUrl !== '', $appUrl ? $appUrl . '/api/inpost/points?q=KRA' : 'BRAK APP_URL', false);

        foreach (['storage/logs', 'storage/labels', 'public/uploads'] as $directory) {
            $path = $directory === 'public/uploads'
                ? Paths::publicRoot() . '/uploads'
                : $root . '/' . $directory;
            if (!is_dir($path)) {
                @mkdir($path, 0775, true);
            }
            $this->add(
                $report,
                'Pliki',
                $directory,
                is_writable($path),
                is_writable($path) ? 'ZAPISYWALNY' : 'BRAK ZAPISU'
            );
        }

        try {
            $pdo = Database::pdo();
            $this->add($report, 'Baza danych', 'Połączenie', true, (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
            foreach ([
                'admins', 'books', 'content_pages', 'book_images', 'orders', 'order_items',
                'payments', 'shipments', 'subscribers', 'email_logs', 'webhook_logs', 'settings',
            ] as $table) {
                $exists = $this->tableExists($pdo, $table);
                $this->add($report, 'Baza danych', 'Tabela ' . $table, $exists, $exists ? 'OK' : 'BRAK');
            }
            if ($this->tableExists($pdo, 'orders')) {
                $pending = (int)$pdo->query(
                    "SELECT COUNT(*) FROM orders WHERE status IN ('payment_pending', 'pending')"
                )->fetchColumn();
                $this->add($report, 'Dane', 'Zamówienia oczekujące', true, (string)$pending, false);
            }
        } catch (Throwable $exception) {
            $this->add($report, 'Baza danych', 'Połączenie', false, $exception->getMessage());
        }

        foreach ($report['sections'] as $section => $checks) {
            foreach ($checks as $check) {
                if (!$check['ok'] && $check['blocking']) {
                    $report['errors'][] = $section . ': ' . $check['name'] . ' — ' . $check['value'];
                } elseif (!$check['ok']) {
                    $report['warnings'][] = $section . ': ' . $check['name'] . ' — ' . $check['value'];
                }
            }
        }
        $report['ok'] = $report['errors'] === [];
        return $report;
    }

    /** @param list<string> $keys
     *  @return array{ok:bool,summary:string}
     */
    private function filled(array $keys): array
    {
        $missing = [];
        foreach ($keys as $key) {
            $value = trim((string)Env::get($key, ''));
            if ($value === '' || str_contains(strtolower($value), 'change-me')) {
                $missing[] = $key;
            }
        }
        return [
            'ok' => $missing === [],
            'summary' => $missing === [] ? 'GOTOWE' : 'brak: ' . implode(', ', $missing),
        ];
    }

    private function add(
        array &$report,
        string $section,
        string $name,
        bool $ok,
        string $value,
        bool $blocking = true
    ): void {
        $report['sections'][$section][] = [
            'name' => $name,
            'ok' => $ok,
            'value' => $value,
            'blocking' => $blocking,
        ];
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        try {
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                $statement = $pdo->prepare('SHOW TABLES LIKE ?');
                $statement->execute([$table]);
                return (bool)$statement->fetchColumn();
            }
            $statement = $pdo->prepare(
                "SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?"
            );
            $statement->execute([$table]);
            return (bool)$statement->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }
}
