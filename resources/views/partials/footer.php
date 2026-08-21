</main>
<footer class="site-footer">
  <div class="newsletter-band">
    <div>
      <p class="eyebrow"><?= htmlspecialchars($storefront['newsletter_eyebrow'] ?? 'Newsletter') ?></p>
      <h2><?= htmlspecialchars($storefront['newsletter_title'] ?? '') ?></h2>
      <p><?= htmlspecialchars($storefront['newsletter_text'] ?? '') ?></p>
    </div>
    <form class="newsletter" method="post" action="/newsletter/zapisz" data-public-csrf-form>
      <input type="hidden" name="_csrf" value="" data-public-csrf>
      <div class="newsletter__row">
        <input name="email" type="email" placeholder="Twój adres e-mail" aria-label="Twój adres e-mail" required>
        <button class="btn" type="submit" disabled data-public-csrf-submit><?= htmlspecialchars($storefront['newsletter_button_label'] ?? 'Zapisuję się') ?></button>
      </div>
      <label class="newsletter__consent"><input type="checkbox" name="consent" value="1" required> <?= htmlspecialchars($storefront['newsletter_consent_text'] ?? 'Znam') ?> <a href="/polityka-prywatnosci">politykę prywatności</a>.</label>
      <?php if (($message ?? '') === 'ok'): ?><span class="notice">Zapis przyjęty.</span><?php endif; ?>
      <?php if (($message ?? '') === 'consent'): ?><span class="error">Zaznacz zgodę na newsletter.</span><?php endif; ?>
    </form>
  </div>
  <div class="site-footer__main">
    <div class="site-footer__brand">
      <a class="site-footer__brand-link" href="/idea-znaku-arka" aria-label="Poznaj ideę znaku ARKA">
        <?php if ($siteLogo !== ''): ?>
          <img src="<?= htmlspecialchars($siteLogo) ?>" alt="<?= htmlspecialchars($shopName) ?>">
        <?php else: ?>
          <strong class="site-footer__brand-text"><?= htmlspecialchars($shopName) ?></strong>
        <?php endif; ?>
        <?php if (trim((string)($storefront['brand_tagline'] ?? '')) !== ''): ?><span class="site-footer__tagline"><?= htmlspecialchars($storefront['brand_tagline']) ?></span><?php endif; ?>
      </a>
    </div>
    <div>
      <strong><?= htmlspecialchars($storefront['footer_shop_heading'] ?? 'Sklep') ?></strong>
      <a href="/#ksiazki"><?= htmlspecialchars($storefront['nav_books_label'] ?? 'Książki') ?></a>
      <a href="/o-wydawnictwie">O wydawnictwie</a>
      <a href="/rekolekcje-pojednania">Rekolekcje Pojednania</a>
      <?php if (!empty($showHowNavigation)): ?><a href="/#jak-kupic"><?= htmlspecialchars($storefront['nav_how_label'] ?? 'Jak kupić') ?></a><?php endif; ?>
      <a href="/kontakt"><?= htmlspecialchars($storefront['nav_contact_label'] ?? 'Kontakt') ?></a>
    </div>
    <div>
      <strong><?= htmlspecialchars($storefront['footer_info_heading'] ?? 'Informacje') ?></strong>
      <a href="/idea-znaku-arka">Idea znaku ARKA</a>
      <a href="/regulamin"><?= htmlspecialchars($storefront['nav_terms_label'] ?? 'Regulamin') ?></a>
      <a href="/polityka-prywatnosci"><?= htmlspecialchars($storefront['privacy_title'] ?? 'Polityka prywatności') ?></a>
      <button class="site-footer__privacy-button" type="button" data-cookie-settings>Ustawienia cookies</button>
      <?php if (trim((string)($storefront['shop_email'] ?? '')) !== ''): ?><a href="mailto:<?= htmlspecialchars($storefront['shop_email']) ?>"><?= htmlspecialchars($storefront['shop_email']) ?></a><?php endif; ?>
      <?php if (trim((string)($storefront['shop_phone'] ?? '')) !== ''): ?><a href="tel:<?= htmlspecialchars($storefront['shop_phone']) ?>"><?= htmlspecialchars($storefront['shop_phone']) ?></a><?php endif; ?>
    </div>
    <div class="site-footer__payments">
      <strong><?= htmlspecialchars($storefront['footer_payments_heading'] ?? 'Płatności') ?></strong>
      <span>Przelewy24</span>
      <span>BLIK i szybkie przelewy</span>
    </div>
  </div>
  <div class="site-footer__bottom"><span>© <?= date('Y') ?> <?= htmlspecialchars($shopName) ?></span><span><?= htmlspecialchars($storefront['footer_bottom_text'] ?? '') ?></span></div>
