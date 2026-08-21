<?php
$loginCssPath = dirname(__DIR__, 3) . '/admin/assets/style.css';
$loginCssVersion = is_file($loginCssPath) ? (string)filemtime($loginCssPath) : '1';
$loginStore = (new \Book100\Services\Storefront\StorefrontSettingsService())->state();
$loginShopName = trim((string)($loginStore['shop_name'] ?? 'Sklep')) ?: 'Sklep';
$loginShopLogo = trim((string)($loginStore['site_logo'] ?? ''));
$loginShopLogoUrl = $loginShopLogo !== '' ? \Book100\Core\AdminPresenter::publicAsset($loginShopLogo) : '';
?>
<!doctype html><html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Logowanie · <?= htmlspecialchars($loginShopName) ?></title><link rel="stylesheet" href="/assets/style.css?v=<?= htmlspecialchars($loginCssVersion) ?>"></head><body><main class="loginbox">
<a class="admin-brand" href="/">
  <?php if ($loginShopLogoUrl !== ''): ?><span class="admin-brand__logo"><img src="<?= htmlspecialchars($loginShopLogoUrl) ?>" alt=""></span><?php else: ?><span class="admin-brand__mark"><?= htmlspecialchars(mb_strtoupper(mb_substr($loginShopName, 0, 2))) ?></span><?php endif; ?>
  <span>Panel sklepu<small><?= htmlspecialchars($loginShopName) ?></small></span>
</a>
<h1>Zaloguj się</h1>
<p class="muted">Zamówienia, książki, sprzedaż i wysyłka w jednym miejscu.</p>
<?php if (!empty($error)): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post" class="form">
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">
  <label class="field">E-mail<input type="email" name="email" required autofocus></label>
  <label class="field">Hasło<input type="password" name="password" required></label>
  <button class="btn" type="submit">Zaloguj</button>
</form>
</main></body></html>
