<?php include __DIR__ . '/../layout_top.php'; ?>
<?php
$ui = \Book100\Core\AdminPresenter::class;
$shippingAddress = $ui::address($order['shipping_address_json'] ?? null);
$billingAddress = $ui::address($order['billing_address_json'] ?? null);
$addressLines = $ui::addressLines($shippingAddress);
$needsInPost = in_array(($order['delivery_method'] ?? ''), ['inpost_locker', 'inpost_courier'], true);
$preorderReleaseDate = \Book100\Services\Books\BookSaleState::latestPreorderDate($order['items'] ?? []);
$preorderWaiting = \Book100\Services\Books\BookSaleState::preorderWaitsForRelease($order['items'] ?? []);
$canCreateLabel = !$shipment
    && in_array($order['status'], ['paid','paid_waiting_for_shipment'], true)
    && $needsInPost
    && !$preorderWaiting;
$currentStatus = (string)($order['status'] ?? '');
$isPhysicalOrder = ($order['delivery_method'] ?? '') !== 'ebook';
$canManageFulfilment = in_array($order['payment_status'] ?? '', ['paid', 'refunded'], true);
if ($canManageFulfilment) {
    $quickStatuses = $isPhysicalOrder
        ? ['paid_waiting_for_shipment', 'paid_stock_problem']
        : ['completed'];
    if (($order['delivery_method'] ?? '') === 'pickup') {
        $quickStatuses[] = 'completed';
    }
    if ($shipment && $isPhysicalOrder) {
        array_push($quickStatuses, 'shipment_created', 'shipped', 'completed');
    }
} else {
    $quickStatuses = ['payment_pending', 'payment_failed', 'payment_expired'];
}
$quickStatuses[] = 'cancelled';
array_unshift($quickStatuses, $currentStatus);
$quickStatuses = array_values(array_unique(array_filter($quickStatuses)));
?>

<div class="page-heading page-heading--compact order-detail-heading">
  <div>
    <a class="back-link" href="/orders">← Zamówienia</a>
    <p class="kicker">ZAMÓWIENIE</p>
    <h1>#<?= htmlspecialchars($ui::orderId($order)) ?></h1>
    <p class="muted"><?= $ui::date($order['created_at'] ?? null) ?> · <?= $ui::money($order['total_gross'] ?? 0, $order['currency'] ?? 'PLN') ?></p>
  </div>
</div>

