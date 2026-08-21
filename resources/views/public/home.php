<?php include __DIR__ . '/../partials/header.php'; ?>
<?php
$saleState = \Book100\Services\Books\BookSaleState::class;
$hero = array_replace([
  'eyebrow' => 'Wydawnictwo Katolickie ARKA',
  'title' => 'Słowo · Wiara · Życie',
  'text' => 'Przestrzeń dla książek, które prowadzą ku wierze, prawdzie, nadziei i pojednaniu.',
  'primary_label' => 'Poznaj ARKĘ',
  'primary_url' => '/idea-znaku-arka',
  'secondary_label' => 'Rekolekcje Pojednania',
  'secondary_url' => '/rekolekcje-pojednania',
  'image' => '',
  'image_url' => '/idea-znaku-arka',
  'image_alt' => 'ARKA',
], is_array($hero ?? null) ? $hero : []);
$heroImage = trim((string)$hero['image']);
if ($heroImage === '') $heroImage = trim((string)($storefront['site_logo'] ?? ''));
if ($heroImage === '') $heroImage = '/assets/brand/arka-logo.png';
$heroImageUrl = trim((string)$hero['image_url']);
$heroImageAlt = trim((string)$hero['image_alt']) ?: 'ARKA';
?>
<section class="arka-intro" aria-labelledby="arka-intro-title">
  <div class="arka-intro__copy">
    <?php if (trim((string)$hero['eyebrow']) !== ''): ?><p class="eyebrow"><?= htmlspecialchars($hero['eyebrow']) ?></p><?php endif; ?>
    <h1 id="arka-intro-title"><?= htmlspecialchars($hero['title']) ?></h1>
    <?php if (trim((string)$hero['text']) !== ''): ?><p><?= nl2br(htmlspecialchars($hero['text'])) ?></p><?php endif; ?>
    <?php if (($hero['primary_label'] !== '' && $hero['primary_url'] !== '') || ($hero['secondary_label'] !== '' && $hero['secondary_url'] !== '')): ?>
      <div class="arka-intro__actions">
        <?php if ($hero['primary_label'] !== '' && $hero['primary_url'] !== ''): ?><a class="btn" href="<?= htmlspecialchars($hero['primary_url']) ?>"><?= htmlspecialchars($hero['primary_label']) ?></a><?php endif; ?>
        <?php if ($hero['secondary_label'] !== '' && $hero['secondary_url'] !== ''): ?><a class="btn secondary" href="<?= htmlspecialchars($hero['secondary_url']) ?>"><?= htmlspecialchars($hero['secondary_label']) ?></a><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
  <?php if ($heroImageUrl !== ''): ?>
    <a class="arka-intro__mark" href="<?= htmlspecialchars($heroImageUrl) ?>" aria-label="<?= htmlspecialchars($heroImageAlt) ?>">
      <img src="<?= htmlspecialchars($heroImage) ?>" alt="<?= htmlspecialchars($heroImageAlt) ?>">
    </a>
  <?php else: ?>
    <div class="arka-intro__mark"><img src="<?= htmlspecialchars($heroImage) ?>" alt="<?= htmlspecialchars($heroImageAlt) ?>"></div>
  <?php endif; ?>
</section>

<section class="featured-showcase <?= count($featured) === 1 ? 'featured-showcase--single' : '' ?>" aria-label="Polecane treści">
<?php foreach ($featured as $feature): ?>
  <?php
    $featuredItem = $feature['item'] ?? $feature['book'] ?? null;
    $featuredType = (string)($feature['type'] ?? (!empty($feature['book']) ? 'book' : ''));
    $featuredTitle = (string)($feature['title'] ?? $featuredItem['title'] ?? '');
    $featuredHref = (string)($feature['href'] ?? (
      $featuredType === 'book' && $featuredItem
        ? '/book/' . rawurlencode((string)$featuredItem['slug']) . '/'
        : ''
    ));
  ?>
  <?php if ($featuredItem && $featuredHref !== '' && !empty($feature['image'])): ?>
  <article class="feature-card">
    <a class="feature-card__visual" href="<?= htmlspecialchars($featuredHref) ?>" aria-label="Zobacz: <?= htmlspecialchars($featuredTitle) ?>">
      <img src="<?= htmlspecialchars($feature['image']) ?>" alt="<?= htmlspecialchars($featuredTitle) ?>">
      <?php if ($featuredType === 'book' && $saleState::isPreorder($featuredItem)): ?>
        <span class="sale-badge sale-badge--feature sale-badge--preorder"><?= htmlspecialchars($saleState::availabilityLabel($featuredItem)) ?></span>
      <?php endif; ?>
    </a>
  </article>
  <?php endif; ?>
