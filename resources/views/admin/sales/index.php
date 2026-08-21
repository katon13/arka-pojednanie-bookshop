<?php include __DIR__ . '/../layout_top.php'; ?>
<?php $ui = \Book100\Core\AdminPresenter::class; ?>

<div class="page-heading page-heading--compact">
  <div>
    <p class="kicker">SPRZEDAŻ</p>
    <h1>Sprzedane książki</h1>
    <p class="muted">Czytelna historia pozycji, płatności i dostaw.</p>
  </div>
  <a class="btn secondary" href="/sales/export">Eksport CSV</a>
</div>

<div class="ops-summary">
  <span><strong><?= (int)($stats['paid_orders'] ?? 0) ?></strong> opłaconych zamówień</span>
  <span><strong><?= (int)($stats['units'] ?? 0) ?></strong> sprzedanych sztuk</span>
  <span><strong><?= $ui::money($stats['revenue'] ?? 0) ?></strong> przychodu</span>
  <span><strong><?= (int)($stats['refunds'] ?? 0) ?></strong> zwrotów</span>
</div>

<div class="table-shell">
  <table class="admin-table sales-table">
    <thead><tr><th>Książka</th><th>Zamówienie</th><th>Ilość</th><th>Wartość</th><th>Płatność</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $row): ?>
      <tr>
        <td>
          <div class="order-product">
            <?php if (!empty($row['cover_image'])): ?><img src="<?= htmlspecialchars($ui::publicAsset($row['cover_image'])) ?>" alt=""><?php else: ?><span class="cover-placeholder">100</span><?php endif; ?>
            <span><strong><?= htmlspecialchars($row['title']) ?></strong><?php if (!empty($row['sku'])): ?><small>SKU <?= htmlspecialchars($row['sku']) ?></small><?php endif; ?></span>
          </div>
        </td>
        <td><a class="order-number" href="/orders/<?= (int)$row['order_id'] ?>">#<?= htmlspecialchars($ui::orderId($row)) ?></a><small class="table-subline"><?= $ui::date($row['created_at'] ?? null) ?></small></td>
        <td><strong><?= (int)$row['quantity'] ?> szt.</strong></td>
        <td><strong><?= $ui::money($row['item_total'] ?? 0) ?></strong></td>
        <td><?= htmlspecialchars(strtoupper((string)($row['payment_provider'] ?? '—'))) ?><small class="table-subline"><?= htmlspecialchars($ui::paymentStatus((string)($row['payment_status'] ?? ''))) ?></small></td>
        <td><span class="pill pill--<?= $ui::tone((string)($row['status'] ?? '')) ?>"><?= htmlspecialchars($ui::orderStatus((string)($row['status'] ?? ''))) ?></span></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6" class="empty-state">Brak sprzedaży.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/../layout_bottom.php'; ?>
