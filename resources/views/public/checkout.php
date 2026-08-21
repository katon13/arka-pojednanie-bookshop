<?php include __DIR__ . '/../partials/header.php'; ?>
<?php
$selectedBooks = $selectedBooks ?? [$book + ['checkout_quantity' => 1]];
$checkoutCurrency = (string)($book['currency'] ?? $storefront['currency'] ?? 'PLN');
$delivery = (string)($old['delivery_method'] ?? 'inpost_locker');
$inpostCourierEnabled = (bool)($inpostCourierEnabled ?? false);
if (!$inpostCourierEnabled && $delivery === 'inpost_courier') $delivery = 'inpost_locker';
$hasPaperInitially = (bool)array_filter($selectedBooks, static fn(array $item): bool => ($item['product_type'] ?? 'paper') !== 'ebook');
$hasEbookInitially = (bool)array_filter($selectedBooks, static fn(array $item): bool => ($item['product_type'] ?? 'paper') === 'ebook');
$preorderBooksInitially = array_values(array_filter($selectedBooks, static fn(array $item): bool => ($item['status'] ?? $item['sale_mode'] ?? '') === 'preorder'));
if (!$hasPaperInitially) $delivery = 'ebook';
?>
<nav class="breadcrumbs" aria-label="Okruszki"><a href="/">Książki</a><span>/</span><a href="/book/<?= urlencode($book['slug']) ?>/"><?= htmlspecialchars($book['title']) ?></a><span>/</span><span>Zakup</span></nav>

<form
  class="checkout-page checkout-order"
  method="post"
  data-order-builder
  data-currency="<?= htmlspecialchars($checkoutCurrency) ?>"
  data-shipping-locker="<?= htmlspecialchars((string)$shipping['inpost_locker']) ?>"
  data-shipping-courier="<?= htmlspecialchars((string)$shipping['inpost_courier']) ?>"
  data-shipping-pickup="0"
