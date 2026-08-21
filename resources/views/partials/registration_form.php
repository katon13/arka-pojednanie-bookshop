<?php
$registrationForm = is_array($registrationForm ?? null) ? $registrationForm : null;
$registrationFields = $registrationForm
    ? \Book100\Repository\RegistrationFormRepository::fields($registrationForm)
    : [];
$registrationFlash = is_array($registrationFlash ?? null) ? $registrationFlash : null;
?>
<?php if ($registrationForm && ($registrationForm['status'] ?? '') === 'active' && $registrationFields): ?>
<section class="registration-box" id="formularz-zgloszeniowy" aria-labelledby="registration-form-title">
  <div class="registration-box__heading">
    <p class="eyebrow">Zgłoszenie</p>
    <h2 id="registration-form-title"><?= htmlspecialchars($registrationForm['name']) ?></h2>
    <?php if (!empty($registrationForm['intro_text'])): ?><p><?= nl2br(htmlspecialchars((string)$registrationForm['intro_text'])) ?></p><?php endif; ?>
  </div>

  <?php if ($registrationFlash): ?>
    <div class="registration-notice registration-notice--<?= ($registrationFlash['type'] ?? '') === 'success' ? 'success' : 'error' ?>" role="status">
      <?= htmlspecialchars((string)($registrationFlash['message'] ?? '')) ?>
    </div>
  <?php endif; ?>

  <form class="registration-form" method="post" action="/zgloszenie">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">
    <input type="hidden" name="form_id" value="<?= (int)$registrationForm['id'] ?>">
    <input type="hidden" name="context_type" value="<?= htmlspecialchars((string)($registrationContextType ?? 'page')) ?>">
    <input type="hidden" name="context_id" value="<?= (int)($registrationContextId ?? 0) ?>">
    <label class="registration-honeypot" aria-hidden="true">Strona internetowa<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
    <div class="registration-form__fields">
      <?php foreach ($registrationFields as $field): ?>
        <?php if (empty($field['enabled'])) continue; ?>
        <?php
          $key = (string)($field['key'] ?? '');
          if (!preg_match('/^[a-z][a-z0-9_]{1,39}$/', $key)) continue;
          $type = in_array(($field['type'] ?? ''), ['email', 'tel'], true) ? (string)$field['type'] : 'text';
        ?>
        <label class="registration-field">
          <span><?= htmlspecialchars((string)($field['label'] ?? $key)) ?><?= !empty($field['required']) ? ' *' : '' ?></span>
          <input type="<?= htmlspecialchars($type) ?>" name="fields[<?= htmlspecialchars($key) ?>]" maxlength="500" <?= !empty($field['required']) ? 'required' : '' ?> autocomplete="<?= htmlspecialchars(match ($key) {
            'first_name' => 'given-name',
            'last_name' => 'family-name',
            'email' => 'email',
            'phone' => 'tel',
            default => 'off',
          }) ?>">
        </label>
      <?php endforeach; ?>
    </div>
    <label class="registration-consent">
      <input type="checkbox" name="privacy_consent" value="1" required>
      <span>Wyrażam wyraźną zgodę na przetwarzanie danych podanych w formularzu — w tym, gdy wynika to z charakteru zgłoszenia, danych mogących pośrednio ujawniać moje przekonania religijne — wyłącznie w celu obsługi zgłoszenia i organizacji wydarzenia. Zgodę mogę wycofać, pisząc na biuro@arka-pojednanie.pl. <a href="/polityka-prywatnosci" target="_blank" rel="noopener">Polityka prywatności</a>.</span>
    </label>
    <button class="btn" type="submit"><?= htmlspecialchars((string)($registrationForm['submit_label'] ?? 'Wyślij zgłoszenie')) ?></button>
  </form>
</section>
<?php endif; ?>
