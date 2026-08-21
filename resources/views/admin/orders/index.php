<?php include __DIR__ . '/../layout_top.php'; ?>
<?php $ui = \Book100\Core\AdminPresenter::class; ?>

<div class="page-heading page-heading--compact">
  <div>
    <p class="kicker">CODZIENNA OBSŁUGA</p>
    <h1>Zamówienia</h1>
    <p class="muted">Od razu widzisz, co spakować, dokąd wysłać i czy etykieta jest gotowa.</p>
  </div>
  <a class="btn secondary" href="/shipments">Tylko wysyłka</a>
</div>

<nav class="status-tabs" aria-label="Szybkie filtry zamówień">
  <a class="<?= empty($filters['status']) ? 'active' : '' ?>" href="/orders">
    Wszystkie <strong><?= (int)($stats['total'] ?? 0) ?></strong>
  </a>
  <a class="<?= ($filters['status'] ?? '') === 'paid_waiting_for_shipment' ? 'active' : '' ?>" href="/orders?status=paid_waiting_for_shipment">
    Do wysłania <strong><?= (int)($stats['ready'] ?? 0) ?></strong>
  </a>
  <a class="<?= ($filters['status'] ?? '') === 'shipment_created' ? 'active' : '' ?>" href="/orders?status=shipment_created">
    Etykiety gotowe <strong><?= (int)($stats['labels'] ?? 0) ?></strong>
  </a>
  <a class="<?= ($filters['status'] ?? '') === 'completed' ? 'active' : '' ?>" href="/orders?status=completed">
    Zrealizowane <strong><?= (int)($stats['done'] ?? 0) ?></strong>
  </a>
</nav>

<form class="filter-bar" method="get">
  <label class="field">
    <span class="sr-only">Szukaj zamówienia</span>
    <input name="q" value="<?= htmlspecialchars($filters['q'] ?? '') ?>" placeholder="Numer, klient lub e-mail">
  </label>
  <label class="field">
    <span class="sr-only">Status</span>
    <select name="status">
      <option value="">Wszystkie statusy</option>
      <?php foreach ([
        'paid_waiting_for_shipment'=>'Do wysłania',
        'paid_stock_problem'=>'Brak towaru',
        'shipment_created'=>'Etykieta gotowa',
        'shipped'=>'Wysłane',
        'completed'=>'Zrealizowane',
        'refund_pending'=>'Zwrot w toku',
        'cancelled'=>'Anulowane',
        'refunded'=>'Zwrócone',
        'archived'=>'Archiwalne',
      ] as $value=>$label): ?>
        <option value="<?= htmlspecialchars($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <button class="btn" type="submit">Pokaż</button>
  <?php if (!empty($filters['q']) || !empty($filters['status'])): ?><a class="btn secondary" href="/orders">Wyczyść</a><?php endif; ?>
</form>

