<?php include __DIR__ . '/../layout_top.php'; ?>
<?php $ui = \Book100\Core\AdminPresenter::class; ?>

<div class="page-heading page-heading--compact">
  <div>
    <p class="kicker">PAKOWANIE I ETYKIETY</p>
    <h1>Wysyłka</h1>
    <p class="muted">Krótka lista pracy: książka, liczba sztuk, odbiorca, status i etykieta.</p>
  </div>
  <a class="btn secondary" href="/orders">Wszystkie zamówienia</a>
</div>

<div class="ops-summary" aria-label="Podsumowanie wysyłki">
  <span><strong><?= (int)($stats['ready'] ?? 0) ?></strong> do utworzenia etykiety</span>
  <span><strong><?= (int)($stats['labels'] ?? 0) ?></strong> z gotową etykietą</span>
  <span><strong><?= (int)($stats['sent'] ?? 0) ?></strong> wysłanych</span>
  <span class="<?= $inpostConfigured ? 'config-ok' : 'config-waiting' ?>"><?= $inpostConfigured ? 'InPost połączony' : 'InPost czeka na dane dostępowe' ?></span>
</div>

<?php if (!$inpostConfigured): ?>
  <div class="notice notice--plain">
    Panel jest gotowy, ale nie wyśle nic do InPost bez tokenu API i ID organizacji. Po otrzymaniu danych aktywuję generowanie prawdziwych etykiet.
  </div>
<?php endif; ?>

<div class="shipping-list">
  <?php foreach ($orders as $order):
    $shipment = $shipments[(int)$order['id']] ?? null;
    $orderItems = $items[(int)$order['id']] ?? [];
    $canCreate = !$shipment
      && in_array($order['status'], ['paid','paid_waiting_for_shipment'], true)
      && in_array(($order['delivery_method'] ?? ''), ['inpost_locker','inpost_courier'], true);
  ?>
    <article class="shipping-row">
      <div class="shipping-row__order">
        <a href="/orders/<?= (int)$order['id'] ?>">#<?= htmlspecialchars($ui::orderId($order)) ?></a>
        <strong><?= htmlspecialchars($order['customer_name']) ?></strong>
        <small><?= $ui::date($order['created_at'] ?? null) ?></small>
      </div>

      <div class="shipping-row__books">
        <?php foreach ($orderItems as $item): ?>
          <div class="order-product">
            <?php if (!empty($item['cover_image'])): ?><img src="<?= htmlspecialchars($ui::publicAsset($item['cover_image'])) ?>" alt=""><?php else: ?><span class="cover-placeholder">100</span><?php endif; ?>
            <span><strong><?= (int)$item['quantity'] ?> × <?= htmlspecialchars($item['title']) ?></strong><?php if (!empty($item['sku'])): ?><small>SKU <?= htmlspecialchars($item['sku']) ?></small><?php endif; ?></span>
          </div>
        <?php endforeach; ?>
        <?php if (!$orderItems): ?><span class="muted">Brak pozycji</span><?php endif; ?>
      </div>

      <div class="shipping-row__address">
        <strong><?= htmlspecialchars($ui::delivery((string)($order['delivery_method'] ?? ''))) ?></strong>
        <?php if (!empty($order['inpost_point'])): ?><span class="point-code"><?= htmlspecialchars($order['inpost_point']) ?></span><?php endif; ?>
        <small><?= htmlspecialchars($order['customer_phone'] ?? '') ?></small>
      </div>

      <div class="shipping-row__status">
        <span class="pill pill--<?= $ui::tone((string)($shipment['status'] ?? $order['status'] ?? '')) ?>">
          <?= htmlspecialchars($shipment
            ? $ui::shipmentStatus((string)($shipment['status'] ?? 'created'))
            : $ui::orderStatus((string)($order['status'] ?? ''))) ?>
        </span>
        <?php if ($shipment && !empty($shipment['tracking_number'])): ?><small><?= htmlspecialchars($shipment['tracking_number']) ?></small><?php endif; ?>
      </div>

      <div class="shipping-row__action">
        <?php if ($shipment): ?>
          <a class="btn small" href="/shipments/<?= (int)$shipment['id'] ?>/label" target="_blank" rel="noopener">Drukuj etykietę</a>
          <?php if (!in_array($order['status'] ?? '', ['shipped','completed'], true)): ?>
            <form method="post" action="/shipments/<?= (int)$shipment['id'] ?>/sent" data-ajax-refresh>
              <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">
              <button class="text-button" type="submit">Oznacz jako wysłane</button>
            </form>
          <?php endif; ?>
        <?php elseif ($canCreate && $inpostConfigured): ?>
          <form method="post" action="/shipments/<?= (int)$order['id'] ?>/create" target="_blank">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">
            <button class="btn small" type="submit">Utwórz i drukuj etykietę</button>
          </form>
        <?php elseif ($canCreate): ?>
          <span class="muted">Czeka na konfigurację</span>
        <?php else: ?>
          <span class="muted">Brak akcji</span>
        <?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>

  <?php if (!$orders): ?>
    <div class="empty-state">Nie ma teraz żadnych przesyłek do obsługi.</div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../layout_bottom.php'; ?>
