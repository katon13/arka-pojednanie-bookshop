<?php include __DIR__ . '/../layout_top.php'; ?>
<?php
$attributeValues = json_decode((string)($book['attributes_json'] ?? ''), true);
if (!is_array($attributeValues)) $attributeValues = [];
$attributeLines = implode("\n", array_map(
    static fn(string $name, mixed $value): string => $name . ': ' . (string)$value,
    array_keys($attributeValues),
    array_values($attributeValues)
));
$isEdit = $mode === 'edit';
$publicUrl = \Book100\Core\StoreUrl::to('/book/' . ($book['slug'] ?? '') . '/');
$seoSuffix = trim((string)($adminStorefront['seo_title_suffix'] ?? $adminShopName ?? 'ARKA')) ?: 'ARKA';
$descriptionHtml = \Book100\Core\ContentFormatter::richHtml((string)($book['description'] ?? ''));
$seoPreviewImage = !empty($book['cover_image']) ? \Book100\Core\AdminPresenter::publicAsset((string)$book['cover_image']) : '';
$seoInStock = \Book100\Services\Books\BookSaleState::isPurchasable($book);
?>

<div class="page-heading page-heading--compact">
  <div>
    <a class="back-link" href="/books">← Książki</a>
    <p class="kicker"><?= $isEdit ? 'EDYCJA KSIĄŻKI' : 'NOWA KSIĄŻKA' ?></p>
    <h1><?= $isEdit ? htmlspecialchars($book['title']) : 'Dodaj książkę' ?></h1>
    <p class="muted">Po zapisaniu cache strony jest czyszczony automatycznie, więc cena, stan i SEO od razu będą aktualne.</p>
  </div>
  <?php if ($isEdit && \Book100\Services\Books\BookSaleState::isPublic($book)): ?><a class="btn secondary" href="<?= htmlspecialchars($publicUrl) ?>" target="_blank" rel="noopener">Zobacz w sklepie ↗</a><?php endif; ?>
</div>

