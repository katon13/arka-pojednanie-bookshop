<?php
$storefront = $storefront ?? (new \Book100\Services\Storefront\StorefrontSettingsService())->state();
$shopName = trim((string)($storefront['shop_name'] ?? 'Wydawnictwo Katolickie ARKA')) ?: 'Wydawnictwo Katolickie ARKA';
$siteLogo = trim((string)($storefront['site_logo'] ?? ''));
$siteIcon = trim((string)($storefront['site_icon'] ?? ''));
$accent = preg_match('/^#[0-9a-f]{6}$/i', (string)($storefront['brand_accent_color'] ?? ''))
    ? (string)$storefront['brand_accent_color']
    : '#8b6f47';
$accentDark = preg_match('/^#[0-9a-f]{6}$/i', (string)($storefront['brand_accent_dark'] ?? ''))
    ? (string)$storefront['brand_accent_dark']
    : '#5d462d';
$showHowNavigation = isset($showHowItWorks)
    ? (bool)$showHowItWorks
    : filter_var($storefront['home_show_how_it_works'] ?? false, FILTER_VALIDATE_BOOLEAN);
$maintenanceEnabled = filter_var($storefront['maintenance_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
$maintenanceMessage = trim((string)($storefront['maintenance_message'] ?? ''));
if ($maintenanceMessage === '') {
    $maintenanceMessage = 'Konserwacja systemu — prosimy nie dokonywać zakupu.';
}
$publicCss = \Book100\Core\Paths::publicRoot() . '/assets/style.css';
$publicCssVersion = is_file($publicCss) ? (string)filemtime($publicCss) : '1';
?>
<!doctype html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($seo['title'] ?? $shopName) ?></title>
<meta name="description" content="<?= htmlspecialchars($seo['description'] ?? 'Księgarnia internetowa') ?>">
<?php if (!empty($seo['keywords'])): ?><meta name="keywords" content="<?= htmlspecialchars($seo['keywords']) ?>"><?php endif; ?>
<meta name="robots" content="<?= htmlspecialchars($seo['robots'] ?? 'index,follow,max-image-preview:large') ?>">
<?php if (!empty($seo['canonical'])): ?><link rel="canonical" href="<?= htmlspecialchars($seo['canonical']) ?>"><?php endif; ?>
<meta property="og:title" content="<?= htmlspecialchars($seo['title'] ?? $shopName) ?>">
<meta property="og:description" content="<?= htmlspecialchars($seo['description'] ?? 'Księgarnia internetowa') ?>">
<meta property="og:type" content="<?= htmlspecialchars($seo['og_type'] ?? 'website') ?>">
<meta property="og:site_name" content="<?= htmlspecialchars($shopName) ?>">
<meta property="og:locale" content="pl_PL">
<?php if (!empty($seo['canonical'])): ?><meta property="og:url" content="<?= htmlspecialchars($seo['canonical']) ?>"><?php endif; ?>
<?php if (!empty($seo['og_image'])): ?><meta property="og:image" content="<?= htmlspecialchars($seo['og_image']) ?>"><?php endif; ?>
<meta name="twitter:card" content="<?= !empty($seo['og_image']) ? 'summary_large_image' : 'summary' ?>">
<meta name="twitter:title" content="<?= htmlspecialchars($seo['title'] ?? $shopName) ?>">
<meta name="twitter:description" content="<?= htmlspecialchars($seo['description'] ?? 'Księgarnia internetowa') ?>">
<?php if (!empty($seo['og_image'])): ?><meta name="twitter:image" content="<?= htmlspecialchars($seo['og_image']) ?>"><?php endif; ?>
<?php if ($siteIcon !== ''): ?><link rel="icon" href="<?= htmlspecialchars($siteIcon) ?>"><?php endif; ?>
<link rel="stylesheet" href="/assets/style.css?v=<?= htmlspecialchars($publicCssVersion) ?>">
<?php if (!empty($inpostGeoWidget['enabled'])): ?>
<link rel="stylesheet" href="<?= htmlspecialchars((string)$inpostGeoWidget['style_url']) ?>">
<script src="<?= htmlspecialchars((string)$inpostGeoWidget['script_url']) ?>" defer></script>
<?php endif; ?>
<?php if (!empty($seo['jsonld'])): ?><script type="application/ld+json"><?= json_encode($seo['jsonld'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?></script><?php endif; ?>
</head>
<body class="site-body" style="--accent:<?= htmlspecialchars($accent) ?>;--accent-dark:<?= htmlspecialchars($accentDark) ?>">
<?php if ($maintenanceEnabled): ?>
<aside class="maintenance-banner" role="status" aria-label="Komunikat techniczny">
  <div class="maintenance-banner__inner">
    <span class="maintenance-banner__indicator" aria-hidden="true"></span>
    <strong><?= htmlspecialchars($maintenanceMessage) ?></strong>
  </div>
</aside>
<?php endif; ?>
<header class="site-header">
  <div class="site-header__inner">
    <button
      class="site-menu-toggle"
      type="button"
      aria-label="Otwórz menu"
      aria-controls="site-navigation"
      aria-expanded="false"
    >
      <span aria-hidden="true"></span>
      <span aria-hidden="true"></span>
      <span aria-hidden="true"></span>
    </button>
    <a class="site-brand" href="/" aria-label="<?= htmlspecialchars($shopName) ?> — strona główna">
      <?php if ($siteLogo !== ''): ?>
        <img src="<?= htmlspecialchars($siteLogo) ?>" alt="<?= htmlspecialchars($shopName) ?>">
      <?php else: ?>
        <strong class="site-brand__text"><?= htmlspecialchars($shopName) ?></strong>
      <?php endif; ?>
      <?php if (trim((string)($storefront['brand_tagline'] ?? '')) !== ''): ?><span class="site-brand__tagline"><?= htmlspecialchars($storefront['brand_tagline']) ?></span><?php endif; ?>
    </a>
    <nav class="site-nav" id="site-navigation" aria-label="Główna nawigacja">
      <a href="/#ksiazki"><?= htmlspecialchars($storefront['nav_books_label'] ?? 'Książki') ?></a>
      <a href="/o-wydawnictwie">O wydawnictwie</a>
      <a href="/idea-znaku-arka">Idea znaku</a>
      <a href="/rekolekcje-pojednania">Rekolekcje</a>
      <a href="/wydarzenia">Wydarzenia</a>
      <?php if ($showHowNavigation): ?><a href="/#jak-kupic"><?= htmlspecialchars($storefront['nav_how_label'] ?? 'Jak kupić') ?></a><?php endif; ?>
      <a href="/regulamin"><?= htmlspecialchars($storefront['nav_terms_label'] ?? 'Regulamin') ?></a>
      <a href="/kontakt"><?= htmlspecialchars($storefront['nav_contact_label'] ?? 'Kontakt') ?></a>
    </nav>
  </div>
</header>
<script>
(() => {
  const header = document.querySelector('.site-header');
  const toggle = header?.querySelector('.site-menu-toggle');
  const navigation = header?.querySelector('.site-nav');
  if (!header || !toggle || !navigation) return;

  const closeMenu = () => {
    header.classList.remove('is-menu-open');
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-label', 'Otwórz menu');
  };

  toggle.addEventListener('click', () => {
    const open = !header.classList.contains('is-menu-open');
    header.classList.toggle('is-menu-open', open);
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    toggle.setAttribute('aria-label', open ? 'Zamknij menu' : 'Otwórz menu');
  });
  navigation.addEventListener('click', (event) => {
    if (event.target.closest('a')) closeMenu();
  });
  document.addEventListener('click', (event) => {
    if (!header.contains(event.target)) closeMenu();
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeMenu();
      toggle.focus();
    }
  });
  window.matchMedia('(min-width: 901px)').addEventListener('change', closeMenu);
})();
</script>
<main class="site-main">
