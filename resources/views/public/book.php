<?php include __DIR__ . '/../partials/header.php'; ?>
<?php
$saleState = \Book100\Services\Books\BookSaleState::class;
$available = $saleState::isPurchasable($book);
$isPreorder = $saleState::isPreorder($book);
$isAnnounced = $saleState::isAnnounced($book);
$releaseMessage = $saleState::releaseMessage($book);
?>
<?php
$productAttributes = json_decode((string)($book['attributes_json'] ?? ''), true);
if (!is_array($productAttributes)) {
    $productAttributes = [];
}
?>
<nav class="breadcrumbs" aria-label="Okruszki"><a href="/">Książki</a><span>/</span><span><?= htmlspecialchars($book['title']) ?></span></nav>
<article class="product-detail">
  <div class="product-detail__visual">
    <?php if (!empty($book['cover_image'])): ?>
      <img src="<?= htmlspecialchars($book['cover_image']) ?>" alt="<?= htmlspecialchars($book['title']) ?>">
    <?php else: ?><?= htmlspecialchars($book['title']) ?><br>okładka<?php endif; ?>
    <span class="product-card__type"><?= ($book['product_type'] ?? '') === 'ebook' ? 'EBOOK' : 'KSIĄŻKA' ?></span>
    <?php if ($isPreorder): ?><span class="sale-badge sale-badge--preorder"><?= htmlspecialchars($saleState::availabilityLabel($book)) ?></span><?php endif; ?>
  </div>
  <div class="product-detail__content">
    <p class="eyebrow"><?= htmlspecialchars($book['author']) ?></p>
    <h1><?= htmlspecialchars($book['title']) ?></h1>
    <?php if (!empty($book['short_description'])): ?><div class="product-detail__lead"><?= \Book100\Core\ContentFormatter::html($book['short_description']) ?></div><?php endif; ?>
    <?php if ($isAnnounced): ?>
      <p class="product-detail__announcement">
        <strong>Zapowiedź</strong>
        <?php if ($releaseMessage !== ''): ?><span><?= htmlspecialchars($releaseMessage) ?></span><?php endif; ?>
      </p>
    <?php endif; ?>
    <div class="purchase-box">
      <div>
        <span class="purchase-box__label">Cena</span>
        <?php $bookCurrencyLabel = ($book['currency'] ?? 'PLN') === 'PLN' ? 'zł' : (string)$book['currency']; ?>
        <strong><?= $isAnnounced && (float)$book['price_gross'] <= 0 ? 'Cena wkrótce' : number_format((float)$book['price_gross'], 2, ',', ' ') . ' ' . htmlspecialchars($bookCurrencyLabel) ?></strong>
      </div>
      <?php if (!$isAnnounced): ?>
        <div class="purchase-box__state">
          <span class="availability <?= $isPreorder ? 'availability--preorder' : ($available ? 'availability--yes' : 'availability--no') ?>"><?= htmlspecialchars($saleState::availabilityLabel($book)) ?></span>
          <?php if ($releaseMessage !== ''): ?><small><?= htmlspecialchars($releaseMessage) ?></small><?php endif; ?>
        </div>
      <?php endif; ?>
      <?php if ($available): ?>
        <a class="btn btn--large <?= $isPreorder ? 'btn--preorder' : '' ?>" href="/kup/<?= urlencode($book['slug']) ?>"><?= $isPreorder ? 'Kup w przedsprzedaży' : 'Kup teraz' ?> <span aria-hidden="true">→</span></a>
      <?php elseif (!$isAnnounced): ?>
        <span class="btn btn--large btn--disabled">Brak nakładu</span>
      <?php endif; ?>
    </div>
    <div class="product-facts">
      <div><span>Format</span><strong><?= htmlspecialchars(($book['format'] ?? '') ?: (($book['product_type'] ?? '') === 'ebook' ? 'Ebook' : 'Książka papierowa')) ?></strong></div>
      <div><span>SKU</span><strong><?= htmlspecialchars($book['sku']) ?></strong></div>
      <?php if (!empty($book['isbn'])): ?><div><span>ISBN</span><strong><?= htmlspecialchars($book['isbn']) ?></strong></div><?php endif; ?>
      <?php if (!empty($book['publisher'])): ?><div><span>Wydawca</span><strong><?= htmlspecialchars($book['publisher']) ?></strong></div><?php endif; ?>
      <?php if (!empty($book['publication_year'])): ?><div><span>Rok wydania</span><strong><?= (int)$book['publication_year'] ?></strong></div><?php endif; ?>
      <?php if ($saleState::releaseDate($book)): ?><div><span>Premiera</span><strong><?= htmlspecialchars($saleState::formattedReleaseDate($book)) ?></strong></div><?php endif; ?>
      <?php if (!empty($book['pages'])): ?><div><span>Liczba stron</span><strong><?= (int)$book['pages'] ?></strong></div><?php endif; ?>
    </div>
    <?php if ($productAttributes): ?>
    <dl class="product-attributes">
      <?php foreach ($productAttributes as $attributeName => $attributeValue): ?>
        <?php
        $normalizedAttribute = mb_strtolower(trim((string)$attributeName));
        if (in_array($normalizedAttribute, ['autor', 'isbn', 'rok wydania', 'stron', 'liczba stron', 'format', 'format pliku'], true)) continue;
        ?>
        <div><dt><?= htmlspecialchars((string)$attributeName) ?></dt><dd><?= htmlspecialchars((string)$attributeValue) ?></dd></div>
      <?php endforeach; ?>
    </dl>
    <?php endif; ?>
  </div>
</article>
<?php $authorEntity = $book; $authorSectionId = 'book-author-name'; include __DIR__ . '/../partials/author_profile.php'; ?>
<?php if (!empty($book['description'])): ?>
<section class="product-description">
  <p class="eyebrow">O książce</p>
  <div class="rich-description"><?= \Book100\Core\ContentFormatter::richHtml($book['description']) ?></div>
</section>
<?php endif; ?>
<?php include __DIR__ . '/../partials/footer.php'; ?>
