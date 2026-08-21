<?php include __DIR__ . '/../layout_top.php'; ?>

<div class="page-heading page-heading--compact">
  <div>
    <p class="kicker">TREŚCI SKLEPU</p>
    <h1>Strony</h1>
    <p class="muted">Publikacje, informacje i inne podstrony — edytowane tak samo wygodnie jak książki.</p>
  </div>
  <a class="btn" href="/pages/new">Dodaj stronę</a>
</div>

<div class="page-admin-list">
  <?php foreach ($pages as $page): ?>
    <article class="page-admin-row">
      <a class="page-admin-visual" href="/pages/<?= (int)$page['id'] ?>/edit" aria-label="Edytuj: <?= htmlspecialchars($page['title']) ?>">
        <?php if (!empty($page['featured_image'])): ?>
          <img src="<?= htmlspecialchars(\Book100\Core\AdminPresenter::publicAsset($page['featured_image'])) ?>" alt="">
        <?php else: ?>
          <span aria-hidden="true">Aa</span>
        <?php endif; ?>
      </a>
      <div class="page-admin-main">
        <span class="section-label"><?= htmlspecialchars(($page['author'] ?? '') ?: 'Bez autora') ?></span>
        <h2><a href="/pages/<?= (int)$page['id'] ?>/edit"><?= htmlspecialchars($page['title']) ?></a></h2>
        <p><?= htmlspecialchars(\Book100\Core\ContentFormatter::excerpt(($page['excerpt'] ?? '') ?: ($page['content'] ?? ''), 150)) ?></p>
        <small>/<?= htmlspecialchars($page['slug']) ?></small>
      </div>
      <div class="page-admin-meta">
        <span>Status<strong class="pill pill--<?= ($page['status'] ?? '') === 'published' ? 'success' : 'neutral' ?>"><?= htmlspecialchars([
          'published' => 'Opublikowana',
          'draft' => 'Szkic',
          'hidden' => 'Ukryta',
        ][$page['status']] ?? $page['status']) ?></strong></span>
        <span>Aktualizacja<strong><?= htmlspecialchars(date('d.m.Y, H:i', strtotime((string)$page['updated_at']))) ?></strong></span>
      </div>
      <div class="page-admin-actions">
        <?php if (($page['status'] ?? '') === 'published'): ?><a class="text-link" href="<?= htmlspecialchars(\Book100\Core\StoreUrl::to('/' . $page['slug'])) ?>" target="_blank" rel="noopener">Zobacz stronę ↗</a><?php endif; ?>
        <a class="btn small secondary" href="/pages/<?= (int)$page['id'] ?>/edit">Edytuj</a>
      </div>
    </article>
  <?php endforeach; ?>
  <?php if (!$pages): ?><div class="empty-state">Nie ma jeszcze żadnej strony. Dodaj pierwszą.</div><?php endif; ?>
</div>

<?php include __DIR__ . '/../layout_bottom.php'; ?>
