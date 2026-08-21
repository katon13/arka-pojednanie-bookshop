<?php
$pageSlug = trim((string)($page['slug'] ?? ''));
$isBrandStory = $pageSlug === 'idea-znaku-arka';
$featuredImage = $isBrandStory ? '' : trim((string)($page['featured_image'] ?? ''));
include __DIR__ . '/../partials/header.php';
?>
<article class="content-page<?= $isBrandStory ? ' content-page--brand-story' : '' ?>">
  <header class="content-page__header">
    <p class="eyebrow"><?= htmlspecialchars($storefront['shop_name'] ?? 'Wydawnictwo Katolickie ARKA') ?></p>
    <h1><?= htmlspecialchars($page['title']) ?></h1>
    <?php if (!empty($page['excerpt'])): ?><p class="content-page__lead"><?= htmlspecialchars($page['excerpt']) ?></p><?php endif; ?>
  </header>
  <?php if ($isBrandStory): ?>
    <section class="brand-story__origin" aria-label="Od inspiracji do znaku ARKA">
      <figure class="brand-story__origin-card">
        <div class="brand-story__origin-media brand-story__origin-media--photo">
          <img src="/assets/brand/arka-inspiration-branch.webp" alt="Gałązka zauważona na posadzce kościoła — inspiracja znaku ARKA">
        </div>
        <figcaption><span>Inspiracja</span>Samotna gałązka</figcaption>
      </figure>
      <div class="brand-story__origin-path" aria-hidden="true">
        <span></span>
        <strong>z natury<br>do symbolu</strong>
        <span></span>
      </div>
      <figure class="brand-story__origin-card">
        <div class="brand-story__origin-media brand-story__origin-media--mark">
          <img src="/assets/brand/arka-logo-idea.webp" alt="Znak Wydawnictwa Katolickiego ARKA">
        </div>
        <figcaption><span>Znak</span>Żywy krzyż, który rodzi liście</figcaption>
      </figure>
    </section>
  <?php endif; ?>
  <?php if ($featuredImage !== ''): ?>
    <figure class="content-page__image">
      <img src="<?= htmlspecialchars($featuredImage) ?>" alt="<?= htmlspecialchars($page['title']) ?>">
    </figure>
  <?php endif; ?>
  <?php $authorEntity = $page; $authorSectionId = 'content-page-author-name'; include __DIR__ . '/../partials/author_profile.php'; ?>
  <div class="content-page__body rich-description">
    <?= \Book100\Core\ContentFormatter::richHtml($page['content'] ?? '') ?>
  </div>
  <?php include __DIR__ . '/../partials/registration_form.php'; ?>
</article>
<?php include __DIR__ . '/../partials/footer.php'; ?>
