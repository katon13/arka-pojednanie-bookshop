<?php
namespace Book100\Controllers;

use Book100\Core\Csrf;
use Book100\Core\Redirect;
use Book100\Core\Session;
use Book100\Core\View;
use Book100\Repository\BookRepository;
use Book100\Repository\ContentPageRepository;
use Book100\Repository\EventRepository;
use Book100\Repository\RegistrationFormRepository;
use Book100\Repository\OrderRepository;
use Book100\Repository\SubscriberRepository;
use Book100\Repository\SettingsRepository;
use Book100\Services\Cache\PublicCache;
use Book100\Services\Books\BookSaleState;
use Book100\Services\Homepage\HomepageSettingsService;
use Book100\Services\Integrations\GoogleMerchantFeed;
use Book100\Services\InPost\InPostClient;
use Book100\Services\Orders\CheckoutValidator;
use Book100\Services\Payments\PaymentService;
use Book100\Services\Registrations\RegistrationService;
use Book100\Services\Seo\SeoBuilder;
use Book100\Services\Storefront\StorefrontSettingsService;
use Throwable;

final class PublicController
{
    public function home(): void
    {
        $this->releaseExpiredReservations();
        echo PublicCache::remember('home', 600, function () {
            $homepage = (new HomepageSettingsService())->publicState();
            return View::capture('public/home', [
                'books' => $homepage['books'],
                'featured' => $homepage['featured'],
                'showHowItWorks' => $homepage['show_how_it_works'],
                'hero' => $homepage['hero'],
                'seo' => SeoBuilder::home(),
                'message' => $_GET['newsletter'] ?? null,
            ]);
        });
    }

    public function book(string $slug): void
    {
        $this->releaseExpiredReservations();
        $book = (new BookRepository())->findBySlug($slug);
        if (!$book || !BookSaleState::isPublic($book)) { http_response_code(404); echo 'Nie znaleziono książki'; return; }
        echo PublicCache::remember('book:' . $slug, 600, fn() => View::capture('public/book', ['book' => $book, 'seo' => SeoBuilder::book($book)]));
    }

    public function legacyBook(string $slug): void
    {
        $book = (new BookRepository())->findBySlug($slug);
        if (!$book || !BookSaleState::isPublic($book)) {
            http_response_code(404);
            echo 'Nie znaleziono książki';
            return;
        }
        header('Location: ' . SeoBuilder::url('/book/' . rawurlencode($slug) . '/'), true, 301);
    }

    public function checkout(string $slug): void
    {
        $this->releaseExpiredReservations();
        $book = (new BookRepository())->findBySlug($slug);
        if (!$book || !BookSaleState::isPurchasable($book)) { http_response_code(404); echo 'Nie znaleziono książki'; return; }
        $book['checkout_quantity'] = 1;
        $settings = new SettingsRepository();
        $inpost = new InPostClient();
        $payments = PaymentService::availableProviders();
        View::render('public/checkout', [
            'book'=>$book,
            'selectedBooks'=>[$book],
            'errors'=>$payments
                ? (($_GET['payment'] ?? '') === 'cancelled' ? ['Płatność została przerwana. Możesz spróbować ponownie.'] : [])
                : ['Sprzedaż jest chwilowo niedostępna — administrator musi skonfigurować płatności.'],
            'old'=>[],
            'payments'=>$payments,
            'shipping'=>[
                'inpost_locker'=>$settings->shippingCost('inpost_locker'),
                'inpost_courier'=>$settings->shippingCost('inpost_courier'),
                'pickup'=>0.0,
            ],
            'inpostGeoWidget'=>$inpost->geoWidgetConfiguration(),
            'inpostCourierEnabled'=>$inpost->courierEnabled(),
            'seo'=>['title'=>'Kup: ' . $book['title'], 'robots'=>'noindex,nofollow'],
        ]);
    }

