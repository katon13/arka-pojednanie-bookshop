<?php
namespace Book100\Controllers;

use Book100\Core\Csrf;
use Book100\Core\ContentFormatter;
use Book100\Core\Redirect;
use Book100\Core\View;
use Book100\Repository\AuthorRepository;
use Book100\Repository\BookRepository;
use Book100\Repository\ContentPageRepository;
use Book100\Repository\EmailLogRepository;
use Book100\Repository\EventRepository;
use Book100\Repository\OrderRepository;
use Book100\Repository\RegistrationFormRepository;
use Book100\Repository\RegistrationRepository;
use Book100\Repository\ShipmentRepository;
use Book100\Repository\SubscriberRepository;
use Book100\Repository\MailingRepository;
use Book100\Repository\SettingsRepository;
use Book100\Services\Auth\AdminAuth;
use Book100\Services\Auth\AdminTwoFactor;
use Book100\Services\Cache\PublicCache;
use Book100\Services\InPost\InPostClient;
use Book100\Services\Integrations\IntegrationSettingsService;
use Book100\Services\Homepage\HomepageSettingsService;
use Book100\Services\Books\BookAssetService;
use Book100\Services\Books\BookSaleState;
use Book100\Services\Media\AdminAssetRemovalService;
use Book100\Services\Media\MediaLibraryService;
use Book100\Services\Media\RichContentMediaService;
use Book100\Services\Mail\Mailer;
use Book100\Services\Payments\PaymentService;
use Book100\Services\Storefront\StorefrontSettingsService;
use Throwable;

final class AdminController
{
    public function login(): void
    {
        if (AdminAuth::check()) Redirect::to('/');
        if (AdminAuth::hasPendingSecondFactor()) Redirect::to('/login/2fa');
        View::render('admin/login', ['error' => null]);
    }

    public function loginSubmit(): void
    {
        Csrf::check();
        $result = AdminAuth::attemptPassword(
            (string)($_POST['email'] ?? ''),
            (string)($_POST['password'] ?? '')
        );
        if ($result === 'authenticated') Redirect::to('/');
        if ($result === 'totp_required') Redirect::to('/login/2fa');
        View::render('admin/login', ['error' => 'Błędny e-mail albo hasło.']);
    }

    public function secondFactor(): void
    {
        if (AdminAuth::check()) Redirect::to('/');
        if (!AdminAuth::hasPendingSecondFactor()) Redirect::to('/login');
        header('Cache-Control: no-store, max-age=0');
        View::render('admin/login_2fa', ['error' => null]);
    }

    public function secondFactorSubmit(): void
    {
        Csrf::check();
        $code = trim((string)($_POST['code'] ?? ''));
        if (preg_match('/^\d{6}$/D', $code) && AdminAuth::completeSecondFactor($code)) {
            Redirect::to('/');
        }
        header('Cache-Control: no-store, max-age=0');
        View::render('admin/login_2fa', [
            'error' => 'Nieprawidłowy lub wykorzystany kod. Sprawdź czas w telefonie i spróbuj ponownie.',
        ]);
    }

    public function cancelSecondFactor(): void
    {
        Csrf::check();
        AdminAuth::cancelSecondFactor();
        Redirect::to('/login');
    }

    public function logout(): void
    {
        Csrf::check();
        AdminAuth::logout();
        Redirect::to('/login');
    }

    public function dashboard(): void
    {
        AdminAuth::requireLogin();
        $books = (new BookRepository())->all();
        View::render('admin/dashboard', [
            'user' => AdminAuth::user(),
            'books' => $books,
            'stats' => [
                'books' => count($books),
                'active' => count(array_filter($books, static fn(array $book): bool => BookSaleState::isPurchasable($book))),
                'hidden' => count(array_filter($books, static fn(array $book): bool => !BookSaleState::isPublic($book))),
                'orders_today' => (new OrderRepository())->countToday(),
                'to_ship' => (new OrderRepository())->countToShip(),
                'paid_revenue' => (new OrderRepository())->paidRevenue(),
            ]
        ]);
    }

    public function orders(): void
    {
        AdminAuth::requireLogin();
        $query = trim((string)($_GET['q'] ?? ''));
        $status = trim((string)($_GET['status'] ?? ''));
        $page = max(1, (int)($_GET['page'] ?? 1));
        $repo = new OrderRepository();
        $result = $repo->search($query, $status, $page, 25);
        $shipments = (new ShipmentRepository())->listForOrders($result['rows']);
        View::render('admin/orders/index', [
            'orders'=>$result['rows'],
            'items'=>$repo->itemSummariesForOrders($result['rows']),
            'shipments'=>$shipments,
            'stats'=>$repo->adminSummary(),
            'inpostConfigured'=>(new InPostClient())->isConfigured(),
            'pagination'=>$result,
            'filters'=>['q'=>$query, 'status'=>$status],
            'user'=>AdminAuth::user(),
        ]);
    }

    public function sales(): void
    {
        AdminAuth::requireLogin();
        $repo = new OrderRepository();
        View::render('admin/sales/index', [
            'rows' => $repo->salesRows(),
            'stats' => $repo->salesSummary(),
            'user' => AdminAuth::user(),
        ]);
    }

    public function homepage(): void
    {
        AdminAuth::requireLogin();
        $state = (new HomepageSettingsService())->adminState();
        View::render('admin/homepage/index', [
            'books' => $state['books'],
            'pages' => $state['pages'],
            'events' => $state['events'],
            'featuredIds' => $state['featured_ids'],
            'featuredTargets' => $state['featured_targets'],
            'featuredImages' => $state['featured_images'],
            'hiddenIds' => $state['hidden_ids'],
            'showHowItWorks' => $state['show_how_it_works'],
            'hero' => $state['hero'],
            'user' => AdminAuth::user(),
        ]);
    }

