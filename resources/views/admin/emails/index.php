<?php include __DIR__ . '/../layout_top.php'; ?>
<?php
$rows = $result['rows'] ?? [];
$stats = $result['stats'] ?? [];
$labels = [
    'order_created' => 'Zamówienie przyjęte',
    'payment_paid' => 'Płatność potwierdzona',
    'order_status_changed' => 'Zmiana statusu',
    'shipment_created' => 'Etykieta utworzona',
    'order_shipped' => 'Zamówienie wysłane',
    'order_cancelled' => 'Anulowanie',
    'payment_refunded' => 'Zwrot płatności',
    'system_test' => 'Test systemu',
    'newsletter' => 'Newsletter',
];
?>

<div class="page-heading page-heading--compact">
  <div>
    <p class="kicker">HISTORIA KOMUNIKACJI</p>
    <h1>Maile</h1>
    <p class="muted">Wiadomości do klientów, stan wysyłki i podgląd dokładnej treści w jednym miejscu.</p>
  </div>
  <a class="btn secondary" href="/integrations#mail">Ustawienia poczty</a>
</div>

<div class="mail-stats" aria-label="Stan wiadomości">
  <a href="/emails"><span>Wszystkie</span><strong><?= (int)($stats['total'] ?? 0) ?></strong></a>
  <a href="/emails?status=sent"><span>Wysłane</span><strong><?= (int)($stats['sent'] ?? 0) ?></strong></a>
  <a href="/emails?status=queued"><span>Oczekują</span><strong><?= (int)($stats['waiting'] ?? 0) ?></strong></a>
  <a href="/emails?status=failed_retry"><span>Do poprawy</span><strong><?= (int)($stats['failed'] ?? 0) ?></strong></a>
</div>

<form class="filter-bar mail-filter" method="get">
  <label class="field">
    <span class="sr-only">Szukaj wiadomości</span>
    <input name="q" value="<?= htmlspecialchars($query) ?>" placeholder="Odbiorca, klient lub temat">
  </label>
  <label class="field">
    <span class="sr-only">Status</span>
    <select name="status">
      <option value="">Wszystkie stany</option>
      <option value="sent" <?= $status === 'sent' ? 'selected' : '' ?>>Wysłane</option>
      <option value="queued" <?= $status === 'queued' ? 'selected' : '' ?>>Oczekują</option>
      <option value="failed_retry" <?= $status === 'failed_retry' ? 'selected' : '' ?>>Błąd — do ponowienia</option>
    </select>
  </label>
  <label class="field">
    <span class="sr-only">Rodzaj</span>
    <select name="template">
      <option value="">Wszystkie rodzaje</option>
      <?php foreach ($labels as $value => $label): ?>
        <option value="<?= htmlspecialchars($value) ?>" <?= $template === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <button class="btn" type="submit">Pokaż</button>
  <?php if ($query !== '' || $status !== '' || $template !== ''): ?><a class="btn secondary" href="/emails">Wyczyść</a><?php endif; ?>
</form>

<div class="table-shell">
  <table class="admin-table admin-table--mail">
    <thead>
      <tr>
        <th>Odbiorca</th>
        <th>Wiadomość</th>
        <th>Stan</th>
        <th>Data</th>
        <th class="cell-action">Akcja</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $mail):
      $mailStatus = (string)($mail['status'] ?? 'queued');
      $isHtml = preg_match('/<\s*(?:html|body|table|div|p|h[1-6])\b/i', (string)($mail['body'] ?? '')) === 1;
    ?>
      <tr>
        <td class="mail-recipient">
          <strong><?= htmlspecialchars((string)($mail['customer_name'] ?: $mail['to_email'])) ?></strong>
          <a href="mailto:<?= htmlspecialchars((string)$mail['to_email']) ?>"><?= htmlspecialchars((string)$mail['to_email']) ?></a>
          <?php if (!empty($mail['order_id'])): ?><a class="text-link" href="/orders/<?= (int)$mail['order_id'] ?>">Zamówienie #<?= (int)$mail['order_id'] ?> →</a><?php endif; ?>
        </td>
        <td class="mail-subject">
          <strong><?= htmlspecialchars((string)$mail['subject']) ?></strong>
          <span><?= htmlspecialchars($labels[(string)($mail['template'] ?? '')] ?? ((string)($mail['template'] ?? '') ?: 'Wiadomość')) ?></span>
          <details class="mail-preview">
            <summary>Podgląd treści</summary>
            <?php if ($isHtml): ?>
              <iframe sandbox title="Podgląd wiadomości <?= (int)$mail['id'] ?>" loading="lazy" srcdoc="<?= htmlspecialchars((string)$mail['body'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></iframe>
            <?php else: ?>
              <pre><?= htmlspecialchars((string)($mail['body'] ?? '')) ?></pre>
            <?php endif; ?>
          </details>
        </td>
        <td>
          <span class="mail-state mail-state--<?= htmlspecialchars($mailStatus) ?>">
            <?= $mailStatus === 'sent' ? 'Wysłany' : ($mailStatus === 'failed_retry' ? 'Błąd' : 'Oczekuje') ?>
          </span>
          <small><?= (int)($mail['attempts'] ?? 0) ?> prób</small>
          <?php if (!empty($mail['last_error'])): ?><span class="mail-error" title="<?= htmlspecialchars((string)$mail['last_error']) ?>"><?= htmlspecialchars(mb_strimwidth((string)$mail['last_error'], 0, 90, '…')) ?></span><?php endif; ?>
        </td>
        <td>
          <strong><?= htmlspecialchars(date('d.m.Y', strtotime((string)$mail['created_at']))) ?></strong>
          <small><?= htmlspecialchars(date('H:i', strtotime((string)($mail['sent_at'] ?: $mail['created_at'])))) ?></small>
        </td>
        <td class="cell-action">
          <?php if ($mailStatus !== 'sent'): ?>
            <form method="post" action="/emails/<?= (int)$mail['id'] ?>/retry" data-ajax-refresh>
              <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">
              <button class="btn small" type="submit">Wyślij ponownie</button>
            </form>
          <?php else: ?>
            <span class="mail-ok" aria-label="Wysłano">✓</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="5" class="empty-state">Brak wiadomości spełniających wybrane kryteria.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php if (($result['pages'] ?? 1) > 1):
  $lastPage = (int)$result['pages'];
  $currentPage = (int)$result['page'];
  $pageNumbers = array_unique(array_filter([1, $currentPage-2, $currentPage-1, $currentPage, $currentPage+1, $currentPage+2, $lastPage], static fn(int $number): bool => $number >= 1 && $number <= $lastPage));
  sort($pageNumbers);
?>
  <nav class="pagination" aria-label="Strony wiadomości">
    <?php $previousPage = 0; foreach ($pageNumbers as $number): ?>
      <?php if ($previousPage && $number > $previousPage + 1): ?><span>…</span><?php endif; ?>
      <a class="<?= $number === $currentPage ? 'active' : '' ?>" href="?<?= http_build_query(['q'=>$query, 'status'=>$status, 'template'=>$template, 'page'=>$number]) ?>"><?= $number ?></a>
    <?php $previousPage = $number; endforeach; ?>
  </nav>
<?php endif; ?>

<?php include __DIR__ . '/../layout_bottom.php'; ?>
