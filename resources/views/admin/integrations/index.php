<?php include __DIR__ . '/../layout_top.php'; ?>
<?php
$inpostData = $integrations['inpost'] ?? [];
$p24 = $integrations['p24'] ?? [];
$stripe = $integrations['stripe'] ?? [];
$tawk = $integrations['tawk'] ?? [];
$merchant = $integrations['merchant'] ?? [];
$analytics = $integrations['analytics'] ?? [];
$mail = $integrations['mail'] ?? [];
$secretPlaceholder = static fn(array $state): string => !empty($state['configured'])
    ? (string)($state['masked'] ?? 'ustawione')
    : 'nie ustawiono';
?>

<div class="page-heading page-heading--compact">
  <div>
    <p class="kicker">POŁĄCZENIA SKLEPU</p>
    <h1>Integracje</h1>
    <p class="muted">W jednym miejscu: płatności, InPost, Google Merchant, poczta transakcyjna i czat Tawk.to.</p>
  </div>
  <span class="pill pill--<?= !empty($inpost['configured']) ? 'success' : 'warning' ?>">
    <?= !empty($inpost['configured']) ? 'InPost skonfigurowany' : 'InPost wymaga danych' ?>
  </span>
</div>

<form method="post" action="/integrations" class="integration-form editor-form editor-form--with-savebar" data-ajax-refresh>
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">

  <div class="editor-savebar">
    <div class="editor-savebar__copy">
      <strong>Zapisz integracje</strong>
      <span>Puste pola haseł i kluczy zachowują obecne sekrety.</span>
    </div>
    <button class="btn" type="submit">Zapisz wszystkie integracje</button>
  </div>

  <section class="integration-card integration-card--inpost">
    <div class="integration-card__heading">
      <div>
        <span class="integration-logo integration-logo--inpost">InPost</span>
        <h2>Wysyłka i etykiety ShipX</h2>
        <p>Tworzenie przesyłki, druk PDF A6, numer śledzenia i automatyczne statusy.</p>
      </div>
      <span class="config-state <?= !empty($inpost['configured']) ? 'config-state--ok' : '' ?>">
        <?= !empty($inpost['configured']) ? 'Gotowe do testu' : 'Niepołączone' ?>
      </span>
    </div>

    <div class="integration-fields integration-fields--four">
      <label class="field">Tryb
        <select name="INPOST_MODE">
          <option value="sandbox" <?= ($inpostData['mode'] ?? '') === 'sandbox' ? 'selected' : '' ?>>Sandbox — testy</option>
          <option value="production" <?= ($inpostData['mode'] ?? '') === 'production' ? 'selected' : '' ?>>Produkcja — prawdziwe przesyłki</option>
        </select>
      </label>
      <label class="field">ID organizacji
        <input name="INPOST_ORGANIZATION_ID" value="<?= htmlspecialchars($inpostData['organization_id'] ?? '') ?>" inputmode="numeric" autocomplete="off">
      </label>
      <label class="field">Token API ShipX
        <input type="password" name="INPOST_API_TOKEN" value="" placeholder="<?= htmlspecialchars($secretPlaceholder($inpostData['api_token'] ?? [])) ?>" autocomplete="new-password">
        <small>Puste pole zachowuje obecny token.</small>
      </label>
      <label class="field">Token Geowidget
        <input type="password" name="INPOST_GEO_WIDGET_TOKEN" value="" placeholder="<?= htmlspecialchars($secretPlaceholder($inpostData['geowidget_token'] ?? [])) ?>" autocomplete="new-password">
        <small>Oddzielny token do wyboru Paczkomatu w koszyku.</small>
      </label>
      <label class="field">Domyślny gabaryt
        <select name="INPOST_DEFAULT_PARCEL_TEMPLATE">
          <option value="small" <?= ($inpostData['parcel_template'] ?? '') === 'small' ? 'selected' : '' ?>>A — 8 × 38 × 64 cm</option>
          <option value="medium" <?= ($inpostData['parcel_template'] ?? '') === 'medium' ? 'selected' : '' ?>>B — 19 × 38 × 64 cm</option>
          <option value="large" <?= ($inpostData['parcel_template'] ?? '') === 'large' ? 'selected' : '' ?>>C — 41 × 38 × 64 cm</option>
        </select>
      </label>
      <label class="field">Domyślny sposób nadania
        <select name="INPOST_DEFAULT_SENDING_METHOD">
          <option value="any_point" <?= ($inpostData['sending_method'] ?? '') === 'any_point' ? 'selected' : '' ?>>Dowolny punkt InPost</option>
          <option value="parcel_locker" <?= ($inpostData['sending_method'] ?? '') === 'parcel_locker' ? 'selected' : '' ?>>Paczkomat</option>
          <option value="pop" <?= ($inpostData['sending_method'] ?? '') === 'pop' ? 'selected' : '' ?>>Punkt Obsługi Paczek</option>
          <option value="branch" <?= ($inpostData['sending_method'] ?? '') === 'branch' ? 'selected' : '' ?>>Oddział</option>
          <option value="dispatch_order" <?= ($inpostData['sending_method'] ?? '') === 'dispatch_order' ? 'selected' : '' ?>>Podjazd kuriera</option>
        </select>
      </label>
      <label class="check-line integration-service-toggle">
        <input type="checkbox" name="INPOST_COURIER_ENABLED" value="1" <?= !empty($inpostData['courier_enabled']) ? 'checked' : '' ?>>
        Udostępnij klientom InPost Kurier
        <small>Włącz dopiero, gdy organizacja ma usługę inpost_courier_standard.</small>
      </label>
    </div>

    <div class="webhook-box">
      <div>
        <span class="section-label">WEBHOOK INPOST</span>
        <strong><?= !empty($inpostData['webhook_secret']['configured']) ? 'Sekret ustawiony' : 'Wygeneruj bezpieczny adres' ?></strong>
        <p>Po wdrożeniu publicznym wklejasz ten adres w Managerze Paczek: Moje konto → API.</p>
      </div>
      <?php if (!empty($inpostData['webhook_url'])): ?>
        <label class="copy-field">Adres odbiorczy
          <input readonly value="<?= htmlspecialchars($inpostData['webhook_url']) ?>" onclick="this.select()">
        </label>
      <?php else: ?>
        <label class="check-line"><input type="checkbox" name="GENERATE_INPOST_WEBHOOK_SECRET" value="1"> Wygeneruj nowy sekret webhooka przy zapisie</label>
      <?php endif; ?>
    </div>
    <?php if (!empty($inpostData['webhook_url']) && empty($inpost['public_webhook'])): ?>
      <div class="notice notice--plain">Adres lokalny działa do testów panelu, ale serwery InPost go nie zobaczą. W Managerze Paczek ustaw webhook dopiero po wdrożeniu sklepu pod publicznym adresem HTTPS.</div>
    <?php endif; ?>

    <div class="integration-actions">
      <button class="btn" type="submit">Zapisz dane integracji</button>
      <span>Format etykiety: <strong>PDF A6</strong></span>
    </div>
  </section>

  <section class="integration-card">
    <div class="integration-card__heading">
      <div>
        <span class="integration-logo integration-logo--p24">P24</span>
        <h2>Przelewy24 — główna płatność</h2>
        <p>Bezpośrednia integracja z kontem Przelewy24: BLIK, szybkie przelewy, weryfikacja transakcji i zwroty.</p>
      </div>
      <span class="pill pill--success">Główna płatność</span>
    </div>
    <input type="hidden" name="PAYMENT_PRIMARY" value="przelewy24">
    <div class="integration-fields integration-fields--three">
      <label class="field">Tryb
        <select name="P24_MODE">
          <option value="sandbox" <?= ($p24['mode'] ?? '') === 'sandbox' ? 'selected' : '' ?>>Sandbox — testy</option>
          <option value="production" <?= ($p24['mode'] ?? '') === 'production' ? 'selected' : '' ?>>Produkcja</option>
        </select>
      </label>
      <label class="field">Merchant ID
        <input type="text" name="P24_MERCHANT_ID" inputmode="numeric" value="<?= htmlspecialchars($p24['merchant_id'] ?? '') ?>" autocomplete="off">
      </label>
      <label class="field">POS ID
        <input type="text" name="P24_POS_ID" inputmode="numeric" value="<?= htmlspecialchars($p24['pos_id'] ?? '') ?>" autocomplete="off">
      </label>
      <label class="field">Klucz API (secretId)
        <input type="password" name="P24_API_KEY" placeholder="<?= htmlspecialchars($secretPlaceholder($p24['api_key'] ?? [])) ?>" autocomplete="new-password">
        <small>Puste pole zachowuje obecny klucz.</small>
      </label>
      <label class="field">Klucz CRC
        <input type="password" name="P24_CRC" placeholder="<?= htmlspecialchars($secretPlaceholder($p24['crc'] ?? [])) ?>" autocomplete="new-password">
        <small>CRC jest inne dla sandboxu i produkcji.</small>
      </label>
    </div>
    <div class="endpoint-list">
      <span>Webhook płatności <input readonly value="<?= htmlspecialchars($p24['webhook_url'] ?? '') ?>" onclick="this.select()"></span>
      <span>Webhook zwrotów <input readonly value="<?= htmlspecialchars($p24['refund_webhook_url'] ?? '') ?>" onclick="this.select()"></span>
    </div>
    <div class="notice notice--plain">
      Najpierw skonfiguruj i przetestuj konto sandbox. Sklep uznaje płatność dopiero po podpisanym
      powiadomieniu i dodatkowym wywołaniu <strong>transaction/verify</strong>.
    </div>
  </section>

  <section class="integration-card">
    <div class="integration-card__heading">
      <div>
        <span class="integration-logo integration-logo--stripe">stripe</span>
        <h2>Stripe — moduł opcjonalny</h2>
        <p>Kod Stripe pozostaje dostępny, ale nie jest wymagany ani domyślnie używany przez sklep ARKA.</p>
      </div>
      <span class="config-state">Opcjonalny</span>
    </div>
    <div class="integration-fields integration-fields--three">
      <label class="field">Tryb
        <select name="STRIPE_MODE">
          <option value="sandbox" <?= ($stripe['mode'] ?? '') === 'sandbox' ? 'selected' : '' ?>>Sandbox — testy</option>
          <option value="production" <?= ($stripe['mode'] ?? '') === 'production' ? 'selected' : '' ?>>Produkcja</option>
        </select>
      </label>
      <label class="field">Publishable key
        <input type="text" name="STRIPE_PUBLISHABLE_KEY" value="<?= htmlspecialchars($stripe['publishable_key'] ?? '') ?>" placeholder="pk_live_…" autocomplete="off" spellcheck="false">
      </label>
      <label class="field">Nazwa płatności widoczna dla klienta
        <input type="text" name="STRIPE_CHECKOUT_LABEL" maxlength="80" value="<?= htmlspecialchars($stripe['checkout_label'] ?? 'Karta przez Stripe') ?>" placeholder="Karta przez Stripe">
      </label>
      <label class="field">Konfiguracja metod Stripe
        <input type="text" name="STRIPE_PAYMENT_METHOD_CONFIGURATION" maxlength="100" value="<?= htmlspecialchars($stripe['payment_method_configuration'] ?? '') ?>" placeholder="pmc_…">
        <small>Opcjonalny identyfikator konfiguracji metod płatności.</small>
      </label>
      <label class="field">Secret key
        <input type="password" name="STRIPE_SECRET_KEY" placeholder="<?= htmlspecialchars($secretPlaceholder($stripe['secret_key'] ?? [])) ?>" autocomplete="new-password">
      </label>
      <label class="field">Webhook signing secret
        <input type="password" name="STRIPE_WEBHOOK_SECRET" placeholder="<?= htmlspecialchars($secretPlaceholder($stripe['webhook_secret'] ?? [])) ?>" autocomplete="new-password">
      </label>
    </div>
    <div class="endpoint-list">
      <span>Webhook Stripe <input readonly value="<?= htmlspecialchars($stripe['webhook_url'] ?? '') ?>" onclick="this.select()"></span>
    </div>
    <div class="endpoint-list">
      <span>Zdarzenia webhook
        <input readonly value="payment_intent.succeeded, payment_intent.processing, payment_intent.payment_failed, payment_intent.canceled, checkout.session.completed, checkout.session.async_payment_succeeded, checkout.session.async_payment_failed, checkout.session.expired, refund.updated, refund.failed" onclick="this.select()">
      </span>
    </div>
  </section>

  <section class="integration-card integration-card--merchant">
    <div class="integration-card__heading">
      <div>
        <span class="integration-logo integration-logo--google">Google</span>
        <h2>Merchant Center i bezpłatne informacje o produktach</h2>
        <p>Automatyczny kanał XML z cenami, dostępnością, okładkami, ISBN i nowymi adresami <strong>/book/</strong>.</p>
      </div>
      <span class="config-state <?= !empty($merchant['configured']) ? 'config-state--ok' : '' ?>">
        <?= !empty($merchant['configured']) ? 'Połączono' : 'Gotowy do podłączenia' ?>
      </span>
    </div>

    <label class="integration-toggle">
      <input type="checkbox" name="GOOGLE_MERCHANT_ENABLED" value="1" <?= !empty($merchant['enabled']) ? 'checked' : '' ?>>
      <span><strong>Włącz integrację Google Merchant</strong><small>Kanał pozostaje czytelny do testów, a ten przełącznik oznacza aktywne użycie w Merchant Center.</small></span>
    </label>

    <div class="integration-fields integration-fields--three">
      <label class="field">Merchant Center ID
        <input name="GOOGLE_MERCHANT_ID" inputmode="numeric" value="<?= htmlspecialchars($merchant['merchant_id'] ?? '') ?>" placeholder="np. 123456789">
      </label>
      <label class="field">Kraj sprzedaży
        <input name="GOOGLE_MERCHANT_COUNTRY" maxlength="2" value="<?= htmlspecialchars($merchant['country'] ?? 'PL') ?>">
      </label>
      <label class="field">Język
        <input name="GOOGLE_MERCHANT_LANGUAGE" maxlength="2" value="<?= htmlspecialchars($merchant['language'] ?? 'pl') ?>">
      </label>
      <label class="field">Domyślna marka / wydawca
        <input name="GOOGLE_MERCHANT_BRAND" maxlength="70" value="<?= htmlspecialchars($merchant['brand'] ?? '') ?>" placeholder="<?= htmlspecialchars($adminShopName) ?>">
        <small>Używana, jeśli książka nie ma wpisanego wydawcy.</small>
      </label>
      <label class="copy-field integration-fields__wide">Adres kanału produktowego
        <input readonly value="<?= htmlspecialchars($merchant['feed_url'] ?? '') ?>" onclick="this.select()">
      </label>
    </div>
    <div class="notice notice--plain">
      Kanał zawiera obecnie <strong><?= (int)($merchant['products_count'] ?? 0) ?> produktów z okładką</strong>.
      W Merchant Center dodaj źródło danych pobierane z powyższego adresu. Cena i dostępność aktualizują się automatycznie z katalogu.
    </div>
  </section>

  <section class="integration-card integration-card--analytics" id="google-analytics">
    <div class="integration-card__heading">
      <div>
        <span class="integration-logo integration-logo--google">Google</span>
        <h2>Google Analytics 4 — statystyki strony</h2>
        <p>Pomiar odwiedzin, źródeł ruchu i najczęściej oglądanych treści. Kod uruchamia się dopiero po zgodzie użytkownika.</p>
      </div>
      <?php if (!empty($analytics['enabled'])): ?>
        <span class="config-state config-state--ok">Połączono</span>
      <?php elseif (!empty($analytics['configured'])): ?>
        <span class="config-state">Gotowy, wyłączony</span>
      <?php else: ?>
        <span class="config-state">Niepołączony</span>
      <?php endif; ?>
    </div>

    <label class="integration-toggle">
      <input type="checkbox" name="GOOGLE_ANALYTICS_ENABLED" value="1" <?= !empty($analytics['requested_enabled']) ? 'checked' : '' ?>>
      <span>
        <strong>Włącz statystyki Google Analytics</strong>
        <small>Użytkownik sam wybiera, czy zgadza się na statystyki. Bez zgody skrypt Google nie jest pobierany.</small>
      </span>
    </label>

    <div class="integration-fields integration-fields--three">
      <label class="field">Identyfikator pomiaru GA4
        <input
          name="GOOGLE_ANALYTICS_MEASUREMENT_ID"
          value="<?= htmlspecialchars($analytics['measurement_id'] ?? '') ?>"
          maxlength="24"
          pattern="G-[A-Za-z0-9]{4,20}"
          autocomplete="off"
          spellcheck="false"
          placeholder="G-XXXXXXXXXX"
        >
        <small>Google Analytics → Administracja → Strumienie danych → Internet.</small>
      </label>
      <div class="integration-help">
        <strong>Panel statystyk</strong>
        <a href="https://analytics.google.com/" target="_blank" rel="noopener noreferrer">Otwórz Google Analytics</a>
      </div>
    </div>

    <div class="notice notice--plain">
      Tryb reklamowy, Google Signals i personalizacja reklam pozostają wyłączone. Zgoda jest zapamiętywana w przeglądarce i można ją zmienić przez link „Ustawienia cookies” w stopce.
    </div>
  </section>

  <section class="integration-card integration-card--mail" id="mail">
    <div class="integration-card__heading">
      <div>
        <span class="integration-logo integration-logo--mail">MAIL</span>
        <h2>Maile transakcyjne i ochrona przed spamem</h2>
        <p>Potwierdzenie zakupu, zmiana statusu, wysyłka i e-book — automatycznie po każdym zdarzeniu.</p>
      </div>
      <span class="config-state <?= !empty($mail['production_ready']) ? 'config-state--ok' : '' ?>">
        <?= !empty($mail['production_ready']) ? 'SMTP + DKIM gotowe' : (!empty($mail['configured']) ? 'SMTP gotowe, sprawdź DKIM' : 'Wymaga konfiguracji') ?>
      </span>
    </div>

    <div class="integration-fields integration-fields--three">
      <label class="field">Sposób wysyłki
        <select name="MAIL_TRANSPORT">
          <option value="smtp" <?= ($mail['transport'] ?? '') === 'smtp' ? 'selected' : '' ?>>SMTP — produkcja</option>
          <option value="log" <?= ($mail['transport'] ?? '') === 'log' ? 'selected' : '' ?>>Log — test lokalny</option>
          <option value="mail" <?= ($mail['transport'] ?? '') === 'mail' ? 'selected' : '' ?>>PHP mail()</option>
        </select>
      </label>
      <label class="field">Adres nadawcy
        <input type="email" name="MAIL_FROM" value="<?= htmlspecialchars($mail['from'] ?? '') ?>" placeholder="biuro@arka-pojednanie.pl">
      </label>
      <label class="field">Nazwa nadawcy
        <input name="MAIL_FROM_NAME" value="<?= htmlspecialchars($mail['from_name'] ?? '') ?>" placeholder="<?= htmlspecialchars($adminShopName) ?>">
      </label>
      <label class="field">Odpowiedzi kieruj do
        <input type="email" name="MAIL_REPLY_TO" value="<?= htmlspecialchars($mail['reply_to'] ?? '') ?>" placeholder="biuro@arka-pojednanie.pl">
      </label>
      <label class="field">Powiadomienia o opłaconych zakupach
        <input type="email" name="MAIL_ORDER_NOTIFICATION_TO" value="<?= htmlspecialchars($mail['order_notification_to'] ?? '') ?>" placeholder="biuro@arka-pojednanie.pl">
        <small>Na ten adres sklep wysyła operacyjną kopię po potwierdzeniu płatności.</small>
      </label>
      <label class="field">Serwer SMTP
        <input name="SMTP_HOST" value="<?= htmlspecialchars($mail['smtp_host'] ?? '') ?>" placeholder="smtp.twojadomena.pl" autocomplete="off">
      </label>
      <label class="field">Port
        <input name="SMTP_PORT" type="number" min="1" max="65535" value="<?= (int)($mail['smtp_port'] ?? 587) ?>">
      </label>
      <label class="field">Szyfrowanie
        <select name="SMTP_ENCRYPTION">
          <option value="tls" <?= ($mail['smtp_encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>STARTTLS (zwykle 587)</option>
          <option value="ssl" <?= ($mail['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL/TLS (zwykle 465)</option>
          <option value="none" <?= ($mail['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>Brak — tylko zaufany serwer lokalny</option>
        </select>
      </label>
      <label class="field">Login SMTP
        <input name="SMTP_USERNAME" value="<?= htmlspecialchars($mail['smtp_username'] ?? '') ?>" autocomplete="off">
      </label>
      <label class="field">Hasło SMTP
        <input type="password" name="SMTP_PASSWORD" placeholder="<?= htmlspecialchars($secretPlaceholder($mail['smtp_password'] ?? [])) ?>" autocomplete="new-password">
        <small>Puste pole zachowuje obecne hasło.</small>
      </label>
    </div>

    <div class="mail-auth-box">
      <label class="integration-toggle">
        <input type="checkbox" name="MAIL_DKIM_ENABLED" value="1" <?= !empty($mail['dkim_enabled']) ? 'checked' : '' ?>>
        <span><strong>Podpisuj wiadomości DKIM</strong><small>Najważniejsze techniczne zabezpieczenie reputacji domeny nadawcy.</small></span>
      </label>
      <div class="integration-fields integration-fields--three">
        <label class="field">Domena DKIM
          <input name="MAIL_DKIM_DOMAIN" value="<?= htmlspecialchars($mail['dkim_domain'] ?? '') ?>" placeholder="arka-pojednanie.pl">
        </label>
        <label class="field">Selektor DKIM
          <input name="MAIL_DKIM_SELECTOR" value="<?= htmlspecialchars($mail['dkim_selector'] ?? 'default') ?>" placeholder="default">
        </label>
        <label class="field">Klucz prywatny DKIM — Base64
          <input type="password" name="MAIL_DKIM_PRIVATE_KEY_BASE64" placeholder="<?= htmlspecialchars($secretPlaceholder($mail['dkim_private_key'] ?? [])) ?>" autocomplete="new-password">
          <small>Wklej zakodowany Base64 klucz PEM. Puste pole zachowuje obecny.</small>
        </label>
      </div>
    </div>

    <div class="deliverability-grid">
      <?php foreach (['spf'=>'SPF', 'dkim'=>'DKIM w DNS', 'dmarc'=>'DMARC'] as $dnsKey => $dnsLabel): $dnsState = $mail['dns'][$dnsKey] ?? []; ?>
        <div class="<?= !empty($dnsState['ok']) ? 'is-ok' : '' ?>">
          <span><?= htmlspecialchars($dnsLabel) ?></span>
          <strong><?= !empty($dnsState['ok']) ? 'Znaleziony' : 'Do ustawienia' ?></strong>
          <small><?= htmlspecialchars((string)($dnsState['value'] ?? '')) ?></small>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if (!empty($mail['dkim_dns_value'])): ?>
      <div class="dkim-dns-box">
        <div>
          <span class="section-label">REKORD TXT DO DODANIA W DNS</span>
          <strong>Publiczna część podpisu DKIM</strong>
          <p>Klucz prywatny pozostaje wyłącznie w sklepie. Do operatora domeny kopiujesz tylko poniższe dane.</p>
        </div>
        <label class="copy-field">Nazwa / host
          <input readonly value="<?= htmlspecialchars((string)$mail['dkim_dns_host']) ?>" onclick="this.select()">
        </label>
        <label class="copy-field">Wartość TXT
          <textarea readonly rows="4" onclick="this.select()"><?= htmlspecialchars((string)$mail['dkim_dns_value']) ?></textarea>
        </label>
      </div>
    <?php endif; ?>
    <div class="notice notice--plain">
      Najlepsza dostarczalność wymaga zgodności domeny adresu nadawcy z SMTP oraz rekordów SPF, DKIM i DMARC.
      System generuje wersję tekstową i HTML, poprawny Message-ID, datę, Reply-To i podpis DKIM.
    </div>
  </section>

  <section class="integration-card integration-card--tawk">
    <div class="integration-card__heading">
      <div>
        <span class="integration-logo integration-logo--tawk">tawk.to</span>
        <h2>Czat z klientami</h2>
        <p>Mały przycisk czatu na każdej publicznej stronie sklepu. Panel administratora pozostaje bez widgetu.</p>
      </div>
      <?php if (!empty($tawk['enabled'])): ?>
        <span class="config-state config-state--ok">Włączony</span>
      <?php elseif (!empty($tawk['configured'])): ?>
        <span class="config-state">Gotowy, wyłączony</span>
      <?php else: ?>
        <span class="config-state">Niepołączony</span>
      <?php endif; ?>
    </div>

    <label class="integration-toggle">
      <input type="checkbox" name="TAWK_ENABLED" value="1" <?= !empty($tawk['requested_enabled']) ? 'checked' : '' ?>>
      <span>
        <strong>Włącz Tawk.to na stronie sklepu</strong>
        <small>Widget zacznie działać po zapisaniu obu identyfikatorów.</small>
      </span>
    </label>

    <div class="integration-fields integration-fields--three">
      <label class="field">Property ID
        <input name="TAWK_PROPERTY_ID" value="<?= htmlspecialchars($tawk['property_id'] ?? '') ?>" autocomplete="off" spellcheck="false" placeholder="np. 0123456789abcdef01234567">
        <small>Tawk.to → Administration → Overview.</small>
      </label>
      <label class="field">Widget ID
        <input name="TAWK_WIDGET_ID" value="<?= htmlspecialchars($tawk['widget_id'] ?? '') ?>" autocomplete="off" spellcheck="false" placeholder="np. 1abcdefghi">
        <small>Tawk.to → Administration → Chat Widget.</small>
      </label>
      <?php if (!empty($tawk['direct_chat_url'])): ?>
        <label class="copy-field">Bezpośredni adres testowy
          <input readonly value="<?= htmlspecialchars($tawk['direct_chat_url']) ?>" onclick="this.select()">
        </label>
      <?php else: ?>
        <div class="integration-help">
          <strong>Skąd wziąć identyfikatory?</strong>
          <a href="https://help.tawk.to/article/where-can-i-find-the-property-and-widget-id" target="_blank" rel="noopener noreferrer">Otwórz instrukcję Tawk.to</a>
        </div>
      <?php endif; ?>
    </div>

    <?php if (!empty($tawk['embed_url'])): ?>
      <div class="endpoint-list">
        <span>Adres skryptu widgetu
          <input readonly value="<?= htmlspecialchars($tawk['embed_url']) ?>" onclick="this.select()">
        </span>
      </div>
    <?php endif; ?>

    <div class="notice notice--plain">
      Tawk.to jest usługą zewnętrzną i po włączeniu łączy przeglądarkę klienta z serwerami Tawk.to.
      Przed uruchomieniem produkcyjnym opisz czat w polityce prywatności.
    </div>
  </section>

</form>

<?php if (empty($mail['dkim_dns_value'])): ?>
<section class="panel-section connection-test">
  <div>
    <p class="section-label">PROSTY DKIM</p>
    <h2>Wygeneruj podpis domeny</h2>
    <p class="muted">Najpierw zapisz adres nadawcy, domenę i selektor. System utworzy bezpieczną parę RSA 2048 i pokaże publiczny rekord TXT do wklejenia u operatora domeny.</p>
  </div>
  <form method="post" action="/integrations/mail/dkim-generate" data-ajax-refresh>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">
    <button class="btn secondary" type="submit">Wygeneruj DKIM</button>
  </form>
</section>
<?php endif; ?>

<section class="panel-section connection-test">
  <div>
    <p class="section-label">TEST POCZTY</p>
    <h2>Wyślij wiadomość kontrolną</h2>
    <p class="muted">Test zapisze się także w zakładce Maile. Najpierw zapisz konfigurację powyżej.</p>
  </div>
  <form method="post" action="/integrations/mail/test" data-ajax-success="Wiadomość testowa została przetworzona.">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">
    <label class="field">Adres testowy<input type="email" name="test_email" required value="<?= htmlspecialchars((string)(($mail['reply_to'] ?? '') ?: ($mail['from'] ?? ''))) ?>"></label>
    <button class="btn secondary" type="submit">Wyślij test</button>
  </form>
</section>

<section class="panel-section connection-test">
  <div>
    <p class="section-label">TEST POŁĄCZENIA</p>
    <h2>Sprawdź InPost bez tworzenia paczki</h2>
    <p class="muted">Testuje token i przynależność do organizacji. Niczego nie kupuje ani nie wysyła.</p>
  </div>
  <form method="post" action="/integrations/inpost/test">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">
    <button class="btn secondary" type="submit" <?= empty($inpost['configured']) ? 'disabled' : '' ?>>Sprawdź połączenie</button>
  </form>
</section>

<details class="panel-section collapsible">
  <summary><span><span class="section-label">DIAGNOSTYKA</span><strong>Kontrola techniczna integracji</strong></span><span>Rozwiń</span></summary>
  <div class="diagnostic-content">
    <?php foreach (($report['sections'] ?? []) as $section => $checks): ?>
      <h3><?= htmlspecialchars($section) ?></h3>
      <table class="admin-table">
        <tr><th>Test</th><th>Wynik</th><th>Wartość</th></tr>
        <?php foreach ($checks as $check): ?>
          <tr>
            <td><?= htmlspecialchars($check['name']) ?></td>
            <td><?= $check['ok'] ? 'OK' : ($check['blocking'] ? 'BŁĄD' : 'UWAGA') ?></td>
            <td><code><?= htmlspecialchars((string)$check['value']) ?></code></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endforeach; ?>
  </div>
</details>

<?php include __DIR__ . '/../layout_bottom.php'; ?>