</footer>
<script data-public-csrf-bootstrap>
(function () {
  const forms = Array.from(document.querySelectorAll('[data-public-csrf-form]'));
  if (!forms.length) return;

  fetch('/api/csrf', {
    credentials: 'same-origin',
    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
  })
    .then(function (response) {
      if (!response.ok) throw new Error('csrf');
      return response.json();
    })
    .then(function (payload) {
      if (!payload.token) throw new Error('csrf');
      forms.forEach(function (form) {
        const field = form.querySelector('[data-public-csrf]');
        const submit = form.querySelector('[data-public-csrf-submit]');
        if (field) field.value = payload.token;
        if (submit) submit.disabled = false;
      });
    })
    .catch(function () {
      forms.forEach(function (form) {
        const submit = form.querySelector('[data-public-csrf-submit]');
        if (submit) submit.textContent = 'Odśwież stronę';
      });
    });
})();
</script>
<?php
$publicScript = \Book100\Core\Paths::publicRoot() . '/assets/public.js';
$publicScriptVersion = is_file($publicScript) ? (string)filemtime($publicScript) : '1';
?>
<?php if (is_file($publicScript)): ?>
<script src="/assets/public.js?v=<?= htmlspecialchars($publicScriptVersion) ?>" defer></script>
<?php endif; ?>
<?php $analytics = \Book100\Services\Integrations\GoogleAnalytics::configuration(); ?>
<?php if (!empty($analytics['enabled'])): ?>
<aside class="cookie-consent" data-cookie-consent hidden aria-labelledby="cookie-consent-title">
  <div class="cookie-consent__copy">
    <strong id="cookie-consent-title">Dbamy o Twoją prywatność</strong>
    <p>
      Używamy niezbędnych plików cookies, dzięki którym strona działa prawidłowo.
      Za Twoją zgodą włączymy również statystyki Google Analytics, aby lepiej rozumieć, które treści są pomocne.
      Szczegóły znajdziesz w <a href="/polityka-prywatnosci">polityce prywatności</a>
      i <a href="/regulamin">regulaminie</a>.
    </p>
  </div>
  <div class="cookie-consent__actions">
    <button class="btn secondary" type="button" data-cookie-choice="necessary">Tylko niezbędne</button>
    <button class="btn" type="button" data-cookie-choice="analytics">Zgadzam się na statystyki</button>
  </div>
</aside>
<script data-google-analytics-consent>
(function () {
  const measurementId = <?= json_encode((string)$analytics['measurement_id'], JSON_UNESCAPED_SLASHES) ?>;
  const storageKey = 'arka_cookie_consent_v1';
  const banner = document.querySelector('[data-cookie-consent]');
  const settingsButtons = document.querySelectorAll('[data-cookie-settings]');
  let analyticsLoaded = false;

  window.dataLayer = window.dataLayer || [];
  window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
  window.gtag('consent', 'default', {
    analytics_storage: 'denied',
    ad_storage: 'denied',
    ad_user_data: 'denied',
    ad_personalization: 'denied',
    wait_for_update: 500
  });

  function readChoice() {
    try { return window.localStorage.getItem(storageKey) || ''; } catch (error) { return ''; }
  }

  function saveChoice(choice) {
    try { window.localStorage.setItem(storageKey, choice); } catch (error) {}
  }

  function showBanner() {
    if (!banner) return;
    banner.hidden = false;
    window.requestAnimationFrame(function () { banner.classList.add('is-visible'); });
  }

  function hideBanner() {
    if (!banner) return;
    banner.classList.remove('is-visible');
    window.setTimeout(function () { banner.hidden = true; }, 180);
  }

  function removeAnalyticsCookies() {
    document.cookie.split(';').forEach(function (item) {
      const name = item.split('=')[0].trim();
      if (name === '_ga' || name.indexOf('_ga_') === 0) {
        const expired = name + '=; Max-Age=0; Path=/; SameSite=Lax';
        document.cookie = expired;
        document.cookie = expired + '; Domain=' + window.location.hostname;
        document.cookie = expired + '; Domain=.' + window.location.hostname;
      }
    });
  }

  function loadAnalytics() {
    if (analyticsLoaded || !measurementId) return;
    analyticsLoaded = true;
    window.gtag('consent', 'update', {
      analytics_storage: 'granted',
      ad_storage: 'denied',
      ad_user_data: 'denied',
      ad_personalization: 'denied'
    });
    window.gtag('set', 'ads_data_redaction', true);
    window.gtag('js', new Date());
    window.gtag('config', measurementId, {
      allow_google_signals: false,
      allow_ad_personalization_signals: false
    });

    const script = document.createElement('script');
    script.async = true;
    script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(measurementId);
    document.head.appendChild(script);
  }

  function applyChoice(choice) {
    saveChoice(choice);
    if (choice === 'analytics') {
      loadAnalytics();
    } else {
      window.gtag('consent', 'update', {
        analytics_storage: 'denied',
        ad_storage: 'denied',
        ad_user_data: 'denied',
        ad_personalization: 'denied'
      });
      removeAnalyticsCookies();
    }
    hideBanner();
  }

  document.querySelectorAll('[data-cookie-choice]').forEach(function (button) {
    button.addEventListener('click', function () {
      applyChoice(button.getAttribute('data-cookie-choice') || 'necessary');
    });
  });
  settingsButtons.forEach(function (button) {
    button.addEventListener('click', showBanner);
  });

  const savedChoice = readChoice();
  if (savedChoice === 'analytics') {
    loadAnalytics();
  } else if (savedChoice !== 'necessary') {
    showBanner();
  }
})();
</script>
<?php endif; ?>
<?php $tawk = \Book100\Services\Integrations\TawkWidget::configuration(); ?>
<?php if (!empty($tawk['enabled']) && !empty($tawk['embed_url'])): ?>
<button
  class="tawk-launcher"
  type="button"
  data-tawk-launcher
  data-tawk-url="<?= htmlspecialchars((string)$tawk['embed_url'], ENT_QUOTES) ?>"
  aria-label="Jesteśmy online — rozpocznij rozmowę"
