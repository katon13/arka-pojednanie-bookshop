<?php
require __DIR__ . '/../app/bootstrap.php';
\Book100\Core\Env::load(__DIR__ . '/../.env');
\Book100\Core\HttpSecurity::apply();

$requestHost = strtolower(preg_replace('/:\d+$/', '', trim((string)($_SERVER['HTTP_HOST'] ?? ''))));
if ($requestHost === 'www.arka-pojednanie.pl') {
    $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    if ($requestUri === '' || $requestUri[0] !== '/') {
        $requestUri = '/';
    }
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    header('Location: https://arka-pojednanie.pl' . $requestUri, true, in_array($method, ['GET', 'HEAD'], true) ? 301 : 308);
    return;
}

$router = new \Book100\Core\Router();
$public = new \Book100\Controllers\PublicController();
$api = new \Book100\Controllers\ApiController();

$legacyInPostSecret = trim((string)($_GET['shipx_webhook'] ?? ''));
if ($legacyInPostSecret !== '') {
    if (strcasecmp((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'), 'POST') === 0) {
        $api->inpostWebhook($legacyInPostSecret);
    } else {
        $api->inpostWebhookHealth($legacyInPostSecret);
    }
    return;
}

$router->get('/', [$public, 'home']);
$router->get('/book/{slug}', [$public, 'book']);
$router->get('/ksiazka/{slug}', [$public, 'legacyBook']);
$router->get('/kup/{slug}', [$public, 'checkout']);
$router->post('/kup/{slug}', [$public, 'checkoutSubmit']);
$router->get('/platnosc/{token}', [$public, 'payment']);
$router->get('/platnosc/anulowana/{token}', [$public, 'paymentCancelled']);
$router->get('/dziekujemy/{token}', [$public, 'thanks']);
$router->get('/ebook/{token}/{item_id}', [$public, 'ebookDownload']);
$router->post('/newsletter/zapisz', [$public, 'subscribe']);
$router->post('/zgloszenie', [$public, 'submitRegistration']);
$router->get('/wydarzenia', [$public, 'events']);
$router->get('/wydarzenia/{slug}', [$public, 'event']);
$router->get('/newsletter/wypisz/{token}', [$public, 'unsubscribe']);
$router->post('/newsletter/wypisz/{token}', [$public, 'unsubscribe']);
$router->get('/api/csrf', [$public, 'csrfToken']);
$router->get('/sitemap.xml', [$public, 'sitemap']);
$router->get('/google-merchant.xml', [$public, 'googleMerchantFeed']);
$router->get('/robots.txt', [$public, 'robots']);
$router->get('/kontakt', [$public, 'contact']);
$router->get('/regulamin', [$public, 'terms']);
$router->get('/polityka-prywatnosci', [$public, 'privacy']);
$router->post('/api/webhooks/stripe', [$api, 'stripeWebhook']);
$router->post('/api/webhooks/przelewy24', [$api, 'p24Notify']);
$router->post('/api/webhooks/przelewy24/refund', [$api, 'p24RefundNotify']);
$router->get('/api/webhooks/inpost/{secret}', [$api, 'inpostWebhookHealth']);
$router->post('/api/webhooks/inpost/{secret}', [$api, 'inpostWebhook']);
$router->get('/api/inpost/points', [$api, 'inpostPoints']);
$router->get('/api/checkout/books', [$api, 'checkoutBooks']);
$router->get('/{slug}', [$public, 'contentPage']);
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