    public function saveHomepage(): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        try {
            (new HomepageSettingsService())->save($_POST, $_FILES);
            PublicCache::clear();
            View::render('admin/message', [
                'title' => 'Strona główna zapisana',
                'message' => 'Baner, promocje, kolejność i widoczność książek zostały zapisane.',
                'backUrl' => '/homepage',
                'user' => AdminAuth::user(),
            ]);
        } catch (Throwable $e) {
            View::render('admin/message', [
                'title' => 'Nie zapisano strony głównej',
                'message' => $e->getMessage(),
                'backUrl' => '/homepage',
                'user' => AdminAuth::user(),
            ]);
        }
    }


    public function orderDetail(string $id): void
    {
        AdminAuth::requireLogin();
        $repo = new OrderRepository();
        $order = $repo->find((int)$id);
        if (!$order) { http_response_code(404); echo 'Nie znaleziono zamówienia'; return; }
        View::render('admin/orders/detail', [
            'order' => $order,
            'shipment' => (new ShipmentRepository())->findByOrderId((int)$order['id']),
            'timeline' => $repo->timeline((int)$order['id']),
            'inpostConfigured' => (new InPostClient())->isConfigured(),
            'inpostConfig' => (new InPostClient())->configuration(),
            'user' => AdminAuth::user(),
        ]);
    }

    public function updateOrder(string $id): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        try {
            (new OrderRepository())->updateAdminDetails((int)$id, $_POST);
            View::render('admin/message', [
                'title'=>'Zamówienie zapisane',
                'message'=>'Dane klienta i notatka zostały zapisane.',
                'backUrl'=>'/orders/' . (int)$id,
                'user'=>AdminAuth::user(),
            ]);
        } catch (Throwable $e) {
            View::render('admin/message', [
                'title'=>'Nie zapisano zamówienia',
                'message'=>$e->getMessage(),
                'backUrl'=>'/orders/' . (int)$id,
                'user'=>AdminAuth::user(),
            ]);
        }
    }

    public function updateOrderStatus(string $id): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        try {
            $orders = new OrderRepository();
            $order = $orders->find((int)$id);
            if (!$order) {
                throw new \RuntimeException('Nie znaleziono zamówienia.');
            }

            $status = trim((string)($_POST['status'] ?? ''));
            $notifyCustomer = (string)($_POST['notify_customer'] ?? '1') !== '0';
            $shipments = new ShipmentRepository();
            $shipment = $shipments->findByOrderId((int)$order['id']);
            $needsInPost = in_array(($order['delivery_method'] ?? ''), ['inpost_locker', 'inpost_courier'], true);
            $shipmentStatus = trim((string)($order['shipment_status'] ?? ''));
            $hasShippingEvidence = $shipment
                || !in_array($shipmentStatus, ['', 'not_created', 'not_required'], true);

            if (in_array($status, ['shipment_created', 'shipped', 'completed'], true)
                && $needsInPost
                && !$hasShippingEvidence) {
                throw new \RuntimeException('Najpierw utwórz etykietę InPost.');
            }

            if ($status === 'cancelled') {
                if (($order['payment_status'] ?? '') === 'paid') {
                    throw new \RuntimeException('Opłacone zamówienie anuluj przez zwrot płatności w sekcji na dole.');
                }
                if (($order['payment_status'] ?? '') === 'refund_pending') {
                    throw new \RuntimeException('Poczekaj na zakończenie rozpoczętego zwrotu płatności.');
                }
                if (($order['payment_status'] ?? '') === 'refunded') {
                    $orders->updateStatusOnly((int)$order['id'], $status, $notifyCustomer);
                } else {
                    $result = $orders->cancel((int)$order['id'], 'Anulowano z szybkiej zmiany statusu.');
                    if (empty($result['ok'])) {
                        throw new \RuntimeException((string)($result['message'] ?? 'Nie udało się anulować zamówienia.'));
                    }
                }
            } elseif ($status === 'shipped' && $shipment) {
                $shipments->markSent((int)$shipment['id']);
            } else {
                $orders->updateStatusOnly((int)$order['id'], $status, $notifyCustomer);
            }

            View::render('admin/message', [
                'title'=>'Status zapisany',
                'message'=>'Status zamówienia zmieniono na: ' . \Book100\Core\AdminPresenter::orderStatus($status) . '.'
                    . (!$notifyCustomer ? ' Wiadomość e-mail nie została wysłana.' : ''),
                'backUrl'=>'/orders/' . (int)$id,
                'user'=>AdminAuth::user(),
            ]);
        } catch (Throwable $e) {
            View::render('admin/message', [
                'title'=>'Nie zmieniono statusu',
                'message'=>$e->getMessage(),
                'backUrl'=>'/orders/' . (int)$id,
                'user'=>AdminAuth::user(),
            ]);
        }
    }

    public function cancelOrder(string $id): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        $result = (new OrderRepository())->cancel((int)$id, trim((string)($_POST['note'] ?? '')));
        PublicCache::clear();
        View::render('admin/message', [
            'title'=>!empty($result['ok']) ? 'Zamówienie anulowane' : 'Nie anulowano zamówienia',
            'message'=>$result['message'] ?? '',
            'backUrl'=>'/orders/' . (int)$id,
            'user'=>AdminAuth::user(),
        ]);
    }

    public function deleteUnpaidOrder(string $id): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        try {
            (new OrderRepository())->deleteUnpaidOrder((int)$id);
            PublicCache::clear();
            View::render('admin/message', [
                'title'=>'Nieopłacone zamówienie usunięte',
                'message'=>'Usunięto rekord techniczny, historię i rezerwację magazynową.',
                'backUrl'=>'/orders',
                'user'=>AdminAuth::user(),
            ]);
        } catch (Throwable $e) {
            View::render('admin/message', [
                'title'=>'Nie usunięto zamówienia',
                'message'=>$e->getMessage(),
                'backUrl'=>'/orders/' . (int)$id,
                'user'=>AdminAuth::user(),
            ]);
        }
    }

    public function refundOrder(string $id): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        try {
            $result = PaymentService::refundOrder((int)$id, !empty($_POST['restock']));
            PublicCache::clear();
            $title = !empty($result['ok'])
                ? (!empty($result['finalized']) ? 'Zwrot wykonany' : 'Zwrot przyjęty')
                : 'Zwrot niewykonany';
            View::render('admin/message', [
                'title'=>$title,
                'message'=>$result['message'] ?? 'Brak odpowiedzi operatora.',
                'backUrl'=>'/orders/' . (int)$id,
                'user'=>AdminAuth::user(),
            ]);
        } catch (Throwable $e) {
            View::render('admin/message', [
                'title'=>'Zwrot niewykonany',
                'message'=>$e->getMessage(),
                'backUrl'=>'/orders/' . (int)$id,
                'user'=>AdminAuth::user(),
            ]);
        }
    }

    public function salesExport(): void
    {
        AdminAuth::requireLogin();
        $rows = (new OrderRepository())->salesRows(10000);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="arka-sprzedaz-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['data','zamowienie','email','ksiazka','ilosc','wartosc','platnosc','status_platnosci','dostawa','status'], ';');
        foreach ($rows as $row) {
            fputcsv($out, [
                $row['created_at'] ?? '',
                $row['order_number'] ?? '',
                $row['customer_email'] ?? '',
                $row['title'] ?? '',
                $row['quantity'] ?? 0,
                $row['item_total'] ?? 0,
                $row['payment_provider'] ?? '',
                $row['payment_status'] ?? '',
                $row['delivery_method'] ?? '',
                $row['status'] ?? '',
            ], ';');
        }
        fclose($out);
    }

    public function settings(): void
    {
        AdminAuth::requireLogin();
        $repo = new SettingsRepository();
        View::render('admin/settings/index', [
            'settings' => $repo->allKeyed(),
            'storefront' => (new StorefrontSettingsService())->state(),
            'envStatus' => $repo->envStatus(),
            'user' => AdminAuth::user(),
        ]);
    }

    public function saveSettings(): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        try {
            (new StorefrontSettingsService())->save($_POST, $_FILES);
            PublicCache::clear();
            View::render('admin/message', ['title'=>'Ustawienia zapisane', 'message'=>'Marka, treści sklepu, SEO, sprzedaż i dokumenty zostały zapisane. Cache publiczny wyczyszczono.', 'backUrl'=>'/settings', 'user'=>AdminAuth::user()]);
        } catch (Throwable $e) {
            View::render('admin/message', ['title'=>'Nie zapisano ustawień', 'message'=>$e->getMessage(), 'backUrl'=>'/settings', 'user'=>AdminAuth::user()]);
        }
    }

    public function changePassword(): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        $new = (string)($_POST['new_password'] ?? '');
        $repeat = (string)($_POST['new_password_repeat'] ?? '');
        $ok = $new === $repeat && AdminAuth::changePassword((string)($_POST['current_password'] ?? ''), $new);
        View::render('admin/message', [
            'title'=>$ok ? 'Hasło zmienione' : 'Hasło niezmienione',
            'message'=>$ok ? 'Nowe hasło administratora zostało zapisane.' : 'Sprawdź obecne hasło. Nowe hasło musi mieć co najmniej 12 znaków i oba pola muszą być identyczne.',
            'backUrl'=>'/settings',
            'user'=>AdminAuth::user(),
        ]);
    }

    public function twoFactorSettings(): void
    {
        AdminAuth::requireLogin();
        header('Cache-Control: no-store, max-age=0');
        $this->renderTwoFactor();
    }

    public function beginTwoFactorSetup(): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        $user = AdminAuth::user();
        try {
            AdminTwoFactor::beginSetup(
                (int)$user['id'],
                (string)($_POST['current_password'] ?? ''),
                trim((string)($_POST['current_code'] ?? ''))
            );
            Redirect::to('/security/2fa');
        } catch (Throwable $exception) {
            http_response_code(422);
            header('Cache-Control: no-store, max-age=0');
            $this->renderTwoFactor($exception->getMessage());
        }
    }

    public function confirmTwoFactorSetup(): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        $user = AdminAuth::user();
        $code = trim((string)($_POST['code'] ?? ''));
        try {
            $ok = preg_match('/^\d{6}$/D', $code)
                && AdminTwoFactor::confirmSetup((int)$user['id'], $code);
        } catch (Throwable $exception) {
            $ok = false;
        }
        if ($ok) {
            Redirect::to('/security/2fa?enabled=1');
        }
        http_response_code(422);
        header('Cache-Control: no-store, max-age=0');
        $this->renderTwoFactor('Kod jest nieprawidłowy, wygasł albo został już użyty. 2FA nie zostało włączone.');
    }

    public function cancelTwoFactorSetup(): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        $user = AdminAuth::user();
        AdminTwoFactor::cancelPending((int)$user['id']);
        Redirect::to('/security/2fa?cancelled=1');
    }

    public function disableTwoFactor(): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        $user = AdminAuth::user();
        try {
            $ok = AdminTwoFactor::disable(
                (int)$user['id'],
                (string)($_POST['current_password'] ?? ''),
                trim((string)($_POST['current_code'] ?? ''))
            );
        } catch (Throwable $exception) {
            $ok = false;
        }
        if ($ok) {
            Redirect::to('/security/2fa?disabled=1');
        }
        http_response_code(422);
        header('Cache-Control: no-store, max-age=0');
        $this->renderTwoFactor('Nie wyłączono 2FA. Sprawdź hasło i podaj nowy, aktualny kod 6-cyfrowy.');
    }

    private function renderTwoFactor(?string $error = null): void
    {
        $user = AdminAuth::user();
        if (!$user) {
            Redirect::to('/login');
        }
        try {
            $state = AdminTwoFactor::state((int)$user['id']);
            $setup = AdminTwoFactor::setupData((int)$user['id']);
        } catch (Throwable $exception) {
            $state = ['enabled' => false, 'pending' => false, 'enabled_at' => null];
            $setup = null;
            $error = $error ?: 'Konfiguracja 2FA jest chwilowo niedostępna. Nie zmieniono stanu logowania.';
        }
        View::render('admin/security/two_factor', [
            'user' => $user,
            'twoFactor' => $state,
            'setup' => $setup,
            'error' => $error,
        ]);
    }

    public function systemCheck(): void
    {
        AdminAuth::requireLogin();
        $checks = [
            'PHP' => PHP_VERSION,
            'PDO' => extension_loaded('pdo') ? 'TAK' : 'NIE',
            'PDO MySQL' => extension_loaded('pdo_mysql') ? 'TAK' : 'NIE',
            'cURL' => extension_loaded('curl') ? 'TAK' : 'NIE',
            'OpenSSL' => extension_loaded('openssl') ? 'TAK' : 'NIE',
            'storage/cache writable' => is_writable(dirname(__DIR__, 2) . '/storage/cache') ? 'TAK' : 'NIE',
            'storage/uploads writable' => is_writable(dirname(__DIR__, 2) . '/storage/uploads') ? 'TAK' : 'NIE',
        ];
        View::render('admin/settings/check', ['checks'=>$checks, 'user'=>AdminAuth::user()]);
    }


    public function shipments(): void
    {
        AdminAuth::requireLogin();
        $repo = new OrderRepository();
        $orders = array_values(array_filter(
            $repo->latest(200),
            fn($o) => in_array($o['status'], ['paid','paid_waiting_for_shipment','shipment_created','shipped','payment_pending'], true)
                && in_array(($o['delivery_method'] ?? ''), ['inpost_locker','inpost_courier'], true)
        ));
        $shipments = (new ShipmentRepository())->listForOrders($orders);
        $ready = 0;
        $labels = 0;
        $sent = 0;
        foreach ($orders as $order) {
            $shipment = $shipments[(int)$order['id']] ?? null;
            if (!$shipment && in_array($order['status'], ['paid','paid_waiting_for_shipment'], true) && in_array($order['delivery_method'], ['inpost_locker','inpost_courier'], true)) $ready++;
            if ($shipment && !in_array($order['status'] ?? '', ['shipped','completed'], true)) $labels++;
            if ($shipment && in_array($order['status'] ?? '', ['shipped','completed'], true)) $sent++;
        }
        View::render('admin/shipments/index', [
            'orders' => $orders,
            'items' => $repo->itemSummariesForOrders($orders),
            'shipments' => $shipments,
            'stats' => ['ready'=>$ready, 'labels'=>$labels, 'sent'=>$sent],
            'inpostConfigured' => (new InPostClient())->isConfigured(),
            'user' => AdminAuth::user(),
        ]);
    }

    public function emails(): void
    {
        AdminAuth::requireLogin();
        $query = trim((string)($_GET['q'] ?? ''));
        $status = trim((string)($_GET['status'] ?? ''));
        $template = trim((string)($_GET['template'] ?? ''));
        $page = max(1, (int)($_GET['page'] ?? 1));
        $result = (new EmailLogRepository())->search($query, $status, $template, $page, 40);
        View::render('admin/emails/index', [
            'result' => $result,
            'query' => $query,
            'status' => $status,
            'template' => $template,
            'user' => AdminAuth::user(),
        ]);
    }

    public function retryEmail(string $id): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        $repository = new EmailLogRepository();
        $mail = $repository->find((int)$id);
        if (!$mail) {
            http_response_code(404);
            View::render('admin/message', [
                'title' => 'Nie znaleziono wiadomości',
                'message' => 'Wiadomość nie istnieje albo została usunięta razem z zamówieniem.',
                'backUrl' => '/emails',
                'user' => AdminAuth::user(),
            ]);
            return;
        }
        $repository->retry((int)$id);
        $report = (new Mailer())->processOne((int)$id);
        $item = $report['items'][0] ?? [];
        $ok = ($item['status'] ?? '') === 'sent';
        if (!$ok) http_response_code(422);
        View::render('admin/message', [
            'title' => $ok ? 'Wiadomość wysłana' : 'Wiadomość czeka na ponowienie',
            'message' => $ok
                ? 'Wiadomość została poprawnie przetworzona przez skonfigurowany transport.'
                : (string)($item['message'] ?? 'Serwer pocztowy odrzucił wiadomość. Szczegóły zapisano w rejestrze.'),
            'backUrl' => '/emails',
            'user' => AdminAuth::user(),
        ]);
    }

    public function createShipment(string $orderId): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        $orders = new OrderRepository();
        $order = $orders->find((int)$orderId);
        if (!$order) { http_response_code(404); echo 'Nie znaleziono zamówienia'; return; }
        if (!in_array(($order['delivery_method'] ?? ''), ['inpost_locker', 'inpost_courier'], true)) {
            View::render('admin/message', ['title'=>'Etykieta nie jest potrzebna', 'message'=>'To zamówienie nie korzysta z dostawy InPost.', 'backUrl'=>'/orders/' . (int)$orderId, 'user'=>AdminAuth::user()]);
            return;
        }
        if (!in_array($order['status'], ['paid','paid_waiting_for_shipment','shipment_created'], true)) {
            View::render('admin/message', ['title'=>'Nie można utworzyć przesyłki', 'message'=>'Przesyłkę InPost tworzymy dopiero dla opłaconego zamówienia papierowego.', 'user'=>AdminAuth::user()]);
            return;
        }
        if (BookSaleState::preorderWaitsForRelease($order['items'] ?? [])) {
            $releaseDate = BookSaleState::latestPreorderDate($order['items'] ?? []);
            View::render('admin/message', [
                'title'=>'Przedsprzedaż — etykieta jeszcze zablokowana',
                'message'=>'To zamówienie zawiera przedsprzedaż. Etykietę utworzysz od ' . BookSaleState::formattedReleaseDate($releaseDate) . '.',
                'backUrl'=>'/orders/' . (int)$orderId,
                'user'=>AdminAuth::user(),
            ]);
            return;
        }
        $shipmentRepository = new ShipmentRepository();
        $existingShipment = $shipmentRepository->findByOrderId((int)$order['id']);
        if ($existingShipment && !empty($existingShipment['provider_shipment_id'])) {
            Redirect::to('/shipments/' . (int)$existingShipment['id'] . '/label');
        }
        $result = (new InPostClient())->createShipment($order, [
            'parcel_template' => $_POST['parcel_template'] ?? null,
            'sending_method' => $_POST['sending_method'] ?? null,
            'insurance' => $_POST['insurance'] ?? null,
            'weight_kg' => $_POST['weight_kg'] ?? null,
            'reference' => $_POST['reference'] ?? null,
        ]);
        if (!($result['ok'] ?? false)) {
            View::render('admin/message', ['title'=>'InPost: przesyłka nieutworzona', 'message'=>$result['message'] ?? 'Błąd InPost', 'user'=>AdminAuth::user()]);
            return;
        }
        $shipment = $shipmentRepository->createOrUpdateFromInPost($order, $result);
        $orders->markShipmentCreated((int)$order['id'], $shipment);
        Redirect::to('/shipments/' . (int)$shipment['id'] . '/label');
    }

    public function downloadShipmentLabel(string $shipmentId): void
    {
        AdminAuth::requireLogin();
        $repo = new ShipmentRepository();
        $shipment = $repo->find((int)$shipmentId);
        if (!$shipment) { http_response_code(404); echo 'Nie znaleziono przesyłki'; return; }
        $labelPath = $shipment['label_path'] ?? '';
        $root = dirname(__DIR__, 2);
        $labelsRoot = realpath($root . '/storage/labels');
        $full = $labelPath ? realpath($root . '/' . ltrim($labelPath, '/\\')) : false;
        if ($labelsRoot && $full && str_starts_with(strtolower($full), strtolower($labelsRoot . DIRECTORY_SEPARATOR)) && is_file($full)) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="inpost-zamowienie-' . $shipment['order_id'] . '.pdf"');
            readfile($full);
            return;
        }
        $result = (new InPostClient())->fetchLabel((string)($shipment['provider_shipment_id'] ?? ''));
        if (!($result['ok'] ?? false) || !str_starts_with((string)($result['body'] ?? ''), '%PDF-')) {
            View::render('admin/message', ['title'=>'Etykieta niedostępna', 'message'=>$result['message'] ?? 'Nie udało się pobrać etykiety.', 'user'=>AdminAuth::user()]);
            return;
        }
        $dir = $root . '/storage/labels';
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $relative = 'storage/labels/inpost-' . $shipment['id'] . '.pdf';
        file_put_contents($root . '/' . $relative, $result['body']);
        $providerState = $repo->updateFromProvider((int)$shipment['id'], $result) ?: $shipment;
        $repo->createOrUpdateFromInPost([
            'id' => $shipment['order_id'],
            'delivery_method' => $shipment['method'] ?? '',
            'inpost_point' => $shipment['inpost_point'] ?? '',
            'customer_name' => $shipment['receiver_name'] ?? '',
            'customer_email' => $shipment['receiver_email'] ?? '',
            'customer_phone' => $shipment['receiver_phone'] ?? '',
        ], [
            'status' => $providerState['status'] ?? $shipment['status'] ?? 'confirmed',
            'provider_shipment_id' => $shipment['provider_shipment_id'] ?? null,
            'tracking_number' => $providerState['tracking_number'] ?? $shipment['tracking_number'] ?? null,
            'label_path' => $relative,
            'raw' => $result['raw'] ?? ['label' => 'downloaded'],
        ]);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="inpost-zamowienie-' . $shipment['order_id'] . '.pdf"');
        echo $result['body'];
    }

    public function markShipmentSent(string $shipmentId): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        $repository = new ShipmentRepository();
        $shipment = $repository->find((int)$shipmentId);
        if (!$shipment) {
            View::render('admin/message', ['title'=>'Nie oznaczono wysyłki', 'message'=>'Nie znaleziono przesyłki.', 'backUrl'=>'/shipments', 'user'=>AdminAuth::user()]);
            return;
        }
        $repository->markSent((int)$shipmentId);
        View::render('admin/message', [
            'title'=>'Oznaczono wysyłkę',
            'message'=>'Zamówienie oznaczone jako wysłane.',
            'backUrl'=>'/orders/' . (int)$shipment['order_id'],
            'user'=>AdminAuth::user(),
        ]);
    }


    public function subscribers(): void
    {
        AdminAuth::requireLogin();
        View::render('admin/subscribers/index', [
            'subscribers' => (new SubscriberRepository())->all(),
            'campaigns' => (new MailingRepository())->latest(),
            'user' => AdminAuth::user(),
        ]);
    }

    public function deleteSubscriber(string $id): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        (new SubscriberRepository())->deleteById((int)$id);
        Redirect::to('/subscribers?removed=1');
    }

    public function sendMailing(): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        $subject = trim((string)($_POST['subject'] ?? ''));
        $body = trim((string)($_POST['body'] ?? ''));
        if ($subject === '' || $body === '') {
            View::render('admin/message', ['title'=>'Mailing nieutworzony', 'message'=>'Temat i treść są wymagane.', 'user'=>AdminAuth::user()]);
            return;
        }
        $subscribers = (new SubscriberRepository())->activeEmails();
        $id = (new MailingRepository())->createCampaign($subject, $body, $subscribers);
        View::render('admin/message', ['title'=>'Mailing zapisany', 'message'=>'Kampania #'.$id.' została zapisana do kolejki email_logs dla '.count($subscribers).' odbiorców. Realna wysyłka SMTP to kolejny krok produkcyjny.', 'user'=>AdminAuth::user()]);
    }



    public function integrations(): void
    {
        AdminAuth::requireLogin();
        $report = (new \Book100\Services\Integrations\IntegrationHealthChecker())->check();
        View::render('admin/integrations/index', [
            'report' => $report,
            'integrations' => (new IntegrationSettingsService())->overview(),
            'inpost' => (new InPostClient())->configuration(),
            'user' => AdminAuth::user(),
        ]);
    }

    public function saveIntegrations(): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        try {
            $result = (new IntegrationSettingsService())->save($_POST);
            PublicCache::clear();
            View::render('admin/message', [
                'title' => 'Integracje zapisane',
                'message' => $result['message'],
                'backUrl' => '/integrations',
                'user' => AdminAuth::user(),
            ]);
        } catch (Throwable $e) {
            View::render('admin/message', [
                'title' => 'Nie zapisano integracji',
                'message' => $e->getMessage(),
                'backUrl' => '/integrations',
                'user' => AdminAuth::user(),
            ]);
        }
    }

    public function testInPostConnection(): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        $result = (new InPostClient())->testConnection();
        View::render('admin/message', [
            'title' => ($result['ok'] ?? false) ? 'InPost połączony' : 'InPost niepołączony',
            'message' => $result['message'] ?? 'Nie udało się sprawdzić połączenia.',
            'backUrl' => '/integrations',
            'user' => AdminAuth::user(),
        ]);
    }

    public function testMailIntegration(): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        $email = trim((string)($_POST['test_email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(422);
            View::render('admin/message', [
                'title' => 'Nie wysłano testu',
                'message' => 'Podaj poprawny adres odbiorcy wiadomości testowej.',
                'backUrl' => '/integrations',
                'user' => AdminAuth::user(),
            ]);
            return;
        }

        $id = (new EmailLogRepository())->queueTest($email);
        $report = (new Mailer())->processOne($id);
        $item = $report['items'][0] ?? [];
        $ok = ($item['status'] ?? '') === 'sent';
        if (!$ok) http_response_code(422);
        $mode = (string)($item['message'] ?? '');
        View::render('admin/message', [
            'title' => $ok ? 'Test poczty zakończony' : 'Test poczty nieudany',
            'message' => $ok
                ? ($mode === 'log'
                    ? 'Tryb kontrolny działa. Wiadomość została zapisana lokalnie jako plik EML; wybierz SMTP, aby wysyłać ją do odbiorców.'
                    : 'Serwer SMTP przyjął wiadomość testową. Sprawdź również folder odebrane i nagłówki SPF, DKIM oraz DMARC.')
                : (string)($item['message'] ?? 'Nie udało się przetworzyć wiadomości testowej.'),
            'backUrl' => '/integrations',
            'user' => AdminAuth::user(),
        ]);
    }

    public function generateDkimKey(): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        try {
            $result = (new IntegrationSettingsService())->generateDkimKey();
            View::render('admin/message', [
                'title' => 'Klucz DKIM wygenerowany',
                'message' => 'Klucz prywatny zapisano bezpiecznie. Dodaj w DNS rekord TXT '
                    . $result['host'] . ' o wartości: ' . $result['value'],
                'backUrl' => '/integrations#mail',
                'user' => AdminAuth::user(),
            ]);
        } catch (Throwable $exception) {
            http_response_code(422);
            View::render('admin/message', [
                'title' => 'Nie wygenerowano DKIM',
                'message' => $exception->getMessage(),
                'backUrl' => '/integrations#mail',
                'user' => AdminAuth::user(),
            ]);
        }
    }

    public function books(): void
    {
        AdminAuth::requireLogin();
        View::render('admin/books/index', ['books' => (new BookRepository())->all(), 'user' => AdminAuth::user()]);
    }

    public function authors(): void
    {
        AdminAuth::requireLogin();
        View::render('admin/authors/index', [
            'authors' => (new AuthorRepository())->all(),
            'user' => AdminAuth::user(),
        ]);
    }

    public function createAuthor(): void
    {
        AdminAuth::requireLogin();
        View::render('admin/authors/form', [
            'author' => $this->emptyAuthor(),
            'mode' => 'create',
            'user' => AdminAuth::user(),
        ]);
    }

    public function storeAuthor(): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        $data = $this->authorData();
        $errors = $this->validateAuthorData($data);
        $authors = new AuthorRepository();
        if (!$errors && $authors->findBySlug((string)$data['slug'])) {
            $errors[] = 'Autor o takim adresie już istnieje.';
        }
        if (!$errors && $authors->findByName((string)$data['name'])) {
            $errors[] = 'Autor o takim imieniu i nazwisku już istnieje.';
        }
        if (!$errors) {
            try {
                if (is_array($_FILES['author_photo_file'] ?? null)
                    && ($_FILES['author_photo_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $image = (new MediaLibraryService())->save($_FILES['author_photo_file']);
                    $data['photo'] = (string)$image['url'];
                }
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }
        if ($errors) {
            View::render('admin/authors/form', [
                'author' => $data,
                'errors' => $errors,
                'mode' => 'create',
                'user' => AdminAuth::user(),
            ]);
            return;
        }

        try {
            $id = $authors->create($data);
            PublicCache::clear();
            $editUrl = '/authors/' . $id . '/edit';
            if (!$this->isAjaxRequest()) Redirect::to($editUrl);
            View::render('admin/message', [
                'title' => 'Autor zapisany',
                'message' => 'Autor został dodany. Możesz od razu przypisać go do książki.',
                'backUrl' => $editUrl,
                'formAction' => '/authors/' . $id,
                'replaceUrl' => $editUrl,
                'pageTitle' => (string)$data['name'],
                'pageKicker' => 'EDYCJA AUTORA',
                'user' => AdminAuth::user(),
            ]);
        } catch (Throwable $e) {
            View::render('admin/authors/form', [
                'author' => $data,
                'errors' => [$e->getMessage()],
                'mode' => 'create',
                'user' => AdminAuth::user(),
            ]);
        }
    }

    public function editAuthor(string $id): void
    {
        AdminAuth::requireLogin();
        $author = (new AuthorRepository())->find((int)$id);
        if (!$author) { http_response_code(404); echo 'Nie znaleziono autora'; return; }
        View::render('admin/authors/form', [
            'author' => $author,
            'mode' => 'edit',
            'user' => AdminAuth::user(),
        ]);
    }

    public function updateAuthor(string $id): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        $repository = new AuthorRepository();
        $existing = $repository->find((int)$id);
        if (!$existing) { http_response_code(404); echo 'Nie znaleziono autora'; return; }
        $data = $this->authorData($existing);
        $errors = $this->validateAuthorData($data);
        $sameSlug = $repository->findBySlug((string)$data['slug']);
        if (!$errors && $sameSlug && (int)$sameSlug['id'] !== (int)$id) {
            $errors[] = 'Autor o takim adresie już istnieje.';
        }
        $sameName = $repository->findByName((string)$data['name']);
        if (!$errors && $sameName && (int)$sameName['id'] !== (int)$id) {
            $errors[] = 'Autor o takim imieniu i nazwisku już istnieje.';
        }
        if (!$errors) {
            try {
                if (!empty($_POST['remove_photo'])) {
                    $data['photo'] = '';
                }
                if (is_array($_FILES['author_photo_file'] ?? null)
                    && ($_FILES['author_photo_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $image = (new MediaLibraryService())->save($_FILES['author_photo_file']);
                    $data['photo'] = (string)$image['url'];
                }
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }
        if ($errors) {
            $data['id'] = (int)$id;
            View::render('admin/authors/form', [
                'author' => $data,
                'errors' => $errors,
                'mode' => 'edit',
                'user' => AdminAuth::user(),
            ]);
            return;
        }

        try {
            $repository->update((int)$id, $data);
            PublicCache::clear();
            $editUrl = '/authors/' . (int)$id . '/edit';
            if (!$this->isAjaxRequest()) Redirect::to($editUrl);
            View::render('admin/message', [
                'title' => 'Autor zapisany',
                'message' => 'Zdjęcie i notka zostały zaktualizowane we wszystkich przypisanych książkach.',
                'backUrl' => $editUrl,
                'formAction' => '/authors/' . (int)$id,
                'replaceUrl' => $editUrl,
                'pageTitle' => (string)$data['name'],
                'pageKicker' => 'EDYCJA AUTORA',
                'user' => AdminAuth::user(),
            ]);
        } catch (Throwable $e) {
            $data['id'] = (int)$id;
            View::render('admin/authors/form', [
                'author' => $data,
                'errors' => [$e->getMessage()],
                'mode' => 'edit',
                'user' => AdminAuth::user(),
            ]);
        }
    }

    public function archiveAuthor(string $id): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        (new AuthorRepository())->archive((int)$id);
        PublicCache::clear();
        View::render('admin/message', [
            'title' => 'Autor ukryty',
            'message' => 'Profil autora nie jest już wyświetlany przy książkach.',
            'backUrl' => '/authors/' . (int)$id . '/edit',
            'user' => AdminAuth::user(),
        ]);
    }

    public function media(): void
    {
        AdminAuth::requireLogin();
        $images = array_map(static function (array $image): array {
            $image['preview_url'] = \Book100\Core\AdminPresenter::publicAsset((string)($image['url'] ?? ''));
            return $image;
        }, (new MediaLibraryService())->all());
        View::render('admin/media/index', [
            'images' => $images,
            'user' => AdminAuth::user(),
        ]);
    }

    public function mediaLibrary(): void
    {
        AdminAuth::requireLogin();
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, max-age=0');
        $images = array_map(static function (array $image): array {
            $image['preview_url'] = \Book100\Core\AdminPresenter::publicAsset((string)($image['url'] ?? ''));
            return $image;
        }, (new MediaLibraryService())->all());
        echo json_encode([
            'ok' => true,
            'images' => $images,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function uploadMedia(): void
    {
        AdminAuth::requireLogin();
        Csrf::check();
        try {
            $file = $_FILES['media_image'] ?? $_FILES['description_image'] ?? [];
            $image = (new MediaLibraryService())->save(is_array($file) ? $file : []);
            $image['preview_url'] = \Book100\Core\AdminPresenter::publicAsset((string)($image['url'] ?? ''));
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'ok' => true,
                'title' => 'Grafika zapisana',
                'message' => 'Obraz został zoptymalizowany i dodany do biblioteki Media.',
                'media_url' => $image['url'],
                'image' => $image,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            http_response_code(422);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'ok' => false,
                'title' => 'Nie wgrano grafiki',
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }

    public function deleteMedia(): void
    {
        AdminAuth::requireLogin();
        Csrf::check();
        try {
            $service = new MediaLibraryService();
            $url = trim((string)($_POST['url'] ?? ''));
            $usages = $service->usages($url);
            if ($usages) {
                http_response_code(409);
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode([
                    'ok' => false,
                    'title' => 'Grafika jest używana',
                    'message' => implode(' · ', array_slice($usages, 0, 3)),
                    'usages' => $usages,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                return;
            }
            $service->delete($url);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'ok' => true,
                'title' => 'Grafika usunięta',
                'message' => 'Plik został usunięty z biblioteki Media.',
                'url' => $url,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            http_response_code(422);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'ok' => false,
                'title' => 'Nie usunięto grafiki',
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }

    public function removeAsset(): void
    {
        AdminAuth::requireLogin();
        Csrf::check();
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');

        try {
            $result = (new AdminAssetRemovalService())->remove(
                (string)($_POST['scope'] ?? ''),
                (string)($_POST['asset'] ?? ''),
                (int)($_POST['id'] ?? 0)
            );
            PublicCache::clear();
            echo json_encode([
                'ok' => true,
                'title' => 'Usunięto',
                'message' => $result['message'],
                'removed_url' => $result['removed_url'],
                'file_trashed' => $result['file_trashed'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'title' => 'Nie usunięto',
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }

    public function createBook(): void
    {
        AdminAuth::requireLogin();
        View::render('admin/books/form', [
            'book' => $this->emptyBook(),
            'authors' => (new AuthorRepository())->all(),
            'mode' => 'create',
            'user' => AdminAuth::user(),
        ]);
    }

    public function uploadBookDescriptionImage(): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        try {
            $slug = trim((string)($_POST['slug'] ?? ''));
            if ($slug === '') {
                $slug = BookRepository::slugify((string)($_POST['title'] ?? 'ksiazka'));
            }
            $url = (new BookAssetService())->saveDescriptionImage(
                is_array($_FILES['description_image'] ?? null) ? $_FILES['description_image'] : [],
                $slug
            );
            View::render('admin/message', [
                'title' => 'Grafika wgrana',
                'message' => 'Grafika została zoptymalizowana i dodana do opisu.',
                'mediaUrl' => $url,
                'user' => AdminAuth::user(),
            ]);
        } catch (Throwable $e) {
            View::render('admin/message', [
                'title' => 'Nie wgrano grafiki',
                'message' => $e->getMessage(),
                'user' => AdminAuth::user(),
            ]);
        }
    }

    public function uploadRichContentImage(): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        try {
            $scope = (string)($_POST['scope'] ?? 'books');
            if (!in_array($scope, ['books', 'pages', 'events'], true)) {
                throw new \RuntimeException('Nieprawidłowy typ treści.');
            }
            $title = trim((string)($_POST['title'] ?? ''));
            $slug = trim((string)($_POST['slug'] ?? '')) ?: BookRepository::slugify($title ?: 'strona');
            $url = (new RichContentMediaService())->saveInlineImage(
                is_array($_FILES['description_image'] ?? null) ? $_FILES['description_image'] : [],
                $scope,
                $slug
            );
            View::render('admin/message', [
                'title' => 'Grafika wgrana',
                'message' => 'Grafika została zoptymalizowana i połączona z treścią.',
                'mediaUrl' => $url,
                'user' => AdminAuth::user(),
            ]);
        } catch (Throwable $e) {
            View::render('admin/message', [
                'title' => 'Nie wgrano grafiki',
                'message' => $e->getMessage(),
                'user' => AdminAuth::user(),
            ]);
        }
    }

    public function storeBook(): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        $data = $this->bookData();
        $errors = $this->validateBookData($data);
        if (!$errors) {
            try {
                $assets = new BookAssetService();
                $data['cover_image'] = $assets->saveCover($_FILES['cover_file'] ?? [], $data['slug'], $data['cover_image']);
                $data['ebook_file_path'] = $assets->saveEbook($_FILES['ebook_file'] ?? [], $data['slug'], null);
                if (in_array($data['status'], ['active', 'preorder', 'announced'], true) && !$data['cover_image']) {
                    $errors[] = 'Publiczna książka musi mieć wgraną okładkę.';
                }
                if ($data['product_type'] === 'ebook' && in_array($data['status'], ['active', 'preorder'], true) && !$data['ebook_file_path']) {
                    $errors[] = 'E-book dostępny w sprzedaży musi mieć wgrany plik.';
                }
            } catch (Throwable $e) { $errors[] = $e->getMessage(); }
        }
        if ($errors) {
            View::render('admin/books/form', ['book'=>$data, 'authors'=>(new AuthorRepository())->all(), 'errors'=>$errors, 'mode'=>'create', 'user'=>AdminAuth::user()]);
            return;
        }
        try {
            $bookId = (new BookRepository())->create($data);
            PublicCache::clear();
            $editUrl = '/books/' . $bookId . '/edit';
            if (!$this->isAjaxRequest()) {
                Redirect::to($editUrl);
            }
            View::render('admin/message', [
                'title' => 'Książka zapisana',
                'message' => 'Nowa książka została utworzona. Możesz dalej pracować na tym formularzu.',
                'backUrl' => $editUrl,
                'formAction' => '/books/' . $bookId,
                'replaceUrl' => $editUrl,
                'pageTitle' => (string)$data['title'],
                'pageKicker' => 'EDYCJA KSIĄŻKI',
                'user' => AdminAuth::user(),
            ]);
        } catch (Throwable $e) {
            View::render('admin/books/form', ['book'=>$data, 'authors'=>(new AuthorRepository())->all(), 'errors'=>[$e->getMessage()], 'mode'=>'create', 'user'=>AdminAuth::user()]);
        }
    }

    public function editBook(string $id): void
    {
        AdminAuth::requireLogin();
        $book = (new BookRepository())->find((int)$id);
        if (!$book) { http_response_code(404); echo 'Nie znaleziono książki'; return; }
        View::render('admin/books/form', ['book' => $book, 'authors'=>(new AuthorRepository())->all(), 'mode' => 'edit', 'user' => AdminAuth::user()]);
    }

    public function updateBook(string $id): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        $repo = new BookRepository();
        $existing = $repo->find((int)$id);
        if (!$existing) { http_response_code(404); echo 'Nie znaleziono książki'; return; }
        $data = $this->bookData($existing);
        $errors = $this->validateBookData($data);
        if (!$errors) {
            try {
                $assets = new BookAssetService();
                $data['cover_image'] = $assets->saveCover($_FILES['cover_file'] ?? [], $data['slug'], $existing['cover_image'] ?? null);
                $data['ebook_file_path'] = !empty($_POST['remove_ebook']) ? null : $assets->saveEbook($_FILES['ebook_file'] ?? [], $data['slug'], $existing['ebook_file_path'] ?? null);
                if (in_array($data['status'], ['active', 'preorder', 'announced'], true) && !$data['cover_image']) {
                    $errors[] = 'Publiczna książka musi mieć wgraną okładkę.';
                }
                if ($data['product_type'] === 'ebook' && in_array($data['status'], ['active', 'preorder'], true) && !$data['ebook_file_path']) {
                    $errors[] = 'E-book dostępny w sprzedaży musi mieć wgrany plik.';
                }
            } catch (Throwable $e) { $errors[] = $e->getMessage(); }
        }
        if ($errors) {
            $data['id'] = (int)$id;
            View::render('admin/books/form', ['book'=>$data, 'authors'=>(new AuthorRepository())->all(), 'errors'=>$errors, 'mode'=>'edit', 'user'=>AdminAuth::user()]);
            return;
        }
        try {
            $repo->update((int)$id, $data);
            PublicCache::clear();
            $editUrl = '/books/' . (int)$id . '/edit';
            if (!$this->isAjaxRequest()) {
                Redirect::to($editUrl);
            }
            View::render('admin/message', [
                'title' => 'Książka zapisana',
                'message' => 'Zmiany zostały zapisane. Pozostajesz na formularzu książki.',
                'backUrl' => $editUrl,
                'formAction' => '/books/' . (int)$id,
                'replaceUrl' => $editUrl,
                'pageTitle' => (string)$data['title'],
                'pageKicker' => 'EDYCJA KSIĄŻKI',
                'user' => AdminAuth::user(),
            ]);
        } catch (Throwable $e) {
            $data['id'] = (int)$id;
            View::render('admin/books/form', ['book'=>$data, 'authors'=>(new AuthorRepository())->all(), 'errors'=>[$e->getMessage()], 'mode'=>'edit', 'user'=>AdminAuth::user()]);
        }
    }

    public function deleteBook(string $id): void
    {
        AdminAuth::requireLogin();
        Csrf::check();
        $bookId = (int)$id;
        $repo = new BookRepository();
        $book = $repo->find($bookId);
        if (!$book) {
            if ($this->isAjaxRequest()) {
                http_response_code(404);
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode([
                    'ok' => false,
                    'title' => 'Nie usunięto książki',
                    'message' => 'Książka już nie istnieje.',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                return;
            }
            Redirect::to('/books');
        }

        $result = $repo->deletePermanentlyWithSalesHistory($bookId);
        PublicCache::clear();
        if ($this->isAjaxRequest()) {
            $ordersDeleted = (int)($result['orders_deleted'] ?? 0);
            $historyMessage = $ordersDeleted === 0
                ? 'Książka została trwale usunięta.'
                : ($ordersDeleted === 1
                    ? 'Książka i jedno powiązane zamówienie zostały trwale usunięte.'
                    : 'Książka i powiązane zamówienia (' . $ordersDeleted . ') zostały trwale usunięte.');
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'ok' => true,
                'title' => 'Książka usunięta na stałe',
                'message' => $historyMessage,
                'result' => 'deleted',
                'book_id' => $bookId,
                'orders_deleted' => $ordersDeleted,
                'redirect' => \Book100\Core\AdminUrl::route('/books'),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }
        Redirect::to('/books');
    }

    public function pages(): void
    {
        AdminAuth::requireLogin();
        View::render('admin/pages/index', [
            'pages' => (new ContentPageRepository())->all(),
            'user' => AdminAuth::user(),
        ]);
    }

    public function createPage(): void
    {
        AdminAuth::requireLogin();
        View::render('admin/pages/form', [
            'page' => $this->emptyPage(),
            'authors' => (new AuthorRepository())->all(),
            'forms' => (new RegistrationFormRepository())->active(),
            'mode' => 'create',
            'user' => AdminAuth::user(),
        ]);
    }

    public function storePage(): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        $data = $this->pageData();
        $errors = $this->validatePageData($data);
        if (!$errors) {
            try {
                $data['featured_image'] = (new RichContentMediaService())->savePageFeaturedImage(
                    is_array($_FILES['featured_image_file'] ?? null) ? $_FILES['featured_image_file'] : [],
                    $data['slug'],
                    null
                );
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }
        if ($errors) {
            View::render('admin/pages/form', ['page'=>$data, 'authors'=>(new AuthorRepository())->all(), 'forms'=>(new RegistrationFormRepository())->active(), 'errors'=>$errors, 'mode'=>'create', 'user'=>AdminAuth::user()]);
            return;
        }
        try {
            $id = (new ContentPageRepository())->create($data);
            PublicCache::clear();
            $editUrl = '/pages/' . $id . '/edit';
            if (!$this->isAjaxRequest()) Redirect::to($editUrl);
            View::render('admin/message', [
                'title'=>'Strona zapisana',
                'message'=>'Nowa strona została utworzona. Możesz dalej ją edytować.',
                'backUrl'=>$editUrl,
                'formAction'=>'/pages/' . $id,
                'replaceUrl'=>$editUrl,
                'pageTitle'=>(string)$data['title'],
                'pageKicker'=>'EDYCJA STRONY',
                'user'=>AdminAuth::user(),
            ]);
        } catch (Throwable $e) {
            View::render('admin/pages/form', ['page'=>$data, 'authors'=>(new AuthorRepository())->all(), 'forms'=>(new RegistrationFormRepository())->active(), 'errors'=>[$e->getMessage()], 'mode'=>'create', 'user'=>AdminAuth::user()]);
        }
    }

    public function editPage(string $id): void
    {
        AdminAuth::requireLogin();
        $page = (new ContentPageRepository())->find((int)$id);
        if (!$page) { http_response_code(404); echo 'Nie znaleziono strony'; return; }
        View::render('admin/pages/form', ['page'=>$page, 'authors'=>(new AuthorRepository())->all(), 'forms'=>(new RegistrationFormRepository())->active(), 'mode'=>'edit', 'user'=>AdminAuth::user()]);
    }

    public function updatePage(string $id): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        $repository = new ContentPageRepository();
        $existing = $repository->find((int)$id);
        if (!$existing) { http_response_code(404); echo 'Nie znaleziono strony'; return; }
        $data = $this->pageData($existing);
        $errors = $this->validatePageData($data);
        if (!$errors) {
            try {
                $currentImage = !empty($_POST['remove_featured_image']) ? null : ($existing['featured_image'] ?? null);
                $data['featured_image'] = (new RichContentMediaService())->savePageFeaturedImage(
                    is_array($_FILES['featured_image_file'] ?? null) ? $_FILES['featured_image_file'] : [],
                    $data['slug'],
                    $currentImage
                );
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }
        if ($errors) {
            $data['id'] = (int)$id;
            View::render('admin/pages/form', ['page'=>$data, 'authors'=>(new AuthorRepository())->all(), 'forms'=>(new RegistrationFormRepository())->active(), 'errors'=>$errors, 'mode'=>'edit', 'user'=>AdminAuth::user()]);
            return;
        }
        try {
            $repository->update((int)$id, $data);
            PublicCache::clear();
            $editUrl = '/pages/' . (int)$id . '/edit';
            if (!$this->isAjaxRequest()) Redirect::to($editUrl);
            View::render('admin/message', [
                'title'=>'Strona zapisana',
                'message'=>'Zmiany zostały zapisane. Pozostajesz na formularzu strony.',
                'backUrl'=>$editUrl,
                'formAction'=>'/pages/' . (int)$id,
                'replaceUrl'=>$editUrl,
                'pageTitle'=>(string)$data['title'],
                'pageKicker'=>'EDYCJA STRONY',
                'user'=>AdminAuth::user(),
            ]);
        } catch (Throwable $e) {
            $data['id'] = (int)$id;
            View::render('admin/pages/form', ['page'=>$data, 'authors'=>(new AuthorRepository())->all(), 'forms'=>(new RegistrationFormRepository())->active(), 'errors'=>[$e->getMessage()], 'mode'=>'edit', 'user'=>AdminAuth::user()]);
        }
    }

    public function archivePage(string $id): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        (new ContentPageRepository())->archive((int)$id);
        PublicCache::clear();
        View::render('admin/message', [
            'title'=>'Strona ukryta',
            'message'=>'Strona nie jest już widoczna publicznie.',
            'backUrl'=>'/pages/' . (int)$id . '/edit',
            'user'=>AdminAuth::user(),
        ]);
    }

    public function deletePage(string $id): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        $pageId = (int)$id;
        $repository = new ContentPageRepository();
        if (!$repository->find($pageId)) {
            if ($this->isAjaxRequest()) {
                http_response_code(404);
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode([
                    'ok' => false,
                    'title' => 'Nie usunięto strony',
                    'message' => 'Strona już nie istnieje.',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                return;
            }
            Redirect::to('/pages');
        }

        $result = $repository->delete($pageId);
        PublicCache::clear();
        if ($this->isAjaxRequest()) {
            $registrations = (int)($result['registrations_detached'] ?? 0);
            $message = $registrations > 0
                ? 'Strona została trwale usunięta. Powiązane zgłoszenia (' . $registrations . ') zachowano w formularzu.'
                : 'Strona została trwale usunięta.';
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'ok' => true,
                'title' => 'Strona usunięta',
                'message' => $message,
                'result' => 'deleted',
                'page_id' => $pageId,
                'redirect' => \Book100\Core\AdminUrl::route('/pages'),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }
        Redirect::to('/pages');
    }

    public function forms(): void
    {
        AdminAuth::requireLogin();
        View::render('admin/forms/index', [
            'forms' => (new RegistrationFormRepository())->all(),
            'user' => AdminAuth::user(),
        ]);
    }

    public function createRegistrationForm(): void
    {
        AdminAuth::requireLogin();
        View::render('admin/forms/form', [
            'form' => $this->emptyRegistrationForm(),
            'registrations' => [],
            'mode' => 'create',
            'user' => AdminAuth::user(),
        ]);
    }

    public function storeRegistrationForm(): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        $data = $this->registrationFormData();
        $errors = $this->validateRegistrationFormData($data);
        if ($errors) {
            View::render('admin/forms/form', [
                'form' => $data,
                'registrations' => [],
                'errors' => $errors,
                'mode' => 'create',
                'user' => AdminAuth::user(),
            ]);
            return;
        }

        try {
            $id = (new RegistrationFormRepository())->create($data);
            PublicCache::clear();
            $editUrl = '/forms/' . $id . '/edit';
            if (!$this->isAjaxRequest()) Redirect::to($editUrl);
            View::render('admin/message', [
                'title' => 'Formularz zapisany',
                'message' => 'Formularz został utworzony. Możesz przypisać go do strony lub wydarzenia.',
                'backUrl' => $editUrl,
                'formAction' => '/forms/' . $id,
                'replaceUrl' => $editUrl,
                'pageTitle' => (string)$data['name'],
                'pageKicker' => 'EDYCJA FORMULARZA',
                'user' => AdminAuth::user(),
            ]);
        } catch (Throwable $e) {
            View::render('admin/forms/form', [
                'form' => $data,
                'registrations' => [],
                'errors' => [$e->getMessage()],
                'mode' => 'create',
                'user' => AdminAuth::user(),
            ]);
        }
    }

    public function editRegistrationForm(string $id): void
    {
        AdminAuth::requireLogin();
        $form = (new RegistrationFormRepository())->find((int)$id);
        if (!$form) {
            http_response_code(404);
            echo 'Nie znaleziono formularza';
            return;
        }
        View::render('admin/forms/form', [
            'form' => $form,
            'registrations' => (new RegistrationRepository())->forForm((int)$id),
            'mode' => 'edit',
            'user' => AdminAuth::user(),
        ]);
    }

    public function updateRegistrationForm(string $id): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        $repository = new RegistrationFormRepository();
        $existing = $repository->find((int)$id);
        if (!$existing) {
            http_response_code(404);
            echo 'Nie znaleziono formularza';
            return;
        }
        $data = $this->registrationFormData();
        $data['id'] = (int)$id;
        $errors = $this->validateRegistrationFormData($data);
        if ($errors) {
            View::render('admin/forms/form', [
                'form' => $data,
                'registrations' => (new RegistrationRepository())->forForm((int)$id),
                'errors' => $errors,
                'mode' => 'edit',
                'user' => AdminAuth::user(),
            ]);
            return;
        }
        try {
            $repository->update((int)$id, $data);
            PublicCache::clear();
            $editUrl = '/forms/' . (int)$id . '/edit';
            if (!$this->isAjaxRequest()) Redirect::to($editUrl);
            View::render('admin/message', [
                'title' => 'Formularz zapisany',
                'message' => 'Zmiany zostały zapisane.',
                'backUrl' => $editUrl,
                'user' => AdminAuth::user(),
            ]);
        } catch (Throwable $e) {
            View::render('admin/forms/form', [
                'form' => $data,
                'registrations' => (new RegistrationRepository())->forForm((int)$id),
                'errors' => [$e->getMessage()],
                'mode' => 'edit',
                'user' => AdminAuth::user(),
            ]);
        }
    }

    public function archiveRegistrationForm(string $id): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        (new RegistrationFormRepository())->archive((int)$id);
        PublicCache::clear();
        View::render('admin/message', [
            'title' => 'Formularz ukryty',
            'message' => 'Formularz nie przyjmuje nowych zgłoszeń.',
            'backUrl' => '/forms/' . (int)$id . '/edit',
            'user' => AdminAuth::user(),
        ]);
    }

    public function events(): void
    {
        AdminAuth::requireLogin();
        View::render('admin/events/index', [
            'events' => (new EventRepository())->all(),
            'user' => AdminAuth::user(),
        ]);
    }

    public function createEvent(): void
    {
        AdminAuth::requireLogin();
        View::render('admin/events/form', [
            'event' => $this->emptyEvent(),
            'authors' => (new AuthorRepository())->all(),
            'forms' => (new RegistrationFormRepository())->active(),
            'registrations' => [],
            'mode' => 'create',
            'user' => AdminAuth::user(),
        ]);
    }

    public function storeEvent(): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        $data = $this->eventData();
        $errors = $this->validateEventData($data);
        if (!$errors) {
            try {
                $data['featured_image'] = (new RichContentMediaService())->saveEventFeaturedImage(
                    is_array($_FILES['featured_image_file'] ?? null) ? $_FILES['featured_image_file'] : [],
                    $data['slug'],
                    null
                );
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }
        if ($errors) {
            $this->renderEventForm($data, 'create', $errors);
            return;
        }

        try {
            $id = (new EventRepository())->create($data);
            PublicCache::clear();
            $editUrl = '/events/' . $id . '/edit';
            if (!$this->isAjaxRequest()) Redirect::to($editUrl);
            View::render('admin/message', [
                'title' => 'Wydarzenie zapisane',
                'message' => 'Anons wydarzenia został utworzony.',
                'backUrl' => $editUrl,
                'formAction' => '/events/' . $id,
                'replaceUrl' => $editUrl,
                'pageTitle' => (string)$data['title'],
                'pageKicker' => 'EDYCJA WYDARZENIA',
                'user' => AdminAuth::user(),
            ]);
        } catch (Throwable $e) {
            $this->renderEventForm($data, 'create', [$e->getMessage()]);
        }
    }

    public function editEvent(string $id): void
    {
        AdminAuth::requireLogin();
        $event = (new EventRepository())->find((int)$id);
        if (!$event) {
            http_response_code(404);
            echo 'Nie znaleziono wydarzenia';
            return;
        }
        $this->renderEventForm($event, 'edit');
    }

    public function updateEvent(string $id): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        $repository = new EventRepository();
        $existing = $repository->find((int)$id);
        if (!$existing) {
            http_response_code(404);
            echo 'Nie znaleziono wydarzenia';
            return;
        }
        $data = $this->eventData($existing);
        $errors = $this->validateEventData($data);
        if (!$errors) {
            try {
                $currentImage = !empty($_POST['remove_featured_image']) ? null : ($existing['featured_image'] ?? null);
                $data['featured_image'] = (new RichContentMediaService())->saveEventFeaturedImage(
                    is_array($_FILES['featured_image_file'] ?? null) ? $_FILES['featured_image_file'] : [],
                    $data['slug'],
                    $currentImage
                );
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }
        $data['id'] = (int)$id;
        if ($errors) {
            $this->renderEventForm($data, 'edit', $errors);
            return;
        }

        try {
            $repository->update((int)$id, $data);
            PublicCache::clear();
            $editUrl = '/events/' . (int)$id . '/edit';
            if (!$this->isAjaxRequest()) Redirect::to($editUrl);
            View::render('admin/message', [
                'title' => 'Wydarzenie zapisane',
                'message' => 'Zmiany zostały zapisane.',
                'backUrl' => $editUrl,
                'user' => AdminAuth::user(),
            ]);
        } catch (Throwable $e) {
            $this->renderEventForm($data, 'edit', [$e->getMessage()]);
        }
    }

    public function archiveEvent(string $id): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        (new EventRepository())->archive((int)$id);
        PublicCache::clear();
        View::render('admin/message', [
            'title' => 'Wydarzenie w archiwum',
            'message' => 'Wydarzenie zostało przeniesione do archiwum, a formularz zapisów wyłączony.',
            'backUrl' => '/events/' . (int)$id . '/edit',
            'user' => AdminAuth::user(),
        ]);
    }

    public function deleteEvent(string $id): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        $eventId = (int)$id;
        $repository = new EventRepository();
        if (!$repository->find($eventId)) {
            if ($this->isAjaxRequest()) {
                http_response_code(404);
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode([
                    'ok' => false,
                    'title' => 'Nie usunięto wydarzenia',
                    'message' => 'Wydarzenie już nie istnieje.',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                return;
            }
            Redirect::to('/events');
        }

        $result = $repository->delete($eventId);
        PublicCache::clear();
        if ($this->isAjaxRequest()) {
            $registrations = (int)($result['registrations_detached'] ?? 0);
            $message = $registrations > 0
                ? 'Wydarzenie zostało trwale usunięte. Zgłoszenia uczestników (' . $registrations . ') zachowano w formularzu.'
                : 'Wydarzenie zostało trwale usunięte.';
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'ok' => true,
                'title' => 'Wydarzenie usunięte',
                'message' => $message,
                'result' => 'deleted',
                'event_id' => $eventId,
                'redirect' => \Book100\Core\AdminUrl::route('/events'),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }
        Redirect::to('/events');
    }

    public function addEventRegistration(string $id): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        $event = (new EventRepository())->find((int)$id);
        if (!$event || empty($event['registration_form_id'])) {
            View::render('admin/message', [
                'title' => 'Nie dodano osoby',
                'message' => 'Najpierw przypisz formularz do wydarzenia.',
                'backUrl' => '/events/' . (int)$id . '/edit',
                'user' => AdminAuth::user(),
            ]);
            return;
        }
        $firstName = trim((string)($_POST['first_name'] ?? ''));
        $lastName = trim((string)($_POST['last_name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        if ($firstName === '' && $lastName === '' && $email === '' && $phone === '') {
            View::render('admin/message', [
                'title' => 'Nie dodano osoby',
                'message' => 'Podaj przynajmniej jedną daną kontaktową.',
                'backUrl' => '/events/' . (int)$id . '/edit',
                'user' => AdminAuth::user(),
            ]);
            return;
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            View::render('admin/message', [
                'title' => 'Nie dodano osoby',
                'message' => 'Adres e-mail jest nieprawidłowy.',
                'backUrl' => '/events/' . (int)$id . '/edit',
                'user' => AdminAuth::user(),
            ]);
            return;
        }
        (new RegistrationRepository())->create([
            'form_id' => (int)$event['registration_form_id'],
            'event_id' => (int)$id,
            'source_label' => (string)$event['title'],
            'person_name' => trim($firstName . ' ' . $lastName),
            'email' => $email,
            'phone' => $phone,
            'values' => [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
            ],
            'status' => 'confirmed',
            'admin_note' => trim((string)($_POST['admin_note'] ?? '')),
            'consent_at' => null,
        ]);
        View::render('admin/message', [
            'title' => 'Osoba dodana',
            'message' => 'Uczestnik został dopisany do wydarzenia.',
            'backUrl' => '/events/' . (int)$id . '/edit',
            'user' => AdminAuth::user(),
        ]);
    }

    public function updateRegistration(string $id): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        $repository = new RegistrationRepository();
        $registration = $repository->find((int)$id);
        if (!$registration) {
            http_response_code(404);
            echo 'Nie znaleziono zgłoszenia';
            return;
        }
        $repository->update(
            (int)$id,
            (string)($_POST['status'] ?? 'new'),
            (string)($_POST['admin_note'] ?? '')
        );
        $backUrl = !empty($registration['event_id'])
            ? '/events/' . (int)$registration['event_id'] . '/edit'
            : '/forms/' . (int)$registration['form_id'] . '/edit';
        View::render('admin/message', [
            'title' => 'Zgłoszenie zapisane',
            'message' => 'Status i notatka zostały zaktualizowane.',
            'backUrl' => $backUrl,
            'user' => AdminAuth::user(),
        ]);
    }

    public function clearCache(): void
    {
        AdminAuth::requireLogin(); Csrf::check();
        $count = PublicCache::clear();
        View::render('admin/message', ['title' => 'Cache wyczyszczony', 'message' => "Usunięto plików cache: $count", 'user' => AdminAuth::user()]);
    }

    private function registrationFormData(): array
    {
        $definitions = [
            'first_name' => ['type' => 'text', 'label' => 'Imię'],
            'last_name' => ['type' => 'text', 'label' => 'Nazwisko'],
            'email' => ['type' => 'email', 'label' => 'E-mail'],
            'phone' => ['type' => 'tel', 'label' => 'Telefon'],
        ];
        $postedFields = is_array($_POST['fields'] ?? null) ? $_POST['fields'] : [];
        $fields = [];
        foreach ($definitions as $key => $definition) {
            $posted = is_array($postedFields[$key] ?? null) ? $postedFields[$key] : [];
            $fields[] = [
                'key' => $key,
                'type' => $definition['type'],
                'label' => mb_substr(trim((string)($posted['label'] ?? $definition['label'])), 0, 100),
                'enabled' => !empty($posted['enabled']),
                'required' => !empty($posted['enabled']) && !empty($posted['required']),
            ];
        }
        return [
            'name' => trim((string)($_POST['name'] ?? '')),
            'recipient_email' => trim((string)($_POST['recipient_email'] ?? '')),
            'email_subject' => trim((string)($_POST['email_subject'] ?? '')),
            'intro_text' => trim((string)($_POST['intro_text'] ?? '')),
            'submit_label' => trim((string)($_POST['submit_label'] ?? '')),
            'success_message' => trim((string)($_POST['success_message'] ?? '')),
            'fields_json' => json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]',
            'status' => (string)($_POST['status'] ?? 'active'),
        ];
    }

    private function validateRegistrationFormData(array $data): array
    {
        $errors = [];
        if ($data['name'] === '') $errors[] = 'Nazwa formularza jest wymagana.';
        if (mb_strlen((string)$data['name']) > 190) $errors[] = 'Nazwa formularza jest za długa.';
        if (!filter_var((string)$data['recipient_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Podaj prawidłowy adres odbiorcy zgłoszeń.';
        }
        if (!in_array((string)$data['status'], ['active', 'hidden'], true)) {
            $errors[] = 'Nieprawidłowy status formularza.';
        }
        $fields = RegistrationFormRepository::fields($data);
        $enabled = array_values(array_filter($fields, static fn(array $field): bool => !empty($field['enabled'])));
        if (!$enabled) $errors[] = 'Włącz przynajmniej jedno pole formularza.';
        foreach ($enabled as $field) {
            if (trim((string)($field['label'] ?? '')) === '') {
                $errors[] = 'Każde widoczne pole musi mieć nazwę.';
                break;
            }
        }
        $contactEnabled = array_filter(
            $enabled,
            static fn(array $field): bool => in_array(($field['key'] ?? ''), ['email', 'phone'], true)
        );
        if (!$contactEnabled) $errors[] = 'Włącz pole E-mail lub Telefon, aby można było odpowiedzieć na zgłoszenie.';
        return $errors;
    }

    private function eventData(array $existing = []): array
    {
        $title = trim((string)($_POST['title'] ?? ''));
        $slug = trim((string)($_POST['slug'] ?? '')) ?: BookRepository::slugify($title);
        $authorId = max(0, (int)($_POST['author_id'] ?? ($existing['author_id'] ?? 0)));
        $selectedAuthor = $authorId > 0 ? (new AuthorRepository())->find($authorId) : null;
        $formId = max(0, (int)($_POST['registration_form_id'] ?? ($existing['registration_form_id'] ?? 0)));
        $selectedForm = $formId > 0 ? (new RegistrationFormRepository())->find($formId) : null;
        return [
            'slug' => $slug,
            'title' => $title,
            'author_id' => $selectedAuthor ? (int)$selectedAuthor['id'] : null,
            'excerpt' => trim((string)($_POST['excerpt'] ?? '')),
            'content' => ContentFormatter::richHtml((string)($_POST['content'] ?? '')),
            'starts_at' => $this->normalizeDateTime((string)($_POST['starts_at'] ?? '')),
            'ends_at' => $this->normalizeDateTime((string)($_POST['ends_at'] ?? '')),
            'location' => trim((string)($_POST['location'] ?? '')),
            'organizer' => trim((string)($_POST['organizer'] ?? '')),
            'featured_image' => $existing['featured_image'] ?? null,
            'registration_form_id' => $selectedForm && ($selectedForm['status'] ?? '') === 'active'
                ? (int)$selectedForm['id']
                : null,
            'status' => (string)($_POST['status'] ?? 'draft'),
            'seo_title' => trim((string)($_POST['seo_title'] ?? ($existing['seo_title'] ?? ''))),
            'seo_description' => trim((string)($_POST['seo_description'] ?? ($existing['seo_description'] ?? ''))),
        ];
    }

    private function validateEventData(array $data): array
    {
        $errors = [];
        if (empty($data['author_id'])) $errors[] = 'Wybierz autora wydarzenia.';
        if (trim((string)$data['title']) === '') $errors[] = 'Nazwa wydarzenia jest wymagana.';
        if (mb_strlen((string)$data['title']) > 255) $errors[] = 'Nazwa wydarzenia jest za długa.';
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string)$data['slug'])
            || strlen((string)$data['slug']) > 190) {
            $errors[] = 'Adres wydarzenia może zawierać tylko małe litery, cyfry i myślniki.';
        }
        if (trim((string)$data['excerpt']) === '') $errors[] = 'Krótki opis wydarzenia jest wymagany.';
        if (mb_strlen((string)$data['excerpt']) > 900) $errors[] = 'Krótki opis może mieć maksymalnie 900 znaków.';
        if ($data['starts_at'] === '') {
            $errors[] = 'Podaj termin rozpoczęcia wydarzenia.';
        } elseif (!$this->isStoredDateTime((string)$data['starts_at'])) {
            $errors[] = 'Termin rozpoczęcia jest nieprawidłowy.';
        }
        if ($data['ends_at'] !== '' && !$this->isStoredDateTime((string)$data['ends_at'])) {
            $errors[] = 'Termin zakończenia jest nieprawidłowy.';
        } elseif ($data['ends_at'] !== '' && $data['starts_at'] !== '' && $data['ends_at'] < $data['starts_at']) {
            $errors[] = 'Zakończenie nie może być wcześniejsze niż rozpoczęcie.';
        }
        if (!in_array((string)$data['status'], ['draft', 'published', 'archived'], true)) {
            $errors[] = 'Nieprawidłowy status wydarzenia.';
        }
        return $errors;
    }

    private function renderEventForm(array $event, string $mode, array $errors = []): void
    {
        View::render('admin/events/form', [
            'event' => $event,
            'authors' => (new AuthorRepository())->all(),
            'forms' => (new RegistrationFormRepository())->active(),
            'registrations' => $mode === 'edit' && !empty($event['id'])
                ? (new RegistrationRepository())->forEvent((int)$event['id'])
                : [],
            'errors' => $errors,
            'mode' => $mode,
            'user' => AdminAuth::user(),
        ]);
    }

    private function normalizeDateTime(string $value): string
    {
        $value = trim($value);
        if ($value === '') return '';
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);
        return $date && $date->format('Y-m-d\TH:i') === $value ? $date->format('Y-m-d H:i:s') : $value;
    }

    private function isStoredDateTime(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
        return $date !== false && $date->format('Y-m-d H:i:s') === $value;
    }

    private function bookData(array $existing = []): array
    {
        $title = trim((string)($_POST['title'] ?? ''));
        $slug = trim((string)($_POST['slug'] ?? '')) ?: BookRepository::slugify($title);
        $authorId = max(0, (int)($_POST['author_id'] ?? ($existing['author_id'] ?? 0)));
        $selectedAuthor = $authorId > 0 ? (new AuthorRepository())->find($authorId) : null;
        return [
            'old_wp_id' => $_POST['old_wp_id'] ?? null,
            'sku' => $_POST['sku'] ?? '',
            'slug' => $slug,
            'title' => $title,
            'author_id' => $selectedAuthor ? (int)$selectedAuthor['id'] : null,
            'author' => $selectedAuthor['name'] ?? ($existing['author'] ?? ''),
            'short_description' => $_POST['short_description'] ?? '',
            'description' => ContentFormatter::richHtml((string)($_POST['description'] ?? '')),
            'price_gross' => $_POST['price_gross'] ?? 0,
            'currency' => (new SettingsRepository())->get('currency', 'PLN'),
            'product_type' => $_POST['product_type'] ?? 'paper',
            'status' => $_POST['status'] ?? 'draft',
            'release_date' => $_POST['release_date'] ?? ($existing['release_date'] ?? ''),
            'stock_qty' => $_POST['stock_qty'] ?? 0,
            'manage_stock' => isset($_POST['manage_stock']) ? 1 : 0,
            'weight_kg' => $_POST['weight_kg'] ?? '',
            'length_cm' => $_POST['length_cm'] ?? '',
            'width_cm' => $_POST['width_cm'] ?? '',
            'height_cm' => $_POST['height_cm'] ?? '',
            'isbn' => $_POST['isbn'] ?? '',
            'publisher' => $_POST['publisher'] ?? '',
            'publication_year' => $_POST['publication_year'] ?? '',
            'pages' => $_POST['pages'] ?? '',
            'format' => $_POST['format'] ?? '',
            'attributes_json' => $this->attributesJson((string)($_POST['attribute_lines'] ?? '')),
            'cover_image' => $existing['cover_image'] ?? ($_POST['cover_image'] ?? ''),
            'ebook_file_path' => $existing['ebook_file_path'] ?? '',
            'seo_title' => $_POST['seo_title'] ?? '',
            'seo_description' => $_POST['seo_description'] ?? '',
            'seo_keywords' => $_POST['seo_keywords'] ?? '',
            'canonical_url' => $_POST['canonical_url'] ?? '',
        ];
    }

    private function pageData(array $existing = []): array
    {
        $title = trim((string)($_POST['title'] ?? ''));
        $slug = trim((string)($_POST['slug'] ?? '')) ?: BookRepository::slugify($title);
        $authorId = max(0, (int)($_POST['author_id'] ?? ($existing['author_id'] ?? 0)));
        $selectedAuthor = $authorId > 0 ? (new AuthorRepository())->find($authorId) : null;
        $formId = max(0, (int)($_POST['registration_form_id'] ?? ($existing['registration_form_id'] ?? 0)));
        $selectedForm = $formId > 0 ? (new RegistrationFormRepository())->find($formId) : null;
        return [
            'old_wp_id' => $_POST['old_wp_id'] ?? ($existing['old_wp_id'] ?? null),
            'slug' => $slug,
            'title' => $title,
            'author_id' => $selectedAuthor ? (int)$selectedAuthor['id'] : null,
            'registration_form_id' => $selectedForm && ($selectedForm['status'] ?? '') === 'active'
                ? (int)$selectedForm['id']
                : null,
            'excerpt' => trim((string)($_POST['excerpt'] ?? '')),
            'content' => ContentFormatter::richHtml((string)($_POST['content'] ?? '')),
            'status' => (string)($_POST['status'] ?? 'draft'),
            'featured_image' => $existing['featured_image'] ?? null,
            'seo_title' => trim((string)($_POST['seo_title'] ?? '')),
            'seo_description' => trim((string)($_POST['seo_description'] ?? '')),
            'canonical_url' => trim((string)($_POST['canonical_url'] ?? '')),
        ];
    }

    private function validatePageData(array $data): array
    {
        $errors = [];
        if (empty($data['author_id'])) $errors[] = 'Wybierz autora strony.';
        if (trim((string)$data['title']) === '') $errors[] = 'Tytuł jest wymagany.';
        if (mb_strlen((string)$data['title']) > 255) $errors[] = 'Tytuł jest za długi.';
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string)$data['slug']) || strlen((string)$data['slug']) > 190) {
            $errors[] = 'Slug może zawierać tylko małe litery, cyfry i myślniki.';
        }
        if (!in_array((string)$data['status'], ['draft', 'published', 'hidden'], true)) {
            $errors[] = 'Nieprawidłowy status strony.';
        }
        $reserved = ['ksiazka','kup','platnosc','dziekujemy','ebook','newsletter','api','sitemap.xml','robots.txt','kontakt','regulamin','polityka-prywatnosci'];
        if (in_array((string)$data['slug'], $reserved, true)) {
            $errors[] = 'Ten adres jest zarezerwowany przez sklep.';
        }
        $canonical = trim((string)$data['canonical_url']);
        if ($canonical !== '' && (!filter_var($canonical, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $canonical))) {
            $errors[] = 'Adres kanoniczny musi być pełnym adresem http:// lub https://.';
        }
        return $errors;
    }

    private function validateBookData(array $data): array
    {
        $errors = [];
        if (trim((string)$data['title']) === '') $errors[] = 'Tytuł jest wymagany.';
        if (mb_strlen((string)$data['title']) > 255) $errors[] = 'Tytuł jest za długi.';
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string)$data['slug']) || strlen((string)$data['slug']) > 190) {
            $errors[] = 'Slug może zawierać tylko małe litery, cyfry i myślniki.';
        }
        $price = str_replace(',', '.', trim((string)$data['price_gross']));
        if ($price === '' || !is_numeric($price) || (float)$price < 0) $errors[] = 'Cena musi być poprawną liczbą i nie może być ujemna.';
        $canonical = trim((string)($data['canonical_url'] ?? ''));
        if ($canonical !== '' && (!filter_var($canonical, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $canonical))) {
            $errors[] = 'Adres kanoniczny musi być pełnym adresem http:// lub https://.';
        }
        if (mb_strlen((string)($data['seo_keywords'] ?? '')) > 1000) {
            $errors[] = 'Lista słów kluczowych jest za długa.';
        }
        if (empty($data['author_id'])) {
            $errors[] = 'Wybierz autora z kartoteki Autorzy.';
        }
        if (in_array($data['status'], ['active', 'preorder'], true) && (float)$price <= 0) {
            $errors[] = 'Książka dostępna w sprzedaży musi mieć cenę większą od zera.';
        }
        $stock = filter_var($data['stock_qty'], FILTER_VALIDATE_INT);
        if ($stock === false || $stock < 0) $errors[] = 'Stan magazynowy musi być liczbą całkowitą równą lub większą od zera.';
        if (in_array($data['status'], ['active', 'preorder'], true)
            && $data['product_type'] === 'paper'
            && !empty($data['manage_stock'])
            && (int)$stock < 1) {
            $errors[] = 'Książka papierowa dostępna w sprzedaży musi mieć co najmniej jeden egzemplarz albo wyłączoną kontrolę stanu.';
        }
        $releaseDate = trim((string)($data['release_date'] ?? ''));
        if ($data['status'] === 'preorder' || ($data['status'] === 'announced' && $releaseDate !== '')) {
            $release = \DateTimeImmutable::createFromFormat('!Y-m-d', $releaseDate);
            if (!$release || $release->format('Y-m-d') !== $releaseDate) {
                $errors[] = 'Podaj planowaną datę premiery.';
            } elseif ($release < new \DateTimeImmutable('today')) {
                $errors[] = 'Data premiery dla zapowiedzi lub przedsprzedaży nie może być wcześniejsza niż dzisiaj.';
            }
        }
        foreach (['publication_year' => 'Rok wydania', 'pages' => 'Liczba stron'] as $field => $label) {
            $value = trim((string)($data[$field] ?? ''));
            if ($value !== '' && (filter_var($value, FILTER_VALIDATE_INT) === false || (int)$value < 0)) {
                $errors[] = $label . ' musi być nieujemną liczbą całkowitą.';
            }
        }
        if (!in_array($data['product_type'], ['paper','ebook'], true)) $errors[] = 'Nieprawidłowy typ książki.';
        if (!in_array($data['status'], ['draft','active','preorder','announced','hidden','sold_out'], true)) {
            $errors[] = 'Nieprawidłowy status książki.';
        }
        return $errors;
    }

    private function emptyBook(): array
    {
        $currency = (new SettingsRepository())->get('currency', 'PLN');
        return ['id'=>null,'old_wp_id'=>null,'sku'=>'','slug'=>'','title'=>'','author_id'=>null,'author'=>'','short_description'=>'','description'=>'','price_gross'=>'0.00','currency'=>$currency,'product_type'=>'paper','status'=>'draft','release_date'=>'','stock_qty'=>0,'manage_stock'=>1,'weight_kg'=>'','length_cm'=>'','width_cm'=>'','height_cm'=>'','isbn'=>'','publisher'=>'','publication_year'=>'','pages'=>'','format'=>'','attributes_json'=>'[]','cover_image'=>'','ebook_file_path'=>'','seo_title'=>'','seo_description'=>'','seo_keywords'=>'','canonical_url'=>''];
    }

    private function authorData(array $existing = []): array
    {
        $name = trim((string)($_POST['name'] ?? ''));
        return [
            'name' => $name,
            'slug' => trim((string)($_POST['slug'] ?? '')) ?: BookRepository::slugify($name),
            'photo' => $existing['photo'] ?? trim((string)($_POST['photo'] ?? '')),
            'short_bio' => trim((string)($_POST['short_bio'] ?? '')),
            'publications_url' => trim((string)($_POST['publications_url'] ?? '')),
            'status' => (string)($_POST['status'] ?? 'active'),
        ];
    }

    private function validateAuthorData(array $data): array
    {
        $errors = [];
        if (trim((string)$data['name']) === '') $errors[] = 'Imię i nazwisko autora są wymagane.';
        if (mb_strlen((string)$data['name']) > 190) $errors[] = 'Nazwa autora jest za długa.';
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string)$data['slug'])
            || strlen((string)$data['slug']) > 190) {
            $errors[] = 'Slug może zawierać tylko małe litery, cyfry i myślniki.';
        }
        if (mb_strlen((string)$data['short_bio']) > 1600) {
            $errors[] = 'Krótka notka nie może przekraczać 1600 znaków.';
        }
        $url = trim((string)$data['publications_url']);
        if ($url !== ''
            && !str_starts_with($url, '/')
            && (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url))) {
            $errors[] = 'Link do publikacji musi być adresem wewnętrznym /... albo pełnym adresem https://.';
        }
        if (!in_array((string)$data['status'], ['active', 'hidden'], true)) {
            $errors[] = 'Nieprawidłowy status autora.';
        }
        return $errors;
    }

    private function emptyAuthor(): array
    {
        return [
            'id' => null,
            'name' => '',
            'slug' => '',
            'photo' => '',
            'short_bio' => '',
            'publications_url' => '',
            'status' => 'active',
        ];
    }

    private function emptyRegistrationForm(): array
    {
        $fields = [
            ['key' => 'first_name', 'type' => 'text', 'label' => 'Imię', 'enabled' => true, 'required' => true],
            ['key' => 'last_name', 'type' => 'text', 'label' => 'Nazwisko', 'enabled' => true, 'required' => true],
            ['key' => 'email', 'type' => 'email', 'label' => 'E-mail', 'enabled' => true, 'required' => true],
            ['key' => 'phone', 'type' => 'tel', 'label' => 'Telefon', 'enabled' => true, 'required' => true],
        ];
        return [
            'id' => null,
            'name' => '',
            'recipient_email' => 'rekolekcje@arka-pojednanie.pl',
            'email_subject' => 'Nowe zgłoszenie',
            'intro_text' => '',
            'submit_label' => 'Wyślij zgłoszenie',
            'success_message' => 'Dziękujemy. Twoje zgłoszenie zostało przyjęte.',
            'fields_json' => json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'active',
        ];
    }

    private function emptyEvent(): array
    {
        return [
            'id' => null,
            'slug' => '',
            'title' => '',
            'author_id' => null,
            'excerpt' => '',
            'content' => '',
            'starts_at' => '',
            'ends_at' => '',
            'location' => '',
            'organizer' => 'Wydawnictwo Katolickie ARKA',
            'featured_image' => '',
            'registration_form_id' => null,
            'status' => 'draft',
            'seo_title' => '',
            'seo_description' => '',
        ];
    }

    private function emptyPage(): array
    {
        return [
            'id'=>null,
            'old_wp_id'=>null,
            'slug'=>'',
            'title'=>'',
            'author_id'=>null,
            'registration_form_id'=>null,
            'excerpt'=>'',
            'content'=>'',
            'status'=>'draft',
            'featured_image'=>'',
            'seo_title'=>'',
            'seo_description'=>'',
            'canonical_url'=>'',
        ];
    }

    private function attributesJson(string $lines): string
    {
        $attributes = [];
        foreach (preg_split('/\R/u', $lines) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || !str_contains($line, ':')) continue;
            [$name, $value] = array_map('trim', explode(':', $line, 2));
            if ($name === '' || $value === '') continue;
            $attributes[mb_substr($name, 0, 80)] = mb_substr($value, 0, 500);
        }

        return json_encode($attributes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    private function isAjaxRequest(): bool
    {
        return strcasecmp((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''), 'XMLHttpRequest') === 0;
    }
}
