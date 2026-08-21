<?php
namespace Book100\Services\Integrations;

use Book100\Core\Env;
use Book100\Core\StoreUrl;
use Book100\Repository\SettingsRepository;
use Book100\Services\Mail\MailDeliverabilityChecker;
use RuntimeException;

final class IntegrationSettingsService
{
    private const SECRET_KEYS = [
        'INPOST_API_TOKEN',
        'INPOST_GEO_WIDGET_TOKEN',
        'INPOST_WEBHOOK_SECRET',
        'P24_API_KEY',
        'P24_CRC',
        'STRIPE_SECRET_KEY',
        'STRIPE_WEBHOOK_SECRET',
        'SMTP_PASSWORD',
        'MAIL_DKIM_PRIVATE_KEY_BASE64',
    ];

    private const TEXT_KEYS = [
        'INPOST_ORGANIZATION_ID',
        'P24_MERCHANT_ID',
        'P24_POS_ID',
        'STRIPE_PUBLISHABLE_KEY',
        'STRIPE_CHECKOUT_LABEL',
        'STRIPE_PAYMENT_METHOD_CONFIGURATION',
        'MAIL_FROM',
        'MAIL_FROM_NAME',
        'MAIL_REPLY_TO',
        'MAIL_ORDER_NOTIFICATION_TO',
        'SMTP_HOST',
        'SMTP_USERNAME',
        'MAIL_DKIM_DOMAIN',
        'MAIL_DKIM_SELECTOR',
    ];

