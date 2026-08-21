<?php include __DIR__ . '/../layout_top.php'; ?>
<?php
$ui = \Book100\Core\AdminPresenter::class;
$s = $storefront ?? $settings ?? [];
$logo = trim((string)($s['site_logo'] ?? ''));
$icon = trim((string)($s['site_icon'] ?? ''));
$ogImage = trim((string)($s['seo_default_og_image'] ?? ''));
?>

<div class="page-heading page-heading--compact">
  <div>
    <p class="kicker">KONFIGURACJA SKLEPU</p>
    <h1>Ustawienia witryny</h1>
    <p class="muted">Zmień markę, teksty, kontakt, SEO, sprzedaż i dokumenty — bez edycji kodu.</p>
  </div>
  <div class="actions">
    <a class="btn secondary" href="/homepage">Książki i promocje</a>
    <a class="btn secondary" href="<?= htmlspecialchars(\Book100\Core\StoreUrl::to('/')) ?>" target="_blank" rel="noopener">Zobacz sklep ↗</a>
  </div>
</div>

<nav class="settings-anchor-nav" aria-label="Sekcje ustawień">
  <a href="#konserwacja">Ważne uwagi</a>
  <a href="#marka">Marka</a>
  <a href="#strona-glowna">Strona główna</a>
  <a href="#newsletter-stopka">Newsletter i stopka</a>
  <a href="#zakup">Zakup</a>
  <a href="#seo">SEO</a>
  <a href="#sprzedaz">Sprzedaż</a>
  <a href="#dokumenty">Dokumenty</a>
  <button class="btn settings-anchor-nav__save" type="submit" form="store-settings-form">Zapisz wszystkie ustawienia</button>
</nav>

