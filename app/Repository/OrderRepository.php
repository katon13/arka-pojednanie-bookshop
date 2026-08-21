<?php
namespace Book100\Repository;

use Book100\Core\Database;
use Book100\Core\Env;
use Book100\Core\StoreUrl;
use Book100\Core\Utf8Sanitizer;
use Book100\Services\Mail\EmailTemplate;
use Book100\Services\Mail\Mailer;
use PDO;
use RuntimeException;

final class OrderRepository
{
    private const EDITABLE_STATUSES = [
        'payment_pending',
        'payment_failed',
        'payment_expired',
        'paid_waiting_for_shipment',
        'paid_stock_problem',
        'shipment_created',
        'shipped',
        'completed',
        'refund_pending',
        'cancelled',
        'refunded',
    ];

    public function createForBook(array $book, array $data): array
    {
        $book['checkout_quantity'] = ($book['product_type'] ?? 'paper') === 'ebook'
            ? 1
            : max(1, min(20, (int)($data['quantity'] ?? 1)));
        return $this->createForBooks([$book], $data);
    }

    public function createForBooks(array $books, array $data): array
    {
        $pdo = Database::pdo();
        $now = date('Y-m-d H:i:s');
        $token = bin2hex(random_bytes(24));
        $orderNumber = '';
        $items = [];
        $hasPaper = false;
        $hasEbook = false;
        $subtotal = 0.0;
        foreach ($books as $book) {
            $isEbook = ($book['product_type'] ?? 'paper') === 'ebook';
            $qty = $isEbook ? 1 : max(1, min(20, (int)($book['checkout_quantity'] ?? 1)));
            $price = round((float)$book['price_gross'], 2);
            $lineTotal = round($price * $qty, 2);
            $items[] = ['book'=>$book, 'quantity'=>$qty, 'price'=>$price, 'total'=>$lineTotal, 'is_ebook'=>$isEbook];
            $subtotal = round($subtotal + $lineTotal, 2);
            $hasEbook = $hasEbook || $isEbook;
            $hasPaper = $hasPaper || !$isEbook;
        }
        if ($items === []) {
            throw new RuntimeException('Zamówienie nie zawiera żadnej książki.');
        }
        $deliveryMethod = $hasPaper ? (string)($data['delivery_method'] ?? 'inpost_locker') : 'ebook';
        $settings = new SettingsRepository();
        $shipping = $settings->shippingCost($deliveryMethod);
        $termsSnapshot = $settings->get('terms_text', '');
        $total = round($subtotal + $shipping, 2);
        $paymentProvider = (string)($data['payment_provider'] ?? Env::get('PAYMENT_PRIMARY', 'przelewy24'));
        $customerName = trim((string)($data['customer_name'] ?? ''));
        $customerEmail = trim((string)($data['customer_email'] ?? ''));
        $customerPhone = trim((string)($data['customer_phone'] ?? ''));
        $inpostPoint = $deliveryMethod === 'inpost_locker'
            ? strtoupper(trim((string)($data['inpost_point'] ?? '')))
            : null;
        $stockState = 'not_required';

        $pdo->beginTransaction();
        try {
            $orderNumber = $this->nextOrderNumber($pdo);
            foreach ($items as $line) {
                $book = $line['book'];
                $qty = (int)$line['quantity'];
                if (!empty($line['is_ebook']) || empty($book['manage_stock'])) continue;
                $reserve = $pdo->prepare(
                    "UPDATE books
                     SET stock_qty = stock_qty - :qty,
                         status = CASE WHEN stock_qty - :qty <= 0 THEN 'sold_out' ELSE status END,
                         updated_at = :updated_at
                     WHERE id = :id AND status IN ('active','preorder') AND manage_stock = 1 AND stock_qty >= :qty"
                );
                $reserve->execute([':qty'=>$qty, ':updated_at'=>$now, ':id'=>(int)$book['id']]);
                if ($reserve->rowCount() !== 1) {
                    throw new RuntimeException('Brak wystarczającej liczby egzemplarzy.');
                }
                $stockState = 'reserved';
            }

            $stmt = $pdo->prepare(
                'INSERT INTO orders
                (order_number, order_token, status, customer_email, customer_name, customer_phone,
                 billing_address_json, shipping_address_json, delivery_method, inpost_point,
                 subtotal_gross, discount_gross, shipping_gross, total_gross, currency,
                 payment_provider, payment_status, shipment_status, stock_state, terms_accepted_at,
                 terms_snapshot, digital_content_consent_at, created_at, updated_at)
                VALUES
                (:order_number,:order_token,:status,:customer_email,:customer_name,:customer_phone,
                 :billing_address_json,:shipping_address_json,:delivery_method,:inpost_point,
                 :subtotal_gross,0,:shipping_gross,:total_gross,:currency,
                 :payment_provider,:payment_status,:shipment_status,:stock_state,:terms_accepted_at,
                 :terms_snapshot,:digital_content_consent_at,:created_at,:updated_at)'
            );
            $stmt->execute([
                ':order_number' => $orderNumber,
                ':order_token' => $token,
                ':status' => 'payment_pending',
                ':customer_email' => $customerEmail,
                ':customer_name' => $customerName,
                ':customer_phone' => $customerPhone,
                ':billing_address_json' => json_encode([
                    'name'=>$customerName, 'email'=>$customerEmail, 'phone'=>$customerPhone,
                    'digital_content_consent'=>$hasEbook && !empty($data['digital_content_consent']),
                ], JSON_UNESCAPED_UNICODE),
                ':shipping_address_json' => json_encode([
                    'delivery_method'=>$deliveryMethod,
                    'inpost_point'=>$inpostPoint,
                    'street'=>trim((string)($data['street'] ?? '')),
                    'building_number'=>trim((string)($data['building_number'] ?? '')),
                    'city'=>trim((string)($data['city'] ?? '')),
                    'post_code'=>trim((string)($data['post_code'] ?? '')),
                ], JSON_UNESCAPED_UNICODE),
                ':delivery_method' => $deliveryMethod,
                ':inpost_point' => $inpostPoint,
                ':subtotal_gross' => $subtotal,
                ':shipping_gross' => $shipping,
                ':total_gross' => $total,
                ':currency' => 'PLN',
                ':payment_provider' => $paymentProvider,
                ':payment_status' => 'created',
                ':shipment_status' => $hasPaper ? 'not_created' : 'not_required',
                ':stock_state' => $stockState,
                ':terms_accepted_at' => $now,
                ':terms_snapshot' => $termsSnapshot,
                ':digital_content_consent_at' => $hasEbook && !empty($data['digital_content_consent']) ? $now : null,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
            $orderId = (int)$pdo->lastInsertId();

            $item = $pdo->prepare(
                'INSERT INTO order_items
                (order_id, book_id, old_product_id, sku, title, quantity, unit_price_gross, total_gross, ebook_file_path, sale_mode, release_date)
                VALUES (:order_id,:book_id,:old_product_id,:sku,:title,:quantity,:unit_price_gross,:total_gross,:ebook_file_path,:sale_mode,:release_date)'
            );
            foreach ($items as $line) {
                $book = $line['book'];
                $item->execute([
                    ':order_id'=>$orderId,
                    ':book_id'=>$book['id'] ?? null,
                    ':old_product_id'=>$book['old_wp_id'] ?? null,
                    ':sku'=>$book['sku'] ?? null,
                    ':title'=>$book['title'],
                    ':quantity'=>$line['quantity'],
                    ':unit_price_gross'=>$line['price'],
                    ':total_gross'=>$line['total'],
                    ':ebook_file_path'=>!empty($line['is_ebook']) ? ($book['ebook_file_path'] ?? null) : null,
                    ':sale_mode'=>(string)($book['status'] ?? 'active'),
                    ':release_date'=>trim((string)($book['release_date'] ?? '')) ?: null,
                ]);
            }

            $payment = $pdo->prepare(
                'INSERT INTO payments
                (order_id, provider, provider_session_id, provider_payment_id, status, amount_gross, currency, raw_event_json, created_at)
                VALUES (:order_id,:provider,NULL,NULL,:status,:amount_gross,:currency,:raw_event_json,:created_at)'
            );
            $payment->execute([
                ':order_id'=>$orderId,
                ':provider'=>$paymentProvider,
                ':status'=>'created',
                ':amount_gross'=>$total,
                ':currency'=>'PLN',
                ':raw_event_json'=>json_encode(['mode'=>'payment_session_pending'], JSON_UNESCAPED_UNICODE),
                ':created_at'=>$now,
            ]);

            if (!empty($data['newsletter'])) {
                $subscriberSql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
                    ? 'INSERT INTO subscribers (email, name, source, consent_marketing, consent_date, status, created_at)
                       VALUES (:email,:name,:source,1,:consent_date,:status,:created_at)
                       ON DUPLICATE KEY UPDATE name=VALUES(name), consent_marketing=1, consent_date=VALUES(consent_date), status=VALUES(status)'
                    : 'INSERT INTO subscribers (email, name, source, consent_marketing, consent_date, status, created_at)
                       VALUES (:email,:name,:source,1,:consent_date,:status,:created_at)
                       ON CONFLICT(email) DO UPDATE SET name=excluded.name, consent_marketing=1, consent_date=excluded.consent_date, status=excluded.status';
                $pdo->prepare($subscriberSql)->execute([
                    ':email'=>$customerEmail, ':name'=>$customerName, ':source'=>'checkout',
                    ':consent_date'=>$now, ':status'=>'active', ':created_at'=>$now,
                ]);
            }

            $pdo->commit();
            return $this->findByToken($token) ?: [
                'id'=>$orderId, 'order_number'=>$orderNumber, 'order_token'=>$token,
                'payment_provider'=>$paymentProvider, 'total_gross'=>$total, 'currency'=>'PLN',
            ];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public function findByToken(string $token): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM orders WHERE order_token = ? LIMIT 1');
        $stmt->execute([$token]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        return $order ? $this->hydrate($order) : null;
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        return $order ? $this->hydrate($order) : null;
    }

    public function findByOrderNumber(string $number): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM orders WHERE order_number = ? LIMIT 1');
        $stmt->execute([$number]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        return $order ? $this->hydrate($order) : null;
    }

    public function findByPaymentSession(string $provider, string $sessionId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT o.* FROM orders o JOIN payments p ON p.order_id = o.id
             WHERE p.provider = ? AND p.provider_session_id = ? ORDER BY p.id DESC LIMIT 1'
        );
        $stmt->execute([$provider, $sessionId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        return $order ? $this->hydrate($order) : null;
    }

    public function findByProviderPaymentId(string $provider, string $paymentId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT o.* FROM orders o JOIN payments p ON p.order_id = o.id
             WHERE p.provider = ? AND p.provider_payment_id = ? ORDER BY p.id DESC LIMIT 1'
        );
        $stmt->execute([$provider, $paymentId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        return $order ? $this->hydrate($order) : null;
    }

    public function paymentForOrder(int $orderId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$orderId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        return $payment ?: null;
    }

    public function savePaymentSession(
        int $orderId,
        string $provider,
        string $sessionId,
        ?string $paymentId,
        string $status,
        array $raw
    ): void {
        $now = date('Y-m-d H:i:s');
        Database::pdo()->prepare(
            'UPDATE payments SET provider_session_id=:session, provider_payment_id=:payment_id,
             status=:status, raw_event_json=:raw, updated_at=:updated_at
             WHERE order_id=:order_id AND provider=:provider'
        )->execute([
            ':session'=>$sessionId, ':payment_id'=>$paymentId, ':status'=>$status,
            ':raw'=>json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':updated_at'=>$now, ':order_id'=>$orderId, ':provider'=>$provider,
        ]);
        Database::pdo()->prepare(
            "UPDATE orders SET payment_status=?, status='payment_pending', updated_at=? WHERE id=?"
        )->execute([$status, $now, $orderId]);
    }

    public function markPaid(int $orderId, string $provider, ?string $paymentId, array $raw = []): bool
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) throw new RuntimeException('Nie znaleziono zamówienia.');
            if (in_array($order['payment_status'], ['paid','refunded'], true)) {
                $pdo->commit();
                return false;
            }

            $now = date('Y-m-d H:i:s');
            $nextStatus = $order['delivery_method'] === 'ebook' ? 'completed' : 'paid_waiting_for_shipment';
            $stockState = $order['stock_state'] === 'reserved' ? 'committed' : $order['stock_state'];
            if ($order['delivery_method'] !== 'ebook' && $order['stock_state'] === 'released') {
                if ($this->reserveItemsAgain($pdo, $orderId)) {
                    $stockState = 'committed';
                } else {
                    $stockState = 'shortage';
                    $nextStatus = 'paid_stock_problem';
                }
            }
            $pdo->prepare(
                'UPDATE orders SET status=?, payment_status=?, stock_state=?, paid_at=?,
                 completed_at=CASE WHEN ? = ? THEN ? ELSE completed_at END, updated_at=? WHERE id=?'
            )->execute([
                $nextStatus, 'paid', $stockState, $now,
                $nextStatus, 'completed', $now, $now, $orderId,
            ]);
            $pdo->prepare(
                'UPDATE payments SET status=:status,
                 provider_payment_id=COALESCE(:payment_id,provider_payment_id),
                 raw_event_json=:raw, confirmed_at=:confirmed_at, verified_at=:verified_at, updated_at=:updated_at
                 WHERE order_id=:order_id AND provider=:provider'
            )->execute([
                ':status'=>'paid', ':payment_id'=>$paymentId,
                ':raw'=>json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':confirmed_at'=>$now, ':verified_at'=>$now, ':updated_at'=>$now,
                ':order_id'=>$orderId, ':provider'=>$provider,
            ]);

            $body = 'Płatność zamówienia ' . $order['order_number'] . ' została potwierdzona.';
            if ($nextStatus === 'paid_stock_problem') {
                $body .= "\nSkontaktujemy się z Tobą w sprawie dostępności książki.";
            }
            $items = $this->itemsWithPdo($pdo, $orderId);
            $base = StoreUrl::base();
            foreach ($items as $item) {
                if (($item['sale_mode'] ?? '') === 'preorder') {
                    $date = \Book100\Services\Books\BookSaleState::formattedReleaseDate((string)($item['release_date'] ?? ''));
                    $body .= "\nPrzedsprzedaż „{$item['title']}”"
                        . ($date !== '' ? ' — premiera i wysyłka od ' . $date . '.' : ' — termin wysyłki podamy przed premierą.');
                }
                if (!empty($item['ebook_file_path'])) {
                    $body .= "\nPobierz „{$item['title']}”: {$base}/ebook/{$order['order_token']}/{$item['id']}";
                }
            }
            $customerEmailLogId = $this->queueEmail(
                $pdo,
                (string)$order['customer_email'],
                'Płatność zamówienia ' . $order['order_number'] . ' potwierdzona',
                'payment_paid',
                $body,
                $orderId
            );
            $storeEmailLogId = null;
            $orderNotificationTo = trim((string)Env::get('MAIL_ORDER_NOTIFICATION_TO', ''));
            if (filter_var($orderNotificationTo, FILTER_VALIDATE_EMAIL)) {
                $storeBody = 'Opłacono zamówienie ' . $order['order_number']
                    . ' na kwotę ' . number_format((float)$order['total_gross'], 2, ',', ' ')
                    . ' ' . ($order['currency'] ?: 'PLN') . '.'
                    . "\nKlient: " . ($order['customer_name'] ?: '-')
                    . "\nE-mail: " . ($order['customer_email'] ?: '-');
                $storeEmailLogId = $this->queueEmail(
                    $pdo,
                    $orderNotificationTo,
                    'Nowe opłacone zamówienie ' . $order['order_number'],
                    'store_payment_paid',
                    $storeBody,
                    $orderId
                );
            }
            $pdo->commit();
            $this->deliverQueuedEmail($customerEmailLogId);
            $this->deliverQueuedEmail($storeEmailLogId);
            return true;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public function markPaymentFailed(int $orderId, string $provider, string $status, array $raw = []): void
    {
        $this->deleteUnpaidOrder($orderId);
    }

    public function recordPaymentStatus(int $orderId, string $provider, string $status, array $raw = []): void
    {
        $now = date('Y-m-d H:i:s');
        Database::pdo()->prepare(
            'UPDATE payments SET status=:status, raw_event_json=:raw, updated_at=:updated_at
             WHERE order_id=:order_id AND provider=:provider'
        )->execute([
            ':status'=>$status,
            ':raw'=>json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':updated_at'=>$now,
            ':order_id'=>$orderId,
            ':provider'=>$provider,
        ]);
        Database::pdo()->prepare(
            "UPDATE orders SET status='payment_pending', payment_status=?, updated_at=?
             WHERE id=? AND payment_status NOT IN ('paid','refund_pending','refunded')"
        )->execute([$status, $now, $orderId]);
    }

    public function deleteUnpaidOrder(int $orderId): bool
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT payment_status FROM orders WHERE id=? LIMIT 1');
            $stmt->execute([$orderId]);
            $status = $stmt->fetchColumn();
            if ($status === false) {
                $pdo->commit();
                return false;
            }
            if (in_array((string)$status, ['paid','refund_pending','refunded'], true)) {
                throw new RuntimeException('Opłaconego zamówienia nie można usunąć. Użyj zwrotu płatności.');
            }
            $this->releaseReservedStock($pdo, $orderId);
            foreach (['email_logs','webhook_logs','shipments','payments','order_items'] as $table) {
                $pdo->prepare("DELETE FROM {$table} WHERE order_id=?")->execute([$orderId]);
            }
            $pdo->prepare('DELETE FROM orders WHERE id=?')->execute([$orderId]);
            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public function cancel(int $orderId, string $note = ''): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) throw new RuntimeException('Nie znaleziono zamówienia.');
            if (in_array($order['payment_status'], ['paid','refund_pending','refunded'], true)) {
                $pdo->rollBack();
                return ['ok'=>false, 'message'=>'Opłacone zamówienie wymaga zwrotu płatności.'];
            }
            if ($order['status'] === 'cancelled') {
                $pdo->commit();
                return ['ok'=>true, 'message'=>'Zamówienie było już anulowane.'];
            }
            $this->releaseReservedStock($pdo, $orderId);
            $now = date('Y-m-d H:i:s');
            $adminNote = trim((string)$order['admin_note'] . "\n" . $note);
            $pdo->prepare(
                "UPDATE orders SET status='cancelled', payment_status='cancelled',
                 admin_note=?, cancelled_at=?, updated_at=? WHERE id=?"
            )->execute([$adminNote ?: null, $now, $now, $orderId]);
            $pdo->prepare(
                "UPDATE payments SET status='cancelled', updated_at=? WHERE order_id=? AND status NOT IN ('paid','refunded')"
            )->execute([$now, $orderId]);
            $pdo->commit();
            return ['ok'=>true, 'message'=>'Zamówienie anulowano, a rezerwację magazynową zwolniono.'];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public function markRefunded(int $orderId, string $refundId, array $raw, bool $restock): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) throw new RuntimeException('Nie znaleziono zamówienia.');
            if ($order['payment_status'] === 'refunded') {
                $pdo->commit();
                return;
            }
            $now = date('Y-m-d H:i:s');
            if ($restock && $order['stock_state'] === 'committed') {
                $this->restockItems($pdo, $orderId);
                $stockState = 'released';
            } else {
                $stockState = $order['stock_state'];
            }
            $pdo->prepare(
                "UPDATE orders SET status='refunded', payment_status='refunded',
                 stock_state=?, refunded_at=?, updated_at=? WHERE id=?"
            )->execute([$stockState, $now, $now, $orderId]);
            $pdo->prepare(
                "UPDATE payments SET status='refunded', refund_id=?, refund_raw_json=?,
                 refunded_at=?, updated_at=? WHERE order_id=?"
            )->execute([
                $refundId,
                json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $now, $now, $orderId,
            ]);
            $emailLogId = $this->queueEmail(
                $pdo,
                (string)$order['customer_email'],
                'Zwrot płatności ' . $order['order_number'],
                'payment_refunded',
                'Zwrot płatności za zamówienie ' . $order['order_number'] . ' został zlecony.',
                $orderId
            );
            $pdo->commit();
            $this->deliverQueuedEmail($emailLogId);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public function markRefundPending(int $orderId, string $refundId, array $raw, bool $restock): void
    {
        $pdo = Database::pdo();
        $now = date('Y-m-d H:i:s');
        $orderStmt = $pdo->prepare('SELECT status FROM orders WHERE id = ?');
        $orderStmt->execute([$orderId]);
        $previousStatus = $orderStmt->fetchColumn();
        if ($previousStatus === false) {
            throw new RuntimeException('Nie znaleziono zamówienia.');
        }
        $encoded = json_encode(
            ['request'=>$raw, 'requested_restock'=>$restock, 'previous_status'=>(string)$previousStatus],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "UPDATE orders SET status='refund_pending', payment_status='refund_pending', updated_at=? WHERE id=?"
            )->execute([$now, $orderId]);
            $pdo->prepare(
                "UPDATE payments SET status='refund_pending', refund_id=?, refund_raw_json=?, updated_at=? WHERE order_id=?"
            )->execute([$refundId, $encoded, $now, $orderId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public function markRefundRejected(int $orderId, array $raw): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) throw new RuntimeException('Nie znaleziono zamówienia.');
            if ($order['payment_status'] === 'refunded') {
                $pdo->commit();
                return;
            }
            $paymentStmt = $pdo->prepare('SELECT refund_raw_json FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1');
            $paymentStmt->execute([$orderId]);
            $pendingData = json_decode((string)$paymentStmt->fetchColumn(), true);
            $previousStatus = is_array($pendingData) ? (string)($pendingData['previous_status'] ?? '') : '';
            $restorable = ['paid_waiting_for_shipment','shipment_created','shipped','completed'];
            $status = in_array($previousStatus, $restorable, true)
                ? $previousStatus
                : ($order['delivery_method'] === 'ebook' ? 'completed' : 'paid_waiting_for_shipment');
            $now = date('Y-m-d H:i:s');
            $note = trim((string)($order['admin_note'] ?? '') . "\nZwrot odrzucony przez operatora płatności.");
            $encoded = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $pdo->prepare(
                "UPDATE orders SET status=?, payment_status='paid', admin_note=?, updated_at=? WHERE id=?"
            )->execute([$status, $note, $now, $orderId]);
            $pdo->prepare(
                "UPDATE payments SET status='paid', refund_raw_json=?, updated_at=? WHERE order_id=?"
            )->execute([$encoded, $now, $orderId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public function requestedRefundRestock(int $orderId): bool
    {
        $payment = $this->paymentForOrder($orderId);
        $raw = json_decode((string)($payment['refund_raw_json'] ?? ''), true);
        return is_array($raw) && !empty($raw['requested_restock']);
    }

    public function updateAdminDetails(int $orderId, array $data): void
    {
        $status = (string)($data['status'] ?? '');
        $pdo = Database::pdo();
        $current = $this->find($orderId);
        $this->assertManualStatusChange($current, $status);
        $pdo->prepare(
            'UPDATE orders SET customer_name=?, customer_email=?, customer_phone=?,
             inpost_point=?, admin_note=?, status=?, updated_at=? WHERE id=?'
        )->execute([
            trim((string)($data['customer_name'] ?? '')),
            trim((string)($data['customer_email'] ?? '')),
            trim((string)($data['customer_phone'] ?? '')) ?: null,
            strtoupper(trim((string)($data['inpost_point'] ?? ''))) ?: null,
            trim((string)($data['admin_note'] ?? '')) ?: null,
            $status, date('Y-m-d H:i:s'), $orderId,
        ]);
        if ($status !== (string)($current['status'] ?? '')) {
            $emailLogId = $this->queueStatusEmail($pdo, $orderId, $status);
            $this->deliverQueuedEmail($emailLogId);
        }
    }

    public function updateStatusOnly(int $orderId, string $status, bool $notifyCustomer = true): void
    {
        $current = $this->find($orderId);
        $this->assertManualStatusChange($current, $status);

        $now = date('Y-m-d H:i:s');
        $fields = ['status=?', 'updated_at=?'];
        $params = [$status, $now];

        if ($status === 'shipped') {
            $fields[] = 'shipped_at=COALESCE(shipped_at, ?)';
            $fields[] = 'completed_at=NULL';
            $params[] = $now;
        } elseif ($status === 'completed') {
            $fields[] = 'completed_at=COALESCE(completed_at, ?)';
            $params[] = $now;
        } elseif (in_array($status, ['paid_waiting_for_shipment', 'paid_stock_problem', 'shipment_created'], true)) {
            $fields[] = 'shipped_at=NULL';
            $fields[] = 'completed_at=NULL';
        }

        $params[] = $orderId;
        Database::pdo()->prepare(
            'UPDATE orders SET ' . implode(', ', $fields) . ' WHERE id=?'
        )->execute($params);
        if ($notifyCustomer && $status !== (string)($current['status'] ?? '')) {
            $emailLogId = $this->queueStatusEmail(Database::pdo(), $orderId, $status);
            $this->deliverQueuedEmail($emailLogId);
        }
    }

    private function assertManualStatusChange(?array $current, string $status): void
    {
        if (!$current) {
            throw new RuntimeException('Nie znaleziono zamówienia.');
        }
        if (!in_array($status, self::EDITABLE_STATUSES, true) && $status !== (string)$current['status']) {
            throw new RuntimeException('Nieprawidłowy status zamówienia.');
        }
        if (in_array($status, ['paid_waiting_for_shipment','shipment_created','shipped','completed'], true)
            && !in_array($current['payment_status'], ['paid','refunded'], true)) {
            throw new RuntimeException('Najpierw operator płatności musi potwierdzić zapłatę.');
        }
    }

    public function markShipmentCreated(int $orderId, array $shipment): void
    {
        $pdo = Database::pdo();
        $tracking = $shipment['tracking_number'] ?? null;
        $shipmentStatus = trim((string)($shipment['status'] ?? 'created')) ?: 'created';
        $pdo->prepare(
            "UPDATE orders SET status='shipment_created', shipment_status=?, updated_at=? WHERE id=?"
        )->execute([$shipmentStatus, date('Y-m-d H:i:s'), $orderId]);
        $order = $this->find($orderId);
        $emailLogId = $this->queueEmail(
            $pdo,
            (string)($order['customer_email'] ?? ''),
            'Przesyłka InPost dla zamówienia ' . ($order['order_number'] ?? '') . ' utworzona',
            'shipment_created',
            'Przesyłka InPost została utworzona. Numer przesyłki: ' . ($tracking ?: '-'),
            $orderId
        );
        $this->deliverQueuedEmail($emailLogId);
    }

    public function notifyShipmentSent(int $orderId, ?string $trackingNumber = null): void
    {
        $pdo = Database::pdo();
        $duplicate = $pdo->prepare(
            "SELECT id FROM email_logs
             WHERE order_id=? AND template='order_shipped' AND status IN ('queued','failed_retry','sent')
             ORDER BY id DESC LIMIT 1"
        );
        $duplicate->execute([$orderId]);
        if ($duplicate->fetchColumn()) return;

        $order = $this->find($orderId);
        if (!$order) return;
        $body = 'Zamówienie ' . $order['order_number'] . ' zostało wysłane.';
        $trackingNumber = trim((string)$trackingNumber);
        if ($trackingNumber !== '') $body .= "\nNumer przesyłki: " . $trackingNumber . '.';
        $emailLogId = $this->queueEmail(
            $pdo,
            (string)$order['customer_email'],
            'Zamówienie ' . $order['order_number'] . ' zostało wysłane',
            'order_shipped',
            $body,
            $orderId
        );
        $this->deliverQueuedEmail($emailLogId);
    }

    public function search(string $query = '', string $status = '', int $page = 1, int $perPage = 50): array
    {
        $pdo = Database::pdo();
        $where = ["payment_status IN ('paid','refund_pending','refunded')"];
        $params = [];
        if ($query !== '') {
            $where[] = '(order_number LIKE :q OR customer_email LIKE :q OR customer_name LIKE :q)';
            $params[':q'] = '%' . $query . '%';
        }
        if ($status !== '') {
            $where[] = 'status = :status';
            $params[':status'] = $status;
        }
        $whereSql = ' WHERE ' . implode(' AND ', $where);
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM orders' . $whereSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $page = max(1, $page);
        $perPage = max(10, min(100, $perPage));
        $offset = ($page - 1) * $perPage;
        $stmt = $pdo->prepare(
            'SELECT * FROM orders' . $whereSql . ' ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) $stmt->bindValue($key, $value);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return [
            'rows'=>$stmt->fetchAll(PDO::FETCH_ASSOC),
            'total'=>$total,
            'page'=>$page,
            'pages'=>max(1, (int)ceil($total / $perPage)),
        ];
    }

    public function latest(int $limit = 50): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM orders
             WHERE payment_status IN ('paid','refund_pending','refunded')
             ORDER BY created_at DESC, id DESC LIMIT ?"
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countToday(): int
    {
        $stmt = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM orders
             WHERE payment_status IN ('paid','refund_pending','refunded')
             AND DATE(created_at) = DATE(?)"
        );
        $stmt->execute([date('Y-m-d H:i:s')]);
        return (int)$stmt->fetchColumn();
    }

    public function countToShip(): int
    {
        return (int)Database::pdo()->query(
            "SELECT COUNT(*) FROM orders WHERE status IN ('paid','paid_waiting_for_shipment','shipment_created')"
        )->fetchColumn();
    }

    public function adminSummary(): array
    {
        $row = Database::pdo()->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status IN ('paid','paid_waiting_for_shipment') AND delivery_method <> 'ebook' THEN 1 ELSE 0 END) AS ready,
                SUM(CASE WHEN status = 'shipment_created' THEN 1 ELSE 0 END) AS labels,
                SUM(CASE WHEN status IN ('shipped','completed') THEN 1 ELSE 0 END) AS done,
                SUM(CASE WHEN status IN ('payment_failed','paid_stock_problem','refund_pending','failed') THEN 1 ELSE 0 END) AS attention
             FROM orders
             WHERE payment_status IN ('paid','refund_pending','refunded')"
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int)($row['total'] ?? 0),
            'ready' => (int)($row['ready'] ?? 0),
            'labels' => (int)($row['labels'] ?? 0),
            'done' => (int)($row['done'] ?? 0),
            'attention' => (int)($row['attention'] ?? 0),
        ];
    }

    public function paidRevenue(): float
    {
        return (float)Database::pdo()->query(
            "SELECT COALESCE(SUM(total_gross),0) FROM orders WHERE payment_status='paid'"
        )->fetchColumn();
    }

    public function salesRows(int $limit = 1000): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT o.id AS order_id, o.old_wp_id AS legacy_order_id, o.created_at, o.order_number, o.customer_email, o.total_gross,
             o.payment_provider, o.payment_status, o.delivery_method, o.status,
             oi.book_id, oi.sku, oi.title, oi.quantity, oi.total_gross AS item_total,
             b.cover_image
             FROM orders o
             JOIN order_items oi ON oi.order_id=o.id
             LEFT JOIN books b ON b.id=oi.book_id
             WHERE o.payment_status IN ('paid','refund_pending','refunded')
             ORDER BY o.created_at DESC, o.id DESC LIMIT ?"
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function salesSummary(): array
    {
        $row = Database::pdo()->query(
            "SELECT
                COUNT(DISTINCT CASE WHEN payment_status='paid' THEN id END) AS paid_orders,
                COALESCE(SUM(CASE WHEN payment_status='paid' THEN total_gross ELSE 0 END),0) AS revenue,
                COUNT(DISTINCT CASE WHEN payment_status='refunded' OR status='refunded' THEN id END) AS refunds
             FROM orders"
        )->fetch(PDO::FETCH_ASSOC) ?: [];
        $units = (int)Database::pdo()->query(
            "SELECT COALESCE(SUM(oi.quantity),0)
             FROM order_items oi JOIN orders o ON o.id=oi.order_id
             WHERE o.payment_status='paid'"
        )->fetchColumn();

        return [
            'paid_orders' => (int)($row['paid_orders'] ?? 0),
            'revenue' => (float)($row['revenue'] ?? 0),
            'refunds' => (int)($row['refunds'] ?? 0),
            'units' => $units,
        ];
    }

    public function itemSummariesForOrders(array $orders): array
    {
        $ids = array_values(array_filter(array_map(
            static fn(array $order): int => (int)($order['id'] ?? 0),
            $orders
        )));
        if (!$ids) return [];

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::pdo()->prepare(
            "SELECT oi.order_id, oi.title, oi.quantity, oi.sku, oi.sale_mode, oi.release_date, b.cover_image
             FROM order_items oi
             LEFT JOIN books b ON b.id=oi.book_id
             WHERE oi.order_id IN ($placeholders)
             ORDER BY oi.order_id, oi.id"
        );
        $stmt->execute($ids);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(int)$row['order_id']][] = $row;
        }
        return $out;
    }

    public function timeline(int $orderId): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id=? LIMIT 1');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) return [];

        $events = [];
        $add = static function (
            array &$events,
            ?string $date,
            string $type,
            string $title,
            string $description = ''
        ): void {
            if (!$date) return;
            $events[] = [
                'date' => $date,
                'type' => $type,
                'title' => $title,
                'description' => $description,
            ];
        };

        $add($events, $order['created_at'] ?? null, 'order', 'Zamówienie zostało złożone');

        $paymentStmt = $pdo->prepare('SELECT * FROM payments WHERE order_id=? ORDER BY id DESC LIMIT 1');
        $paymentStmt->execute([$orderId]);
        $payment = $paymentStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        if (!empty($payment['provider_session_id'])) {
            $add(
                $events,
                $payment['created_at'] ?? null,
                'payment',
                'Rozpoczęto płatność',
                strtoupper((string)($payment['provider'] ?? ''))
            );
        }
        $add(
            $events,
            $payment['confirmed_at'] ?? ($order['paid_at'] ?? null),
            'payment',
            'Płatność została potwierdzona',
            strtoupper((string)($payment['provider'] ?? $order['payment_provider'] ?? ''))
        );
        $add($events, $payment['refunded_at'] ?? ($order['refunded_at'] ?? null), 'refund', 'Płatność została zwrócona');

        $shipmentStmt = $pdo->prepare('SELECT * FROM shipments WHERE order_id=? ORDER BY id DESC LIMIT 1');
        $shipmentStmt->execute([$orderId]);
        $shipment = $shipmentStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $add(
            $events,
            $shipment['created_at'] ?? null,
            'shipment',
            'Utworzono przesyłkę InPost',
            (string)($shipment['tracking_number'] ?? '')
        );
        $add(
            $events,
            $shipment['sent_at'] ?? ($order['shipped_at'] ?? null),
            'shipment',
            'Przesyłka została nadana',
            (string)($shipment['tracking_number'] ?? '')
        );
        $add($events, $shipment['delivered_at'] ?? null, 'shipment', 'Przesyłka została doręczona');

        $add($events, $order['completed_at'] ?? null, 'order', 'Zamówienie zostało zrealizowane');
        $add($events, $order['cancelled_at'] ?? null, 'order', 'Zamówienie zostało anulowane');

        $emailStmt = $pdo->prepare(
            'SELECT subject, template, status, created_at, sent_at
             FROM email_logs
             WHERE to_email=? AND subject LIKE ?
             ORDER BY created_at DESC LIMIT 20'
        );
        $emailStmt->execute([
            (string)($order['customer_email'] ?? ''),
            '%' . (string)($order['order_number'] ?? '') . '%',
        ]);
        foreach ($emailStmt->fetchAll(PDO::FETCH_ASSOC) as $email) {
            $add(
                $events,
                $email['sent_at'] ?: ($email['created_at'] ?? null),
                'email',
                ($email['status'] ?? '') === 'sent' ? 'Wysłano e-mail do klienta' : 'E-mail zapisany w kolejce',
                (string)($email['subject'] ?? '')
            );
        }

        usort($events, static function (array $left, array $right): int {
            return strcmp((string)$right['date'], (string)$left['date']);
        });
        $unique = [];
        foreach ($events as $event) {
            $key = ($event['date'] ?? '') . '|' . ($event['title'] ?? '') . '|' . ($event['description'] ?? '');
            $unique[$key] = $event;
        }
        return array_values($unique);
    }

