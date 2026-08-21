<?php include __DIR__ . '/../partials/header.php'; ?>
<?php
$currency = strtoupper((string)($order['currency'] ?? 'PLN'));
$currencyLabel = $currency === 'PLN' ? 'zł' : $currency;
$stripeReady = !empty($stripe['ok']) && !empty($stripe['client_secret']) && !empty($stripe['publishable_key']);
$contactName = trim((string)($order['customer_name'] ?? ''));
$contactEmail = trim((string)($order['customer_email'] ?? ''));
$contactPhone = trim((string)($order['customer_phone'] ?? ''));
?>
<main class="payment-page">
  <section class="payment-page__intro">
    <a class="payment-back" href="<?= htmlspecialchars((string)($stripe['cancel_url'] ?? '/')) ?>">← Wróć do zamówienia</a>
    <p class="eyebrow">Bezpieczna płatność</p>
    <h1>BLIK lub karta</h1>
    <p>Wybierz metodę i zapłać. Dane płatnicze są obsługiwane bezpośrednio przez Stripe.</p>
  </section>

  <div class="payment-layout">
    <aside class="payment-summary">
      <div class="payment-summary__heading">
        <span>Zamówienie</span>
        <strong>#<?= htmlspecialchars((string)$order['order_number']) ?></strong>
      </div>
      <div class="payment-summary__items">
        <?php foreach (($order['items'] ?? []) as $item): ?>
          <article class="payment-summary__item">
            <span class="payment-summary__cover">
              <?php if (!empty($item['cover_image'])): ?>
                <img src="<?= htmlspecialchars((string)$item['cover_image']) ?>" alt="">
              <?php endif; ?>
            </span>
            <span>
              <strong><?= htmlspecialchars((string)$item['title']) ?></strong>
              <?php if (($item['sale_mode'] ?? '') === 'preorder'): ?><em class="payment-summary__preorder">Przedsprzedaż<?= !empty($item['release_date']) ? ' · ' . htmlspecialchars(\Book100\Services\Books\BookSaleState::formattedReleaseDate((string)$item['release_date'])) : '' ?></em><?php endif; ?>
              <small><?= (int)$item['quantity'] ?> × <?= number_format((float)$item['unit_price_gross'], 2, ',', ' ') ?> <?= htmlspecialchars($currencyLabel) ?></small>
            </span>
            <b><?= number_format((float)$item['total_gross'], 2, ',', ' ') ?> <?= htmlspecialchars($currencyLabel) ?></b>
          </article>
        <?php endforeach; ?>
      </div>
      <?php if ((float)($order['shipping_gross'] ?? 0) > 0): ?>
        <div class="payment-summary__line"><span>Dostawa</span><strong><?= number_format((float)$order['shipping_gross'], 2, ',', ' ') ?> <?= htmlspecialchars($currencyLabel) ?></strong></div>
      <?php endif; ?>
      <div class="payment-summary__total"><span>Do zapłaty</span><strong><?= number_format((float)$order['total_gross'], 2, ',', ' ') ?> <?= htmlspecialchars($currencyLabel) ?></strong></div>
    </aside>

    <section class="payment-card">
      <div class="payment-contact">
        <div>
          <span>Dane kontaktowe</span>
          <strong><?= htmlspecialchars($contactEmail) ?></strong>
        </div>
        <?php if ($contactPhone !== ''): ?><small><?= htmlspecialchars($contactPhone) ?></small><?php endif; ?>
      </div>

      <div id="stripe-express-region" class="payment-express" hidden>
        <div class="payment-express__heading">
          <strong>Zapłać szybciej</strong>
          <small>Google Pay lub Apple Pay</small>
        </div>
        <div id="stripe-express-element"></div>
        <div class="payment-divider"><span>lub wybierz BLIK albo kartę</span></div>
      </div>

      <div class="payment-card__heading">
        <span>Metoda płatności</span>
        <small>BLIK jest wybrany jako pierwszy</small>
      </div>

      <?php if (!empty($paymentError)): ?><div class="payment-message payment-message--error"><?= htmlspecialchars((string)$paymentError) ?></div><?php endif; ?>
      <?php if (!$stripeReady): ?>
        <div class="payment-message payment-message--error">Formularz płatności jest chwilowo niedostępny.</div>
      <?php else: ?>
        <form id="stripe-payment-form" class="stripe-payment-form">
          <div id="stripe-payment-element" aria-live="polite">
            <div class="payment-loading"><span></span>Ładowanie bezpiecznego formularza…</div>
          </div>
          <div id="stripe-payment-message" class="payment-message payment-message--error" role="alert" hidden></div>
          <button id="stripe-payment-submit" class="payment-submit" type="submit" disabled>
            <span class="payment-submit__label">Zapłać <?= number_format((float)$order['total_gross'], 2, ',', ' ') ?> <?= htmlspecialchars($currencyLabel) ?></span>
            <span class="payment-submit__working" hidden>Potwierdzanie płatności…</span>
          </button>
        </form>
        <div class="payment-secure-note">
          <span aria-hidden="true">⌁</span>
          <span>Szyfrowane połączenie. Kod BLIK i dane karty nie trafiają do sklepu.</span>
        </div>
      <?php endif; ?>
    </section>
  </div>
</main>