>
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">

  <aside class="checkout-summary">
    <p class="eyebrow">Twoje zamówienie</p>
    <div class="checkout-items" data-checkout-items>
      <?php foreach ($selectedBooks as $selectedBook): ?>
        <?php
        $isSelectedEbook = ($selectedBook['product_type'] ?? 'paper') === 'ebook';
        $selectedQuantity = $isSelectedEbook ? 1 : max(1, min(20, (int)($selectedBook['checkout_quantity'] ?? 1)));
        $maxQuantity = $isSelectedEbook ? 1 : 20;
        ?>
        <article
          class="checkout-item"
          data-checkout-item
          data-book-id="<?= (int)$selectedBook['id'] ?>"
          data-book-type="<?= htmlspecialchars($selectedBook['product_type'] ?? 'paper') ?>"
          data-price="<?= htmlspecialchars((string)$selectedBook['price_gross']) ?>"
          data-sale-mode="<?= htmlspecialchars((string)($selectedBook['status'] ?? $selectedBook['sale_mode'] ?? 'active')) ?>"
        >
          <div class="checkout-item__cover">
            <?php if (!empty($selectedBook['cover_image'])): ?><img src="<?= htmlspecialchars($selectedBook['cover_image']) ?>" alt=""><?php else: ?><span>100</span><?php endif; ?>
          </div>
          <div class="checkout-item__content">
            <strong><?= htmlspecialchars($selectedBook['title']) ?></strong>
            <small><?= htmlspecialchars($selectedBook['author'] ?? '') ?></small>
            <?php if (($selectedBook['status'] ?? $selectedBook['sale_mode'] ?? '') === 'preorder'): ?>
              <em class="checkout-item__sale">Przedsprzedaż<?= !empty($selectedBook['release_date']) ? ' · ' . htmlspecialchars(\Book100\Services\Books\BookSaleState::formattedReleaseDate((string)$selectedBook['release_date'])) : '' ?></em>
            <?php endif; ?>
            <span><?= number_format((float)$selectedBook['price_gross'], 2, ',', ' ') ?> <?= $checkoutCurrency === 'PLN' ? 'zł' : htmlspecialchars($checkoutCurrency) ?></span>
          </div>
          <div class="checkout-item__quantity" aria-label="Liczba egzemplarzy">
            <button type="button" data-qty-delta="-1" aria-label="Zmniejsz liczbę" <?= $isSelectedEbook ? 'disabled' : '' ?>>−</button>
            <input
              name="items[<?= (int)$selectedBook['id'] ?>]"
              type="number"
              min="1"
              max="<?= $maxQuantity ?>"
              value="<?= $selectedQuantity ?>"
              inputmode="numeric"
              aria-label="Ilość: <?= htmlspecialchars($selectedBook['title']) ?>"
              <?= $isSelectedEbook ? 'readonly' : '' ?>
            >
            <button type="button" data-qty-delta="1" aria-label="Zwiększ liczbę" <?= $isSelectedEbook ? 'disabled' : '' ?>>+</button>
          </div>
          <button class="checkout-item__remove" type="button" data-remove-item aria-label="Usuń z zamówienia">×</button>
        </article>
      <?php endforeach; ?>
    </div>

    <button class="checkout-add-book" type="button" data-open-book-picker>
      <span aria-hidden="true">+</span>
      <strong>Dodaj książkę</strong>
      <small>Wybierz kolejną pozycję po okładce</small>
    </button>

    <div class="checkout-totals" aria-live="polite">
      <div><span>Książki</span><strong data-order-subtotal>—</strong></div>
      <div><span>Dostawa</span><strong data-order-shipping>—</strong></div>
      <div class="checkout-totals__final"><span>Do zapłaty</span><strong data-order-total>—</strong></div>
    </div>

    <div class="preorder-checkout-note" data-preorder-note <?= !$preorderBooksInitially ? 'hidden' : '' ?>>
      <strong>W zamówieniu jest przedsprzedaż</strong>
      <span>Całe zamówienie wyślemy razem po najpóźniejszej dacie premiery.</span>
    </div>

    <ul class="checkout-assurances">
      <li><?= htmlspecialchars($storefront['checkout_assurance_1'] ?? 'Bez zakładania konta') ?></li>
      <li><?= htmlspecialchars($storefront['checkout_assurance_2'] ?? 'Bezpieczna płatność') ?></li>
      <li>Cena końcowa aktualizuje się od razu</li>
    </ul>
  </aside>

  <div class="checkout-panel">
    <p class="eyebrow"><?= htmlspecialchars($storefront['checkout_eyebrow'] ?? '') ?></p>
    <h1><?= htmlspecialchars($storefront['checkout_title'] ?? '') ?></h1>
    <p class="checkout-panel__lead">Dodaj książki, ustaw liczbę egzemplarzy i podaj dane. Wszystko na jednej stronie.</p>
    <?php if (!empty($errors)): ?>
      <div class="error"><strong>Popraw formularz:</strong><ul><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <?php if (empty($payments)): ?><div class="notice">Zakup jest chwilowo wyłączony do czasu skonfigurowania płatności przez administratora.</div><?php endif; ?>

    <div class="checkout-form">
      <h2 class="form-section-title"><span>01</span>Dane kupującego</h2>
      <label class="field">Imię i nazwisko<input name="customer_name" required value="<?= htmlspecialchars($old['customer_name'] ?? '') ?>"></label>
      <label class="field">E-mail<input name="customer_email" type="email" required value="<?= htmlspecialchars($old['customer_email'] ?? '') ?>"></label>
      <label class="field" data-paper-phone <?= !$hasPaperInitially ? 'hidden' : '' ?>>Telefon<input name="customer_phone" value="<?= htmlspecialchars($old['customer_phone'] ?? '') ?>" <?= $hasPaperInitially ? 'required' : '' ?>></label>

      <div data-ebook-notice <?= !$hasEbookInitially ? 'hidden' : '' ?> class="notice">
        E-book otrzymasz po potwierdzeniu płatności — link pojawi się na stronie zamówienia i w wiadomości e-mail.
      </div>

      <section data-delivery-section <?= !$hasPaperInitially ? 'hidden' : '' ?>>
        <h2 class="form-section-title"><span>02</span>Dostawa</h2>
        <label class="field">Sposób dostawy
          <select name="delivery_method" data-delivery-select>
            <option value="inpost_locker" <?= $delivery==='inpost_locker'?'selected':'' ?>>InPost Paczkomat — <?= number_format((float)$shipping['inpost_locker'],2,',',' ') ?> <?= htmlspecialchars($checkoutCurrency) ?></option>
            <?php if ($inpostCourierEnabled): ?><option value="inpost_courier" <?= $delivery==='inpost_courier'?'selected':'' ?>>InPost Kurier — <?= number_format((float)$shipping['inpost_courier'],2,',',' ') ?> <?= htmlspecialchars($checkoutCurrency) ?></option><?php endif; ?>
            <option value="pickup" <?= $delivery==='pickup'?'selected':'' ?>>Odbiór osobisty — 0,00 zł</option>
            <option value="ebook" <?= $delivery==='ebook'?'selected':'' ?> hidden>Dostawa elektroniczna — 0,00 zł</option>
          </select>
        </label>
        <div class="field inpost-point-field" id="locker-fields">
          <span>Paczkomat / punkt InPost</span>
          <div class="inpost-point-control">
            <input name="inpost_point" id="inpost-point" autocomplete="off" placeholder="Np. NSZ01M" value="<?= htmlspecialchars($old['inpost_point'] ?? '') ?>" <?= !empty($inpostGeoWidget['enabled']) ? 'readonly' : '' ?>>
            <?php if (!empty($inpostGeoWidget['enabled'])): ?>
              <button class="inpost-map-button" type="button" data-open-inpost-map>
                <span aria-hidden="true">⌖</span>
                <strong><?= !empty($old['inpost_point']) ? 'Zmień punkt' : 'Wybierz na mapie' ?></strong>
              </button>
            <?php endif; ?>
          </div>
          <small data-inpost-point-summary><?= !empty($old['inpost_point']) ? 'Wybrany punkt: ' . htmlspecialchars((string)$old['inpost_point']) : (!empty($inpostGeoWidget['enabled']) ? 'Otwórz mapę i kliknij najwygodniejszy punkt odbioru.' : 'Wpisz kod punktu InPost.') ?></small>
        </div>
        <fieldset class="box" id="courier-fields"><legend>Adres dla kuriera InPost</legend>
          <label class="field">Ulica<input name="street" value="<?= htmlspecialchars($old['street'] ?? '') ?>"></label>
          <label class="field">Nr budynku / lokalu<input name="building_number" value="<?= htmlspecialchars($old['building_number'] ?? '') ?>"></label>
          <label class="field">Miasto<input name="city" value="<?= htmlspecialchars($old['city'] ?? '') ?>"></label>
          <label class="field">Kod pocztowy<input name="post_code" value="<?= htmlspecialchars($old['post_code'] ?? '') ?>"></label>
        </fieldset>
        <div class="pickup-note" data-pickup-note hidden>
          <strong>Odbiór osobisty jest bezpłatny.</strong>
          <span>Po opłaceniu zamówienia otrzymasz informację, kiedy książki będą gotowe.</span>
        </div>
      </section>

      <h2 class="form-section-title"><span>03</span>Płatność</h2>
      <label class="field">Płatność
        <?php $payment = $old['payment_provider'] ?? \Book100\Core\Env::get('PAYMENT_PRIMARY', 'przelewy24'); ?>
        <select name="payment_provider" <?= empty($payments) ? 'disabled' : '' ?>>
          <?php foreach ($payments as $value => $label): ?><option value="<?= htmlspecialchars($value) ?>" <?= $payment===$value?'selected':'' ?>><?= htmlspecialchars($label) ?></option><?php endforeach; ?>
          <?php if (empty($payments)): ?><option>Brak skonfigurowanej płatności</option><?php endif; ?>
        </select>
      </label>

      <div class="checkout-consents">
        <label class="consent-row">
          <input type="checkbox" name="terms" value="1" required <?= !empty($old['terms'])?'checked':'' ?>>
          <span>Akceptuję <a href="/regulamin" target="_blank" rel="noopener">regulamin sklepu</a>.</span>
        </label>
        <label class="consent-row consent-row--important" data-digital-consent <?= !$hasEbookInitially ? 'hidden' : '' ?>>
          <input type="checkbox" name="digital_content_consent" value="1" <?= $hasEbookInitially ? 'required' : '' ?> <?= !empty($old['digital_content_consent'])?'checked':'' ?>>
          <span>Wyraźnie żądam dostarczenia e-booka przed upływem 14 dni. Przyjmuję do wiadomości, że po rozpoczęciu dostarczania utracę prawo odstąpienia od umowy.</span>
        </label>
        <label class="consent-row">
          <input type="checkbox" name="newsletter" value="1" <?= !empty($old['newsletter'])?'checked':'' ?>>
          <span>Chcę dostawać informacje o nowych książkach. Zgoda jest dobrowolna.</span>
        </label>
      </div>
      <button class="btn btn--large btn--full" type="submit" <?= empty($payments) ? 'disabled' : '' ?> data-checkout-submit>
        Kupuję i przechodzę do płatności <span aria-hidden="true">→</span>
      </button>
    </div>
  </div>

  <dialog class="book-picker" data-book-picker>
    <div class="book-picker__heading">
      <div><p class="eyebrow">Dodaj do zamówienia</p><h2>Wybierz książkę</h2></div>
      <button type="button" data-close-book-picker aria-label="Zamknij">×</button>
    </div>
    <label class="book-picker__search">Szukaj po tytule lub autorze<input type="search" data-book-search placeholder="Wpisz tytuł…"></label>
    <div class="book-picker__status" data-book-picker-status>Ładowanie książek…</div>
    <div class="book-picker__grid" data-book-picker-grid></div>
  </dialog>

  <?php if (!empty($inpostGeoWidget['enabled'])): ?>
  <dialog class="inpost-map-picker" data-inpost-map>
    <div class="inpost-map-picker__heading">
      <div>
        <p class="eyebrow">Dostawa InPost</p>
        <h2>Wybierz punkt odbioru</h2>
      </div>
      <button type="button" data-close-inpost-map aria-label="Zamknij mapę">×</button>
    </div>
    <div class="inpost-map-picker__body">
      <inpost-geowidget
        token="<?= htmlspecialchars((string)$inpostGeoWidget['token']) ?>"
        language="pl"
        config="<?= htmlspecialchars((string)($inpostGeoWidget['config'] ?? 'parcelCollect')) ?>"
        onpoint="book100PointSelected"
      ></inpost-geowidget>
    </div>
  </dialog>
  <?php endif; ?>
