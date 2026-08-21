<?php include __DIR__ . '/../layout_top.php'; ?>
<?php
$isEdit = $mode === 'edit';
$configured = [];
foreach (\Book100\Repository\RegistrationFormRepository::fields($form) as $field) {
  if (!empty($field['key'])) $configured[(string)$field['key']] = $field;
}
$fieldDefinitions = [
  'first_name' => ['label' => 'Imię', 'type' => 'text'],
  'last_name' => ['label' => 'Nazwisko', 'type' => 'text'],
  'email' => ['label' => 'E-mail', 'type' => 'email'],
  'phone' => ['label' => 'Telefon', 'type' => 'tel'],
];
?>

<div class="page-heading page-heading--compact">
  <div>
    <a class="back-link" href="/forms">← Formularze</a>
    <p class="kicker"><?= $isEdit ? 'EDYCJA FORMULARZA' : 'NOWY FORMULARZ' ?></p>
    <h1><?= $isEdit ? htmlspecialchars($form['name']) : 'Dodaj formularz' ?></h1>
    <p class="muted">Cztery proste pola, jeden odbiorca wiadomości.</p>
  </div>
</div>

<?php if (!empty($errors)): ?><div class="error"><strong>Popraw dane:</strong><ul><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<form class="editor-form editor-form--with-savebar" method="post" action="<?= $isEdit ? '/forms/' . (int)$form['id'] : '/forms' ?>" data-ajax-success="Formularz został zapisany." data-ajax-refresh>
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">
  <div class="editor-savebar">
    <div class="editor-savebar__copy"><strong>Zapisz formularz</strong><span>Zmiany będą widoczne na wszystkich stronach, które go używają.</span></div>
    <button class="btn" type="submit">Zapisz</button>
  </div>

  <section class="panel-section">
    <div class="section-heading"><div><p class="section-label">PODSTAWOWE</p><h2>Nazwa i wysyłka</h2></div></div>
    <div class="two">
      <label class="field">Nazwa formularza<input name="name" required maxlength="190" value="<?= htmlspecialchars($form['name'] ?? '') ?>"></label>
      <label class="field">Wyślij zgłoszenie na adres<input name="recipient_email" type="email" required value="<?= htmlspecialchars($form['recipient_email'] ?? '') ?>"></label>
    </div>
    <label class="field">Temat wiadomości e-mail<input name="email_subject" maxlength="255" value="<?= htmlspecialchars($form['email_subject'] ?? '') ?>" placeholder="Nowe zgłoszenie — nazwa wydarzenia"></label>
    <label class="field">Krótki tekst nad formularzem<textarea name="intro_text" rows="3" maxlength="1000"><?= htmlspecialchars($form['intro_text'] ?? '') ?></textarea></label>
    <div class="two">
      <label class="field">Tekst przycisku<input name="submit_label" maxlength="100" value="<?= htmlspecialchars($form['submit_label'] ?? 'Wyślij zgłoszenie') ?>"></label>
      <label class="field">Status<select name="status"><option value="active" <?= ($form['status'] ?? '') === 'active' ? 'selected' : '' ?>>Aktywny</option><option value="hidden" <?= ($form['status'] ?? '') === 'hidden' ? 'selected' : '' ?>>Ukryty</option></select></label>
    </div>
    <label class="field">Komunikat po wysłaniu<input name="success_message" maxlength="500" value="<?= htmlspecialchars($form['success_message'] ?? 'Dziękujemy. Twoje zgłoszenie zostało przyjęte.') ?>"></label>
  </section>

  <section class="panel-section">
    <div class="section-heading"><div><p class="section-label">POLA</p><h2>Dane osoby zgłaszającej się</h2></div><span class="muted">Możesz zmienić nazwy, wymaganie lub wyłączyć pole</span></div>
    <div class="form-field-list">
      <?php foreach ($fieldDefinitions as $key => $definition):
        $field = array_replace(['enabled' => true, 'required' => true], $definition, $configured[$key] ?? []);
      ?>
        <div class="form-field-row">
          <input type="hidden" name="fields[<?= htmlspecialchars($key) ?>][key]" value="<?= htmlspecialchars($key) ?>">
          <input type="hidden" name="fields[<?= htmlspecialchars($key) ?>][type]" value="<?= htmlspecialchars($definition['type']) ?>">
          <label class="field">Nazwa pola<input name="fields[<?= htmlspecialchars($key) ?>][label]" maxlength="100" required value="<?= htmlspecialchars($field['label']) ?>"></label>
          <label class="check-line"><input type="checkbox" name="fields[<?= htmlspecialchars($key) ?>][enabled]" value="1" <?= !empty($field['enabled']) ? 'checked' : '' ?>> Pokaż</label>
          <label class="check-line"><input type="checkbox" name="fields[<?= htmlspecialchars($key) ?>][required]" value="1" <?= !empty($field['required']) ? 'checked' : '' ?>> Wymagane</label>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
</form>

<?php if ($isEdit): ?>
  <section class="panel-section registrations-panel">
    <div class="section-heading"><div><p class="section-label">ZGŁOSZENIA</p><h2>Ostatnie zgłoszenia</h2></div><span class="pill pill--neutral"><?= count($registrations ?? []) ?></span></div>
    <?php include __DIR__ . '/../partials/registrations_table.php'; ?>
  </section>
<?php endif; ?>

<?php include __DIR__ . '/../layout_bottom.php'; ?>