<section class="order-workbench" aria-label="Najczęstsze działania dla zamówienia">
  <div class="order-workbench__heading">
    <div>
      <p class="section-label">CODZIENNA OBSŁUGA</p>
      <h2>Status i etykieta</h2>
    </div>
    <span>Najczęstsze działania są w jednym miejscu.</span>
  </div>

  <div class="order-workbench__grid">
    <article class="order-workbench__action">
      <span class="order-workbench__number">1</span>
      <div>
        <p class="section-label">STATUS ZAMÓWIENIA</p>
        <form class="quick-status-form" method="post" action="/orders/<?= (int)$order['id'] ?>/status" data-ajax-refresh>
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">
          <label class="status-select" data-status-tone="<?= htmlspecialchars($ui::tone($currentStatus)) ?>">
            <span class="sr-only">Nowy status zamówienia</span>
            <span class="status-select__dot" aria-hidden="true"></span>
            <select name="status" data-status-select>
              <?php foreach ($quickStatuses as $value): ?>
                <option value="<?= htmlspecialchars($value) ?>" data-tone="<?= htmlspecialchars($ui::tone($value)) ?>" <?= $currentStatus === $value ? 'selected' : '' ?>><?= htmlspecialchars($ui::orderStatus($value)) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <button class="btn" type="submit">Zapisz zmiany</button>
        </form>
        <?php if (in_array($order['payment_status'] ?? '', ['refunded', 'refund_pending', 'cancelled'], true)): ?>
          <small>Zmiana statusu zamówienia nie zmienia wykonanego rozliczenia płatności.</small>
        <?php elseif (!$canManageFulfilment): ?>
          <small>Statusy realizacji pojawią się po potwierdzeniu płatności.</small>
        <?php else: ?>
          <small>Wybierz stan i zapisz — bez otwierania dodatkowej sekcji.</small>
        <?php endif; ?>
      </div>
    </article>

    <article class="order-workbench__action">
      <span class="order-workbench__number">2</span>
      <div>
        <p class="section-label">ETYKIETA INPOST</p>
        <?php if ($shipment): ?>
          <div class="quick-shipment-actions">
            <a class="btn" href="/shipments/<?= (int)$shipment['id'] ?>/label" target="_blank" rel="noopener">Drukuj etykietę PDF A6</a>
            <?php if (!in_array($currentStatus, ['shipped','completed'], true)): ?>
              <form method="post" action="/shipments/<?= (int)$shipment['id'] ?>/sent" data-ajax-refresh>
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">
                <button class="btn secondary" type="submit">Oznacz jako wysłane</button>
              </form>
            <?php endif; ?>
          </div>
          <small>
            <?= htmlspecialchars($ui::shipmentStatus((string)($shipment['status'] ?? 'created'))) ?>
            <?php if (!empty($shipment['tracking_number'])): ?> · <?= htmlspecialchars($shipment['tracking_number']) ?><?php endif; ?>
          </small>
        <?php elseif ($canCreateLabel && $inpostConfigured): ?>
          <form class="quick-shipment-actions" method="post" action="/shipments/<?= (int)$order['id'] ?>/create" target="_blank">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">
            <button class="btn" type="submit">Utwórz i drukuj etykietę</button>
          </form>
          <small>Zostaną użyte domyślne ustawienia InPost. PDF otworzy się w nowej karcie.</small>
        <?php elseif ($preorderWaiting): ?>
          <strong>Przedsprzedaż — jeszcze nie wysyłaj</strong>
          <small>Etykieta będzie dostępna od <?= htmlspecialchars(\Book100\Services\Books\BookSaleState::formattedReleaseDate($preorderReleaseDate)) ?>.</small>
        <?php elseif (($order['delivery_method'] ?? '') === 'pickup'): ?>
          <strong>Odbiór osobisty — bez etykiety</strong>
          <small>Po przygotowaniu książek ustaw zamówienie jako zrealizowane.</small>
        <?php elseif (!$isPhysicalOrder): ?>
          <strong>Ebook — etykieta nie jest potrzebna</strong>
          <small>Plik jest dostarczany elektronicznie po opłaceniu zamówienia.</small>
        <?php elseif ($canCreateLabel): ?>
          <strong>Brak połączenia z InPost</strong>
          <small>Uzupełnij dane ShipX w zakładce <a href="/integrations">Integracje</a>.</small>
        <?php else: ?>
          <strong>Etykieta jeszcze niedostępna</strong>
          <small>Możesz ją utworzyć po potwierdzeniu płatności.</small>
        <?php endif; ?>
      </div>
    </article>
  </div>

  <div class="order-packing-summary">
    <span class="section-label"><?= $isPhysicalOrder ? 'CO WYSŁAĆ' : 'ZAWARTOŚĆ ZAMÓWIENIA' ?></span>
    <div>
      <?php foreach (($order['items'] ?? []) as $item): ?>
        <strong><?= (int)$item['quantity'] ?> × <?= htmlspecialchars($item['title']) ?></strong>
        <?php if (($item['sale_mode'] ?? '') === 'preorder'): ?><small class="preorder-order-label">Przedsprzedaż<?= !empty($item['release_date']) ? ' · wysyłka od ' . htmlspecialchars(\Book100\Services\Books\BookSaleState::formattedReleaseDate((string)$item['release_date'])) : '' ?></small><?php endif; ?>
      <?php endforeach; ?>
    </div>
    <?php if ($isPhysicalOrder): ?>
      <div class="order-packing-summary__destination">
        <span><?= htmlspecialchars($ui::delivery((string)$order['delivery_method'])) ?></span>
        <?php if (!empty($order['inpost_point'])): ?><strong><?= htmlspecialchars($order['inpost_point']) ?></strong><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="status-line" aria-label="Status zamówienia">
  <div><span>Zamówienie</span><strong class="pill pill--<?= $ui::tone((string)$order['status']) ?>"><?= htmlspecialchars($ui::orderStatus((string)$order['status'])) ?></strong></div>
  <div><span>Płatność</span><strong class="pill pill--<?= $ui::tone((string)($order['payment_status'] ?? '')) ?>"><?= htmlspecialchars($ui::paymentStatus((string)($order['payment_status'] ?? ''))) ?></strong></div>
  <div><span>Wysyłka</span><strong><?= htmlspecialchars($ui::shipmentStatus((string)($shipment['status'] ?? $order['shipment_status'] ?? ''))) ?></strong></div>
  <div><span>Kwota</span><strong><?= $ui::money($order['total_gross'] ?? 0, $order['currency'] ?? 'PLN') ?></strong></div>
