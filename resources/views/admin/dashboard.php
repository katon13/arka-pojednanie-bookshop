<?php include __DIR__ . '/layout_top.php'; ?>
<div class="page-heading">
  <div><p class="kicker">WYDAWNICTWO KATOLICKIE ARKA</p><h1>Pulpit sklepu</h1><p class="muted">Książki, zamówienia i wysyłka w jednym prostym miejscu.</p></div>
  <a class="btn" href="/books/new">Dodaj książkę</a>
</div>
<div class="grid stats-grid">
  <div class="card"><p>Książki</p><strong class="price"><?= $stats['books'] ?></strong><span>wszystkie produkty</span></div>
  <div class="card"><p>Widoczne</p><strong class="price"><?= $stats['active'] ?></strong><span>w bieżącej ofercie</span></div>
  <div class="card"><p>Zamówienia dziś</p><strong class="price"><?= $stats['orders_today'] ?></strong><span>nowe zamówienia</span></div>
  <div class="card"><p>Do wysyłki</p><strong class="price"><?= $stats['to_ship'] ?></strong><span>opłacone paczki</span></div>
  <div class="card card--revenue"><p>Opłacona sprzedaż</p><strong class="price"><?= number_format((float)$stats['paid_revenue'],2,',',' ') ?> <small>PLN</small></strong><span>łączna wartość</span></div>
</div>
<section class="admin-section">
  <div><p class="kicker">NAJWAŻNIEJSZE</p><h2>Szybkie działania</h2><p class="muted">Przejdź od razu do najczęściej używanych części panelu.</p></div>
  <div class="quick-links"><a href="/homepage">Strona główna <span>→</span></a><a href="/books">Lista książek <span>→</span></a><a href="/orders">Zamówienia <span>→</span></a><a href="/shipments">Wysyłka <span>→</span></a><a href="/sales">Sprzedaż <span>→</span></a><a href="/integrations">Integracje <span>→</span></a></div>
  <form method="post" action="/cache/clear"><input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>"><button class="btn secondary" type="submit">Wyczyść cache strony</button></form>
</section>
<?php include __DIR__ . '/layout_bottom.php'; ?>
