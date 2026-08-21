<?php include __DIR__ . '/../layout_top.php'; ?>
<?php
$ui = \Book100\Core\AdminPresenter::class;
$booksById = [];
foreach ($books as $book) $booksById[(int)$book['id']] = $book;
$pages = is_array($pages ?? null) ? $pages : [];
$pagesById = [];
foreach ($pages as $contentPage) $pagesById[(int)$contentPage['id']] = $contentPage;
$events = is_array($events ?? null) ? $events : [];
$eventsById = [];
foreach ($events as $event) $eventsById[(int)$event['id']] = $event;
$featuredTargets = is_array($featuredTargets ?? null) ? $featuredTargets : [];
foreach ([1, 2] as $slot) {
  if (!isset($featuredTargets[$slot])) {
    $legacyId = (int)($featuredIds[$slot] ?? 0);
    $featuredTargets[$slot] = ['type' => $legacyId > 0 ? 'book' : '', 'id' => $legacyId];
  }
}
$hero = array_replace((new \Book100\Services\Homepage\HomepageSettingsService())->heroDefaults(), is_array($hero ?? null) ? $hero : []);
$heroCustomImage = trim((string)$hero['image']);
$heroPreview = $heroCustomImage !== ''
  ? $ui::publicAsset($heroCustomImage)
  : ($adminShopLogoUrl !== '' ? $adminShopLogoUrl : $ui::publicAsset('/assets/brand/arka-logo.png'));
?>

<div class="page-heading page-heading--compact">
  <div>
    <p class="kicker">WITRYNA SKLEPU</p>
    <h1>Strona główna</h1>
    <p class="muted">Edytuj baner, wybierz promocje, ustaw kolejność książek i zdecyduj, co ma być widoczne.</p>
  </div>
  <a class="btn secondary" href="<?= htmlspecialchars(\Book100\Core\StoreUrl::to('/')) ?>" target="_blank" rel="noopener">Zobacz sklep ↗</a>
</div>