<?php if ($stripeReady): ?>
<script src="https://js.stripe.com/v3/"></script>
<script>
(() => {
  const publishableKey = <?= json_encode((string)$stripe['publishable_key'], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const clientSecret = <?= json_encode((string)$stripe['client_secret'], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const returnUrl = <?= json_encode((string)$stripe['return_url'], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const customer = {
    name: <?= json_encode($contactName, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    email: <?= json_encode($contactEmail, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    phone: <?= json_encode($contactPhone, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
  };
  const form = document.getElementById('stripe-payment-form');
  const submit = document.getElementById('stripe-payment-submit');
  const message = document.getElementById('stripe-payment-message');
  const label = submit.querySelector('.payment-submit__label');
  const working = submit.querySelector('.payment-submit__working');
  const stripe = Stripe(publishableKey, {locale: 'pl'});
  const appearance = {
    theme: 'stripe',
    inputs: 'spaced',
    labels: 'above',
    variables: {
      colorPrimary: '#e91d2a',
      colorText: '#171717',
      colorTextSecondary: '#686868',
      colorBackground: '#ffffff',
      colorDanger: '#b50f1a',
      fontFamily: '"Segoe UI", Arial, Helvetica, sans-serif',
      fontSizeBase: '17px',
      spacingUnit: '6px',
      borderRadius: '14px'
    },
    rules: {
      '.AccordionItem': {
        border: '1px solid #dedbd5',
        borderRadius: '16px',
        boxShadow: '0 8px 24px rgba(23, 23, 23, 0.05)'
      },
      '.AccordionItem--selected': {
        borderColor: '#171717',
        boxShadow: '0 10px 28px rgba(23, 23, 23, 0.08)'
      },
      '.Input': {
        minHeight: '66px',
        padding: '19px 20px',
        border: '1px solid #cfcec9',
        boxShadow: '0 4px 12px rgba(23, 23, 23, 0.05)',
        fontSize: '22px',
        fontWeight: '600',
        letterSpacing: '0.04em'
      },
      '.Input:focus': {
        borderColor: '#171717',
        boxShadow: '0 0 0 4px rgba(233, 29, 42, 0.12)'
      },
      '.CodeInput': {
        minHeight: '74px',
        padding: '20px 22px',
        border: '2px solid #171717',
        borderRadius: '16px',
        boxShadow: '0 8px 24px rgba(23, 23, 23, 0.08)',
        fontSize: '30px',
        fontWeight: '700',
        letterSpacing: '0.18em'
      },
      '.CodeInput:focus': {
        borderColor: '#e91d2a',
        boxShadow: '0 0 0 5px rgba(233, 29, 42, 0.13)'
      },
      '.Label': {
        marginBottom: '9px',
        color: '#343434',
        fontSize: '15px',
        fontWeight: '600'
      },
      '.Tab': {
        minHeight: '62px',
        border: '1px solid #dedbd5',
        borderRadius: '14px'
      },
      '.Tab--selected': {
        borderColor: '#171717',
        boxShadow: '0 0 0 1px #171717'
      }
    }
  };
  const elements = stripe.elements({clientSecret, appearance, locale: 'pl'});
  const expressRegion = document.getElementById('stripe-express-region');
  const expressCheckoutElement = elements.create('expressCheckout', {
    buttonType: {
      applePay: 'buy',
      googlePay: 'buy'
    },
    buttonTheme: {
      applePay: 'black',
      googlePay: 'black'
    },
    buttonHeight: 55,
    layout: {
      maxColumns: 2,
      maxRows: 1
    },
    paymentMethods: {
      applePay: 'always',
      googlePay: 'always'
    }
  });
  const updateExpressVisibility = (paymentMethods) => {
    expressRegion.hidden = !(paymentMethods && (paymentMethods.googlePay || paymentMethods.applePay));
  };
  expressCheckoutElement.on('ready', (event) => {
    updateExpressVisibility(event.availablePaymentMethods || event.paymentMethods);
  });
  expressCheckoutElement.on('availablepaymentmethodschange', ({paymentMethods}) => {
    updateExpressVisibility(paymentMethods);
  });
  expressCheckoutElement.on('confirm', async () => {
    message.hidden = true;
    const {error: submitError} = await elements.submit();
    if (submitError) {
      message.textContent = submitError.message || 'Nie udało się uruchomić portfela.';
      message.hidden = false;
      return;
    }
    const {error} = await stripe.confirmPayment({
      elements,
      confirmParams: {
        return_url: returnUrl
      }
    });
    if (error) {
      message.textContent = error.message || 'Nie udało się potwierdzić płatności portfelem.';
      message.hidden = false;
    }
  });
  expressCheckoutElement.mount('#stripe-express-element');

  const paymentElement = elements.create('payment', {
    layout: {
      type: 'accordion',
      defaultCollapsed: false,
      radios: true,
      spacedAccordionItems: true
    },
    paymentMethodOrder: ['blik', 'card'],
    defaultValues: {
      billingDetails: customer
    },
    fields: {
      billingDetails: {
        name: 'never',
        email: 'never',
        phone: 'never'
      }
    },
    wallets: {
      applePay: 'never',
      googlePay: 'never'
    }
  });
  paymentElement.mount('#stripe-payment-element');
  paymentElement.on('ready', () => {
    submit.disabled = false;
  });
  paymentElement.on('loaderror', () => {
    message.textContent = 'Nie udało się załadować formularza Stripe. Odśwież stronę i spróbuj ponownie.';
    message.hidden = false;
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (submit.disabled) return;
    submit.disabled = true;
    label.hidden = true;
    working.hidden = false;
    message.hidden = true;

    const {error} = await stripe.confirmPayment({
      elements,
      confirmParams: {
        return_url: returnUrl,
        payment_method_data: {
          billing_details: customer
        }
      }
    });
    if (error) {
      message.textContent = error.message || 'Nie udało się potwierdzić płatności.';
      message.hidden = false;
      submit.disabled = false;
      label.hidden = false;
      working.hidden = true;
    }
  });
})();
</script>
<?php endif; ?>
<?php include __DIR__ . '/../partials/footer.php'; ?>
