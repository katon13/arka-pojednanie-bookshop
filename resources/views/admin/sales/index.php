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
    <h1>Sprzedaż</h1>
    <p class="muted">Najważniejsze wyniki miesiąca. Szczegóły i raport dla księgowej znajdziesz niżej.</p>
  </div>
</div>

<section class="panel-section sales-period-panel">
  <form method="get" action="/sales" class="sales-period-form">
    <label class="field">Miesiąc<select name="month">
      <?php foreach ([1=>'styczeń',2=>'luty',3=>'marzec',4=>'kwiecień',5=>'maj',6=>'czerwiec',7=>'lipiec',8=>'sierpień',9=>'wrzesień',10=>'październik',11=>'listopad',12=>'grudzień'] as $number=>$name): ?>
        <option value="<?= $number ?>" <?= $month === $number ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($name)) ?></option>
      <?php endforeach; ?>
    </select></label>
    <label class="field">Rok<input name="year" type="number" min="2020" max="2100" value="<?= $year ?>"></label>
    <button class="btn" type="submit">Pokaż miesiąc</button>
    <div class="sales-period-current">
      <span>Wybrany okres</span>
      <strong><?= htmlspecialchars(ucfirst((string)$period['label'])) ?></strong>
      <small><?= htmlspecialchars($period['start_date']) ?> – <?= htmlspecialchars($period['end_date']) ?></small>
    </div>
  </form>
</section>

<section class="sales-overview" aria-label="Najważniejsze kwoty sprzedaży">
  <article class="sales-kpi sales-kpi--gross">
    <span class="sales-kpi__label">Sprzedaż brutto</span>
    <strong><?= $ui::money($summary['total_gross']) ?></strong>
    <small>Łącznie z dostawą, przed zwrotami</small>
  </article>
  <article class="sales-kpi sales-kpi--final">
    <span class="sales-kpi__label">Sprzedaż po zwrotach</span>
    <strong><?= $ui::money($summary['final_gross']) ?></strong>
    <small>Końcowa kwota brutto za miesiąc</small>
  </article>
  <article class="sales-kpi sales-kpi--net">
    <span class="sales-kpi__label">Sprzedaż netto</span>
    <strong><?= $ui::money($summary['final_net']) ?></strong>
    <small>Kwota końcowa bez VAT</small>
  </article>
  <article class="sales-kpi sales-kpi--vat">
    <span class="sales-kpi__label">VAT</span>
    <strong><?= $ui::money($summary['final_vat']) ?></strong>
    <small>VAT końcowy za wybrany miesiąc</small>
  </article>
</section>

<div class="sales-counts" aria-label="Liczba zamówień i książek">
  <span><strong><?= (int)$summary['paid_orders'] ?></strong><small>opłaconych zamówień</small></span>
  <span><strong><?= (int)$summary['units'] ?></strong><small>sprzedanych książek</small></span>
  <span><strong><?= (int)$summary['returned_units'] ?></strong><small>zwróconych książek</small></span>
</div>

<section class="panel-section sales-breakdown-panel">
  <details class="sales-breakdown">
    <summary>
      <span><span class="section-label">ROZLICZENIE</span><strong>Szczegóły VAT, dostawy, rabatów i zwrotów</strong></span>
      <span class="sales-breakdown__toggle" aria-hidden="true"></span>
    </summary>
    <div class="sales-breakdown__grid">
      <article>
        <h3>Książki po rabatach</h3>
        <dl>
          <div><dt>Netto</dt><dd><?= $ui::money($summary['sales_net']) ?></dd></div>
          <div><dt>VAT</dt><dd><?= $ui::money($summary['sales_vat']) ?></dd></div>
          <div><dt>Brutto</dt><dd><?= $ui::money($summary['sales_gross']) ?></dd></div>
        </dl>
      </article>
      <article>
        <h3>Dostawa i rabaty</h3>
        <dl>
          <div><dt>Dostawa netto</dt><dd><?= $ui::money($summary['shipping_net']) ?></dd></div>
          <div><dt>VAT dostawy</dt><dd><?= $ui::money($summary['shipping_vat']) ?></dd></div>
          <div><dt>Dostawa brutto</dt><dd><?= $ui::money($summary['shipping_gross']) ?></dd></div>
          <div><dt>Rabaty brutto</dt><dd><?= $ui::money($summary['discount_gross']) ?></dd></div>
        </dl>
      </article>
      <article>
        <h3>Zwroty</h3>
        <dl>
          <div><dt>Netto</dt><dd><?= $ui::money($summary['refund_net']) ?></dd></div>
          <div><dt>VAT</dt><dd><?= $ui::money($summary['refund_vat']) ?></dd></div>
          <div><dt>Brutto</dt><dd><?= $ui::money($summary['refund_gross']) ?></dd></div>
        </dl>
      </article>
    </div>
  </details>
</section>

<section class="panel-section sales-items-panel">
  <div class="section-heading">
    <div><p class="section-label">POZYCJE</p><h2>Sprzedaż i zwroty</h2></div>
    <span class="muted">Tylko opłacone zamówienia i wykonane zwroty</span>
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

<section class="panel-section sales-report-panel">
  <div class="sales-report-cta">
    <div>
      <p class="section-label">RAPORT DLA KSIĘGOWEJ</p>
      <h2>Pobierz lub zapisz zestawienie</h2>
      <p class="muted">Raport nie zawiera danych klientów. CSV i XLSX obejmują wybrany wyżej miesiąc.</p>
    </div>
    <div class="sales-report-actions">
      <a class="btn secondary" href="/sales/export?<?= htmlspecialchars($query) ?>">Pobierz CSV</a>
      <a class="btn" href="/sales/export-xlsx?<?= htmlspecialchars($query) ?>">Pobierz XLSX</a>
      <form method="post" action="/sales/reports/generate" data-ajax-refresh>
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">
        <input type="hidden" name="year" value="<?= $year ?>">
        <input type="hidden" name="month" value="<?= $month ?>">
        <input type="hidden" name="recipient_email" value="<?= htmlspecialchars($reportEmail) ?>">
        <button class="btn secondary" type="submit" <?= $closedPeriod ? '' : 'disabled' ?>><?= $closedPeriod ? 'Zapisz w historii' : 'Zapis po zakończeniu miesiąca' ?></button>
      </form>
    </div>
  </div>
  <?php if (!$closedPeriod): ?><p class="sales-report-note">Bieżący miesiąc możesz pobierać na bieżąco. Zapis do historii będzie dostępny po jego zakończeniu.</p><?php endif; ?>

  <div class="sales-report-archive-heading">
    <div><p class="section-label">ARCHIWUM</p><h3>Wygenerowane raporty</h3></div>
    <a class="text-button" href="/settings#sprzedaz">Ustaw cykliczną wysyłkę →</a>
  </div>
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