    public function overview(): array
    {
        $appUrl = StoreUrl::base();
        $merchant = (new GoogleMerchantFeed())->configuration();
        $merchant['products_count'] = count((new GoogleMerchantFeed())->products());
        $analytics = GoogleAnalytics::configuration();
        $mailTransport = $this->enum('MAIL_TRANSPORT', ['log', 'mail', 'smtp'], 'log');
        $mailFrom = trim((string)Env::get('MAIL_FROM', ''));
        $smtpHost = trim((string)Env::get('SMTP_HOST', ''));
        $dkimEnabled = strtolower((string)Env::get('MAIL_DKIM_ENABLED', 'false')) === 'true';
        $dkimDomain = trim((string)Env::get('MAIL_DKIM_DOMAIN', ''));
        $dkimSelector = trim((string)Env::get('MAIL_DKIM_SELECTOR', 'default'));
        $mailDns = (new MailDeliverabilityChecker())->check($mailFrom, $dkimDomain, $dkimSelector);
        $dkimPublicValue = $this->dkimPublicValue();
        return [
            'payment_primary' => $this->enum('PAYMENT_PRIMARY', ['przelewy24', 'stripe'], 'przelewy24'),
            'inpost' => [
                'mode' => $this->enum('INPOST_MODE', ['sandbox', 'production'], 'sandbox'),
                'organization_id' => trim((string)Env::get('INPOST_ORGANIZATION_ID', '')),
                'api_token' => $this->secretState('INPOST_API_TOKEN'),
                'geowidget_token' => $this->secretState('INPOST_GEO_WIDGET_TOKEN'),
                'webhook_secret' => $this->secretState('INPOST_WEBHOOK_SECRET'),
                'courier_enabled' => strtolower((string)Env::get('INPOST_COURIER_ENABLED', 'false')) === 'true',
                'parcel_template' => $this->enum('INPOST_DEFAULT_PARCEL_TEMPLATE', ['small', 'medium', 'large'], 'small'),
                'sending_method' => $this->enum('INPOST_DEFAULT_SENDING_METHOD', ['parcel_locker', 'branch', 'dispatch_order', 'pop', 'any_point'], 'any_point'),
                'webhook_url' => $this->inPostWebhookUrl($appUrl),
            ],
            'stripe' => [
                'mode' => $this->enum('STRIPE_MODE', ['sandbox', 'production'], 'sandbox'),
                'publishable_key' => trim((string)Env::get('STRIPE_PUBLISHABLE_KEY', '')),
                'checkout_label' => trim((string)Env::get('STRIPE_CHECKOUT_LABEL', 'Karta przez Stripe')),
                'payment_method_configuration' => trim((string)Env::get('STRIPE_PAYMENT_METHOD_CONFIGURATION', '')),
                'secret_key' => $this->secretState('STRIPE_SECRET_KEY'),
                'webhook_secret' => $this->secretState('STRIPE_WEBHOOK_SECRET'),
                'webhook_url' => $appUrl !== '' ? $appUrl . '/api/webhooks/stripe' : '',
            ],
            'p24' => [
                'mode' => $this->enum('P24_MODE', ['sandbox', 'production'], 'sandbox'),
                'merchant_id' => trim((string)Env::get('P24_MERCHANT_ID', '')),
                'pos_id' => trim((string)Env::get('P24_POS_ID', '')),
                'api_key' => $this->secretState('P24_API_KEY'),
                'crc' => $this->secretState('P24_CRC'),
                'webhook_url' => $appUrl !== '' ? $appUrl . '/api/webhooks/przelewy24' : '',
                'refund_webhook_url' => $appUrl !== '' ? $appUrl . '/api/webhooks/przelewy24/refund' : '',
            ],
            'merchant' => $merchant,
            'analytics' => $analytics,
            'mail' => [
                'transport' => $mailTransport,
                'from' => $mailFrom,
                'from_name' => trim((string)Env::get('MAIL_FROM_NAME', '')),
                'reply_to' => trim((string)Env::get('MAIL_REPLY_TO', '')),
                'order_notification_to' => trim((string)Env::get('MAIL_ORDER_NOTIFICATION_TO', '')),
                'smtp_host' => $smtpHost,
                'smtp_port' => max(1, (int)Env::get('SMTP_PORT', '587')),
                'smtp_encryption' => $this->enum('SMTP_ENCRYPTION', ['tls', 'ssl', 'none'], 'tls'),
                'smtp_username' => trim((string)Env::get('SMTP_USERNAME', '')),
                'smtp_password' => $this->secretState('SMTP_PASSWORD'),
                'dkim_enabled' => $dkimEnabled,
                'dkim_domain' => $dkimDomain,
                'dkim_selector' => $dkimSelector,
                'dkim_private_key' => $this->secretState('MAIL_DKIM_PRIVATE_KEY_BASE64'),
                'dkim_dns_host' => $dkimDomain !== '' ? $dkimSelector . '._domainkey.' . $dkimDomain : '',
                'dkim_dns_value' => $dkimPublicValue !== '' ? 'v=DKIM1; k=rsa; p=' . $dkimPublicValue : '',
                'dns' => $mailDns,
                'configured' => $mailTransport === 'smtp'
                    && filter_var($mailFrom, FILTER_VALIDATE_EMAIL)
                    && $smtpHost !== ''
                    && $this->secretState('SMTP_PASSWORD')['configured'],
                'production_ready' => $mailTransport === 'smtp'
                    && filter_var($mailFrom, FILTER_VALIDATE_EMAIL)
                    && $smtpHost !== ''
                    && $this->secretState('SMTP_PASSWORD')['configured']
                    && $dkimEnabled
                    && $this->secretState('MAIL_DKIM_PRIVATE_KEY_BASE64')['configured'],
            ],
            'tawk' => TawkWidget::configuration(),
        ];
    }

