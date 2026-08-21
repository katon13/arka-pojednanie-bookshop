<?php include __DIR__ . '/../layout_top.php'; ?>

<div class="page-heading page-heading--compact">
  <div>
    <p class="kicker">KATALOG</p>
    <h1>Książki</h1>
    <p class="muted">Okładka, cena, dostępność i SEO — wszystko w jednym miejscu.</p>
  </div>
  <a class="btn" href="/books/new">Dodaj książkę</a>
</div>

<div class="book-admin-list">
  <?php foreach ($books as $book): ?>
    <article class="book-admin-row" id="book-admin-row-<?= (int)$book['id'] ?>">
      <a class="book-admin-cover" href="/books/<?= (int)$book['id'] ?>/edit">
        <?php if (!empty($book['cover_image'])): ?>
          <img src="<?= htmlspecialchars(\Book100\Core\AdminPresenter::publicAsset($book['cover_image'])) ?>" alt="Okładka: <?= htmlspecialchars($book['title']) ?>">
        <?php else: ?>
          <span class="cover-placeholder cover-placeholder--large">100</span>
        <?php endif; ?>
      </a>
      <div class="book-admin-main">
        <span class="section-label"><?= htmlspecialchars($book['author'] ?: 'Bez autora') ?></span>
        <h2><a href="/books/<?= (int)$book['id'] ?>/edit"><?= htmlspecialchars($book['title']) ?></a></h2>
        <span class="muted">/book/<?= htmlspecialchars($book['slug']) ?>/</span>
      </div>
      <div class="book-admin-meta">
        <?php $bookCurrencyLabel = ($book['currency'] ?? 'PLN') === 'PLN' ? 'zł' : (string)$book['currency']; ?>
        <span>Cena<strong><?= number_format((float)$book['price_gross'], 2, ',', ' ') ?> <?= htmlspecialchars($bookCurrencyLabel) ?></strong></span>
        <span>Magazyn<strong><?= ($book['product_type'] ?? '') === 'ebook' ? 'E-book' : (int)$book['stock_qty'] . ' szt.' ?></strong></span>
        <span>Status<strong data-book-status class="pill pill--<?= \Book100\Core\AdminPresenter::tone((string)$book['status']) ?>"><?= htmlspecialchars(\Book100\Services\Books\BookSaleState::label($book)) ?></strong></span>
      </div>
      <div class="book-admin-actions">
        <?php if (\Book100\Services\Books\BookSaleState::isPublic($book)): ?><a class="text-link" href="<?= htmlspecialchars(\Book100\Core\StoreUrl::to('/book/' . $book['slug'] . '/')) ?>" target="_blank" rel="noopener">Zobacz w sklepie ↗</a><?php endif; ?>
        <a class="btn small secondary" href="/books/<?= (int)$book['id'] ?>/edit">Edytuj</a>
      </div>
    </article>
  <?php endforeach; ?>
  <?php if (!$books): ?><div class="empty-state">Nie ma jeszcze żadnej książki.</div><?php endif; ?>
</div>

<?php include __DIR__ . '/../layout_bottom.php'; ?>