</section>

<section class="detail-facts">
  <article class="detail-card">
    <p class="section-label">Klient</p>
    <h2><?= htmlspecialchars($order['customer_name']) ?></h2>
    <a href="mailto:<?= htmlspecialchars($order['customer_email']) ?>"><?= htmlspecialchars($order['customer_email']) ?></a>
    <?php if (!empty($order['customer_phone'])): ?><a href="tel:<?= htmlspecialchars($order['customer_phone']) ?>"><?= htmlspecialchars($order['customer_phone']) ?></a><?php endif; ?>
  </article>

  <article class="detail-card">
    <p class="section-label">Dostawa</p>
    <h2><?= htmlspecialchars($ui::delivery((string)$order['delivery_method'])) ?></h2>
    <?php if (!empty($order['inpost_point'])): ?><span class="point-code point-code--large"><?= htmlspecialchars($order['inpost_point']) ?></span><?php endif; ?>
    <?php foreach ($addressLines as $line): ?><span><?= htmlspecialchars($line) ?></span><?php endforeach; ?>
    <?php if (!$addressLines && empty($order['inpost_point']) && !in_array(($order['delivery_method'] ?? ''), ['ebook','pickup'], true)): ?><span class="muted">Brak adresu dostawy</span><?php endif; ?>
    <?php if (($order['delivery_method'] ?? '') === 'pickup'): ?><span class="muted">Bez adresu wysyłki i bez etykiety.</span><?php endif; ?>
  </article>

  <article class="detail-card">
    <p class="section-label">Płatność</p>
    <h2><?= htmlspecialchars(strtoupper((string)($order['payment_provider'] ?? '—'))) ?></h2>
    <?php $payment = $order['payment'] ?? []; ?>
    <span><?= htmlspecialchars($ui::paymentStatus((string)($payment['status'] ?? $order['payment_status'] ?? ''))) ?></span>
    <?php if (!empty($payment['provider_payment_id'])): ?><small>ID: <?= htmlspecialchars($payment['provider_payment_id']) ?></small><?php endif; ?>
  </article>
</section>

