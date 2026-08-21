<?php include __DIR__ . '/../partials/header.php'; ?>
<h1>Płatność potwierdzona</h1>
<?php if (!empty($notice)): ?><div class="notice"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
<p>Numer: <strong><?= htmlspecialchars($order['order_number']) ?></strong></p>
<p>Status zamówienia: <strong><?= htmlspecialchars($order['status']) ?></strong></p>
<p>Status płatności: <strong><?= htmlspecialchars($order['payment_status']) ?></strong></p>
<p>Płatność: <?= htmlspecialchars($order['payment_provider'] ?? 'brak') ?></p>
<p>Dostawa: <?= htmlspecialchars($order['delivery_method']) ?><?= !empty($order['inpost_point']) ? ' · ' . htmlspecialchars($order['inpost_point']) : '' ?></p>
<p>Kwota: <strong><?= number_format((float)$order['total_gross'], 2, ',', ' ') ?> <?= htmlspecialchars($order['currency']) ?></strong></p>
<?php if (!empty($order['items'])): ?>
<h2>Pozycje</h2>
<ul><?php foreach ($order['items'] as $item): ?><li><?= htmlspecialchars($item['title']) ?> × <?= (int)$item['quantity'] ?> — <?= number_format((float)$item['total_gross'], 2, ',', ' ') ?> <?= htmlspecialchars($order['currency']) ?><?php if (($item['sale_mode'] ?? '') === 'preorder'): ?><br><strong>Przedsprzedaż<?= !empty($item['release_date']) ? ' · wysyłka od ' . htmlspecialchars(\Book100\Services\Books\BookSaleState::formattedReleaseDate((string)$item['release_date'])) : '' ?></strong><?php endif; ?></li><?php endforeach; ?></ul>
  <?php endif; ?>
  <?php if (($order['payment_status'] ?? '') === 'paid'): ?>
    <?php foreach (($order['items'] ?? []) as $item): ?>
      <?php if (!empty($item['ebook_file_path'])): ?><p><a class="btn" href="/ebook/<?= urlencode($order['order_token']) ?>/<?= (int)$item['id'] ?>">Pobierz ebook: <?= htmlspecialchars($item['title']) ?></a></p><?php endif; ?>
    <?php endforeach; ?>
  <?php endif; ?>
  <p class="muted">Potwierdzenie zakupu zostało wysłane na podany adres e-mail.</p>
<a class="btn" href="/">Wróć do księgarni</a>
<?php include __DIR__ . '/../partials/footer.php'; ?>
