<?php $registrationRows = is_array($registrations ?? null) ? $registrations : []; ?>
<div class="registration-admin-list">
  <?php foreach ($registrationRows as $registration): ?>
    <article class="registration-admin-row">
      <div>
        <span class="section-label"><?= htmlspecialchars(date('d.m.Y · H:i', strtotime((string)$registration['created_at']))) ?></span>
        <h3><?= htmlspecialchars((string)($registration['person_name'] ?: 'Bez podanego imienia')) ?></h3>
        <p><?= htmlspecialchars(implode(' · ', array_filter([(string)($registration['email'] ?? ''), (string)($registration['phone'] ?? '')]))) ?></p>
        <?php if (!empty($registration['event_title']) || !empty($registration['page_title']) || !empty($registration['source_label'])): ?><small><?= htmlspecialchars((string)($registration['event_title'] ?? $registration['page_title'] ?? $registration['source_label'])) ?></small><?php endif; ?>
      </div>
      <form method="post" action="/registrations/<?= (int)$registration['id'] ?>" class="registration-admin-actions" data-ajax-success="Zgłoszenie zostało zapisane." data-ajax-refresh>
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">
        <select name="status">
          <option value="new" <?= ($registration['status'] ?? '') === 'new' ? 'selected' : '' ?>>Nowe</option>
          <option value="confirmed" <?= ($registration['status'] ?? '') === 'confirmed' ? 'selected' : '' ?>>Potwierdzone</option>
          <option value="cancelled" <?= ($registration['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Anulowane</option>
        </select>
        <input name="admin_note" value="<?= htmlspecialchars((string)($registration['admin_note'] ?? '')) ?>" placeholder="Notatka">
        <button class="btn small secondary" type="submit">Zapisz</button>
      </form>
    </article>
  <?php endforeach; ?>
  <?php if (!$registrationRows): ?><div class="empty-state">Nie ma jeszcze zgłoszeń.</div><?php endif; ?>
</div>