<?php if ($needsInPost && !$shipment && $canCreateLabel && $inpostConfigured): ?>
<details class="panel-section collapsible shipping-control">
  <summary>
    <span><span class="section-label">INPOST</span><strong>Inne ustawienia etykiety</strong></span>
    <span>Gabaryt, sposób nadania i ubezpieczenie</span>
  </summary>
  <form class="shipment-create-form shipment-create-form--advanced" method="post" action="/shipments/<?= (int)$order['id'] ?>/create" target="_blank">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">
      <fieldset class="parcel-choice">
        <legend>Gabaryt</legend>
        <?php foreach ([
          'small' => ['A', '8 × 38 × 64 cm'],
          'medium' => ['B', '19 × 38 × 64 cm'],
          'large' => ['C', '41 × 38 × 64 cm'],
        ] as $value => $parcel): ?>
          <label>
            <input type="radio" name="parcel_template" value="<?= $value ?>" <?= ($inpostConfig['default_parcel_template'] ?? 'small') === $value ? 'checked' : '' ?>>
            <strong><?= $parcel[0] ?></strong><span><?= $parcel[1] ?></span>
          </label>
        <?php endforeach; ?>
      </fieldset>
      <label class="field">Sposób nadania
        <select name="sending_method">
          <option value="any_point" <?= ($inpostConfig['default_sending_method'] ?? '') === 'any_point' ? 'selected' : '' ?>>Dowolny punkt InPost</option>
          <option value="parcel_locker" <?= ($inpostConfig['default_sending_method'] ?? '') === 'parcel_locker' ? 'selected' : '' ?>>Paczkomat</option>
          <option value="pop" <?= ($inpostConfig['default_sending_method'] ?? '') === 'pop' ? 'selected' : '' ?>>Punkt Obsługi Paczek</option>
          <option value="branch" <?= ($inpostConfig['default_sending_method'] ?? '') === 'branch' ? 'selected' : '' ?>>Oddział</option>
          <option value="dispatch_order" <?= ($inpostConfig['default_sending_method'] ?? '') === 'dispatch_order' ? 'selected' : '' ?>>Podjazd kuriera</option>
        </select>
      </label>
      <label class="field">Ubezpieczenie
        <input name="insurance" value="0" inputmode="decimal">
        <small>0 = bez dodatkowego ubezpieczenia</small>
      </label>
      <label class="field">Numer referencyjny
        <input name="reference" value="Zamówienie <?= htmlspecialchars($ui::orderId($order)) ?>" maxlength="100">
      </label>
      <button class="btn" type="submit">Utwórz i drukuj etykietę</button>
  </form>
</details>
<?php endif; ?>

<section class="panel-section">
  <div class="section-heading">
    <div><p class="section-label">DO SPAKOWANIA</p><h2>Książki w zamówieniu</h2></div>
    <strong><?= count($order['items'] ?? []) ?> <?= count($order['items'] ?? []) === 1 ? 'pozycja' : 'pozycje' ?></strong>
  </div>
  <div class="order-item-list">
    <?php foreach (($order['items'] ?? []) as $item): ?>
      <div class="order-item-row">
        <?php if (!empty($item['cover_image'])): ?><img src="<?= htmlspecialchars($ui::publicAsset($item['cover_image'])) ?>" alt=""><?php else: ?><span class="cover-placeholder cover-placeholder--large">100</span><?php endif; ?>
        <div class="order-item-row__title">
          <strong><?= htmlspecialchars($item['title']) ?></strong>
          <?php if (($item['sale_mode'] ?? '') === 'preorder'): ?><small class="preorder-order-label">Przedsprzedaż<?= !empty($item['release_date']) ? ' · premiera ' . htmlspecialchars(\Book100\Services\Books\BookSaleState::formattedReleaseDate((string)$item['release_date'])) : '' ?></small><?php endif; ?>
          <small><?= !empty($item['sku']) ? 'SKU ' . htmlspecialchars($item['sku']) : 'Bez SKU' ?></small>
        </div>
        <span class="quantity-badge"><?= (int)$item['quantity'] ?> szt.</span>
        <span><?= $ui::money($item['unit_price_gross'] ?? 0) ?></span>
        <strong><?= $ui::money($item['total_gross'] ?? 0) ?></strong>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="totals">
    <span>Produkty <strong><?= $ui::money($order['subtotal_gross'] ?? 0) ?></strong></span>
    <?php if ((float)($order['discount_gross'] ?? 0) > 0): ?><span>Rabat <strong>−<?= $ui::money($order['discount_gross']) ?></strong></span><?php endif; ?>
    <span>Dostawa <strong><?= $ui::money($order['shipping_gross'] ?? 0) ?></strong></span>
    <span class="totals__grand">Razem <strong><?= $ui::money($order['total_gross'] ?? 0, $order['currency'] ?? 'PLN') ?></strong></span>
  </div>
