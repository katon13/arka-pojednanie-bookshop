<?php include __DIR__ . '/../layout_top.php'; ?>
<?php
$ui = \Book100\Core\AdminPresenter::class;
$period = $dataset['period'];
$summary = $dataset['summary'];
$rows = $dataset['rows'];
$year = (int)$period['year'];
$month = (int)$period['month'];
$query = 'year=' . $year . '&month=' . $month;
$reportEmail = trim((string)($reportSettings['sales_report_email'] ?? ''));
$closedPeriod = new \DateTimeImmutable($period['end']) <= new \DateTimeImmutable('first day of this month 00:00:00');
?>

<div class="page-heading page-heading--compact">
  <div>
    <p class="kicker">SPRZEDAŻ</p>
    <h1>Raport dla księgowości</h1>
    <p class="muted">Jedno zestawienie netto, VAT i brutto dla wybranego miesiąca. Bez danych klientów.</p>
  </div>
  <div class="page-actions">
    <a class="btn secondary" href="/sales/export?<?= htmlspecialchars($query) ?>">CSV</a>
    <a class="btn secondary" href="/sales/export-xlsx?<?= htmlspecialchars($query) ?>">XLSX</a>
  </div>
</div>

<section class="panel-section">
  <form method="get" action="/sales" class="settings-grid settings-grid--three">
    <label class="field">Miesiąc<select name="month">
      <?php foreach ([1=>'styczeń',2=>'luty',3=>'marzec',4=>'kwiecień',5=>'maj',6=>'czerwiec',7=>'lipiec',8=>'sierpień',9=>'wrzesień',10=>'październik',11=>'listopad',12=>'grudzień'] as $number=>$name): ?>
        <option value="<?= $number ?>" <?= $month === $number ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($name)) ?></option>
      <?php endforeach; ?>
    </select></label>
    <label class="field">Rok<input name="year" type="number" min="2020" max="2100" value="<?= $year ?>"></label>
    <button class="btn" type="submit">Pokaż raport</button>
  </form>
</section>

<div class="ops-summary">
  <span><strong><?= (int)$summary['paid_orders'] ?></strong> opłaconych zamówień</span>
  <span><strong><?= (int)$summary['units'] ?></strong> sprzedanych książek</span>
  <span><strong><?= $ui::money($summary['total_gross']) ?></strong> sprzedaży brutto</span>
  <span><strong><?= $ui::money($summary['final_gross']) ?></strong> po zwrotach</span>
</div>

<section class="panel-section">
  <div class="section-heading"><div><p class="section-label">PODSUMOWANIE</p><h2><?= htmlspecialchars(ucfirst((string)$period['label'])) ?></h2></div><span class="muted"><?= htmlspecialchars($period['start_date']) ?> – <?= htmlspecialchars($period['end_date']) ?></span></div>
  <div class="stats-grid">
    <?php foreach ([
      'Produkty netto po rabatach'=>$summary['sales_net'],
      'VAT produktów'=>$summary['sales_vat'],
      'Produkty brutto po rabatach'=>$summary['sales_gross'],
      'Rabaty brutto'=>$summary['discount_gross'],
      'Wysyłka netto'=>$summary['shipping_net'],
      'VAT wysyłki'=>$summary['shipping_vat'],
      'Wysyłka brutto'=>$summary['shipping_gross'],
      'Zwroty netto'=>$summary['refund_net'],
      'Zwroty VAT'=>$summary['refund_vat'],
      'Zwroty brutto'=>$summary['refund_gross'],
      'Sprzedaż końcowa netto'=>$summary['final_net'],
      'VAT końcowy'=>$summary['final_vat'],
      'Sprzedaż końcowa brutto'=>$summary['final_gross'],
    ] as $label=>$value): ?>
      <article class="stat-card"><span><?= htmlspecialchars($label) ?></span><strong><?= $ui::money($value) ?></strong></article>
    <?php endforeach; ?>
  </div>
</section>