<?php if (!empty($errors)): ?><div class="error"><strong>Popraw dane:</strong><ul><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<form class="book-editor editor-form editor-form--with-savebar" method="post" enctype="multipart/form-data" action="<?= $mode === 'create' ? '/books' : '/books/'.(int)$book['id'] ?>" data-ajax-success="Książka została zapisana." data-ajax-clear-files>
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">
  <input type="hidden" name="old_wp_id" value="<?= htmlspecialchars((string)($book['old_wp_id'] ?? '')) ?>">

  <div class="editor-savebar">
    <div class="editor-savebar__copy">
      <strong><?= $isEdit ? 'Zapisz zmiany w książce' : 'Dodaj książkę' ?></strong>
      <span>Zapis działa bez opuszczania formularza.</span>
    </div>
    <div class="editor-savebar__actions">
      <a class="btn secondary" href="/books">Anuluj</a>
      <button class="btn" type="submit">Zapisz książkę</button>
    </div>
  </div>

  <div class="book-editor__main">
    <section class="panel-section">
      <div class="section-heading"><div><p class="section-label">PODSTAWOWE</p><h2>Nazwa i opis</h2></div></div>
      <label class="field">Tytuł książki<input id="book-title" name="title" required maxlength="255" value="<?= htmlspecialchars($book['title'] ?? '') ?>"></label>
      <div class="two">
        <label class="field">Autor
          <select name="author_id" required>
            <option value="">Wybierz autora</option>
            <?php foreach (($authors ?? []) as $authorOption): ?>
              <option value="<?= (int)$authorOption['id'] ?>" <?= (int)($book['author_id'] ?? 0) === (int)$authorOption['id'] ? 'selected' : '' ?>><?= htmlspecialchars($authorOption['name']) ?><?= ($authorOption['status'] ?? 'active') === 'hidden' ? ' (ukryty)' : '' ?></option>
            <?php endforeach; ?>
          </select>
          <small>Profil wybierany z kartoteki Autorzy. <a href="/authors/new" target="_blank">Dodaj nowego autora ↗</a></small>
        </label>
        <label class="field">Adres książki (slug)<input id="book-slug" name="slug" value="<?= htmlspecialchars($book['slug'] ?? '') ?>" placeholder="utworzy się z tytułu"></label>
      </div>
      <label class="field">Krótki opis <small>Widoczny pod tytułem na liście książek.</small><textarea name="short_description" rows="4"><?= htmlspecialchars($book['short_description'] ?? '') ?></textarea></label>
      <div class="field">
        <span>Pełny opis</span>
        <small>Treść na stronie konkretnej książki. Zaznacz tekst i wybierz formatowanie.</small>
        <div class="rich-editor" data-rich-editor data-rich-upload-url="/media/upload" data-rich-media-scope="books" data-rich-drop-label="Upuść grafikę w tym miejscu">
          <div class="rich-editor__toolbar" role="toolbar" aria-label="Formatowanie pełnego opisu">
            <div class="rich-editor__group">
              <label class="rich-editor__select-label">
                <span class="sr-only">Rodzaj tekstu</span>
                <select data-rich-block aria-label="Rodzaj tekstu">
                  <option value="p">Zwykły tekst</option>
                  <option value="h2">Nagłówek duży</option>
                  <option value="h3">Nagłówek średni</option>
                  <option value="h4">Nagłówek mały</option>
                  <option value="blockquote">Cytat</option>
                </select>
              </label>
            </div>
            <div class="rich-editor__group">
              <button type="button" data-rich-command="bold" title="Pogrubienie" aria-label="Pogrubienie"><strong>B</strong></button>
              <button type="button" data-rich-command="italic" title="Kursywa" aria-label="Kursywa"><em>I</em></button>
              <button type="button" data-rich-command="underline" title="Podkreślenie" aria-label="Podkreślenie"><u>U</u></button>
              <button type="button" data-rich-command="strikeThrough" title="Przekreślenie" aria-label="Przekreślenie"><s>S</s></button>
            </div>
            <div class="rich-editor__group">
              <button type="button" data-rich-command="insertUnorderedList" title="Lista punktowana" aria-label="Lista punktowana">• Lista</button>
              <button type="button" data-rich-command="insertOrderedList" title="Lista numerowana" aria-label="Lista numerowana">1. Lista</button>
            </div>
            <div class="rich-editor__group">
              <button type="button" data-rich-command="justifyLeft" title="Wyrównaj do lewej" aria-label="Wyrównaj do lewej">≡</button>
              <button type="button" data-rich-command="justifyCenter" title="Wyśrodkuj" aria-label="Wyśrodkuj">≡</button>
              <button type="button" data-rich-command="justifyRight" title="Wyrównaj do prawej" aria-label="Wyrównaj do prawej">≡</button>
            </div>
            <div class="rich-editor__group">
              <label class="rich-editor__color" title="Kolor tekstu">
                <span>Kolor</span>
                <input type="color" value="#17191f" data-rich-color aria-label="Kolor tekstu">
              </label>
              <button type="button" data-rich-action="link" title="Dodaj link">Link</button>
              <button type="button" data-rich-command="unlink" title="Usuń link" aria-label="Usuń link">Bez linku</button>
            </div>
            <div class="rich-editor__group rich-editor__group--end">
              <button type="button" data-rich-action="image" title="Otwórz bibliotekę Media i wstaw grafikę">Grafika</button>
              <button type="button" data-rich-action="youtube" title="Wstaw film z YouTube">YouTube</button>
              <input type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-rich-image-input hidden>
              <button type="button" data-rich-command="undo" title="Cofnij" aria-label="Cofnij">↶</button>
              <button type="button" data-rich-command="redo" title="Ponów" aria-label="Ponów">↷</button>
              <button type="button" data-rich-command="removeFormat" title="Usuń formatowanie">Wyczyść</button>
            </div>
          </div>
          <div
            class="rich-editor__surface"
            contenteditable="true"
            role="textbox"
            aria-multiline="true"
            aria-label="Pełny opis książki"
            data-rich-surface
            data-placeholder="Zacznij pisać pełny opis książki…"
          ><?= $descriptionHtml ?></div>
          <textarea name="description" data-rich-input hidden><?= htmlspecialchars($descriptionHtml) ?></textarea>
        </div>
      </div>
    </section>

    <section class="panel-section">
      <div class="section-heading"><div><p class="section-label">DANE KSIĄŻKI</p><h2>Wydanie i magazyn</h2></div></div>
      <div class="two">
        <label class="field">SKU<input name="sku" value="<?= htmlspecialchars($book['sku'] ?? '') ?>"></label>
        <label class="field">ISBN<input name="isbn" value="<?= htmlspecialchars($book['isbn'] ?? '') ?>"></label>
        <label class="field">Wydawca<input name="publisher" value="<?= htmlspecialchars($book['publisher'] ?? '') ?>"></label>
        <label class="field">Rok wydania<input name="publication_year" type="number" min="0" value="<?= htmlspecialchars((string)($book['publication_year'] ?? '')) ?>"></label>
        <label class="field">Liczba stron<input name="pages" type="number" min="0" value="<?= htmlspecialchars((string)($book['pages'] ?? '')) ?>"></label>
        <label class="field">Format<input name="format" value="<?= htmlspecialchars($book['format'] ?? '') ?>" placeholder="np. A5, miękka oprawa albo EPUB"></label>
      </div>
      <label class="field">Dodatkowe informacje <small>Jedna pozycja w wierszu, np. Oprawa: miękka.</small><textarea name="attribute_lines" rows="6"><?= htmlspecialchars($attributeLines) ?></textarea></label>
      <details class="sub-details">
        <summary>Wymiary przesyłki</summary>
        <div class="four">
          <label class="field">Waga kg<input name="weight_kg" value="<?= htmlspecialchars((string)($book['weight_kg'] ?? '')) ?>"></label>
          <label class="field">Długość cm<input name="length_cm" value="<?= htmlspecialchars((string)($book['length_cm'] ?? '')) ?>"></label>
          <label class="field">Szerokość cm<input name="width_cm" value="<?= htmlspecialchars((string)($book['width_cm'] ?? '')) ?>"></label>
          <label class="field">Wysokość cm<input name="height_cm" value="<?= htmlspecialchars((string)($book['height_cm'] ?? '')) ?>"></label>
        </div>
      </details>
    </section>

    <section class="panel-section seo-editor">
      <div class="section-heading">
        <div><p class="section-label">SEO I GOOGLE MERCHANT</p><h2>Wygląd wyszukiwania</h2></div>
        <span class="pill pill--success">Google gotowe</span>
      </div>
      <p class="muted">Tak klient zobaczy książkę w Google. Podgląd aktualizuje się podczas pisania.</p>
      <div class="seo-device-switch" role="group" aria-label="Rozmiar podglądu">
        <button class="is-active" type="button" data-seo-device="mobile" aria-pressed="true"><span></span> Telefon</button>
        <button type="button" data-seo-device="desktop" aria-pressed="false"><span></span> Komputer</button>
      </div>
      <div class="seo-preview seo-preview--mobile" data-seo-preview>
        <div class="seo-preview__source">
          <span class="seo-preview__favicon"><?= htmlspecialchars(mb_strtoupper(mb_substr($adminShopName, 0, 1))) ?></span>
          <span><strong><?= htmlspecialchars($adminShopName) ?></strong><small id="seo-preview-url"><?= htmlspecialchars($publicUrl) ?></small></span>
          <b aria-hidden="true">⋮</b>
        </div>
        <div class="seo-preview__result">
          <div>
            <strong id="seo-preview-title"><?= htmlspecialchars(($book['seo_title'] ?? '') ?: (($book['title'] ?? 'Tytuł książki') . ' — ' . $seoSuffix)) ?></strong>
            <p id="seo-preview-description"><?= htmlspecialchars(($book['seo_description'] ?? '') ?: ($book['short_description'] ?? 'Opis książki wyświetlany w wynikach wyszukiwania.')) ?></p>
          </div>
          <?php if ($seoPreviewImage !== ''): ?><img src="<?= htmlspecialchars($seoPreviewImage) ?>" alt="" data-seo-preview-image><?php endif; ?>
        </div>
        <div class="seo-preview__commerce">
          <span>Cena<strong data-seo-price><?= number_format((float)($book['price_gross'] ?? 0), 2, ',', ' ') ?> zł</strong></span>
          <span>Dostępność<strong data-seo-availability><?= $seoInStock ? 'W magazynie' : 'Brak nakładu' ?></strong></span>
        </div>
      </div>
      <label class="field">Tytuł SEO <small><span id="seo-title-count">0</span>/60 znaków. Puste pole użyje tytułu książki.</small><input id="seo-title" name="seo_title" maxlength="255" value="<?= htmlspecialchars($book['seo_title'] ?? '') ?>"></label>
      <label class="field">Opis SEO <small><span id="seo-description-count">0</span>/160 znaków.</small><textarea id="seo-description" name="seo_description" maxlength="320" rows="4"><?= htmlspecialchars($book['seo_description'] ?? '') ?></textarea></label>
      <label class="field">Hasła kluczowe <small>Oddziel przecinkami, np. reportaż, historia Polski, Nowy Sącz. Pomagają uporządkować tematykę strony i dane produktu.</small><textarea name="seo_keywords" maxlength="1000" rows="3" placeholder="książka, reportaż, historia"><?= htmlspecialchars($book['seo_keywords'] ?? '') ?></textarea></label>
      <label class="field">Adres kanoniczny <small>Zostaw pusty, aby użyć zwykłego adresu tej książki. Zmieniaj tylko przy duplikacie treści.</small><input name="canonical_url" type="url" value="<?= htmlspecialchars($book['canonical_url'] ?? '') ?>" placeholder="<?= htmlspecialchars($publicUrl) ?>"></label>
      <p class="seo-checklist">✓ adres /book/ &nbsp; ✓ canonical &nbsp; ✓ Open Graph &nbsp; ✓ Product/Book &nbsp; ✓ Merchant XML &nbsp; ✓ sitemap.xml</p>
    </section>
  </div>

  <aside class="book-editor__side">
    <section class="panel-section cover-editor">
      <p class="section-label">OKŁADKA</p>
      <div class="cover-preview" id="cover-preview">
        <?php if (!empty($book['cover_image'])): ?>
          <img id="cover-preview-image" src="<?= htmlspecialchars(\Book100\Core\AdminPresenter::publicAsset($book['cover_image'])) ?>" alt="Podgląd okładki">
        <?php else: ?>
          <span id="cover-preview-placeholder" class="cover-placeholder cover-placeholder--editor">100</span>
          <img id="cover-preview-image" src="" alt="Podgląd okładki" hidden>
        <?php endif; ?>
      </div>
      <label
        class="upload-zone"
        for="cover-file"
        tabindex="0"
        role="button"
        data-upload-zone
        data-upload-kind="image"
        data-preview-target="#cover-preview-image"
        data-placeholder-target="#cover-preview-placeholder"
        data-max-mb="10"
      >
        <span class="upload-zone__icon" aria-hidden="true">↑</span>
        <strong>Przeciągnij okładkę tutaj</strong>
        <span>albo kliknij i wybierz plik z dysku</span>
        <em data-upload-status>JPG, PNG lub WEBP · optymalizacja automatyczna</em>
      </label>
      <input id="cover-file" class="visually-hidden-file" name="cover_file" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
    </section>

    <section class="panel-section publish-editor">
      <p class="section-label">SPRZEDAŻ</p>
      <label class="field">Status<select name="status">
        <option value="draft" <?= ($book['status'] ?? '')==='draft'?'selected':'' ?>>Szkic</option>
        <option value="active" <?= ($book['status'] ?? '')==='active'?'selected':'' ?>>Aktywna</option>
        <option value="preorder" <?= ($book['status'] ?? '')==='preorder'?'selected':'' ?>>Przedsprzedaż</option>
        <option value="announced" <?= ($book['status'] ?? '')==='announced'?'selected':'' ?>>Zapowiedź</option>
        <option value="hidden" <?= ($book['status'] ?? '')==='hidden'?'selected':'' ?>>Ukryta</option>
        <option value="sold_out" <?= ($book['status'] ?? '')==='sold_out'?'selected':'' ?>>Brak nakładu</option>
      </select></label>
      <div class="sale-status-guide" data-sale-status-guide>
        <span data-sale-status-icon aria-hidden="true">●</span>
        <div>
          <strong data-sale-status-title>Ustaw status książki</strong>
          <small data-sale-status-description>Status określa widoczność i możliwość zakupu.</small>
        </div>
      </div>
      <label class="field release-date-field" data-release-date-field <?= !in_array(($book['status'] ?? ''), ['preorder','announced'], true) ? 'hidden' : '' ?>>
        Planowana data premiery
        <input name="release_date" type="date" min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars((string)($book['release_date'] ?? '')) ?>">
        <small>Wyświetlimy ją przy okładce, zakupie i w potwierdzeniu zamówienia.</small>
      </label>
      <label class="field">Typ<select id="product-type" name="product_type">
        <option value="paper" <?= ($book['product_type'] ?? '')==='paper'?'selected':'' ?>>Książka papierowa</option>
        <option value="ebook" <?= ($book['product_type'] ?? '')==='ebook'?'selected':'' ?>>E-book</option>
      </select></label>
      <label class="field">Cena brutto (<?= htmlspecialchars($adminStorefront['currency'] ?? $book['currency'] ?? 'PLN') ?>)<input name="price_gross" inputmode="decimal" value="<?= htmlspecialchars((string)($book['price_gross'] ?? '0.00')) ?>"></label>
      <label class="field">Stan magazynowy<input name="stock_qty" type="number" min="0" value="<?= htmlspecialchars((string)($book['stock_qty'] ?? 0)) ?>"></label>
      <label class="check-field"><input type="checkbox" name="manage_stock" value="1" <?= !empty($book['manage_stock']) ? 'checked' : '' ?>> Kontroluj stan magazynowy</label>
    </section>

    <section class="panel-section">
      <p class="section-label">PLIK E-BOOKA</p>
      <label
        class="upload-zone upload-zone--compact"
        for="ebook-file"
        tabindex="0"
        role="button"
        data-upload-zone
        data-max-mb="100"
      >
        <span class="upload-zone__icon" aria-hidden="true">↑</span>
        <strong>Przeciągnij plik e-booka</strong>
        <span>albo wybierz PDF, EPUB lub MOBI</span>
        <em data-upload-status>Maksymalnie 100 MB</em>
      </label>
      <input id="ebook-file" class="visually-hidden-file" name="ebook_file" type="file" accept=".pdf,.epub,.mobi,application/pdf,application/epub+zip">
      <?php if (!empty($book['ebook_file_path'])): ?>
        <div data-asset-current>
          <p class="file-attached">Plik jest podpięty</p>
          <button class="asset-remove-button" type="button" data-asset-remove
                  data-asset-scope="book" data-asset-name="ebook_file_path" data-asset-id="<?= (int)$book['id'] ?>"
                  data-file-input="#ebook-file" data-empty-target="#ebook-file-empty">
            <span aria-hidden="true">×</span> Odłącz plik
          </button>
        </div>
        <p id="ebook-file-empty" class="muted" hidden>Brak podpiętego pliku.</p>
      <?php else: ?>
        <p id="ebook-file-empty" class="muted">Brak podpiętego pliku.</p>
      <?php endif; ?>
    </section>

  </aside>
