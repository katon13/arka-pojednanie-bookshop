<?php include __DIR__ . '/../partials/header.php'; ?>
<article class="legal-page">
  <header class="legal-page__header">
    <p class="eyebrow"><?= htmlspecialchars($storefront['shop_name'] ?? 'Wydawnictwo Katolickie ARKA') ?></p>
    <h1><?= htmlspecialchars($title) ?></h1>
  </header>
  <div class="legal-page__content">
    <?= \Book100\Core\ContentFormatter::documentHtml($body) ?>
  </div>
</article>
<?php include __DIR__ . '/../partials/footer.php'; ?>