    public function checkoutSubmit(string $slug): void
    {
        Csrf::check();
        $this->releaseExpiredReservations();
        $book = (new BookRepository())->findBySlug($slug);
        if (!$book || !BookSaleState::isPurchasable($book)) { http_response_code(404); echo 'Nie znaleziono książki'; return; }

        $selection = $this->checkoutSelection($book, $_POST);
        $selectedBooks = $selection['books'];
        $errors = CheckoutValidator::validateItems($selectedBooks, $_POST);
        if (!empty($selection['missing'])) {
            $errors[] = 'Jedna z wybranych książek nie jest już dostępna. Odśwież wybór i spróbuj ponownie.';
        }
        if ($errors) {
            $settings = new SettingsRepository();
            $inpost = new InPostClient();
            View::render('public/checkout', [
                'book'=>$book,
                'selectedBooks'=>$selectedBooks ?: [$book + ['checkout_quantity'=>1]],
                'errors'=>$errors,
                'old'=>$_POST,
                'payments'=>PaymentService::availableProviders(),
                'shipping'=>[
                    'inpost_locker'=>$settings->shippingCost('inpost_locker'),
                    'inpost_courier'=>$settings->shippingCost('inpost_courier'),
                    'pickup'=>0.0,
                ],
                'inpostGeoWidget'=>$inpost->geoWidgetConfiguration(),
                'inpostCourierEnabled'=>$inpost->courierEnabled(),
                'seo'=>['title'=>'Kup: ' . $book['title'], 'robots'=>'noindex,nofollow'],
            ]);
            return;
        }

        try {
            $order = (new OrderRepository())->createForBooks($selectedBooks, $_POST);
            $payment = PaymentService::startForOrder($order);
            PublicCache::clear();
            if (!empty($payment['redirect_url'])) {
                header('Location: ' . (string)$payment['redirect_url'], true, 303);
                exit;
            }
            throw new \RuntimeException('Operator płatności nie zwrócił adresu płatności.');
        } catch (Throwable $e) {
            error_log('[checkout_submit_error] ' . (string)($_SERVER['REQUEST_URI'] ?? '') . ' | ' . $e->getMessage());
            View::render('public/checkout', [
                'book' => $book,
                'selectedBooks' => $selectedBooks,
                'errors' => ['Nie udało się zapisać zamówienia albo utworzyć sesji płatności. Sprawdź konfigurację bazy/API i spróbuj ponownie.'],
                'old' => $_POST,
                'payments' => PaymentService::availableProviders(),
                'shipping' => [
                    'inpost_locker'=>(new SettingsRepository())->shippingCost('inpost_locker'),
                    'inpost_courier'=>(new SettingsRepository())->shippingCost('inpost_courier'),
                    'pickup'=>0.0,
                ],
                'inpostGeoWidget'=>(new InPostClient())->geoWidgetConfiguration(),
                'inpostCourierEnabled'=>(new InPostClient())->courierEnabled(),
                'seo' => ['title' => 'Kup: '.$book['title'], 'robots'=>'noindex,nofollow']
            ]);
        }
    }

    public function thanks(string $token): void
    {
        $orders = new OrderRepository();
        $order = $orders->findByToken($token);
        if (!$order) { http_response_code(404); echo 'Nie znaleziono zamówienia'; return; }
        $returnId = '';
        if (($_GET['payment'] ?? '') === 'stripe_success' && !empty($_GET['session_id'])) {
            $returnId = (string)$_GET['session_id'];
        } elseif (($_GET['payment'] ?? '') === 'stripe_intent' && !empty($_GET['payment_intent'])) {
            $returnId = (string)$_GET['payment_intent'];
        }
        if (($order['payment_status'] ?? '') !== 'paid' && $returnId !== '') {
            PaymentService::confirmReturn($order, $returnId);
            $order = $orders->findByToken($token) ?? $order;
        }
        if (($order['payment_status'] ?? '') !== 'paid') {
            Redirect::to('/platnosc/' . rawurlencode($token) . '?payment=pending');
        }
        $notice = null;
        View::render('public/thanks', ['order' => $order, 'notice' => $notice, 'seo' => ['title' => 'Płatność potwierdzona', 'robots'=>'noindex,nofollow']]);
    }

