<?php include __DIR__ . '/../layout_top.php'; ?>

<div class="page-heading page-heading--compact">
  <div>
    <p class="kicker">KALENDARZ</p>
    <h1>Wydarzenia</h1>
    <p class="muted">Proste anonse wydarzeń, terminy i lista osób zgłoszonych.</p>
  </div>
  <a class="btn" href="/events/new">Dodaj wydarzenie</a>
</div>

<div class="event-admin-list">
  <?php foreach ($events as $event): ?>
    <?php $start = new DateTimeImmutable((string)$event['starts_at']); ?>
    <article class="event-admin-row">
      <time datetime="<?= htmlspecialchars($start->format(DATE_ATOM)) ?>"><strong><?= $start->format('d') ?></strong><span><?= htmlspecialchars($start->format('m.Y')) ?></span><small><?= htmlspecialchars($start->format('H:i')) ?></small></time>
      <div class="event-admin-main">
        <span class="section-label"><?= htmlspecialchars((string)($event['author'] ?: 'Bez autora')) ?></span>
        <h2><a href="/events/<?= (int)$event['id'] ?>/edit"><?= htmlspecialchars($event['title']) ?></a></h2>
        <p><?= htmlspecialchars((string)($event['excerpt'] ?? '')) ?></p>
        <small><?= htmlspecialchars((string)($event['location'] ?? '')) ?></small>
      </div>
      <div class="event-admin-meta">
        <span class="pill pill--<?= ($event['status'] ?? '') === 'published' ? 'success' : (($event['status'] ?? '') === 'archived' ? 'neutral' : 'warning') ?>"><?= htmlspecialchars([
          'published' => 'Opublikowane',
          'draft' => 'Szkic',
          'archived' => 'Archiwum',
        ][$event['status']] ?? $event['status']) ?></span>
        <span>Zgłoszenia<strong><?= (int)($event['registrations_count'] ?? 0) ?></strong></span>
      </div>
      <div class="page-admin-actions">
        <?php if (in_array(($event['status'] ?? ''), ['published', 'archived'], true)): ?><a class="text-link" href="<?= htmlspecialchars(\Book100\Core\StoreUrl::to('/wydarzenia/' . $event['slug'])) ?>" target="_blank" rel="noopener">Zobacz ↗</a><?php endif; ?>
        <a class="btn small secondary" href="/events/<?= (int)$event['id'] ?>/edit">Edytuj</a>
      </div>
    </article>
  <?php endforeach; ?>
  <?php if (!$events): ?><div class="empty-state">Nie ma jeszcze wydarzeń.</div><?php endif; ?>
</div>

<?php include __DIR__ . '/../layout_bottom.php'; ?>
