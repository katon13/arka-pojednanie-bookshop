<?php
$bootstrapCandidates = [
    __DIR__ . '/../app/bootstrap.php',
    __DIR__ . '/../../app/bootstrap.php',
];
$bootstrap = null;
foreach ($bootstrapCandidates as $candidate) {
    if (is_file($candidate)) {
        $bootstrap = $candidate;
        break;
    }
}
if ($bootstrap === null) {
    http_response_code(500);
    exit('Brak plików aplikacji.');
}
require $bootstrap;
$projectRoot = dirname(dirname($bootstrap));
\Book100\Core\Env::load($projectRoot . '/.env');
\Book100\Core\HttpSecurity::apply();
define('BOOK100_ADMIN_REQUEST', true);

$router = new \Book100\Core\Router();
$admin = new \Book100\Controllers\AdminController();
$router->get('/login', [$admin, 'login']);
$router->post('/login', [$admin, 'loginSubmit']);
$router->get('/login/2fa', [$admin, 'secondFactor']);
$router->post('/login/2fa', [$admin, 'secondFactorSubmit']);
$router->post('/login/2fa/cancel', [$admin, 'cancelSecondFactor']);
$router->post('/logout', [$admin, 'logout']);
$router->get('/', [$admin, 'dashboard']);
$router->get('/homepage', [$admin, 'homepage']);
$router->post('/homepage', [$admin, 'saveHomepage']);
$router->get('/orders', [$admin, 'orders']);
$router->get('/orders/{id}', [$admin, 'orderDetail']);
$router->post('/orders/{id}', [$admin, 'updateOrder']);
$router->post('/orders/{id}/status', [$admin, 'updateOrderStatus']);
$router->post('/orders/{id}/cancel', [$admin, 'cancelOrder']);
$router->post('/orders/{id}/delete-unpaid', [$admin, 'deleteUnpaidOrder']);
$router->post('/orders/{id}/refund', [$admin, 'refundOrder']);
$router->get('/sales', [$admin, 'sales']);
$router->get('/sales/export', [$admin, 'salesExport']);
$router->get('/shipments', [$admin, 'shipments']);
$router->get('/emails', [$admin, 'emails']);
$router->post('/emails/{id}/retry', [$admin, 'retryEmail']);
$router->get('/subscribers', [$admin, 'subscribers']);
$router->post('/subscribers/mailing', [$admin, 'sendMailing']);
$router->post('/subscribers/{id}/delete', [$admin, 'deleteSubscriber']);
$router->post('/shipments/{order_id}/create', [$admin, 'createShipment']);
$router->get('/shipments/{shipment_id}/label', [$admin, 'downloadShipmentLabel']);
$router->post('/shipments/{shipment_id}/sent', [$admin, 'markShipmentSent']);
$router->get('/books', [$admin, 'books']);
$router->get('/authors', [$admin, 'authors']);
$router->get('/authors/new', [$admin, 'createAuthor']);
$router->post('/authors', [$admin, 'storeAuthor']);
$router->get('/authors/{id}/edit', [$admin, 'editAuthor']);
$router->post('/authors/{id}', [$admin, 'updateAuthor']);
$router->post('/authors/{id}/archive', [$admin, 'archiveAuthor']);
$router->get('/media', [$admin, 'media']);
$router->get('/media/library', [$admin, 'mediaLibrary']);
$router->post('/media/upload', [$admin, 'uploadMedia']);
$router->post('/media/delete', [$admin, 'deleteMedia']);
$router->post('/assets/remove', [$admin, 'removeAsset']);
$router->get('/books/new', [$admin, 'createBook']);
$router->post('/books', [$admin, 'storeBook']);
$router->post('/books/description-image', [$admin, 'uploadBookDescriptionImage']);
$router->post('/media/rich-image', [$admin, 'uploadRichContentImage']);
$router->get('/books/{id}/edit', [$admin, 'editBook']);
$router->post('/books/{id}', [$admin, 'updateBook']);
$router->post('/books/{id}/delete', [$admin, 'deleteBook']);
$router->get('/pages', [$admin, 'pages']);
$router->get('/pages/new', [$admin, 'createPage']);
$router->post('/pages', [$admin, 'storePage']);
$router->get('/pages/{id}/edit', [$admin, 'editPage']);
$router->post('/pages/{id}', [$admin, 'updatePage']);
$router->post('/pages/{id}/archive', [$admin, 'archivePage']);
$router->post('/pages/{id}/delete', [$admin, 'deletePage']);
$router->get('/events', [$admin, 'events']);
$router->get('/events/new', [$admin, 'createEvent']);
$router->post('/events', [$admin, 'storeEvent']);
$router->get('/events/{id}/edit', [$admin, 'editEvent']);
$router->post('/events/{id}', [$admin, 'updateEvent']);
$router->post('/events/{id}/archive', [$admin, 'archiveEvent']);
$router->post('/events/{id}/delete', [$admin, 'deleteEvent']);
$router->post('/events/{id}/registrations', [$admin, 'addEventRegistration']);
$router->get('/forms', [$admin, 'forms']);
$router->get('/forms/new', [$admin, 'createRegistrationForm']);
$router->post('/forms', [$admin, 'storeRegistrationForm']);
$router->get('/forms/{id}/edit', [$admin, 'editRegistrationForm']);
$router->post('/forms/{id}', [$admin, 'updateRegistrationForm']);
$router->post('/forms/{id}/archive', [$admin, 'archiveRegistrationForm']);
$router->post('/registrations/{id}', [$admin, 'updateRegistration']);
$router->post('/cache/clear', [$admin, 'clearCache']);
$router->get('/settings', [$admin, 'settings']);
$router->post('/settings', [$admin, 'saveSettings']);
$router->post('/settings/password', [$admin, 'changePassword']);
$router->get('/security/2fa', [$admin, 'twoFactorSettings']);
$router->post('/security/2fa/setup', [$admin, 'beginTwoFactorSetup']);
$router->post('/security/2fa/confirm', [$admin, 'confirmTwoFactorSetup']);
$router->post('/security/2fa/cancel', [$admin, 'cancelTwoFactorSetup']);
$router->post('/security/2fa/disable', [$admin, 'disableTwoFactor']);
$router->get('/system-check', [$admin, 'systemCheck']);
$router->get('/integrations', [$admin, 'integrations']);
$router->post('/integrations', [$admin, 'saveIntegrations']);
$router->post('/integrations/inpost/test', [$admin, 'testInPostConnection']);
$router->post('/integrations/mail/test', [$admin, 'testMailIntegration']);
$router->post('/integrations/mail/dkim-generate', [$admin, 'generateDkimKey']);
$router->dispatch(
    $_SERVER['REQUEST_METHOD'],
    \Book100\Core\AdminUrl::stripRequestUri($_SERVER['REQUEST_URI'])
);