    public function items(int $orderId): array
    {
        return $this->itemsWithPdo(Database::pdo(), $orderId);
    }

    public function downloadableItem(string $token, int $itemId): ?array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT oi.*, o.order_token, o.payment_status, o.status
             FROM order_items oi JOIN orders o ON o.id=oi.order_id
             WHERE o.order_token=? AND oi.id=? AND oi.ebook_file_path IS NOT NULL
             AND oi.ebook_file_path<>'' AND o.payment_status='paid'
             AND o.status IN ('completed','paid_waiting_for_shipment','shipped') LIMIT 1"
        );
        $stmt->execute([$token, $itemId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function releaseExpiredReservations(int $minutes = 45): int
    {
        $minutes = max(30, min(1440, $minutes));
        $cutoff = date('Y-m-d H:i:s', time() - ($minutes * 60));
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            "SELECT id FROM orders
             WHERE payment_status NOT IN ('paid','refund_pending','refunded')
             AND created_at < ? ORDER BY id ASC LIMIT 100"
        );
        $stmt->execute([$cutoff]);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $released = 0;
        foreach ($ids as $orderId) {
            try {
                if ($this->deleteUnpaidOrder($orderId)) $released++;
            } catch (\Throwable $e) {
                throw $e;
            }
        }
        return $released;
    }

    private function hydrate(array $order): array
    {
        $order['items'] = $this->items((int)$order['id']);
        $order['payment'] = $this->paymentForOrder((int)$order['id']);
        return $order;
    }

    private function itemsWithPdo(PDO $pdo, int $orderId): array
    {
        $stmt = $pdo->prepare(
            'SELECT oi.*, b.cover_image, b.slug AS book_slug, b.product_type
             FROM order_items oi
             LEFT JOIN books b ON b.id=oi.book_id
             WHERE oi.order_id=?
             ORDER BY oi.id ASC'
        );
        $stmt->execute([$orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function releaseReservedStock(PDO $pdo, int $orderId): void
    {
        $stmt = $pdo->prepare('SELECT stock_state FROM orders WHERE id = ?');
        $stmt->execute([$orderId]);
        if ($stmt->fetchColumn() !== 'reserved') return;
        $this->restockItems($pdo, $orderId);
        $pdo->prepare("UPDATE orders SET stock_state='released' WHERE id=?")->execute([$orderId]);
    }

    private function restockItems(PDO $pdo, int $orderId): void
    {
        foreach ($this->itemsWithPdo($pdo, $orderId) as $item) {
            if (empty($item['book_id'])) continue;
            if (($item['product_type'] ?? 'paper') === 'ebook') continue;
            $restoredStatus = ($item['sale_mode'] ?? '') === 'preorder' ? 'preorder' : 'active';
            $pdo->prepare(
                "UPDATE books SET stock_qty=stock_qty+?, status=CASE WHEN status='sold_out' THEN ? ELSE status END,
                 updated_at=? WHERE id=? AND manage_stock=1"
            )->execute([(int)$item['quantity'], $restoredStatus, date('Y-m-d H:i:s'), (int)$item['book_id']]);
        }
    }

    private function reserveItemsAgain(PDO $pdo, int $orderId): bool
    {
        $reserved = [];
        foreach ($this->itemsWithPdo($pdo, $orderId) as $item) {
            if (empty($item['book_id'])) continue;
            $bookStmt = $pdo->prepare('SELECT manage_stock, product_type, status FROM books WHERE id = ?');
            $bookStmt->execute([(int)$item['book_id']]);
            $stockBook = $bookStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            if (($stockBook['product_type'] ?? 'paper') === 'ebook' || empty($stockBook['manage_stock'])) continue;
            $quantity = max(1, (int)$item['quantity']);
            $update = $pdo->prepare(
                "UPDATE books SET stock_qty=stock_qty-?,
                 status=CASE WHEN stock_qty-? <= 0 THEN 'sold_out' ELSE status END,
                 updated_at=? WHERE id=? AND status IN ('active','preorder') AND manage_stock=1 AND stock_qty>=?"
            );
            $update->execute([$quantity, $quantity, date('Y-m-d H:i:s'), (int)$item['book_id'], $quantity]);
            if ($update->rowCount() !== 1) {
                foreach ($reserved as $row) {
                    $pdo->prepare(
                        "UPDATE books SET stock_qty=stock_qty+?, status=?, updated_at=? WHERE id=?"
                    )->execute([$row['quantity'], $row['status'], date('Y-m-d H:i:s'), $row['book_id']]);
                }
                return false;
            }
            $reserved[] = [
                'book_id'=>(int)$item['book_id'],
                'quantity'=>$quantity,
                'status'=>(string)($item['sale_mode'] ?? $stockBook['status'] ?? 'active'),
            ];
        }
        return true;
    }

    private function queueStatusEmail(PDO $pdo, int $orderId, string $status): ?int
    {
        $stmt = $pdo->prepare('SELECT order_number, customer_email, payment_status FROM orders WHERE id=? LIMIT 1');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order || !in_array((string)$order['payment_status'], ['paid','refund_pending','refunded'], true)) return null;
        $statusName = \Book100\Core\AdminPresenter::orderStatus($status);
        $template = $status === 'shipped' ? 'order_shipped' : 'order_status_changed';
        return $this->queueEmail(
            $pdo,
            (string)$order['customer_email'],
            'Status zamówienia ' . $order['order_number'] . ': ' . $statusName,
            $template,
            'Status zamówienia ' . $order['order_number'] . ' zmienił się na: ' . $statusName . '.',
            $orderId
        );
    }

    private function queueEmail(
        PDO $pdo,
        string $email,
        string $subject,
        string $template,
        string $body,
        int $orderId
    ): ?int
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return null;
        $subject = Utf8Sanitizer::normalize($subject);
        $body = Utf8Sanitizer::normalize($body);
        $orderStmt = $pdo->prepare('SELECT * FROM orders WHERE id=? LIMIT 1');
        $orderStmt->execute([$orderId]);
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) return null;
        $html = (new EmailTemplate())->order(
            $order,
            $this->itemsWithPdo($pdo, $orderId),
            $subject,
            $body,
            $template
        );
        $pdo->prepare(
            'INSERT INTO email_logs
             (order_id, customer_name, to_email, subject, template, body, status, created_at)
             VALUES (:order_id,:customer_name,:to_email,:subject,:template,:body,:status,:created_at)'
        )->execute([
            ':order_id'=>$orderId,
            ':customer_name'=>(string)($order['customer_name'] ?? ''),
            ':to_email'=>$email, ':subject'=>$subject, ':template'=>$template,
            ':body'=>$html, ':status'=>'queued', ':created_at'=>date('Y-m-d H:i:s'),
        ]);
        return (int)$pdo->lastInsertId();
    }

    private function deliverQueuedEmail(?int $emailLogId): void
    {
        if (!$emailLogId) return;
        try {
            (new Mailer())->processOne($emailLogId);
        } catch (\Throwable) {
            // Zamówienie pozostaje poprawnie zapisane; błąd wysyłki jest widoczny w panelu Maile.
        }
    }

    private function nextOrderNumber(PDO $pdo): string
    {
        $maximum = (int)$pdo->query('SELECT COALESCE(MAX(old_wp_id),0) FROM orders')->fetchColumn();
        $numbers = $pdo->query('SELECT order_number FROM orders')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($numbers as $number) {
            $number = trim((string)$number);
            if ($number !== '' && ctype_digit($number)) {
                $maximum = max($maximum, (int)$number);
            }
        }
        return (string)($maximum + 1);
    }
}