</section>

<section class="panel-section">
  <div class="section-heading"><div><p class="section-label">PRZEBIEG</p><h2>Historia zamówienia</h2></div><span class="muted">Tylko zdarzenia zapisane w systemie</span></div>
  <div class="timeline-list">
    <?php foreach ($timeline as $event): ?>
      <div class="timeline-event">
        <time><?= $ui::date($event['date'] ?? null) ?></time>
        <span class="timeline-event__dot timeline-event__dot--<?= htmlspecialchars($event['type'] ?? 'order') ?>"></span>
        <div><strong><?= htmlspecialchars($event['title'] ?? '') ?></strong><?php if (!empty($event['description'])): ?><p><?= htmlspecialchars($event['description']) ?></p><?php endif; ?></div>
      </div>
    <?php endforeach; ?>
    <?php if (!$timeline): ?><div class="empty-state">Brak zapisanych zdarzeń.</div><?php endif; ?>
  </div>
</section>

<details class="panel-section collapsible">
  <summary><span><span class="section-label">EDYCJA</span><strong>Dane klienta i notatka</strong></span><span>Rozwiń</span></summary>
  <form class="form order-edit-form" method="post" action="/orders/<?= (int)$order['id'] ?>" data-ajax-refresh>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">
    <input type="hidden" name="status" value="<?= htmlspecialchars($currentStatus) ?>">
    <div class="two">
      <label class="field">Klient<input name="customer_name" value="<?= htmlspecialchars($order['customer_name']) ?>"></label>
      <label class="field">E-mail<input name="customer_email" type="email" value="<?= htmlspecialchars($order['customer_email']) ?>"></label>
      <label class="field">Telefon<input name="customer_phone" value="<?= htmlspecialchars($order['customer_phone'] ?? '') ?>"></label>
      <label class="field">Punkt InPost<input name="inpost_point" value="<?= htmlspecialchars($order['inpost_point'] ?? '') ?>"></label>
    </div>
    <label class="field">Notatka administratora<textarea name="admin_note" rows="4"><?= htmlspecialchars($order['admin_note'] ?? '') ?></textarea></label>
    <button class="btn" type="submit">Zapisz zmiany</button>
  </form>
</details>

<details class="panel-section collapsible collapsible--danger">
  <summary><span><span class="section-label">PŁATNOŚĆ</span><strong>Anulowanie i zwrot</strong></span><span>Rozwiń</span></summary>
  <?php if (($order['payment_status'] ?? '') === 'paid'): ?>
    <form method="post" action="/orders/<?= (int)$order['id'] ?>/refund" data-ajax-refresh onsubmit="return confirm('Zlecić pełny zwrot przez operatora płatności?')">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">
      <label><input type="checkbox" name="restock" value="1"> Przywróć egzemplarze do magazynu</label>
      <button class="danger" type="submit">Zwróć całą płatność</button>
    </form>
  <?php elseif (($order['payment_status'] ?? '') === 'refund_pending'): ?>
    <p class="notice">Operator przyjął zwrot. Status zmieni się po potwierdzonym webhooku końcowym.</p>
  <?php elseif (!in_array($order['status'], ['cancelled','refunded','archived'], true)): ?>
    <form method="post" action="/orders/<?= (int)$order['id'] ?>/cancel" data-ajax-refresh onsubmit="return confirm('Anulować zamówienie i zwolnić rezerwację magazynową?')">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">
      <label class="field">Powód anulowania<input name="note" placeholder="Opcjonalnie"></label>
      <button class="danger" type="submit">Anuluj zamówienie</button>
    </form>
  <?php else: ?>
    <p class="muted">Brak dostępnych operacji płatniczych dla tego zamówienia.</p>
  <?php endif; ?>
</details>

<?php include __DIR__ . '/../layout_bottom.php'; ?>