<?php endforeach; ?>
</section>

<section class="catalog" id="ksiazki">
  <div class="section-heading">
    <div>
      <p class="eyebrow"><?= htmlspecialchars($storefront['home_catalog_eyebrow'] ?? 'Nasze tytuły') ?></p>
      <h2><?= htmlspecialchars($storefront['home_catalog_title'] ?? 'Wybierz coś dla siebie') ?></h2>
    </div>
    <p><?= count($books) ?> <?= count($books) === 1 ? 'książka' : 'książek' ?> w aktualnej ofercie</p>
  </div>
  <div class="product-grid">
<?php foreach ($books as $book): ?>
  <?php
    $available = $saleState::isPurchasable($book);
    $isPreorder = $saleState::isPreorder($book);
    $isAnnounced = $saleState::isAnnounced($book);
  ?>
  <article class="product-card">
    <a class="product-card__cover" href="/book/<?= urlencode($book['slug']) ?>/">
      <?php if (!empty($book['cover_image'])): ?>
        <img src="<?= htmlspecialchars($book['cover_image']) ?>" alt="<?= htmlspecialchars($book['title']) ?>">
      <?php else: ?><?= htmlspecialchars($book['title']) ?><br>okładka<?php endif; ?>
      <span class="product-card__type"><?= $book['product_type'] === 'ebook' ? 'EBOOK' : 'KSIĄŻKA' ?></span>
      <?php if ($isPreorder): ?><span class="sale-badge sale-badge--preorder"><?= htmlspecialchars($saleState::availabilityLabel($book)) ?></span><?php endif; ?>
    </a>
    <div class="product-card__content">
      <h3><a href="/book/<?= urlencode($book['slug']) ?>/"><?= htmlspecialchars($book['title']) ?></a></h3>
      <p class="product-card__author"><?= htmlspecialchars($book['author']) ?></p>
      <?php $cardDescription = \Book100\Core\ContentFormatter::excerpt(($book['short_description'] ?? '') ?: ($book['description'] ?? ''), 150); ?>
      <?php if ($cardDescription !== ''): ?><p class="product-card__excerpt"><?= htmlspecialchars($cardDescription) ?></p><?php endif; ?>
      <div class="product-card__bottom">
        <?php $bookCurrencyLabel = ($book['currency'] ?? 'PLN') === 'PLN' ? 'zł' : (string)$book['currency']; ?>
        <p class="product-card__price"><?= (float)$book['price_gross'] > 0 ? number_format((float)$book['price_gross'], 2, ',', ' ') . ' ' . htmlspecialchars($bookCurrencyLabel) : 'Cena wkrótce' ?></p>
        <a class="product-card__link" href="/book/<?= urlencode($book['slug']) ?>/">O książce <span aria-hidden="true">→</span></a>
      </div>
      <?php if ($isPreorder || $isAnnounced): ?>
        <div class="product-card__release">
          <span class="availability <?= $isPreorder ? 'availability--preorder' : 'availability--announced' ?>"><?= htmlspecialchars($saleState::availabilityLabel($book)) ?></span>
          <?php if ($saleState::releaseMessage($book) !== ''): ?><small><?= htmlspecialchars($saleState::releaseMessage($book)) ?></small><?php endif; ?>
        </div>
      <?php elseif (!$available): ?><span class="availability availability--no">Brak nakładu</span><?php endif; ?>
    </div>
  </article>
<?php endforeach; ?>
  </div>
</section>

<?php if ($showHowItWorks): ?>
<section class="how-it-works" id="jak-kupic">
  <div>
    <p class="eyebrow"><?= htmlspecialchars($storefront['home_how_eyebrow'] ?? '') ?></p>
    <h2><?= htmlspecialchars($storefront['home_how_title'] ?? '') ?></h2>
  </div>
  <ol class="steps">
    <?php foreach ([1, 2, 3] as $step): ?>
      <li><span><?= str_pad((string)$step, 2, '0', STR_PAD_LEFT) ?></span><strong><?= htmlspecialchars($storefront['home_step_' . $step . '_title'] ?? '') ?></strong><p><?= htmlspecialchars($storefront['home_step_' . $step . '_text'] ?? '') ?></p></li>
    <?php endforeach; ?>
  </ol>
</section>
<?php endif; ?>
<?php include __DIR__ . '/../partials/footer.php'; ?>