>
  <span class="tawk-launcher__dot" aria-hidden="true"></span>
  <span data-tawk-label>Jesteśmy online — porozmawiaj</span>
</button>
<script data-tawk-widget-live>
(function () {
  const launcher = document.querySelector('[data-tawk-launcher]');
  if (!launcher) return;
  const label = launcher.querySelector('[data-tawk-label]');
  let openWhenReady = false;

  function updateStatus(status) {
    launcher.dataset.tawkStatus = status || 'offline';
    if (!label || launcher.getAttribute('aria-busy') === 'true') return;
    if (status === 'online') {
      label.textContent = 'Jesteśmy online — porozmawiaj';
      launcher.setAttribute('aria-label', 'Jesteśmy online — rozpocznij rozmowę');
      return;
    }
    if (status === 'away') {
      label.textContent = 'Napisz do nas';
      launcher.setAttribute('aria-label', 'Napisz do nas na czacie');
      return;
    }
    label.textContent = 'Zostaw wiadomość';
    launcher.setAttribute('aria-label', 'Zostaw wiadomość na czacie');
  }

  launcher.addEventListener('click', function () {
    openWhenReady = true;
    launcher.setAttribute('aria-busy', 'true');
    if (label) label.textContent = 'Otwieram czat…';

    if (window.Tawk_API && typeof window.Tawk_API.maximize === 'function') {
      window.Tawk_API.showWidget();
      window.Tawk_API.maximize();
      launcher.hidden = true;
      launcher.removeAttribute('aria-busy');
    }
  });

  window.Tawk_API = window.Tawk_API || {};
  window.Tawk_LoadStart = new Date();
  window.Tawk_API.onBeforeLoad = function () {
    window.Tawk_API.hideWidget();
  };
  window.Tawk_API.onLoad = function () {
    window.Tawk_API.hideWidget();
    updateStatus(window.Tawk_API.getStatus());
    if (openWhenReady) {
      window.Tawk_API.showWidget();
      window.Tawk_API.maximize();
      launcher.hidden = true;
    }
    launcher.removeAttribute('aria-busy');
  };
  window.Tawk_API.onStatusChange = updateStatus;
  window.Tawk_API.onChatHidden = function () {
    window.Tawk_API.hideWidget();
    launcher.hidden = false;
    openWhenReady = false;
    updateStatus(window.Tawk_API.getStatus());
  };

  const script = document.createElement('script');
  script.async = true;
  script.src = launcher.dataset.tawkUrl;
  script.charset = 'UTF-8';
  script.setAttribute('crossorigin', '*');
  script.onerror = function () {
    launcher.removeAttribute('aria-busy');
    updateStatus('offline');
  };
  document.head.appendChild(script);
})();
</script>
<?php endif; ?>
</body>
</html>
