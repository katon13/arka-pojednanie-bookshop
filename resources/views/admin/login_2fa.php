<?php
$loginCssPath = dirname(__DIR__, 3) . '/admin/assets/style.css';
$loginCssVersion = is_file($loginCssPath) ? (string)filemtime($loginCssPath) : '1';
$loginStore = (new \Book100\Services\Storefront\StorefrontSettingsService())->state();
$loginShopName = trim((string)($loginStore['shop_name'] ?? 'Sklep')) ?: 'Sklep';
?>
<!doctype html><html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Kod 2FA · <?= htmlspecialchars($loginShopName) ?></title><link rel="stylesheet" href="/assets/style.css?v=<?= htmlspecialchars($loginCssVersion) ?>"></head><body><main class="loginbox">
<div class="admin-brand"><span class="admin-brand__mark">2FA</span><span>Panel sklepu<small><?= htmlspecialchars($loginShopName) ?></small></span></div>
<h1>Potwierdź logowanie</h1>
<p class="muted">Wpisz aktualny 6-cyfrowy kod z Google Authenticator. Kod może zostać użyty tylko raz.</p>
<?php if (!empty($error)): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post" action="/login/2fa" class="form">
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">
  <label class="field">Kod jednorazowy<input type="text" name="code" required autofocus autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6"></label>
  <button class="btn" type="submit">Potwierdź kod</button>
</form>
<form method="post" action="/login/2fa/cancel" class="form form--compact">
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">
  <button class="btn secondary" type="submit">Wróć do logowania</button>
</form>
</main></body></html>
