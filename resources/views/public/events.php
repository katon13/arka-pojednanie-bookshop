<?php include __DIR__ . '/../partials/header.php'; ?>
<?php
$today = new DateTimeImmutable('today');
$upcoming = [];
$past = [];
foreach ($events as $event) {
  $start = new DateTimeImmutable((string)$event['starts_at']);
  if (($event['status'] ?? '') === 'published' && $start >= $today) $upcoming[] = $event;
  else $past[] = $event;
}
?>
<section class="events-page">
  <header class="events-page__header">
    <p class="eyebrow">Spotkania i rekolekcje</p>
    <h1>Wydarzenia</h1>
    <p>Aktualne terminy, miejsca i informacje o zapisach.</p>
  </header>

  <div class="events-section">
    <div class="section-heading"><div><p class="eyebrow">Kalendarz</p><h2>Nadchodzące wydarzenia</h2></div></div>
    <div class="event-grid">
      <?php foreach ($upcoming as $event): ?>
        <?php $start = new DateTimeImmutable((string)$event['starts_at']); ?>
        <article class="event-card">
          <a class="event-card__media" href="/wydarzenia/<?= rawurlencode((string)$event['slug']) ?>">
            <?php if (!empty($event['featured_image'])): ?><img src="<?= htmlspecialchars($event['featured_image']) ?>" alt=""><?php else: ?><span><?= $start->format('d') ?><small><?= htmlspecialchars(mb_strtoupper($start->format('m'))) ?></small></span><?php endif; ?>
          </a>
          <div class="event-card__body">
            <time datetime="<?= htmlspecialchars($start->format(DATE_ATOM)) ?>"><?= htmlspecialchars($start->format('d.m.Y · H:i')) ?></time>
            <h3><a href="/wydarzenia/<?= rawurlencode((string)$event['slug']) ?>"><?= htmlspecialchars($event['title']) ?></a></h3>
            <?php if (!empty($event['excerpt'])): ?><p><?= htmlspecialchars($event['excerpt']) ?></p><?php endif; ?>
            <div><span><?= htmlspecialchars((string)($event['location'] ?? '')) ?></span><a href="/wydarzenia/<?= rawurlencode((string)$event['slug']) ?>">Szczegóły →</a></div>
          </div>
        </article>
      <?php endforeach; ?>
      <?php if (!$upcoming): ?><p class="events-empty">Nowe terminy pojawią się tutaj po ich opublikowaniu.</p><?php endif; ?>
    </div>
  </div>

  <?php if ($past): ?>
    <div class="events-section events-section--archive">
      <div class="section-heading"><div><p class="eyebrow">Archiwum</p><h2>Minione wydarzenia</h2></div></div>
      <div class="event-archive-list">
        <?php foreach ($past as $event): ?>
          <?php $start = new DateTimeImmutable((string)$event['starts_at']); ?>
          <a href="/wydarzenia/<?= rawurlencode((string)$event['slug']) ?>"><time><?= htmlspecialchars($start->format('d.m.Y')) ?></time><strong><?= htmlspecialchars($event['title']) ?></strong><span><?= htmlspecialchars((string)($event['location'] ?? '')) ?></span></a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</section>
<?php include __DIR__ . '/../partials/footer.php'; ?>