<div class="table-shell">
  <table class="admin-table admin-table--orders">
    <thead>
      <tr>
        <th>Zamówienie</th>
        <th>Co wysłać</th>
        <th>Dostawa</th>
        <th>Status</th>
        <th class="cell-action">Etykieta</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($orders as $order):
      $orderItems = $items[(int)$order['id']] ?? [];
      $shipment = $shipments[(int)$order['id']] ?? null;
      $preorderWaiting = \Book100\Services\Books\BookSaleState::preorderWaitsForRelease($orderItems);
      $canCreateLabel = !$shipment
        && in_array($order['status'], ['paid','paid_waiting_for_shipment'], true)
        && in_array(($order['delivery_method'] ?? ''), ['inpost_locker','inpost_courier'], true)
        && !$preorderWaiting;
    ?>
      <tr>
        <td class="order-identity">
          <a class="order-number" href="/orders/<?= (int)$order['id'] ?>">#<?= htmlspecialchars($ui::orderId($order)) ?></a>
          <strong><?= htmlspecialchars($order['customer_name']) ?></strong>
          <small><?= $ui::date($order['created_at'] ?? null) ?> · <?= $ui::money($order['total_gross'] ?? 0, $order['currency'] ?? 'PLN') ?></small>
        </td>
        <td>
          <div class="order-products">
            <?php foreach ($orderItems as $item): ?>
              <div class="order-product">
                <?php if (!empty($item['cover_image'])): ?>
                  <img src="<?= htmlspecialchars($ui::publicAsset($item['cover_image'])) ?>" alt="">
                <?php else: ?>
                  <span class="cover-placeholder" aria-hidden="true">100</span>
                <?php endif; ?>
                <span>
                  <strong><?= (int)$item['quantity'] ?> × <?= htmlspecialchars($item['title']) ?></strong>
                  <?php if (($item['sale_mode'] ?? '') === 'preorder'): ?><small class="preorder-order-label">Przedsprzedaż<?= !empty($item['release_date']) ? ' · ' . htmlspecialchars(\Book100\Services\Books\BookSaleState::formattedReleaseDate((string)$item['release_date'])) : '' ?></small><?php elseif (!empty($item['sku'])): ?><small>SKU <?= htmlspecialchars($item['sku']) ?></small><?php endif; ?>
                </span>
              </div>
            <?php endforeach; ?>
            <?php if (!$orderItems): ?><span class="muted">Brak pozycji</span><?php endif; ?>
          </div>
        </td>
        <td class="delivery-cell">
          <strong><?= htmlspecialchars($ui::delivery((string)($order['delivery_method'] ?? ''))) ?></strong>
          <?php if (!empty($order['inpost_point'])): ?><span class="point-code"><?= htmlspecialchars($order['inpost_point']) ?></span><?php endif; ?>
          <small><?= htmlspecialchars($order['customer_phone'] ?? '') ?></small>
        </td>
        <td>
          <span class="pill pill--<?= $ui::tone((string)($order['status'] ?? '')) ?>"><?= htmlspecialchars($ui::orderStatus((string)($order['status'] ?? ''))) ?></span>
          <small class="status-detail"><?= htmlspecialchars($ui::paymentStatus((string)($order['payment_status'] ?? ''))) ?></small>
          <?php if ($shipment && !empty($shipment['tracking_number'])): ?><small><?= htmlspecialchars($shipment['tracking_number']) ?></small><?php endif; ?>
        </td>
        <td class="cell-action">
          <?php if ($shipment): ?>
            <a class="btn small" href="/shipments/<?= (int)$shipment['id'] ?>/label">Pobierz etykietę</a>
          <?php elseif ($preorderWaiting): ?>
            <span class="preorder-label-wait">Etykieta od<br><strong><?= htmlspecialchars(\Book100\Services\Books\BookSaleState::formattedReleaseDate(\Book100\Services\Books\BookSaleState::latestPreorderDate($orderItems))) ?></strong></span>
          <?php elseif ($canCreateLabel && $inpostConfigured): ?>
            <form method="post" action="/shipments/<?= (int)$order['id'] ?>/create" target="_blank">
              <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">
              <button class="btn small" type="submit">Utwórz i pobierz</button>
            </form>
          <?php elseif ($canCreateLabel): ?>
            <span class="muted">Czeka na dane InPost</span>
          <?php else: ?>
            <a class="text-link" href="/orders/<?= (int)$order['id'] ?>">Szczegóły →</a>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$orders): ?><tr><td colspan="5" class="empty-state">Brak zamówień spełniających wybrane kryteria.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php if (($pagination['pages'] ?? 1) > 1):
  $lastPage = (int)$pagination['pages'];
  $currentPage = (int)$pagination['page'];
  $pageNumbers = array_unique(array_filter([1, $currentPage-2, $currentPage-1, $currentPage, $currentPage+1, $currentPage+2, $lastPage], static fn(int $page): bool => $page >= 1 && $page <= $lastPage));
  sort($pageNumbers);
?>
  <nav class="pagination" aria-label="Strony zamówień">
    <?php $previousPage = 0; foreach ($pageNumbers as $p): ?>
      <?php if ($previousPage && $p > $previousPage + 1): ?><span>…</span><?php endif; ?>
      <a class="<?= $p===$currentPage?'active':'' ?>" href="?<?= http_build_query(['q'=>$filters['q'] ?? '', 'status'=>$filters['status'] ?? '', 'page'=>$p]) ?>"><?= $p ?></a>
    <?php $previousPage = $p; endforeach; ?>
  </nav>
<?php endif; ?>

<?php include __DIR__ . '/../layout_bottom.php'; ?>