<form class="homepage-editor editor-form editor-form--with-savebar" method="post" action="/homepage" enctype="multipart/form-data" data-ajax-success="Strona główna została zapisana." data-ajax-refresh>
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">

  <div class="editor-savebar">
    <div class="editor-savebar__copy">
      <strong>Zapisz stronę główną</strong>
      <span>Po zapisie cache strony zostanie od razu wyczyszczony.</span>
    </div>
    <button class="btn" type="submit">Zapisz stronę główną</button>
  </div>

  <section class="panel-section">
    <div class="section-heading">
      <div>
        <p class="section-label">BANER STARTOWY</p>
        <h2>Górna część strony głównej</h2>
      </div>
      <span class="muted">Teksty, przyciski i grafika widoczne od razu po wejściu na stronę</span>
    </div>

    <div class="featured-admin-card homepage-hero-card">
      <div class="homepage-hero-media">
        <div class="featured-admin-preview">
          <img id="hero-preview" src="<?= htmlspecialchars($heroPreview) ?>" alt="">
          <span id="hero-placeholder" class="cover-placeholder cover-placeholder--large" hidden>ARKA</span>
        </div>
        <div class="field">
          <span>Grafika po prawej stronie</span>
          <label
            class="upload-zone upload-zone--compact"
            for="hero-image"
            tabindex="0"
            role="button"
            data-upload-zone
            data-upload-kind="image"
            data-preview-target="#hero-preview"
            data-placeholder-target="#hero-placeholder"
            data-max-mb="12"
          >
            <span class="upload-zone__icon" aria-hidden="true">↑</span>
            <strong>Przeciągnij grafikę</strong>
            <span>albo wybierz plik z dysku</span>
            <em data-upload-status>JPG, PNG lub WEBP · optymalizacja automatyczna</em>
          </label>
          <input id="hero-image" class="visually-hidden-file" type="file" name="hero_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
          <small>Bez własnej grafiki baner użyje aktualnego logo sklepu z Ustawień.</small>
        </div>
        <?php if ($heroCustomImage !== ''): ?>
          <button class="asset-remove-button" type="button" data-asset-remove
                  data-asset-scope="homepage" data-asset-name="hero_image"
                  data-preview-target="#hero-preview" data-placeholder-target="#hero-placeholder"
                  data-file-input="#hero-image"
                  data-fallback-src="<?= htmlspecialchars($adminShopLogoUrl !== '' ? $adminShopLogoUrl : $ui::publicAsset('/assets/brand/arka-logo.png')) ?>">
            <span aria-hidden="true">×</span> Usuń grafikę i użyj logo
          </button>
        <?php endif; ?>
      </div>
      <div>
        <div class="settings-grid">
          <label class="field">Mały tekst nad nagłówkiem
            <input type="text" name="hero_eyebrow" maxlength="120" value="<?= htmlspecialchars($hero['eyebrow']) ?>">
          </label>
          <label class="field">Nagłówek
            <input type="text" name="hero_title" maxlength="160" required value="<?= htmlspecialchars($hero['title']) ?>">
          </label>
        </div>
        <label class="field">Opis
          <textarea name="hero_text" rows="3" maxlength="600"><?= htmlspecialchars($hero['text']) ?></textarea>
        </label>
        <div class="settings-grid">
          <label class="field">Tekst pierwszego przycisku
            <input type="text" name="hero_primary_label" maxlength="80" value="<?= htmlspecialchars($hero['primary_label']) ?>">
          </label>
          <label class="field">Link pierwszego przycisku
            <input type="text" name="hero_primary_url" value="<?= htmlspecialchars($hero['primary_url']) ?>" placeholder="/idea-znaku-arka">
          </label>
          <label class="field">Tekst drugiego przycisku
            <input type="text" name="hero_secondary_label" maxlength="80" value="<?= htmlspecialchars($hero['secondary_label']) ?>">
          </label>
          <label class="field">Link drugiego przycisku
            <input type="text" name="hero_secondary_url" value="<?= htmlspecialchars($hero['secondary_url']) ?>" placeholder="/rekolekcje-pojednania">
          </label>
        </div>
        <div class="settings-grid">
          <label class="field">Link po kliknięciu grafiki
            <input type="text" name="hero_image_url" value="<?= htmlspecialchars($hero['image_url']) ?>" placeholder="/idea-znaku-arka">
          </label>
          <label class="field">Opis grafiki dla czytników
            <input type="text" name="hero_image_alt" maxlength="160" value="<?= htmlspecialchars($hero['image_alt']) ?>">
          </label>
        </div>
      </div>
    </div>
  </section>

  <section class="panel-section">
    <div class="section-heading">
      <div>
        <p class="section-label">PROMOWANE</p>
        <h2>Dwie duże promocje na początku</h2>
      </div>
      <span class="muted">Promocja może prowadzić do książki, strony lub wydarzenia</span>
    </div>

    <div class="featured-admin-grid">
      <?php foreach ([1, 2] as $slot):
        $selectedTarget = $featuredTargets[$slot] ?? ['type' => '', 'id' => 0];
        $selectedType = (string)($selectedTarget['type'] ?? '');
        $selectedId = (int)($selectedTarget['id'] ?? 0);
        $selectedBook = $selectedType === 'book' ? ($booksById[$selectedId] ?? null) : null;
        $selectedPage = $selectedType === 'page' ? ($pagesById[$selectedId] ?? null) : null;
        $selectedEvent = $selectedType === 'event' ? ($eventsById[$selectedId] ?? null) : null;
        $promoImage = (string)($featuredImages[$slot] ?? '');
        $fallbackImage = $selectedBook['cover_image'] ?? $selectedPage['featured_image'] ?? $selectedEvent['featured_image'] ?? '';
        $preview = $promoImage !== '' ? $promoImage : (string)$fallbackImage;
      ?>
        <article class="featured-admin-card">
          <div class="featured-admin-preview">
            <img id="featured-preview-<?= $slot ?>" src="<?= $preview !== '' ? htmlspecialchars($ui::publicAsset($preview)) : '' ?>" alt="" <?= $preview === '' ? 'hidden' : '' ?>>
            <span id="featured-placeholder-<?= $slot ?>" class="cover-placeholder cover-placeholder--large" <?= $preview !== '' ? 'hidden' : '' ?>>100</span>
          </div>
          <div>
            <span class="section-label">MIEJSCE <?= $slot ?></span>
            <label class="field">Promowana treść
              <select
                name="featured_<?= $slot ?>_target"
                data-featured-target-select
                data-preview-target="#featured-preview-<?= $slot ?>"
                data-placeholder-target="#featured-placeholder-<?= $slot ?>"
                data-initial-value="<?= htmlspecialchars($selectedType !== '' && $selectedId > 0 ? $selectedType . ':' . $selectedId : '') ?>"
                data-custom-image="<?= htmlspecialchars($promoImage !== '' ? $ui::publicAsset($promoImage) : '') ?>"
              >
                <option value="">Brak promocji</option>
                <optgroup label="Książki">
                  <?php foreach ($books as $book): ?>
                    <?php if (!\Book100\Services\Books\BookSaleState::isPublic($book)) continue; ?>
                    <?php $targetValue = 'book:' . (int)$book['id']; ?>
                    <option value="<?= $targetValue ?>" data-image="<?= htmlspecialchars(!empty($book['cover_image']) ? $ui::publicAsset($book['cover_image']) : '') ?>" <?= $selectedType === 'book' && $selectedId === (int)$book['id'] ? 'selected' : '' ?>><?= htmlspecialchars($book['title']) ?> · <?= htmlspecialchars(\Book100\Services\Books\BookSaleState::label($book)) ?></option>
                  <?php endforeach; ?>
                </optgroup>
                <optgroup label="Strony">
                  <?php foreach ($pages as $contentPage): ?>
                    <?php if (($contentPage['status'] ?? '') !== 'published') continue; ?>
                    <?php $targetValue = 'page:' . (int)$contentPage['id']; ?>
                    <option value="<?= $targetValue ?>" data-image="<?= htmlspecialchars(!empty($contentPage['featured_image']) ? $ui::publicAsset($contentPage['featured_image']) : '') ?>" <?= $selectedType === 'page' && $selectedId === (int)$contentPage['id'] ? 'selected' : '' ?>><?= htmlspecialchars($contentPage['title']) ?></option>
                  <?php endforeach; ?>
                </optgroup>
                <optgroup label="Wydarzenia">
                  <?php foreach ($events as $eventOption): ?>
                    <?php if (($eventOption['status'] ?? '') !== 'published') continue; ?>
                    <?php $targetValue = 'event:' . (int)$eventOption['id']; ?>
                    <option value="<?= $targetValue ?>" data-image="<?= htmlspecialchars(!empty($eventOption['featured_image']) ? $ui::publicAsset($eventOption['featured_image']) : '') ?>" <?= $selectedType === 'event' && $selectedId === (int)$eventOption['id'] ? 'selected' : '' ?>><?= htmlspecialchars($eventOption['title']) ?> · <?= htmlspecialchars(date('d.m.Y', strtotime((string)$eventOption['starts_at']))) ?></option>
                  <?php endforeach; ?>
                </optgroup>
              </select>
            </label>
            <div class="field">
              <span>Opcjonalna duża grafika</span>
              <label
                class="upload-zone upload-zone--compact"
                for="featured-<?= $slot ?>-image"
                tabindex="0"
                role="button"
                data-upload-zone
                data-upload-kind="image"
                data-preview-target="#featured-preview-<?= $slot ?>"
                data-placeholder-target="#featured-placeholder-<?= $slot ?>"
                data-max-mb="12"
              >
                <span class="upload-zone__icon" aria-hidden="true">↑</span>
                <strong>Przeciągnij grafikę</strong>
                <span>albo wybierz plik z dysku</span>
                <em data-upload-status>JPG, PNG lub WEBP · optymalizacja automatyczna</em>
              </label>
              <input id="featured-<?= $slot ?>-image" class="visually-hidden-file" type="file" name="featured_<?= $slot ?>_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
              <small>Jeśli nic nie wgrasz, zostanie użyta okładka książki albo obraz wyróżniający strony.</small>
            </div>
            <?php if ($promoImage !== ''): ?>
              <button class="asset-remove-button" type="button" data-asset-remove
                      data-asset-scope="homepage" data-asset-name="featured_<?= $slot ?>_image"
                      data-preview-target="#featured-preview-<?= $slot ?>" data-placeholder-target="#featured-placeholder-<?= $slot ?>"
                      data-file-input="#featured-<?= $slot ?>-image"
                      data-fallback-select="[name='featured_<?= $slot ?>_target']">
                <span aria-hidden="true">×</span> Usuń grafikę i użyj obrazu treści
              </button>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="panel-section">
    <div class="section-heading">
      <div>
        <p class="section-label">KATALOG</p>
        <h2>Kolejność książek</h2>
      </div>
      <span class="muted">Złap uchwyt i przeciągnij książkę</span>
    </div>

    <div class="homepage-book-list" data-sortable-books>
      <?php foreach ($books as $position => $book):
        $status = (string)($book['status'] ?? '');
        $active = $status === 'active';
        $preorder = $status === 'preorder';
        $announced = $status === 'announced';
        $soldOut = $status === 'sold_out';
        $catalogEligible = \Book100\Services\Books\BookSaleState::isPublic($book);
        $visible = $catalogEligible && !in_array((int)$book['id'], $hiddenIds, true);
      ?>
        <article class="homepage-book-row" data-book-row data-book-id="<?= (int)$book['id'] ?>">
          <button class="drag-handle" type="button" draggable="true" data-drag-handle aria-label="Przeciągnij: <?= htmlspecialchars($book['title']) ?>" title="Przeciągnij, aby zmienić kolejność">
            <span aria-hidden="true">⠿</span>
          </button>
          <div class="homepage-book-cover">
            <?php if (!empty($book['cover_image'])): ?>
              <img src="<?= htmlspecialchars($ui::publicAsset($book['cover_image'])) ?>" alt="">
            <?php else: ?>
              <span class="cover-placeholder">100</span>
            <?php endif; ?>
          </div>
          <div class="homepage-book-title">
            <strong><?= htmlspecialchars($book['title']) ?></strong>
            <small><?= htmlspecialchars($book['author'] ?: 'Bez autora') ?></small>
          </div>
          <span class="pill pill--<?= $ui::tone($status) ?>"><?= htmlspecialchars(\Book100\Services\Books\BookSaleState::label($book)) ?></span>
          <input type="hidden" name="catalog_order[<?= (int)$book['id'] ?>]" value="<?= $position + 1 ?>" data-order-input>
          <label class="visibility-switch">
            <input type="checkbox" name="catalog_visible[<?= (int)$book['id'] ?>]" value="1" <?= $visible ? 'checked' : '' ?> <?= !$catalogEligible ? 'disabled' : '' ?>>
            <span><?= $soldOut ? 'Pokaż jako „Brak nakładu”' : ($preorder ? 'Pokaż przedsprzedaż' : ($announced ? 'Pokaż zapowiedź' : ($active ? 'Pokaż na głównej' : 'Najpierw opublikuj książkę'))) ?></span>
          </label>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="panel-section homepage-options">
    <div>
      <p class="section-label">SEKCJE</p>
      <h2>Elementy dodatkowe</h2>
      <p class="muted">Promocje i katalog pozostają najważniejsze.</p>
    </div>
    <label class="visibility-switch">
      <input type="checkbox" name="show_how_it_works" value="1" <?= $showHowItWorks ? 'checked' : '' ?>>
      <span>Pokaż krótką sekcję „Jak kupić” pod książkami</span>
    </label>
  </section>

</form>

<script>
(() => {
  document.querySelectorAll('[data-featured-target-select]').forEach((select) => {
    select.addEventListener('change', () => {
      const preview = document.querySelector(select.dataset.previewTarget || '');
      const placeholder = document.querySelector(select.dataset.placeholderTarget || '');
      if (!(preview instanceof HTMLImageElement)) return;
      const selected = select.options[select.selectedIndex];
      const customImage = select.value === select.dataset.initialValue
        ? (select.dataset.customImage || '')
        : '';
      const image = customImage || selected?.dataset.image || '';
      if (image !== '') {
        preview.src = image;
        preview.hidden = false;
        if (placeholder) placeholder.hidden = true;
      } else {
        preview.removeAttribute('src');
        preview.hidden = true;
        if (placeholder) placeholder.hidden = false;
      }
    });
  });
})();
</script>

<?php include __DIR__ . '/../layout_bottom.php'; ?>
