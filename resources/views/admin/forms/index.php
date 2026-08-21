<?php include __DIR__ . '/../layout_top.php'; ?>

<div class="page-heading page-heading--compact">
  <div>
    <p class="kicker">ZGŁOSZENIA</p>
    <h1>Formularze</h1>
    <p class="muted">Ustaw nazwy czterech pól i adres, na który mają trafiać zgłoszenia.</p>
  </div>
  <a class="btn" href="/forms/new">Dodaj formularz</a>
</div>

<div class="simple-admin-list">
  <?php foreach ($forms as $form): ?>
    <article class="simple-admin-row">
      <div>
        <span class="section-label"><?= ($form['status'] ?? '') === 'active' ? 'AKTYWNY' : 'UKRYTY' ?></span>
        <h2><a href="/forms/<?= (int)$form['id'] ?>/edit"><?= htmlspecialchars($form['name']) ?></a></h2>
        <p><?= htmlspecialchars($form['recipient_email']) ?></p>
      </div>
      <div class="simple-admin-row__stats">
        <span>Zgłoszenia<strong><?= (int)($form['registrations_count'] ?? 0) ?></strong></span>
      </div>
      <a class="btn small secondary" href="/forms/<?= (int)$form['id'] ?>/edit">Edytuj</a>
    </article>
  <?php endforeach; ?>
  <?php if (!$forms): ?><div class="empty-state">Nie ma jeszcze formularzy.</div><?php endif; ?>
</div>

<?php include __DIR__ . '/../layout_bottom.php'; ?>
