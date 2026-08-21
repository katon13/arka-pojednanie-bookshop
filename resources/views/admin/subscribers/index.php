<?php include __DIR__ . '/../layout_top.php'; ?>
<h1>Subskrybenci i prosty mailing</h1>
<p class="muted">ETAP 7: prosta lista i kolejka mailingu. Bez ciężkiego CRM i automatyzacji marketingowej.</p>
<?php if (($_GET['removed'] ?? '') === '1'): ?><p class="notice">Subskrybent został usunięty z listy.</p><?php endif; ?>
<section class="box wide">
  <h2>Wyślij kampanię do aktywnych subskrybentów</h2>
  <form method="post" action="/subscribers/mailing" data-ajax-reset>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">
    <label class="field">Temat<input name="subject" required></label>
    <label class="field">Treść<textarea name="body" rows="8" required></textarea></label>
    <button class="btn" type="submit">Zapisz mailing do kolejki</button>
    <p class="muted">Do każdej wiadomości automatycznie dodamy indywidualny przycisk „Wypisz mnie z newslettera”.</p>
  </form>
</section>
<h2>Lista subskrybentów</h2>
<table class="admin-table">
  <thead><tr><th>E-mail</th><th>Imię</th><th>Źródło</th><th>Zgoda</th><th>Status</th><th>Data</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($subscribers as $s): ?>
    <tr>
      <td><?= htmlspecialchars($s['email']) ?></td>
      <td><?= htmlspecialchars($s['name'] ?? '') ?></td>
      <td><?= htmlspecialchars($s['source'] ?? '') ?></td>
      <td><?= !empty($s['consent_marketing']) ? 'tak' : 'nie' ?></td>
      <td><span class="pill"><?= htmlspecialchars($s['status']) ?></span></td>
      <td><?= htmlspecialchars($s['created_at']) ?></td>
      <td class="subscriber-delete-cell">
        <form method="post" action="/subscribers/<?= (int)$s['id'] ?>/delete" class="subscriber-delete-form" data-ajax-success="Subskrybent został usunięty.">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">
          <button class="danger subscriber-delete-button" type="submit">Usuń</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$subscribers): ?><tr><td colspan="7" class="muted">Brak subskrybentów.</td></tr><?php endif; ?>
  </tbody>
</table>
<h2>Kampanie</h2>
<table class="admin-table">
  <thead><tr><th>ID</th><th>Temat</th><th>Status</th><th>Odbiorcy</th><th>Data</th></tr></thead>
  <tbody>
  <?php foreach ($campaigns as $c): ?>
    <tr><td><?= (int)$c['id'] ?></td><td><?= htmlspecialchars($c['subject']) ?></td><td><?= htmlspecialchars($c['status']) ?></td><td><?= (int)$c['recipients_count'] ?></td><td><?= htmlspecialchars($c['created_at']) ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$campaigns): ?><tr><td colspan="5" class="muted">Brak kampanii.</td></tr><?php endif; ?>
  </tbody>
</table>
<?php include __DIR__ . '/../layout_bottom.php'; ?>