<form id="store-settings-form" class="settings-editor" method="post" action="/settings" enctype="multipart/form-data" data-ajax-refresh>
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">

  <section class="panel-section settings-section maintenance-settings" id="konserwacja">
    <div class="section-heading">
      <div><p class="section-label">KOMUNIKAT NAD MENU</p><h2>WAŻNE UWAGI</h2></div>
      <span class="muted">Przełącznik pokazuje lub ukrywa komunikat, ale nie blokuje zakupów.</span>
    </div>
    <label class="integration-toggle maintenance-toggle">
      <input type="checkbox" name="maintenance_enabled" value="1" <?= !empty($s['maintenance_enabled']) ? 'checked' : '' ?>>
      <span>
        <strong>Pokaż komunikat nad menu</strong>
        <small>Możesz wykorzystać go do informacji technicznej, wysyłkowej albo promocyjnej.</small>
      </span>
    </label>
    <label class="field">Treść komunikatu
      <input
        name="maintenance_message"
        maxlength="500"
        value="<?= htmlspecialchars($s['maintenance_message'] ?? 'Konserwacja systemu — prosimy nie dokonywać zakupu.') ?>"
        placeholder="Wpisz ważną informację widoczną nad menu"
      >
    </label>
  </section>

  <section class="panel-section settings-section" id="marka">
    <div class="section-heading">
      <div><p class="section-label">MARKA</p><h2>Logo i dane sklepu</h2></div>
      <span class="muted">Te dane pojawiają się w nagłówku, stopce i metadanych.</span>
    </div>

    <div class="settings-media-grid">
      <article class="brand-upload-card">
        <div class="brand-upload-preview brand-upload-preview--logo">
          <img id="site-logo-preview" src="<?= $logo !== '' ? htmlspecialchars($ui::publicAsset($logo)) : '' ?>" alt="" <?= $logo === '' ? 'hidden' : '' ?>>
          <span id="site-logo-placeholder" <?= $logo !== '' ? 'hidden' : '' ?>><?= htmlspecialchars($s['shop_name'] ?? 'Nazwa sklepu') ?></span>
        </div>
        <div>
          <p class="section-label">LOGO GŁÓWNE</p>
          <label class="upload-zone upload-zone--compact" for="site-logo-file" tabindex="0" role="button"
                 data-upload-zone data-upload-kind="image" data-preview-target="#site-logo-preview"
                 data-placeholder-target="#site-logo-placeholder" data-max-mb="12">
            <span class="upload-zone__icon" aria-hidden="true">↑</span>
            <strong>Przeciągnij logo</strong>
            <span>albo wybierz plik z dysku</span>
            <em data-upload-status>PNG, JPG lub WEBP · automatyczna optymalizacja</em>
          </label>
          <input id="site-logo-file" class="visually-hidden-file" type="file" name="site_logo_file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
          <?php if ($logo !== ''): ?>
            <button class="asset-remove-button" type="button" data-asset-remove
                    data-asset-scope="settings" data-asset-name="site_logo"
                    data-preview-target="#site-logo-preview" data-placeholder-target="#site-logo-placeholder"
                    data-file-input="#site-logo-file">
              <span aria-hidden="true">×</span> Usuń logo
            </button>
          <?php endif; ?>
        </div>
      </article>

      <article class="brand-upload-card">
        <div class="brand-upload-preview brand-upload-preview--icon">
          <img id="site-icon-preview" src="<?= $icon !== '' ? htmlspecialchars($ui::publicAsset($icon)) : '' ?>" alt="" <?= $icon === '' ? 'hidden' : '' ?>>
          <span id="site-icon-placeholder" <?= $icon !== '' ? 'hidden' : '' ?>>IKONA</span>
        </div>
        <div>
          <p class="section-label">IKONA KARTY</p>
          <label class="upload-zone upload-zone--compact" for="site-icon-file" tabindex="0" role="button"
                 data-upload-zone data-upload-kind="image" data-preview-target="#site-icon-preview"
                 data-placeholder-target="#site-icon-placeholder" data-max-mb="5">
            <span class="upload-zone__icon" aria-hidden="true">↑</span>
            <strong>Przeciągnij ikonę</strong>
            <span>najlepiej kwadrat 512×512 px</span>
            <em data-upload-status>PNG, JPG lub WEBP</em>
          </label>
          <input id="site-icon-file" class="visually-hidden-file" type="file" name="site_icon_file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
          <?php if ($icon !== ''): ?>
            <button class="asset-remove-button" type="button" data-asset-remove
                    data-asset-scope="settings" data-asset-name="site_icon"
                    data-preview-target="#site-icon-preview" data-placeholder-target="#site-icon-placeholder"
                    data-file-input="#site-icon-file">
              <span aria-hidden="true">×</span> Usuń ikonę
            </button>
          <?php endif; ?>
        </div>
      </article>
    </div>

    <div class="settings-grid settings-grid--three">
      <label class="field">Nazwa sklepu<input name="shop_name" maxlength="120" required value="<?= htmlspecialchars($s['shop_name'] ?? '') ?>"></label>
      <label class="field">Domena sklepu<small>Jeden główny adres dla SEO, maili, płatności i integracji.</small><input name="shop_url" type="url" maxlength="255" required value="<?= htmlspecialchars($s['shop_url'] ?? '') ?>" placeholder="https://arka-pojednanie.pl"></label>
      <label class="field">Podpis pod logo<input name="brand_tagline" maxlength="180" value="<?= htmlspecialchars($s['brand_tagline'] ?? '') ?>"></label>
      <label class="field">E-mail sklepu<input name="shop_email" type="email" maxlength="190" required value="<?= htmlspecialchars($s['shop_email'] ?? '') ?>"></label>
      <label class="field">Telefon<input name="shop_phone" maxlength="60" value="<?= htmlspecialchars($s['shop_phone'] ?? '') ?>"></label>
      <label class="field color-setting">Kolor główny<input name="brand_accent_color" type="color" value="<?= htmlspecialchars($s['brand_accent_color'] ?? '#e91d2a') ?>" data-color-input><small data-color-value><?= htmlspecialchars($s['brand_accent_color'] ?? '#e91d2a') ?></small></label>
      <label class="field color-setting">Kolor po najechaniu<input name="brand_accent_dark" type="color" value="<?= htmlspecialchars($s['brand_accent_dark'] ?? '#b50f1a') ?>" data-color-input><small data-color-value><?= htmlspecialchars($s['brand_accent_dark'] ?? '#b50f1a') ?></small></label>
    </div>
    <label class="field">Adres i dane sprzedawcy<textarea name="shop_address" rows="4" maxlength="2000"><?= htmlspecialchars($s['shop_address'] ?? '') ?></textarea></label>
  </section>

  <section class="panel-section settings-section" id="strona-glowna">
    <div class="section-heading">
      <div><p class="section-label">STRONA GŁÓWNA</p><h2>Nagłówki i komunikaty</h2></div>
      <a class="text-button" href="/homepage">Ustaw książki, promocje i kolejność →</a>
    </div>

    <h3 class="settings-subheading">Menu</h3>
    <div class="settings-grid settings-grid--four">
      <label class="field">Książki<input name="nav_books_label" maxlength="40" value="<?= htmlspecialchars($s['nav_books_label'] ?? '') ?>"></label>
      <label class="field">Jak kupić<input name="nav_how_label" maxlength="40" value="<?= htmlspecialchars($s['nav_how_label'] ?? '') ?>"></label>
      <label class="field">Regulamin<input name="nav_terms_label" maxlength="40" value="<?= htmlspecialchars($s['nav_terms_label'] ?? '') ?>"></label>
      <label class="field">Kontakt<input name="nav_contact_label" maxlength="40" value="<?= htmlspecialchars($s['nav_contact_label'] ?? '') ?>"></label>
    </div>

    <h3 class="settings-subheading">Katalog książek</h3>
    <div class="settings-grid">
      <label class="field">Mały nagłówek<input name="home_catalog_eyebrow" maxlength="80" value="<?= htmlspecialchars($s['home_catalog_eyebrow'] ?? '') ?>"></label>
      <label class="field">Główny nagłówek<input name="home_catalog_title" maxlength="160" value="<?= htmlspecialchars($s['home_catalog_title'] ?? '') ?>"></label>
    </div>

    <h3 class="settings-subheading">Sekcja „Jak kupić”</h3>
    <div class="settings-grid">
      <label class="field">Mały nagłówek<input name="home_how_eyebrow" maxlength="80" value="<?= htmlspecialchars($s['home_how_eyebrow'] ?? '') ?>"></label>
      <label class="field">Główny nagłówek<input name="home_how_title" maxlength="180" value="<?= htmlspecialchars($s['home_how_title'] ?? '') ?>"></label>
    </div>
    <div class="settings-step-grid">
      <?php foreach ([1, 2, 3] as $step): ?>
        <article class="settings-step-card">
          <span><?= str_pad((string)$step, 2, '0', STR_PAD_LEFT) ?></span>
          <label class="field">Tytuł<input name="home_step_<?= $step ?>_title" maxlength="80" value="<?= htmlspecialchars($s['home_step_' . $step . '_title'] ?? '') ?>"></label>
          <label class="field">Opis<textarea name="home_step_<?= $step ?>_text" rows="4" maxlength="500"><?= htmlspecialchars($s['home_step_' . $step . '_text'] ?? '') ?></textarea></label>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="panel-section settings-section" id="newsletter-stopka">
    <div class="section-heading"><div><p class="section-label">KOMUNIKACJA</p><h2>Newsletter i stopka</h2></div></div>
    <div class="settings-grid">
      <label class="field">Mały nagłówek newslettera<input name="newsletter_eyebrow" maxlength="80" value="<?= htmlspecialchars($s['newsletter_eyebrow'] ?? '') ?>"></label>
      <label class="field">Nagłówek newslettera<input name="newsletter_title" maxlength="180" value="<?= htmlspecialchars($s['newsletter_title'] ?? '') ?>"></label>
      <label class="field">Tekst przycisku<input name="newsletter_button_label" maxlength="60" value="<?= htmlspecialchars($s['newsletter_button_label'] ?? '') ?>"></label>
      <label class="field">Tekst zgody przed linkiem do polityki<input name="newsletter_consent_text" maxlength="500" value="<?= htmlspecialchars($s['newsletter_consent_text'] ?? '') ?>"></label>
    </div>
    <label class="field">Opis newslettera<textarea name="newsletter_text" rows="3" maxlength="1000"><?= htmlspecialchars($s['newsletter_text'] ?? '') ?></textarea></label>
    <div class="settings-grid settings-grid--four">
      <label class="field">Nagłówek „Sklep”<input name="footer_shop_heading" maxlength="60" value="<?= htmlspecialchars($s['footer_shop_heading'] ?? '') ?>"></label>
      <label class="field">Nagłówek „Informacje”<input name="footer_info_heading" maxlength="60" value="<?= htmlspecialchars($s['footer_info_heading'] ?? '') ?>"></label>
      <label class="field">Nagłówek „Płatności”<input name="footer_payments_heading" maxlength="60" value="<?= htmlspecialchars($s['footer_payments_heading'] ?? '') ?>"></label>
      <label class="field">Dolny podpis stopki<input name="footer_bottom_text" maxlength="180" value="<?= htmlspecialchars($s['footer_bottom_text'] ?? '') ?>"></label>
    </div>
  </section>

  <section class="panel-section settings-section" id="zakup">
    <div class="section-heading"><div><p class="section-label">ZAKUP</p><h2>Teksty finalizacji zamówienia</h2></div></div>
    <div class="settings-grid">
      <label class="field">Mały nagłówek<input name="checkout_eyebrow" maxlength="80" value="<?= htmlspecialchars($s['checkout_eyebrow'] ?? '') ?>"></label>
      <label class="field">Główny nagłówek<input name="checkout_title" maxlength="180" value="<?= htmlspecialchars($s['checkout_title'] ?? '') ?>"></label>
    </div>
    <label class="field">Tekst wprowadzający<textarea name="checkout_lead" rows="3" maxlength="1000"><?= htmlspecialchars($s['checkout_lead'] ?? '') ?></textarea></label>
    <div class="settings-grid settings-grid--four">
      <label class="field">Korzyść 1<input name="checkout_assurance_1" maxlength="100" value="<?= htmlspecialchars($s['checkout_assurance_1'] ?? '') ?>"></label>
      <label class="field">Korzyść 2<input name="checkout_assurance_2" maxlength="100" value="<?= htmlspecialchars($s['checkout_assurance_2'] ?? '') ?>"></label>
      <label class="field">Dostawa książki<input name="checkout_assurance_paper" maxlength="120" value="<?= htmlspecialchars($s['checkout_assurance_paper'] ?? '') ?>"></label>
      <label class="field">Dostawa ebooka<input name="checkout_assurance_ebook" maxlength="120" value="<?= htmlspecialchars($s['checkout_assurance_ebook'] ?? '') ?>"></label>
    </div>
  </section>

  <section class="panel-section settings-section" id="seo">
    <div class="section-heading"><div><p class="section-label">WYSZUKIWARKI I SOCIAL MEDIA</p><h2>SEO całego sklepu</h2></div></div>
    <div class="settings-grid">
      <label class="field">Tytuł strony głównej<input name="seo_home_title" maxlength="190" value="<?= htmlspecialchars($s['seo_home_title'] ?? '') ?>"></label>
      <label class="field">Dopisek do tytułów podstron<input name="seo_title_suffix" maxlength="80" value="<?= htmlspecialchars($s['seo_title_suffix'] ?? '') ?>"></label>
    </div>
    <label class="field">Opis strony głównej<textarea name="seo_home_description" rows="3" maxlength="500"><?= htmlspecialchars($s['seo_home_description'] ?? '') ?></textarea></label>

    <article class="brand-upload-card brand-upload-card--wide">
      <div class="brand-upload-preview brand-upload-preview--social">
        <img id="seo-og-preview" src="<?= $ogImage !== '' ? htmlspecialchars($ui::publicAsset($ogImage)) : '' ?>" alt="" <?= $ogImage === '' ? 'hidden' : '' ?>>
        <span id="seo-og-placeholder" <?= $ogImage !== '' ? 'hidden' : '' ?>>1200 × 630</span>
      </div>
      <div>
        <p class="section-label">GRAFIKA UDOSTĘPNIANIA</p>
        <p class="muted">Pokazywana przy udostępnianiu strony głównej w komunikatorach i mediach społecznościowych.</p>
        <label class="upload-zone upload-zone--compact" for="seo-og-image-file" tabindex="0" role="button"
               data-upload-zone data-upload-kind="image" data-preview-target="#seo-og-preview"
               data-placeholder-target="#seo-og-placeholder" data-max-mb="12">
          <span class="upload-zone__icon" aria-hidden="true">↑</span>
          <strong>Przeciągnij grafikę</strong>
          <span>zalecany format 1200×630 px</span>
          <em data-upload-status>PNG, JPG lub WEBP</em>
        </label>
        <input id="seo-og-image-file" class="visually-hidden-file" type="file" name="seo_og_image_file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
        <?php if ($ogImage !== ''): ?>
          <button class="asset-remove-button" type="button" data-asset-remove
                  data-asset-scope="settings" data-asset-name="seo_default_og_image"
                  data-preview-target="#seo-og-preview" data-placeholder-target="#seo-og-placeholder"
                  data-file-input="#seo-og-image-file">
            <span aria-hidden="true">×</span> Usuń grafikę
          </button>
        <?php endif; ?>
      </div>
    </article>
  </section>

  <section class="panel-section settings-section" id="sprzedaz">
    <div class="section-heading">
      <div><p class="section-label">SPRZEDAŻ</p><h2>Waluta i koszty dostawy</h2></div>
      <a class="text-button" href="/integrations">Płatności i InPost są w Integracjach →</a>
    </div>
    <div class="settings-grid settings-grid--four">
      <label class="field">Waluta<input name="currency" maxlength="3" value="<?= htmlspecialchars($s['currency'] ?? 'PLN') ?>"></label>
      <label class="field">Domyślny koszt dostawy<input name="shipping_default_gross" inputmode="decimal" value="<?= htmlspecialchars($s['shipping_default_gross'] ?? '0.00') ?>"></label>
      <label class="field">InPost Paczkomat brutto<input name="shipping_inpost_locker_gross" inputmode="decimal" value="<?= htmlspecialchars($s['shipping_inpost_locker_gross'] ?? '0.00') ?>"></label>
      <label class="field">InPost Kurier brutto<input name="shipping_inpost_courier_gross" inputmode="decimal" value="<?= htmlspecialchars($s['shipping_inpost_courier_gross'] ?? '0.00') ?>"></label>
    </div>
  </section>

  <section class="panel-section settings-section" id="dokumenty">
    <div class="section-heading"><div><p class="section-label">DOKUMENTY I KONTAKT</p><h2>Publiczne podstrony sklepu</h2></div></div>
    <div class="settings-grid">
      <label class="field">Tytuł strony kontaktowej<input name="contact_title" maxlength="120" value="<?= htmlspecialchars($s['contact_title'] ?? '') ?>"></label>
      <label class="field">Tytuł regulaminu<input name="terms_title" maxlength="120" value="<?= htmlspecialchars($s['terms_title'] ?? '') ?>"></label>
      <label class="field">Tytuł polityki prywatności<input name="privacy_title" maxlength="120" value="<?= htmlspecialchars($s['privacy_title'] ?? '') ?>"></label>
    </div>
    <label class="field">Kontakt<textarea name="contact_text" rows="7" maxlength="20000"><?= htmlspecialchars($s['contact_text'] ?? '') ?></textarea><small>Możesz używać zwykłego tekstu z akapitami i listami.</small></label>
    <label class="field">Regulamin<textarea name="terms_text" rows="18" maxlength="200000"><?= htmlspecialchars($s['terms_text'] ?? '') ?></textarea><small>Treść jest publikowana na stronie regulaminu i zapisywana przy zamówieniu jako dowód zaakceptowanej wersji.</small></label>
    <label class="field">Polityka prywatności<textarea name="privacy_text" rows="18" maxlength="200000"><?= htmlspecialchars($s['privacy_text'] ?? '') ?></textarea></label>
  </section>

</form>

<details class="panel-section collapsible">
  <summary><span><span class="section-label">BEZPIECZEŃSTWO</span><strong>Hasło administratora</strong></span><span>Rozwiń</span></summary>
  <form class="form" method="post" action="/settings/password" data-ajax-reset>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">
    <label class="field">Obecne hasło<input type="password" name="current_password" required autocomplete="current-password"></label>
    <label class="field">Nowe hasło (minimum 12 znaków)<input type="password" name="new_password" minlength="12" required autocomplete="new-password"></label>
    <label class="field">Powtórz nowe hasło<input type="password" name="new_password_repeat" minlength="12" required autocomplete="new-password"></label>
    <button class="btn" type="submit">Zmień hasło</button>
  </form>
  <p><a class="text-button" href="/security/2fa">Google Authenticator / 2FA →</a></p>
</details>

<div class="actions"><a class="btn secondary" href="/system-check">Sprawdź system</a><a class="btn secondary" href="/integrations">Integracje</a></div>
<?php include __DIR__ . '/../layout_bottom.php'; ?>