</form>

<?php if ($isEdit): ?>
  <details class="danger-zone">
    <summary>Trwałe usunięcie książki</summary>
    <p class="muted">Usuwa książkę na stałe razem z powiązanymi zamówieniami, płatnościami i przesyłkami.</p>
    <form method="post" action="/books/<?= (int)$book['id'] ?>/delete" data-ajax-success="Książka i jej historia sprzedaży zostały trwale usunięte.">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">
      <button class="danger" type="submit">Usuń książkę na stałe</button>
    </form>
  </details>
<?php endif; ?>

<script>
(() => {
  const title = document.getElementById('book-title');
  const slug = document.getElementById('book-slug');
  const seoTitle = document.getElementById('seo-title');
  const seoDescription = document.getElementById('seo-description');
  const previewTitle = document.getElementById('seo-preview-title');
  const previewDescription = document.getElementById('seo-preview-description');
  const previewUrl = document.getElementById('seo-preview-url');
  const titleCount = document.getElementById('seo-title-count');
  const descriptionCount = document.getElementById('seo-description-count');
  const price = document.querySelector('[name="price_gross"]');
  const status = document.querySelector('[name="status"]');
  const stock = document.querySelector('[name="stock_qty"]');
  const productType = document.querySelector('[name="product_type"]');
  const manageStock = document.querySelector('[name="manage_stock"]');
  const previewPrice = document.querySelector('[data-seo-price]');
  const previewAvailability = document.querySelector('[data-seo-availability]');
  const preview = document.querySelector('[data-seo-preview]');
  const releaseDateField = document.querySelector('[data-release-date-field]');
  const releaseDate = document.querySelector('[name="release_date"]');
  const saleStatusGuide = document.querySelector('[data-sale-status-guide]');
  const saleStatusTitle = document.querySelector('[data-sale-status-title]');
  const saleStatusDescription = document.querySelector('[data-sale-status-description]');
  const baseUrl = <?= json_encode(\Book100\Core\StoreUrl::to('/book/'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const seoSuffix = <?= json_encode($seoSuffix, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

  const refreshSeo = () => {
    const titleValue = seoTitle.value.trim() || ((title.value.trim() || 'Tytuł książki') + ' — ' + seoSuffix);
    const descriptionValue = seoDescription.value.trim() || 'Opis książki wyświetlany w wynikach wyszukiwania.';
    previewTitle.textContent = titleValue;
    previewDescription.textContent = descriptionValue;
    previewUrl.textContent = baseUrl + (slug.value.trim() || 'adres-ksiazki') + '/';
    titleCount.textContent = seoTitle.value.length;
    descriptionCount.textContent = seoDescription.value.length;
    const numericPrice = Number.parseFloat((price?.value || '0').replace(',', '.'));
    if (previewPrice) {
      previewPrice.textContent = `${Number.isFinite(numericPrice) ? numericPrice.toFixed(2).replace('.', ',') : '0,00'} zł`;
    }
    const purchasableStatus = status?.value === 'active' || status?.value === 'preorder';
    const available = purchasableStatus
      && (productType?.value === 'ebook' || !manageStock?.checked || Number(stock?.value || 0) > 0);
    if (previewAvailability) {
      previewAvailability.textContent = status?.value === 'preorder'
        ? 'Przedsprzedaż'
        : status?.value === 'announced'
          ? 'Zapowiedź'
          : (available ? 'W magazynie' : 'Brak nakładu');
    }
    const statusContent = {
      active: ['Aktywna sprzedaż', 'Książkę można kupić i wysłać od razu.'],
      preorder: ['Przedsprzedaż', 'Zakup jest aktywny. Klient widzi datę premiery i późniejszej wysyłki.'],
      announced: ['Zapowiedź', 'Książka jest widoczna, ale przycisk zakupu pozostaje wyłączony.'],
      sold_out: ['Brak nakładu', 'Strona pozostaje widoczna, lecz zakup jest wyłączony.'],
      hidden: ['Ukryta', 'Książka nie jest widoczna w publicznym sklepie.'],
      draft: ['Szkic', 'Wersja robocza dostępna wyłącznie w panelu.'],
    };
    const selectedStatus = statusContent[status?.value] || statusContent.draft;
    if (saleStatusTitle) saleStatusTitle.textContent = selectedStatus[0];
    if (saleStatusDescription) saleStatusDescription.textContent = selectedStatus[1];
    if (saleStatusGuide) saleStatusGuide.dataset.status = status?.value || 'draft';
    const showReleaseDate = ['preorder', 'announced'].includes(status?.value || '');
    if (releaseDateField) releaseDateField.hidden = !showReleaseDate;
    if (releaseDate) releaseDate.disabled = !showReleaseDate;
  };
  [title, slug, seoTitle, seoDescription, price, status, stock, productType, manageStock, releaseDate].forEach((input) => {
    input?.addEventListener('input', refreshSeo);
    input?.addEventListener('change', refreshSeo);
  });
  document.querySelectorAll('[data-seo-device]').forEach((button) => {
    button.addEventListener('click', () => {
      const device = button.dataset.seoDevice === 'desktop' ? 'desktop' : 'mobile';
      preview?.classList.toggle('seo-preview--desktop', device === 'desktop');
      preview?.classList.toggle('seo-preview--mobile', device === 'mobile');
      document.querySelectorAll('[data-seo-device]').forEach((item) => {
        const active = item === button;
        item.classList.toggle('is-active', active);
        item.setAttribute('aria-pressed', String(active));
      });
    });
  });
  refreshSeo();
})();
</script>

<?php include __DIR__ . '/../layout_bottom.php'; ?>