    public function payment(string $token): void
    {
        $orders = new OrderRepository();
        $order = $orders->findByToken($token);
        if (!$order) { http_response_code(404); echo 'Nie znaleziono zamówienia'; return; }
        if (($order['payment_status'] ?? '') === 'paid') {
            Redirect::to('/dziekujemy/' . rawurlencode($token));
        }
        $provider = (string)($order['payment_provider'] ?? '');
        if (PaymentService::isRedirectProvider($provider)) {
            View::render('public/payment_pending', [
                'order' => $order,
                'seo' => ['title' => 'Oczekiwanie na potwierdzenie płatności', 'robots' => 'noindex,nofollow'],
            ]);
            return;
        }
        $stripe = PaymentService::paymentElementState($order);
        $order = $orders->findByToken($token) ?? $order;
        if (($order['payment_status'] ?? '') === 'paid') {
            Redirect::to('/dziekujemy/' . rawurlencode($token));
        }
        if (empty($stripe['ok']) || empty($stripe['client_secret'])) {
            http_response_code(503);
            View::render('public/payment', [
                'order'=>$order,
                'stripe'=>$stripe,
                'paymentError'=>'Nie udało się otworzyć bezpiecznego formularza płatności. Wróć i spróbuj ponownie.',
                'seo'=>['title'=>'Płatność', 'robots'=>'noindex,nofollow'],
            ]);
            return;
        }
        View::render('public/payment', [
            'order'=>$order,
            'stripe'=>$stripe,
            'paymentError'=>($_GET['payment'] ?? '') === 'pending'
                ? 'Płatność nie została jeszcze potwierdzona. Możesz ponowić próbę.'
                : null,
            'seo'=>['title'=>'Bezpieczna płatność', 'robots'=>'noindex,nofollow'],
        ]);
    }

    public function paymentCancelled(string $token): void
    {
        $orders = new OrderRepository();
        $order = $orders->findByToken($token);
        $slug = trim((string)($order['items'][0]['book_slug'] ?? ''));
        if ($order && ($order['payment_status'] ?? '') !== 'paid') {
            PaymentService::cancelUnpaid($order);
            $orders->deleteUnpaidOrder((int)$order['id']);
        }
        Redirect::to($slug !== '' ? '/kup/' . rawurlencode($slug) . '?payment=cancelled' : '/');
    }

