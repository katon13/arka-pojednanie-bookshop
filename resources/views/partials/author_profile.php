<?php
$authorProfile = is_array($authorEntity ?? null) ? $authorEntity : [];
$authorName = trim((string)($authorProfile['author'] ?? ''));
$authorPublicationsUrl = trim((string)($authorProfile['author_publications_url'] ?? ''));
$authorExternalLink = preg_match('#^https?://#i', $authorPublicationsUrl) === 1;
$authorInitial = mb_strtoupper(mb_substr($authorName !== '' ? $authorName : 'A', 0, 1));
$authorHeadingId = preg_replace('/[^a-z0-9_-]+/i', '-', (string)($authorSectionId ?? 'author-profile-name'));
?>
<?php if ($authorName !== ''): ?>
<section class="book-author" aria-labelledby="<?= htmlspecialchars($authorHeadingId) ?>">
  <div class="book-author__portrait">
    <?php if (!empty($authorProfile['author_photo'])): ?>
      <img src="<?= htmlspecialchars((string)$authorProfile['author_photo']) ?>" alt="<?= htmlspecialchars($authorName) ?>" loading="lazy" decoding="async">
    <?php else: ?>
      <span aria-hidden="true"><?= htmlspecialchars($authorInitial) ?></span>
    <?php endif; ?>
  </div>
  <div class="book-author__content">
    <p class="eyebrow">Autor</p>
    <h2 id="<?= htmlspecialchars($authorHeadingId) ?>"><?= htmlspecialchars($authorName) ?></h2>
    <?php if (!empty($authorProfile['author_bio'])): ?><p><?= nl2br(htmlspecialchars((string)$authorProfile['author_bio'])) ?></p><?php endif; ?>
    <?php if ($authorPublicationsUrl !== ''): ?>
      <a class="book-author__link" href="<?= htmlspecialchars($authorPublicationsUrl) ?>"<?= $authorExternalLink ? ' target="_blank" rel="noopener noreferrer"' : '' ?>>Zobacz publikacje <span aria-hidden="true">→</span></a>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>