<section class="panel-section">
  <div class="section-heading">
    <div><p class="section-label">POZYCJE</p><h2>Sprzedaż i zwroty</h2></div>
    <form method="post" action="/sales/reports/generate" data-ajax-refresh>
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">
      <input type="hidden" name="year" value="<?= $year ?>">
      <input type="hidden" name="month" value="<?= $month ?>">
      <input type="hidden" name="recipient_email" value="<?= htmlspecialchars($reportEmail) ?>">
      <button class="btn secondary" type="submit" <?= $closedPeriod ? '' : 'disabled' ?>><?= $closedPeriod ? 'Zapisz raport w historii' : 'Raport po zakończeniu miesiąca' ?></button>
    </form>
  </div>
  <div class="table-shell">
    <table class="admin-table sales-table">
      <thead><tr><th>Data</th><th>Typ</th><th>Zamówienie</th><th>Książka / SKU</th><th>Ilość</th><th>Netto</th><th>VAT</th><th>Brutto</th><th>Rabat</th><th>Wysyłka brutto</th><th>Suma</th><th>Płatność</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td><?= htmlspecialchars($row['date']) ?></td>
          <td><span class="pill pill--<?= $row['type'] === 'ZWROT' ? 'danger' : 'success' ?>"><?= htmlspecialchars($row['type']) ?></span></td>
          <td><a class="order-number" href="/orders/<?= (int)$row['order_id'] ?>"><?= htmlspecialchars($row['order_number']) ?></a></td>
          <td><strong><?= htmlspecialchars($row['title']) ?></strong><small class="table-subline"><?= $row['sku'] !== '' ? 'SKU ' . htmlspecialchars($row['sku']) : 'Bez SKU' ?></small></td>
          <td><strong><?= (int)$row['quantity'] ?></strong></td>
          <td><?= $ui::money(\Book100\Services\Sales\Money::decimal((int)$row['item_net_cents'])) ?></td>
          <td><?= $ui::money(\Book100\Services\Sales\Money::decimal((int)$row['item_vat_cents'])) ?><small class="table-subline"><?= htmlspecialchars($row['vat_rate']) ?>%</small></td>
          <td><strong><?= $ui::money(\Book100\Services\Sales\Money::decimal((int)$row['item_gross_cents'])) ?></strong></td>
          <td><?= $ui::money(\Book100\Services\Sales\Money::decimal((int)$row['discount_gross_cents'])) ?></td>
          <td><?= $ui::money(\Book100\Services\Sales\Money::decimal((int)$row['shipping_gross_cents'])) ?></td>
          <td><strong><?= $ui::money(\Book100\Services\Sales\Money::decimal((int)$row['order_total_gross_cents'])) ?></strong></td>
          <td><?= htmlspecialchars(strtoupper($row['payment_provider'] ?: '—')) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="12" class="empty-state">Brak opłaconej sprzedaży i zwrotów w tym miesiącu.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="panel-section">
  <div class="section-heading"><div><p class="section-label">ARCHIWUM</p><h2>Wygenerowane raporty</h2></div><a class="text-button" href="/settings#sprzedaz">Ustaw cykliczną wysyłkę →</a></div>
  <div class="table-shell">
    <table class="admin-table">
      <thead><tr><th>Okres</th><th>Wygenerowano</th><th>Netto</th><th>VAT</th><th>Brutto</th><th>Wysyłka</th><th>Odbiorca</th><th>Akcje</th></tr></thead>
      <tbody>
      <?php foreach ($reports as $report): ?>
        <?php $sendStatus = (string)($report['send_status'] ?? 'not_sent'); ?>
        <tr>
          <td><strong><?= sprintf('%04d-%02d', (int)$report['period_year'], (int)$report['period_month']) ?></strong><small class="table-subline"><?= htmlspecialchars((string)$report['period_start']) ?> – <?= htmlspecialchars((string)$report['period_end']) ?></small></td>
          <td><?= $ui::date($report['generated_at'] ?? $report['created_at'] ?? null) ?></td>
          <td><?= $ui::money($report['final_net'] ?? 0) ?></td>
          <td><?= $ui::money($report['final_vat'] ?? 0) ?></td>
          <td><strong><?= $ui::money($report['final_gross'] ?? 0) ?></strong></td>
          <td><span class="pill pill--<?= $sendStatus === 'sent' ? 'success' : ($sendStatus === 'failed' ? 'danger' : 'neutral') ?>"><?= htmlspecialchars(['sent'=>'wysłany','failed'=>'błąd','queued'=>'w kolejce','not_sent'=>'niewysłany'][$sendStatus] ?? $sendStatus) ?></span><?php if (!empty($report['last_error'])): ?><small class="table-subline"><?= htmlspecialchars($report['last_error']) ?></small><?php endif; ?></td>
          <td><?= htmlspecialchars((string)($report['recipient_email'] ?? '—')) ?></td>
          <td>
            <?php if (($report['status'] ?? '') === 'generated'): ?><a class="btn secondary" href="/sales/reports/<?= (int)$report['id'] ?>/download">Pobierz</a><?php endif; ?>
            <form method="post" action="/sales/reports/<?= (int)$report['id'] ?>/resend" data-ajax-refresh style="display:inline">
              <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">
              <input type="hidden" name="recipient_email" value="<?= htmlspecialchars((string)(($report['recipient_email'] ?? '') ?: $reportEmail)) ?>">
              <button class="btn secondary" type="submit" <?= (($report['status'] ?? '') !== 'generated' || (($report['recipient_email'] ?? '') ?: $reportEmail) === '') ? 'disabled' : '' ?>>Wyślij ponownie</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$reports): ?><tr><td colspan="8" class="empty-state">Nie wygenerowano jeszcze żadnego raportu.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php include __DIR__ . '/../layout_bottom.php'; ?>