    public function ebookDownload(string $token, string $itemId): void
    {
        $item = (new OrderRepository())->downloadableItem($token, (int)$itemId);
        if (!$item) { http_response_code(404); echo 'Plik jest niedostępny.'; return; }
        $root = dirname(__DIR__, 2);
        $ebooksRoot = realpath($root . '/storage/ebooks');
        $full = realpath($root . '/' . ltrim((string)$item['ebook_file_path'], '/\\'));
        if (!$ebooksRoot || !$full || !str_starts_with(strtolower($full), strtolower($ebooksRoot . DIRECTORY_SEPARATOR)) || !is_file($full)) {
            http_response_code(404);
            echo 'Plik jest niedostępny.';
            return;
        }
        $extension = strtolower(pathinfo($full, PATHINFO_EXTENSION));
        $types = ['pdf'=>'application/pdf', 'epub'=>'application/epub+zip', 'mobi'=>'application/x-mobipocket-ebook'];
        $safeTitle = preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string)$item['title']) ?: 'ebook';
        header('Content-Type: ' . ($types[$extension] ?? 'application/octet-stream'));
        header('Content-Length: ' . filesize($full));
        header('Content-Disposition: attachment; filename="' . trim($safeTitle, '-') . '.' . $extension . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($full);
    }

    public function subscribe(): void
    {
        Csrf::check();
        if (empty($_POST['consent'])) {
            Redirect::to('/?newsletter=consent');
        }
        (new SubscriberRepository())->subscribe((string)($_POST['email'] ?? ''), $_POST['name'] ?? null, 'footer');
        Redirect::to('/?newsletter=ok');
    }

    public function csrfToken(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-store, max-age=0');
        echo json_encode(['token' => Csrf::token()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function unsubscribe(string $token): void
    {
        (new SubscriberRepository())->unsubscribe($token);
        if (strcasecmp((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'), 'POST') === 0) {
            http_response_code(204);
            return;
        }
        $seo = SeoBuilder::page('Wypisanie z newslettera', 'Wypisanie z listy mailingowej ARKA', '/newsletter/wypisz/'.$token);
        $seo['robots'] = 'noindex,nofollow';
        View::render('public/page', [
            'seo' => $seo,
            'title' => 'Subskrypcja usunięta',
            'body' => "Adres nie znajduje się już na liście mailingowej ARKA.\n\nNie będziemy wysyłać na niego kolejnych newsletterów.",
        ]);
    }

    public function sitemap(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        echo PublicCache::remember('sitemap', 900, function () {
            $books = (new BookRepository())->allPublic();
            $pages = (new ContentPageRepository())->allPublished();
            $events = (new EventRepository())->allPublic();
            return View::capture('public/sitemap', ['books'=>$books, 'pages'=>$pages, 'events'=>$events]);
        });
    }

    public function googleMerchantFeed(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        header('X-Robots-Tag: noindex, follow');
        echo PublicCache::remember('google-merchant-feed', 600, function () {
            $feed = new GoogleMerchantFeed();
            return View::capture('public/google_merchant', [
                'products' => $feed->products(),
                'store' => (new StorefrontSettingsService())->state(),
            ]);
        });
    }

    public function contentPage(string $slug): void
    {
        $page = (new ContentPageRepository())->findPublishedBySlug($slug);
        if (!$page) { http_response_code(404); echo 'Nie znaleziono strony'; return; }
        $form = !empty($page['registration_form_id'])
            ? (new RegistrationFormRepository())->find((int)$page['registration_form_id'])
            : null;
        $render = fn() => View::capture('public/content_page', [
            'page' => $page,
            'registrationForm' => $form,
            'registrationContextType' => 'page',
            'registrationContextId' => (int)$page['id'],
            'registrationFlash' => $this->registrationFlash('page', (int)$page['id']),
            'seo' => SeoBuilder::contentPage($page),
        ]);
        echo $form ? $render() : PublicCache::remember('page:' . $slug, 600, $render);
    }

    public function events(): void
    {
        View::render('public/events', [
            'events' => (new EventRepository())->allPublic(),
            'seo' => SeoBuilder::page(
                'Wydarzenia',
                'Aktualne rekolekcje, spotkania i wydarzenia Wydawnictwa Katolickiego ARKA.',
                '/wydarzenia'
            ),
        ]);
    }

    public function event(string $slug): void
    {
        $event = (new EventRepository())->findPublicBySlug($slug);
        if (!$event) { http_response_code(404); echo 'Nie znaleziono wydarzenia'; return; }
        $form = !empty($event['registration_form_id'])
            ? (new RegistrationFormRepository())->find((int)$event['registration_form_id'])
            : null;
        View::render('public/event', [
            'event' => $event,
            'registrationForm' => $form,
            'registrationContextType' => 'event',
            'registrationContextId' => (int)$event['id'],
            'registrationFlash' => $this->registrationFlash('event', (int)$event['id']),
            'seo' => SeoBuilder::event($event),
        ]);
    }

    public function submitRegistration(): void
    {
        Csrf::check();
        $type = (string)($_POST['context_type'] ?? '');
        $id = max(0, (int)($_POST['context_id'] ?? 0));
        $formId = max(0, (int)($_POST['form_id'] ?? 0));
        $label = '';
        $redirect = '/';
        $valid = false;
        if ($type === 'page') {
            $page = (new ContentPageRepository())->find($id);
            $valid = $page && ($page['status'] ?? '') === 'published'
                && (int)($page['registration_form_id'] ?? 0) === $formId;
            if ($page) {
                $label = (string)$page['title'];
                $redirect = '/' . rawurlencode((string)$page['slug']);
            }
        } elseif ($type === 'event') {
            $event = (new EventRepository())->find($id);
            $valid = $event && ($event['status'] ?? '') === 'published'
                && (int)($event['registration_form_id'] ?? 0) === $formId;
            if ($event) {
                $label = (string)$event['title'];
                $redirect = '/wydarzenia/' . rawurlencode((string)$event['slug']);
            }
        }
        try {
            if (!$valid) throw new \RuntimeException('Ten formularz nie jest dostępny.');
            (new RegistrationService())->submit($formId, [
                'type' => $type,
                'id' => $id,
                'label' => $label,
            ], $_POST);
            $form = (new RegistrationFormRepository())->find($formId);
            $this->setRegistrationFlash($type, $id, 'success', (string)($form['success_message'] ?? 'Dziękujemy. Zgłoszenie zostało przyjęte.'));
        } catch (Throwable $exception) {
            $this->setRegistrationFlash($type, $id, 'error', $exception->getMessage());
        }
        Redirect::to($redirect . '#formularz-zgloszeniowy');
    }

    public function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\nAllow: /\nDisallow: /kup/\nDisallow: /dziekujemy/\nDisallow: /api/\nSitemap: " . SeoBuilder::url('/sitemap.xml') . "\n";
    }

    public function contact(): void
    {
        $store = (new StorefrontSettingsService())->state();
        $title = trim((string)$store['contact_title']) ?: 'Kontakt';
        View::render('public/page', [
            'seo'=>SeoBuilder::page($title, 'Kontakt ze sklepem ' . $store['shop_name'], '/kontakt'),
            'title'=>$title,
            'body'=>(string)$store['contact_text'],
        ]);
    }

    public function terms(): void
    {
        $store = (new StorefrontSettingsService())->state();
        $body = (string)$store['terms_text'];
        if ($body === '') {
            $source = dirname(__DIR__, 2) . '/resources/content/terms.pl.txt';
            $body = is_file($source) ? trim((string)file_get_contents($source)) : '';
        }
        $title = trim((string)$store['terms_title']) ?: 'Regulamin';
        View::render('public/page', [
            'seo'=>SeoBuilder::page($title, 'Regulamin sklepu ' . $store['shop_name'], '/regulamin'),
            'title'=>$title,
            'body'=>$body ?: 'Regulamin nie został jeszcze uzupełniony przez administratora.',
        ]);
    }

    public function privacy(): void
    {
        $store = (new StorefrontSettingsService())->state();
        $title = trim((string)$store['privacy_title']) ?: 'Polityka prywatności';
        $body = (string)$store['privacy_text'];
        if ($body === '') {
            $source = dirname(__DIR__, 2) . '/resources/legal/privacy-policy-pl.txt';
            $body = is_file($source) ? trim((string)file_get_contents($source)) : '';
        }
        View::render('public/page', [
            'seo'=>SeoBuilder::page($title, 'Polityka prywatności sklepu ' . $store['shop_name'], '/polityka-prywatnosci'),
            'title'=>$title,
            'body'=>$body ?: 'Polityka prywatności nie została jeszcze uzupełniona przez administratora.',
        ]);
    }

    private function releaseExpiredReservations(): void
    {
        try {
            if ((new OrderRepository())->releaseExpiredReservations(45) > 0) {
                PublicCache::clear();
            }
        } catch (Throwable) {
            // Instalacja może jeszcze nie mieć bazy; właściwa strona pokaże swój standardowy błąd.
        }
    }

    private function setRegistrationFlash(string $type, int $id, string $tone, string $message): void
    {
        Session::start();
        $_SESSION['registration_flash'][$type . ':' . $id] = [
            'type' => $tone,
            'message' => $message,
        ];
    }

    private function registrationFlash(string $type, int $id): ?array
    {
        Session::start();
        $key = $type . ':' . $id;
        $flash = $_SESSION['registration_flash'][$key] ?? null;
        unset($_SESSION['registration_flash'][$key]);
        return is_array($flash) ? $flash : null;
    }

    private function checkoutSelection(array $primaryBook, array $data): array
    {
        $rawItems = is_array($data['items'] ?? null) ? $data['items'] : [];
        if ($rawItems === []) {
            $rawItems = [(int)$primaryBook['id'] => $data['quantity'] ?? 1];
        }
        $quantities = [];
        foreach ($rawItems as $bookId => $quantity) {
            $id = (int)$bookId;
            if ($id < 1) continue;
            $validated = filter_var($quantity, FILTER_VALIDATE_INT);
            if ($validated === false || $validated < 1) continue;
            $quantities[$id] = min(20, (int)$validated);
        }
        $books = (new BookRepository())->findPurchasableByIds(array_keys($quantities));
        foreach ($books as &$book) {
            $book['checkout_quantity'] = ($book['product_type'] ?? 'paper') === 'ebook'
                ? 1
                : ($quantities[(int)$book['id']] ?? 1);
        }
        unset($book);
        $foundIds = array_map(static fn(array $book): int => (int)$book['id'], $books);
        return [
            'books' => $books,
            'missing' => array_values(array_diff(array_keys($quantities), $foundIds)),
        ];
    }
}
