<?php include __DIR__ . '/../partials/header.php'; ?>
<?php
$start = new DateTimeImmutable((string)$event['starts_at']);
$end = !empty($event['ends_at']) ? new DateTimeImmutable((string)$event['ends_at']) : null;
$isArchived = ($event['status'] ?? '') === 'archived';
?>
<article class="event-detail">
  <header class="event-detail__header">
    <p class="eyebrow"><?= $isArchived ? 'Archiwum wydarzeń' : 'Wydarzenie' ?></p>
    <h1><?= htmlspecialchars($event['title']) ?></h1>
    <?php if (!empty($event['excerpt'])): ?><p><?= htmlspecialchars($event['excerpt']) ?></p><?php endif; ?>
  </header>
  <div class="event-detail__facts">
    <div><span>Termin</span><strong><?= htmlspecialchars($start->format('d.m.Y · H:i')) ?><?= $end ? ' — ' . htmlspecialchars($end->format('d.m.Y · H:i')) : '' ?></strong></div>
    <?php if (!empty($event['location'])): ?><div><span>Miejsce</span><strong><?= htmlspecialchars($event['location']) ?></strong></div><?php endif; ?>
    <?php if (!empty($event['author'])): ?><div><span>Autor</span><strong><?= htmlspecialchars($event['author']) ?></strong></div><?php endif; ?>
  </div>
  <?php if (!empty($event['featured_image'])): ?><figure class="event-detail__image"><img src="<?= htmlspecialchars($event['featured_image']) ?>" alt="<?= htmlspecialchars($event['title']) ?>"></figure><?php endif; ?>
  <?php $authorEntity = $event; $authorSectionId = 'event-author-name'; include __DIR__ . '/../partials/author_profile.php'; ?>
  <div class="event-detail__content rich-description"><?= \Book100\Core\ContentFormatter::richHtml($event['content'] ?? '') ?></div>
  <?php if (!$isArchived): ?>
    <?php include __DIR__ . '/../partials/registration_form.php'; ?>
  <?php endif; ?>
</article>
<?php include __DIR__ . '/../partials/footer.php'; ?>