    public function generateDkimKey(): array
    {
        if (!function_exists('openssl_pkey_new')) {
            throw new RuntimeException('Moduł OpenSSL PHP nie jest dostępny — nie można wygenerować DKIM.');
        }
        $path = dirname(__DIR__, 3) . '/.env';
        if (!is_file($path) || !is_readable($path) || !is_writable($path)) {
            throw new RuntimeException('Plik .env nie istnieje albo serwer nie ma prawa go zapisać.');
        }
        $from = trim((string)Env::get('MAIL_FROM', ''));
        $domain = trim((string)Env::get('MAIL_DKIM_DOMAIN', ''));
        if ($domain === '' && filter_var($from, FILTER_VALIDATE_EMAIL)) {
            $domain = strtolower((string)substr(strrchr($from, '@') ?: '', 1));
        }
        if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $domain)) {
            throw new RuntimeException('Najpierw zapisz poprawną domenę DKIM i adres nadawcy.');
        }
        $selector = strtolower(trim((string)Env::get('MAIL_DKIM_SELECTOR', 'arka'))) ?: 'arka';
        if (!preg_match('/^[a-z0-9._-]+$/', $selector)) {
            throw new RuntimeException('Selektor DKIM ma nieprawidłowy format.');
        }

        $privatePem = $this->generatePrivatePem();
        $key = openssl_pkey_get_private($privatePem);
        if ($key === false) throw new RuntimeException('Nie udało się odczytać wygenerowanego klucza DKIM.');
        $details = openssl_pkey_get_details($key);
        $publicPem = is_array($details) ? (string)($details['key'] ?? '') : '';
        $publicValue = $this->publicPemValue($publicPem);
        if ($publicValue === '') {
            throw new RuntimeException('Nie udało się odczytać klucza publicznego DKIM.');
        }

        $contents = file_get_contents($path);
        if (!is_string($contents)) throw new RuntimeException('Nie udało się odczytać pliku .env.');
        foreach ([
            'MAIL_DKIM_ENABLED' => 'true',
            'MAIL_DKIM_DOMAIN' => strtolower($domain),
            'MAIL_DKIM_SELECTOR' => $selector,
            'MAIL_DKIM_PRIVATE_KEY_BASE64' => base64_encode($privatePem),
        ] as $name => $value) {
            $contents = $this->put($contents, $name, $value);
        }
        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new RuntimeException('Nie udało się zapisać klucza DKIM.');
        }
        return [
            'host' => $selector . '._domainkey.' . strtolower($domain),
            'value' => 'v=DKIM1; k=rsa; p=' . $publicValue,
        ];
    }

    public function save(array $input): array
    {
        $path = dirname(__DIR__, 3) . '/.env';
        if (!is_file($path) || !is_readable($path) || !is_writable($path)) {
            throw new RuntimeException('Plik .env nie istnieje albo serwer nie ma prawa go zapisać.');
        }

        $updates = [
            'PAYMENT_PRIMARY' => 'przelewy24',
            'P24_MODE' => $this->validateEnum($input, 'P24_MODE', ['sandbox', 'production'], 'sandbox'),
            'INPOST_MODE' => $this->validateEnum($input, 'INPOST_MODE', ['sandbox', 'production'], 'sandbox'),
            'INPOST_COURIER_ENABLED' => !empty($input['INPOST_COURIER_ENABLED']) ? 'true' : 'false',
            'INPOST_DEFAULT_PARCEL_TEMPLATE' => $this->validateEnum($input, 'INPOST_DEFAULT_PARCEL_TEMPLATE', ['small', 'medium', 'large'], 'small'),
            'INPOST_DEFAULT_SENDING_METHOD' => $this->validateEnum($input, 'INPOST_DEFAULT_SENDING_METHOD', ['parcel_locker', 'branch', 'dispatch_order', 'pop', 'any_point'], 'any_point'),
            'STRIPE_MODE' => $this->validateEnum($input, 'STRIPE_MODE', ['sandbox', 'production'], 'sandbox'),
            'TAWK_ENABLED' => !empty($input['TAWK_ENABLED']) ? 'true' : 'false',
            'MAIL_TRANSPORT' => $this->validateEnum($input, 'MAIL_TRANSPORT', ['log', 'mail', 'smtp'], 'log'),
            'SMTP_ENCRYPTION' => $this->validateEnum($input, 'SMTP_ENCRYPTION', ['tls', 'ssl', 'none'], 'tls'),
            'MAIL_DKIM_ENABLED' => !empty($input['MAIL_DKIM_ENABLED']) ? 'true' : 'false',
        ];
        $smtpPort = filter_var($input['SMTP_PORT'] ?? 587, FILTER_VALIDATE_INT);
        if ($smtpPort === false || $smtpPort < 1 || $smtpPort > 65535) {
            throw new RuntimeException('Port SMTP musi być liczbą od 1 do 65535.');
        }
        $updates['SMTP_PORT'] = (string)$smtpPort;

        foreach (self::TEXT_KEYS as $key) {
            if (array_key_exists($key, $input)) {
                $updates[$key] = mb_substr($this->oneLine((string)$input[$key]), 0, 190);
            }
        }
        foreach (self::SECRET_KEYS as $key) {
            $value = $this->oneLine((string)($input[$key] ?? ''));
            if ($value !== '') {
                $updates[$key] = mb_substr($value, 0, $key === 'MAIL_DKIM_PRIVATE_KEY_BASE64' ? 12000 : 2000);
            }
        }

        $stripeSecret = trim((string)($updates['STRIPE_SECRET_KEY'] ?? Env::get('STRIPE_SECRET_KEY', '')));
        $stripePublishable = trim((string)($updates['STRIPE_PUBLISHABLE_KEY'] ?? Env::get('STRIPE_PUBLISHABLE_KEY', '')));
        if ($updates['STRIPE_MODE'] === 'production') {
            if ($stripeSecret !== '' && !str_starts_with($stripeSecret, 'sk_live_')) {
                throw new RuntimeException('Tryb produkcyjny Stripe wymaga klucza sk_live_.');
            }
            if ($stripePublishable !== '' && !str_starts_with($stripePublishable, 'pk_live_')) {
                throw new RuntimeException('Tryb produkcyjny Stripe wymaga klucza pk_live_.');
            }
        } elseif (str_starts_with($stripeSecret, 'sk_live_') || str_starts_with($stripePublishable, 'pk_live_')) {
            throw new RuntimeException('Kluczy live Stripe nie można zapisać w trybie Sandbox.');
        }
        $stripePaymentConfiguration = trim((string)($updates['STRIPE_PAYMENT_METHOD_CONFIGURATION'] ?? Env::get('STRIPE_PAYMENT_METHOD_CONFIGURATION', '')));
        if ($stripePaymentConfiguration !== '' && !preg_match('/^pmc_[A-Za-z0-9]+$/', $stripePaymentConfiguration)) {
            throw new RuntimeException('Konfiguracja metod Stripe musi mieć identyfikator rozpoczynający się od pmc_.');
        }

        foreach (['P24_MERCHANT_ID', 'P24_POS_ID'] as $p24IdKey) {
            $value = trim((string)($updates[$p24IdKey] ?? Env::get($p24IdKey, '')));
            if ($value !== '' && !preg_match('/^[0-9]+$/', $value)) {
                throw new RuntimeException($p24IdKey . ': identyfikator musi zawierać wyłącznie cyfry.');
            }
        }

        foreach (['MAIL_FROM', 'MAIL_REPLY_TO', 'MAIL_ORDER_NOTIFICATION_TO'] as $emailKey) {
            $email = trim((string)($updates[$emailKey] ?? Env::get($emailKey, '')));
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException($emailKey . ': podaj poprawny adres e-mail.');
            }
        }
        if ($updates['MAIL_TRANSPORT'] === 'smtp') {
            $host = trim((string)($updates['SMTP_HOST'] ?? Env::get('SMTP_HOST', '')));
            $from = trim((string)($updates['MAIL_FROM'] ?? Env::get('MAIL_FROM', '')));
            if ($host === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Dla SMTP podaj host serwera oraz poprawny adres nadawcy.');
            }
        }

        if (!empty($input['GENERATE_INPOST_WEBHOOK_SECRET'])) {
            $updates['INPOST_WEBHOOK_SECRET'] = bin2hex(random_bytes(32));
        }

        $tawkPropertyId = $this->oneLine((string)($input['TAWK_PROPERTY_ID'] ?? ''));
        $tawkWidgetId = $this->oneLine((string)($input['TAWK_WIDGET_ID'] ?? ''));
        if ($tawkPropertyId !== '' && !TawkWidget::validId($tawkPropertyId)) {
            throw new RuntimeException('Property ID Tawk.to ma nieprawidłowy format.');
        }
        if ($tawkWidgetId !== '' && !TawkWidget::validId($tawkWidgetId)) {
            throw new RuntimeException('Widget ID Tawk.to ma nieprawidłowy format.');
        }
        if ($updates['TAWK_ENABLED'] === 'true' && ($tawkPropertyId === '' || $tawkWidgetId === '')) {
            throw new RuntimeException('Aby włączyć Tawk.to, podaj Property ID i Widget ID.');
        }
        $updates['TAWK_PROPERTY_ID'] = mb_substr($tawkPropertyId, 0, 80);
        $updates['TAWK_WIDGET_ID'] = mb_substr($tawkWidgetId, 0, 80);

        $analyticsMeasurementId = strtoupper($this->oneLine((string)($input['GOOGLE_ANALYTICS_MEASUREMENT_ID'] ?? '')));
        $analyticsEnabled = !empty($input['GOOGLE_ANALYTICS_ENABLED']);
        if ($analyticsMeasurementId !== '' && !GoogleAnalytics::validMeasurementId($analyticsMeasurementId)) {
            throw new RuntimeException('Identyfikator pomiaru Google Analytics ma nieprawidłowy format. Powinien zaczynać się od G-.');
        }
        if ($analyticsEnabled && $analyticsMeasurementId === '') {
            throw new RuntimeException('Aby włączyć Google Analytics, podaj identyfikator pomiaru zaczynający się od G-.');
        }

        $contents = file_get_contents($path);
        if (!is_string($contents)) {
            throw new RuntimeException('Nie udało się odczytać pliku .env.');
        }
        foreach ($updates as $key => $value) {
            $contents = $this->put($contents, $key, $value);
        }
        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new RuntimeException('Nie udało się zapisać konfiguracji integracji.');
        }

        (new SettingsRepository())->saveValues([
            'google_merchant_enabled' => !empty($input['GOOGLE_MERCHANT_ENABLED']) ? '1' : '0',
            'google_merchant_id' => mb_substr($this->oneLine((string)($input['GOOGLE_MERCHANT_ID'] ?? '')), 0, 80),
            'google_merchant_country' => $this->countryCode((string)($input['GOOGLE_MERCHANT_COUNTRY'] ?? 'PL')),
            'google_merchant_language' => $this->languageCode((string)($input['GOOGLE_MERCHANT_LANGUAGE'] ?? 'pl')),
            'google_merchant_brand' => mb_substr($this->oneLine((string)($input['GOOGLE_MERCHANT_BRAND'] ?? '')), 0, 70),
            'google_analytics_enabled' => $analyticsEnabled ? '1' : '0',
            'google_analytics_measurement_id' => mb_substr($analyticsMeasurementId, 0, 24),
        ]);

        return [
            'ok' => true,
            'message' => 'Konfiguracja została zapisana. Puste sekrety pozostawiono bez zmian; ustawienia poczty, Google Merchant, Google Analytics, płatności, InPost i Tawk.to są aktywne.',
        ];
    }

    private function secretState(string $key): array
    {
        $value = trim((string)Env::get($key, ''));
        return [
            'configured' => $value !== '',
            'masked' => $value !== '' ? '••••••••' . substr($value, -4) : '',
        ];
    }

    private function enum(string $key, array $allowed, string $default): string
    {
        $value = strtolower(trim((string)Env::get($key, $default)));
        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function validateEnum(array $input, string $key, array $allowed, string $default): string
    {
        $value = strtolower(trim((string)($input[$key] ?? $default)));
        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function inPostWebhookUrl(string $appUrl): string
    {
        if ($appUrl === '') return '';
        $secret = trim((string)Env::get('INPOST_WEBHOOK_SECRET', ''));
        return $secret !== ''
            ? $appUrl . '/?shipx_webhook=' . rawurlencode($secret)
            : '';
    }

    private function put(string $contents, string $key, string $value): string
    {
        $line = $key . '="' . addcslashes($value, "\\\"") . '"';
        $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';
        if (preg_match($pattern, $contents)) {
            return (string)preg_replace($pattern, $line, $contents, 1);
        }
        return rtrim($contents) . PHP_EOL . $line . PHP_EOL;
    }

    private function oneLine(string $value): string
    {
        return trim(str_replace(["\r", "\n"], '', $value));
    }

    private function countryCode(string $value): string
    {
        $value = strtoupper(trim($value));
        return preg_match('/^[A-Z]{2}$/', $value) ? $value : 'PL';
    }

    private function languageCode(string $value): string
    {
        $value = strtolower(trim($value));
        return preg_match('/^[a-z]{2}$/', $value) ? $value : 'pl';
    }

    private function dkimPublicValue(): string
    {
        $encoded = trim((string)Env::get('MAIL_DKIM_PRIVATE_KEY_BASE64', ''));
        if ($encoded === '') return '';
        $privatePem = base64_decode($encoded, true);
        if ($privatePem === false) return '';
        $key = openssl_pkey_get_private($privatePem);
        if ($key === false) return '';
        $details = openssl_pkey_get_details($key);
        return $this->publicPemValue(is_array($details) ? (string)($details['key'] ?? '') : '');
    }

    private function publicPemValue(string $publicPem): string
    {
        return preg_replace(
            '/-----BEGIN PUBLIC KEY-----|-----END PUBLIC KEY-----|\s+/',
            '',
            $publicPem
        ) ?: '';
    }

    private function opensslConfigPath(): string
    {
        foreach ([
            (string)getenv('OPENSSL_CONF'),
            dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf',
        ] as $path) {
            if ($path !== '' && is_file($path)) return $path;
        }
        return '';
    }

    private function generatePrivatePem(): string
    {
        $options = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        $config = $this->opensslConfigPath();
        if ($config !== '') $options['config'] = $config;
        $key = @openssl_pkey_new($options);
        $privatePem = '';
        if ($key !== false && @openssl_pkey_export($key, $privatePem) && $privatePem !== '') {
            return $privatePem;
        }
        while (openssl_error_string() !== false) {
            // Czyścimy kolejkę błędów przed bezpieczną próbą narzędziem systemowym.
        }

        $binary = $this->opensslBinary();
        if ($binary === '' || !function_exists('proc_open')) {
            throw new RuntimeException('OpenSSL nie może utworzyć klucza RSA na tym serwerze.');
        }
        $temporaryKey = tempnam(sys_get_temp_dir(), 'arka-dkim-');
        if ($temporaryKey === false) throw new RuntimeException('Nie udało się przygotować bezpiecznego pliku tymczasowego.');
        $pipes = [];
        $process = proc_open(
            [$binary, 'genpkey', '-algorithm', 'RSA', '-pkeyopt', 'rsa_keygen_bits:2048', '-out', $temporaryKey],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes
        );
        if (!is_resource($process)) {
            @unlink($temporaryKey);
            throw new RuntimeException('Nie udało się uruchomić generatora OpenSSL.');
        }
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        $error = (string)stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        $privatePem = is_file($temporaryKey) ? (string)file_get_contents($temporaryKey) : '';
        @unlink($temporaryKey);
        if ($exitCode !== 0 || !str_contains($privatePem, 'PRIVATE KEY')) {
            throw new RuntimeException('Generator OpenSSL zwrócił błąd: ' . mb_substr(trim($error), 0, 500));
        }
        return $privatePem;
    }

    private function opensslBinary(): string
    {
        $candidates = [
            '/usr/bin/openssl',
            '/usr/local/bin/openssl',
            'C:/laragon/bin/laragon/utils/openssl/openssl.exe',
        ];
        foreach (glob('C:/laragon/bin/apache/*/bin/openssl.exe') ?: [] as $path) $candidates[] = $path;
        foreach ($candidates as $path) {
            if (is_file($path)) return $path;
        }
        return '';
    }
}
