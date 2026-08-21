<?php
include __DIR__ . '/../layout_top.php';
$enabled = !empty($twoFactor['enabled']);
$pending = is_array($setup);
$qrScriptPath = dirname(__DIR__, 4) . '/admin/assets/qrcode.js';
$qrScriptVersion = is_file($qrScriptPath) ? (string)filemtime($qrScriptPath) : '1';
?>
<div class="page-head">
  <div><p class="eyebrow">BEZPIECZEŃSTWO</p><h1>Google Authenticator / 2FA</h1></div>
  <span class="config-state <?= $enabled ? 'config-state--ok' : '' ?>"><?= $enabled ? 'Włączone' : 'Wyłączone' ?></span>
</div>

<?php if (!empty($error)): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if (isset($_GET['enabled'])): ?><p class="notice success">2FA zostało włączone. Sekret nie jest już wyświetlany.</p><?php endif; ?>
<?php if (isset($_GET['disabled'])): ?><p class="notice">2FA zostało bezpiecznie wyłączone.</p><?php endif; ?>
<?php if (isset($_GET['cancelled'])): ?><p class="notice">Anulowano oczekującą konfigurację. Stan aktywnego 2FA nie został zmieniony.</p><?php endif; ?>

<section class="panel-section two-factor-status">
  <div class="section-heading">
    <div><p class="section-label">STAN KONTA</p><h2><?= $enabled ? 'Logowanie dwuetapowe jest aktywne' : 'Logowanie dwuetapowe nie jest aktywne' ?></h2></div>
  </div>
  <p class="muted">
    <?= $enabled
      ? 'Po poprawnym haśle panel wymaga jednorazowego kodu TOTP. Tego samego kodu nie można użyć ponownie.'
      : 'Panel loguje obecnie przy użyciu hasła. Rozpoczęcie konfiguracji nie włączy 2FA — aktywacja nastąpi dopiero po potwierdzeniu kodem z telefonu.' ?>
  </p>
</section>

<?php if ($pending): ?>
<section class="panel-section two-factor-setup">
  <div class="section-heading">
    <div><p class="section-label">KROK 2 Z 2</p><h2>Zeskanuj kod i potwierdź</h2></div>
  </div>
  <?php if ($enabled): ?><p class="notice">Dotychczasowe 2FA pozostaje aktywne, dopóki poprawnie nie potwierdzisz nowego kodu.</p><?php endif; ?>
  <div class="two-factor-setup__grid">
    <div class="two-factor-qr" data-totp-qr data-otpauth="<?= htmlspecialchars((string)$setup['uri'], ENT_QUOTES) ?>">
      <p class="error">Nie udało się jeszcze wygenerować podglądu QR.</p>
    </div>
    <div>
      <ol class="two-factor-steps">
        <li>Otwórz Google Authenticator i wybierz dodanie konta.</li>
        <li>Zeskanuj kod QR. Jeśli aparat nie działa, wpisz ręcznie klucz poniżej.</li>
        <li>Wpisz aktualny kod 6-cyfrowy, aby dopiero wtedy włączyć konfigurację.</li>
      </ol>
      <p class="muted">Klucz ręczny (widoczny tylko podczas oczekującej konfiguracji):</p>
      <code class="two-factor-secret"><?= htmlspecialchars((string)$setup['secret']) ?></code>
      <form class="form" method="post" action="/security/2fa/confirm">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">
        <label class="field">Kod z aplikacji<input type="text" name="code" required autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6"></label>
        <button class="btn" type="submit">Potwierdź i włącz 2FA</button>
      </form>
      <form method="post" action="/security/2fa/cancel">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">
        <button class="btn secondary" type="submit">Anuluj konfigurację</button>
      </form>
    </div>
  </div>
</section>
<script src="/assets/qrcode.js?v=<?= htmlspecialchars($qrScriptVersion) ?>"></script>
<script>
(function () {
  var target = document.querySelector('[data-totp-qr]');
  if (!target || typeof window.qrcode !== 'function') return;
  try {
    var qr = window.qrcode(0, 'M');
    qr.addData(target.getAttribute('data-otpauth'), 'Byte');
    qr.make();
    target.innerHTML = qr.createSvgTag({cellSize: 6, margin: 24, scalable: true, title: 'Kod QR konfiguracji Google Authenticator'});
  } catch (error) {
    target.innerHTML = '<p class="error">Nie udało się wygenerować QR. Użyj klucza ręcznego.</p>';
  }
}());
</script>
<?php else: ?>
<section class="panel-section">
  <div class="section-heading">
    <div><p class="section-label">KROK 1 Z 2</p><h2><?= $enabled ? 'Skonfiguruj ponownie' : 'Rozpocznij konfigurację' ?></h2></div>
  </div>
  <form class="form" method="post" action="/security/2fa/setup" autocomplete="off">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">
    <label class="field">Obecne hasło administratora<input type="password" name="current_password" required autocomplete="current-password"></label>
    <?php if ($enabled): ?>
      <label class="field">Aktualny kod 2FA<input type="text" name="current_code" required autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6"></label>
      <p class="muted">Nowy sekret pozostanie oczekujący. Obecny będzie nadal chronił logowanie aż do potwierdzenia nowego kodu.</p>
    <?php endif; ?>
    <button class="btn" type="submit"><?= $enabled ? 'Wygeneruj nowy kod QR' : 'Wygeneruj kod QR' ?></button>
  </form>
</section>
<?php endif; ?>

<?php if ($enabled): ?>
<details class="panel-section collapsible">
  <summary><span><span class="section-label">OPERACJA WRAŻLIWA</span><strong>Wyłącz Google Authenticator</strong></span><span>Rozwiń</span></summary>
  <form class="form" method="post" action="/security/2fa/disable" autocomplete="off">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">
    <p class="muted">Wyłączenie wymaga jednocześnie hasła administratora i nowego, aktualnego kodu TOTP.</p>
    <label class="field">Obecne hasło<input type="password" name="current_password" required autocomplete="current-password"></label>
    <label class="field">Aktualny kod 2FA<input type="text" name="current_code" required autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6"></label>
    <button class="btn secondary" type="submit">Wyłącz 2FA</button>
  </form>
</details>
<?php endif; ?>

<?php include __DIR__ . '/../layout_bottom.php'; ?>
