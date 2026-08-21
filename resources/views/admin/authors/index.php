<?php include __DIR__ . '/../layout_top.php'; ?>

<div class="page-heading page-heading--compact">
  <div>
    <p class="kicker">KARTOTEKA</p>
    <h1>Autorzy</h1>
    <p class="muted">Jedno zdjęcie i jedna notka zasilają wszystkie przypisane książki i strony.</p>
  </div>
  <a class="btn" href="/authors/new">Dodaj autora</a>
</div>

<div class="author-admin-list">
  <?php foreach ($authors as $author): ?>
    <article class="author-admin-row">
      <a class="author-admin-photo" href="/authors/<?= (int)$author['id'] ?>/edit" aria-label="Edytuj: <?= htmlspecialchars($author['name']) ?>">
        <?php if (!empty($author['photo'])): ?>
          <img src="<?= htmlspecialchars(\Book100\Core\AdminPresenter::publicAsset($author['photo'])) ?>" alt="">
        <?php else: ?>
          <span aria-hidden="true"><?= htmlspecialchars(mb_strtoupper(mb_substr((string)$author['name'], 0, 1))) ?></span>
        <?php endif; ?>
      </a>
      <div class="author-admin-main">
        <span class="section-label"><?= ($author['status'] ?? '') === 'active' ? 'AKTYWNY PROFIL' : 'UKRYTY PROFIL' ?></span>
        <h2><a href="/authors/<?= (int)$author['id'] ?>/edit"><?= htmlspecialchars($author['name']) ?></a></h2>
        <p><?= htmlspecialchars(\Book100\Core\ContentFormatter::excerpt((string)($author['short_bio'] ?? ''), 170) ?: 'Dodaj krótką notkę o autorze.') ?></p>
      </div>
      <div class="author-admin-meta">
        <span>Książki<strong><?= (int)($author['books_count'] ?? 0) ?></strong></span>
        <span>Strony<strong><?= (int)($author['pages_count'] ?? 0) ?></strong></span>
        <span>Publikacje<strong><?= !empty($author['publications_url']) ? 'Podpięte' : 'Brak linku' ?></strong></span>
      </div>
      <div class="author-admin-actions">
        <a class="btn small secondary" href="/authors/<?= (int)$author['id'] ?>/edit">Edytuj profil</a>
      </div>
    </article>
  <?php endforeach; ?>
  <?php if (!$authors): ?><div class="empty-state">Nie ma jeszcze żadnego autora. Dodaj pierwszego.</div><?php endif; ?>
</div>

<?php include __DIR__ . '/../layout_bottom.php'; ?>