</form>

<script>
(() => {
  const builder = document.querySelector('[data-order-builder]');
  if (!builder) return;
  const itemsContainer = builder.querySelector('[data-checkout-items]');
  const delivery = builder.querySelector('[data-delivery-select]');
  const locker = document.getElementById('locker-fields');
  const courier = document.getElementById('courier-fields');
  const pickup = builder.querySelector('[data-pickup-note]');
  const deliverySection = builder.querySelector('[data-delivery-section]');
  const phoneField = builder.querySelector('[data-paper-phone]');
  const ebookNotice = builder.querySelector('[data-ebook-notice]');
  const digitalConsent = builder.querySelector('[data-digital-consent]');
  const preorderNote = builder.querySelector('[data-preorder-note]');
  const point = document.getElementById('inpost-point');
  const pointSummary = builder.querySelector('[data-inpost-point-summary]');
  const inpostMap = builder.querySelector('[data-inpost-map]');
  const picker = builder.querySelector('[data-book-picker]');
  const pickerGrid = builder.querySelector('[data-book-picker-grid]');
  const pickerStatus = builder.querySelector('[data-book-picker-status]');
  const search = builder.querySelector('[data-book-search]');
  const currency = builder.dataset.currency || 'PLN';
  let availableBooks = [];

  const money = (value) => new Intl.NumberFormat('pl-PL', {
    style: 'currency',
    currency,
    minimumFractionDigits: 2,
  }).format(Number(value) || 0);

  const itemElements = () => [...itemsContainer.querySelectorAll('[data-checkout-item]')];
  const selectedIds = () => new Set(itemElements().map(item => Number(item.dataset.bookId)));

  const setRequired = (element, required) => {
    if (!(element instanceof HTMLInputElement)) return;
    element.required = required;
  };

  const refresh = () => {
    const items = itemElements();
    const hasPaper = items.some(item => item.dataset.bookType !== 'ebook');
    const hasEbook = items.some(item => item.dataset.bookType === 'ebook');
    const hasPreorder = items.some(item => item.dataset.saleMode === 'preorder');
    if (!hasPaper && delivery) delivery.value = 'ebook';
    if (hasPaper && delivery?.value === 'ebook') delivery.value = 'inpost_locker';

    deliverySection.hidden = !hasPaper;
    phoneField.hidden = !hasPaper;
    setRequired(phoneField?.querySelector('input'), hasPaper);
    ebookNotice.hidden = !hasEbook;
    digitalConsent.hidden = !hasEbook;
    setRequired(digitalConsent?.querySelector('input'), hasEbook);
    if (preorderNote) preorderNote.hidden = !hasPreorder;

    const method = hasPaper ? delivery?.value : 'ebook';
    locker.hidden = method !== 'inpost_locker';
    courier.hidden = method !== 'inpost_courier';
    pickup.hidden = method !== 'pickup';
    setRequired(locker.querySelector('input'), method === 'inpost_locker');
    courier.querySelectorAll('input').forEach(input => setRequired(input, method === 'inpost_courier'));

    let subtotal = 0;
    items.forEach(item => {
      const quantity = item.querySelector('input[type="number"]');
      const min = Number(quantity?.min || 1);
      const max = Number(quantity?.max || 20);
      if (quantity) quantity.value = String(Math.min(max, Math.max(min, Number(quantity.value) || 1)));
      subtotal += Number(item.dataset.price || 0) * Number(quantity?.value || 1);
      const remove = item.querySelector('[data-remove-item]');
      if (remove) remove.hidden = items.length < 2;
    });
    const shipping = method === 'inpost_courier'
      ? Number(builder.dataset.shippingCourier || 0)
      : method === 'inpost_locker'
        ? Number(builder.dataset.shippingLocker || 0)
        : 0;
    const total = subtotal + shipping;
    builder.querySelector('[data-order-subtotal]').textContent = money(subtotal);
    builder.querySelector('[data-order-shipping]').textContent = shipping > 0 ? money(shipping) : '0,00 zł';
    builder.querySelector('[data-order-total]').textContent = money(total);
    const submit = builder.querySelector('[data-checkout-submit]');
    if (submit) submit.firstChild.textContent = `Kupuję — ${money(total)} `;
    renderPicker();
  };

  const createItem = (book) => {
    if (selectedIds().has(Number(book.id))) return;
    const item = document.createElement('article');
    item.className = 'checkout-item is-new';
    item.dataset.checkoutItem = '';
    item.dataset.bookId = String(book.id);
    item.dataset.bookType = book.type || 'paper';
    item.dataset.price = String(book.price || 0);
    item.dataset.saleMode = book.sale_mode || 'active';

    const cover = document.createElement('div');
    cover.className = 'checkout-item__cover';
    if (book.cover) {
      const image = document.createElement('img');
      image.src = book.cover;
      image.alt = '';
      cover.append(image);
    } else {
      const fallback = document.createElement('span');
      fallback.textContent = '100';
      cover.append(fallback);
    }

    const content = document.createElement('div');
    content.className = 'checkout-item__content';
    const title = document.createElement('strong');
    const author = document.createElement('small');
    const price = document.createElement('span');
    title.textContent = book.title || 'Książka';
    author.textContent = book.author || '';
    price.textContent = money(book.price);
    content.append(title, author);
    if (book.sale_mode === 'preorder') {
      const sale = document.createElement('em');
      sale.className = 'checkout-item__sale';
      sale.textContent = book.release_label || 'Przedsprzedaż';
      content.append(sale);
    }
    content.append(price);

    const quantity = document.createElement('div');
    quantity.className = 'checkout-item__quantity';
    quantity.setAttribute('aria-label', 'Liczba egzemplarzy');
    const minus = document.createElement('button');
    const input = document.createElement('input');
    const plus = document.createElement('button');
    minus.type = plus.type = 'button';
    minus.textContent = '−';
    plus.textContent = '+';
    minus.dataset.qtyDelta = '-1';
    plus.dataset.qtyDelta = '1';
    input.type = 'number';
    input.name = `items[${book.id}]`;
    input.min = '1';
    input.max = book.type === 'ebook' ? '1' : '20';
    input.value = '1';
    input.inputMode = 'numeric';
    input.setAttribute('aria-label', `Ilość: ${book.title || 'książka'}`);
    if (book.type === 'ebook') {
      input.readOnly = true;
      minus.disabled = plus.disabled = true;
    }
    quantity.append(minus, input, plus);

    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'checkout-item__remove';
    remove.dataset.removeItem = '';
    remove.setAttribute('aria-label', 'Usuń z zamówienia');
    remove.textContent = '×';
    item.append(cover, content, quantity, remove);
    itemsContainer.append(item);
    window.setTimeout(() => item.classList.remove('is-new'), 500);
    refresh();
  };

  const renderPicker = () => {
    if (!pickerGrid || !availableBooks.length) return;
    const query = (search?.value || '').trim().toLocaleLowerCase('pl');
    const selected = selectedIds();
    pickerGrid.replaceChildren();
    availableBooks
      .filter(book => !query || `${book.title} ${book.author}`.toLocaleLowerCase('pl').includes(query))
      .forEach(book => {
        const card = document.createElement('button');
        card.type = 'button';
        card.className = 'book-picker-card';
        if (selected.has(Number(book.id))) card.classList.add('is-selected');
        const visual = document.createElement('span');
        visual.className = 'book-picker-card__cover';
        if (book.cover) {
          const image = document.createElement('img');
          image.src = book.cover;
          image.alt = '';
          visual.append(image);
        }
        const text = document.createElement('span');
        const title = document.createElement('strong');
        const meta = document.createElement('small');
        const action = document.createElement('em');
        title.textContent = book.title;
        meta.textContent = `${book.author || ''} · ${money(book.price)}${book.sale_mode === 'preorder' ? ` · ${book.release_label || 'Przedsprzedaż'}` : ''}`;
        action.textContent = selected.has(Number(book.id)) ? 'Już dodana' : '+ Dodaj';
        text.append(title, meta, action);
        card.append(visual, text);
        card.disabled = selected.has(Number(book.id));
        card.addEventListener('click', () => {
          createItem(book);
          picker.close();
        });
        pickerGrid.append(card);
      });
  };

  builder.addEventListener('click', (event) => {
    const deltaButton = event.target.closest('[data-qty-delta]');
    if (deltaButton) {
      const input = deltaButton.parentElement.querySelector('input[type="number"]');
      if (input && !input.readOnly) {
        input.value = String(Number(input.value || 1) + Number(deltaButton.dataset.qtyDelta || 0));
        refresh();
      }
      return;
    }
    const remove = event.target.closest('[data-remove-item]');
    if (remove && itemElements().length > 1) {
      remove.closest('[data-checkout-item]')?.remove();
      refresh();
    }
  });
  itemsContainer.addEventListener('input', refresh);
  delivery?.addEventListener('change', refresh);

  builder.querySelector('[data-open-book-picker]')?.addEventListener('click', async () => {
    picker.showModal();
    if (availableBooks.length) {
      renderPicker();
      return;
    }
    pickerStatus.hidden = false;
    try {
      const response = await fetch('/api/checkout/books', {headers: {'Accept': 'application/json'}});
      const payload = await response.json();
      if (!response.ok || payload.ok === false) throw new Error('Nie pobrano książek.');
      availableBooks = Array.isArray(payload.books) ? payload.books : [];
      pickerStatus.hidden = true;
      renderPicker();
    } catch (_) {
      pickerStatus.textContent = 'Nie udało się pobrać książek. Zamknij okno i spróbuj ponownie.';
    }
  });
  builder.querySelector('[data-close-book-picker]')?.addEventListener('click', () => picker.close());
  picker?.addEventListener('click', (event) => {
    if (event.target === picker) picker.close();
  });
  search?.addEventListener('input', renderPicker);

  const applyInPostPoint = (payload) => {
    const selected = payload?.detail || payload?.details || payload;
    const name = String(selected?.name || '').trim().toUpperCase();
    if (!name || !point) return;
    point.value = name;
    point.dispatchEvent(new Event('change', { bubbles: true }));
    const address = selected?.address_details || selected?.address || {};
    const addressLine = [
      address.street,
      address.building_number,
      address.city,
    ].filter(Boolean).join(' ');
    if (pointSummary) {
      pointSummary.textContent = addressLine ? `Wybrany punkt: ${name} · ${addressLine}` : `Wybrany punkt: ${name}`;
    }
    const buttonLabel = builder.querySelector('[data-open-inpost-map] strong');
    if (buttonLabel) buttonLabel.textContent = 'Zmień punkt';
    if (inpostMap?.open) inpostMap.close();
  };
  window.book100PointSelected = applyInPostPoint;
  window.afterPointSelected = applyInPostPoint;
  document.addEventListener('book100PointSelected', applyInPostPoint);
  builder.querySelector('[data-open-inpost-map]')?.addEventListener('click', () => inpostMap?.showModal());
  builder.querySelector('[data-close-inpost-map]')?.addEventListener('click', () => inpostMap?.close());
  inpostMap?.addEventListener('click', (event) => {
    if (event.target === inpostMap) inpostMap.close();
  });
  refresh();
})();
</script>
<?php include __DIR__ . '/../partials/footer.php'; ?>
