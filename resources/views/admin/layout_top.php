<?php
$adminPath = \Book100\Core\AdminUrl::stripRequestUri($_SERVER['REQUEST_URI'] ?? '/');
$adminCssPath = dirname(__DIR__, 3) . '/admin/assets/style.css';
$adminCssVersion = is_file($adminCssPath) ? (string)filemtime($adminCssPath) : '1';
$adminStorefront = (new \Book100\Services\Storefront\StorefrontSettingsService())->state();
$adminShopName = trim((string)($adminStorefront['shop_name'] ?? 'Wydawnictwo Katolickie ARKA')) ?: 'Wydawnictwo Katolickie ARKA';
$adminShopLogo = trim((string)($adminStorefront['site_logo'] ?? ''));
$adminShopLogoUrl = $adminShopLogo !== '' ? \Book100\Core\AdminPresenter::publicAsset($adminShopLogo) : '';
$adminPublicOrigin = \Book100\Core\AdminUrl::publicOrigin();
?>
<!doctype html><html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Panel sklepu · <?= htmlspecialchars($adminShopName) ?></title><link rel="stylesheet" href="/assets/style.css?v=<?= htmlspecialchars($adminCssVersion) ?>"></head><body class="admin-body" data-public-origin="<?= htmlspecialchars($adminPublicOrigin) ?>" data-admin-base="<?= htmlspecialchars(\Book100\Core\AdminUrl::base()) ?>">
<header class="top">
  <a class="admin-brand" href="/" aria-label="Panel główny <?= htmlspecialchars($adminShopName) ?>">
    <?php if ($adminShopLogoUrl !== ''): ?><span class="admin-brand__logo"><img src="<?= htmlspecialchars($adminShopLogoUrl) ?>" alt=""></span><?php else: ?><span class="admin-brand__mark"><?= htmlspecialchars(mb_strtoupper(mb_substr($adminShopName, 0, 2))) ?></span><?php endif; ?>
    <span>Panel sklepu<small><?= htmlspecialchars($adminShopName) ?></small></span>
  </a>
  <nav aria-label="Nawigacja panelu">
    <a class="<?= $adminPath === '/' ? 'active' : '' ?>" href="/">Pulpit</a><a class="<?= str_starts_with($adminPath, '/homepage') ? 'active' : '' ?>" href="/homepage">Strona główna</a><a class="<?= str_starts_with($adminPath, '/books') ? 'active' : '' ?>" href="/books">Książki</a><a class="<?= str_starts_with($adminPath, '/pages') ? 'active' : '' ?>" href="/pages">Strony</a><a class="<?= str_starts_with($adminPath, '/events') ? 'active' : '' ?>" href="/events">Wydarzenia</a><a class="<?= str_starts_with($adminPath, '/forms') ? 'active' : '' ?>" href="/forms">Formularze</a><a class="<?= str_starts_with($adminPath, '/authors') ? 'active' : '' ?>" href="/authors">Autorzy</a><a class="<?= $adminPath === '/media' ? 'active' : '' ?>" href="/media">Media</a><a class="<?= str_starts_with($adminPath, '/orders') ? 'active' : '' ?>" href="/orders">Zamówienia</a><a class="<?= str_starts_with($adminPath, '/sales') ? 'active' : '' ?>" href="/sales">Sprzedaż</a><a class="<?= str_starts_with($adminPath, '/shipments') ? 'active' : '' ?>" href="/shipments">Wysyłka</a><a class="<?= str_starts_with($adminPath, '/emails') ? 'active' : '' ?>" href="/emails">Maile</a><a class="<?= str_starts_with($adminPath, '/subscribers') ? 'active' : '' ?>" href="/subscribers">Newsletter</a><a class="<?= str_starts_with($adminPath, '/settings') ? 'active' : '' ?>" href="/settings">Ustawienia</a><a class="<?= str_starts_with($adminPath, '/integrations') ? 'active' : '' ?>" href="/integrations">Integracje</a><a class="<?= str_starts_with($adminPath, '/security/2fa') ? 'active' : '' ?>" href="/security/2fa">2FA</a>
    <form method="post" action="/logout"><input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>"><button class="linkbtn" type="submit">Wyloguj</button></form>
  </nav>
</header>
<div class="admin-toast-stack" data-toast-stack aria-live="polite" aria-atomic="true"></div>
<main>
