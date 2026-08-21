<?php
namespace Book100\Core;

use Book100\Services\Integrations\TawkWidget;
use Book100\Services\Integrations\GoogleAnalytics;

final class HttpSecurity
{
    public static function apply(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        if (self::requestIsHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        header('Content-Security-Policy: ' . self::contentSecurityPolicy());
    }

    private static function requestIsHttps(): bool
    {
        $https = strtolower(trim((string)($_SERVER['HTTPS'] ?? '')));
        if ($https !== '' && $https !== 'off' && $https !== '0') {
            return true;
        }

        $forwardedProto = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0] ?? ''));
        return $forwardedProto === 'https' || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }

    public static function contentSecurityPolicy(): string
    {
        // Podglądy plików wybranych lub przeciągniętych w panelu są tworzone
        // lokalnie przez URL.createObjectURL() i mają schemat blob:.
        $imageSources = ["'self'", 'https:', 'data:', 'blob:'];
        $styleSources = ["'self'", "'unsafe-inline'"];
        $scriptSources = ["'self'", "'unsafe-inline'"];
        $connectSources = ["'self'"];
        $frameSources = ["'self'", 'https://www.youtube.com', 'https://www.youtube-nocookie.com'];
        $fontSources = ["'self'", 'data:'];
        $formActionSources = ["'self'"];
        // Chrome applies form-action to redirects initiated by a submitted form.
        // Stripe Checkout therefore has to be allowed explicitly, otherwise the
        // session is created but the customer remains on our checkout page.
        $formActionSources[] = 'https://checkout.stripe.com';
        $imageSources[] = 'https://*.stripe.com';
        $scriptSources[] = 'https://js.stripe.com';
        $connectSources = array_merge($connectSources, [
            'https://api.stripe.com',
            'https://*.stripe.com',
            'https://*.stripe.network',
        ]);
        $frameSources = array_merge($frameSources, [
            'https://js.stripe.com',
            'https://hooks.stripe.com',
            'https://checkout.stripe.com',
        ]);
        $appUrl = StoreUrl::base();
        $scheme = strtolower((string)parse_url($appUrl, PHP_URL_SCHEME));
        $host = (string)parse_url($appUrl, PHP_URL_HOST);
        $port = (int)(parse_url($appUrl, PHP_URL_PORT) ?: 0);
        if (in_array($scheme, ['http', 'https'], true) && preg_match('/^[a-z0-9.-]+$/i', $host)) {
            $imageSources[] = $scheme . '://' . $host . ($port > 0 ? ':' . $port : '');
        }

        if (!empty(TawkWidget::configuration()['enabled'])) {
            $styleSources = array_merge($styleSources, ['https://*.tawk.to', 'https://fonts.googleapis.com', 'https://cdn.jsdelivr.net']);
            $scriptSources = array_merge($scriptSources, ['https://*.tawk.to', 'https://cdn.jsdelivr.net']);
            $connectSources = array_merge($connectSources, ['https://*.tawk.to', 'wss://*.tawk.to']);
            $frameSources[] = 'https://*.tawk.to';
            $fontSources = array_merge($fontSources, ['https://*.tawk.to', 'https://fonts.gstatic.com']);
            $formActionSources[] = 'https://*.tawk.to';
        }

        if (!empty(GoogleAnalytics::configuration()['enabled'])) {
            $scriptSources[] = 'https://www.googletagmanager.com';
            $connectSources = array_merge($connectSources, [
                'https://www.google-analytics.com',
                'https://region1.google-analytics.com',
            ]);
        }

        if (trim((string)Env::get('INPOST_GEO_WIDGET_TOKEN', '')) !== '') {
            $geowidgetHost = strtolower((string)Env::get('INPOST_MODE', 'sandbox')) === 'production'
                ? 'https://geowidget.inpost.pl'
                : 'https://sandbox-easy-geowidget-sdk.easypack24.net';
            $styleSources[] = $geowidgetHost;
            $scriptSources[] = $geowidgetHost;
            $connectSources = array_merge($connectSources, [
                $geowidgetHost,
                'https://api-pl-points.easypack24.net',
                'https://*.easypack24.net',
                'https://*.inpost.pl',
            ]);
            $frameSources = array_merge($frameSources, [
                'https://*.inpost.pl',
                'https://*.easypack24.net',
            ]);
            $fontSources[] = $geowidgetHost;
        }

        return implode('; ', [
            "default-src 'self'",
            'img-src ' . implode(' ', array_unique($imageSources)),
            'style-src ' . implode(' ', array_unique($styleSources)),
            'script-src ' . implode(' ', array_unique($scriptSources)),
            'connect-src ' . implode(' ', array_unique($connectSources)),
            'frame-src ' . implode(' ', array_unique($frameSources)),
            'font-src ' . implode(' ', array_unique($fontSources)),
            "worker-src 'self' blob:",
            'form-action ' . implode(' ', array_unique($formActionSources)),
        ]);
    }
}
