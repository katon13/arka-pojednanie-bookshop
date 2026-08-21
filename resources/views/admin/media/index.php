<?php include __DIR__ . '/../layout_top.php'; ?>
<?php $mediaImages = is_array($images ?? null) ? $images : []; ?>

<div
  class="media-library"
  data-media-browser
  data-media-csrf="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>"
  data-media-selectable="false"
>
  <div class="page-heading media-library__heading">
    <div>
      <p class="section-label">BIBLIOTEKA PLIKÓW</p>
      <h1>Media</h1>
      <p>Jedno miejsce na grafiki używane w opisach książek i na stronach.</p>
    </div>
    <span class="media-library__counter"><strong data-media-count><?= count($mediaImages) ?></strong> grafik</span>
  </div>

  <section class="media-upload-panel">
    <div>
      <p class="section-label">DODAJ GRAFIKI</p>
      <h2>Przeciągnij pliki albo wybierz je z dysku</h2>
      <p>JPG, PNG lub WEBP, maksymalnie 12 MB. Każdy obraz zostanie automatycznie zmniejszony, oczyszczony z metadanych i zapisany jako WEBP.</p>
    </div>
    <label class="media-drop-zone" data-media-drop-zone tabindex="0">
      <input
        type="file"
        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
        multiple
        data-media-file-input
        hidden
      >
      <span class="media-drop-zone__icon" aria-hidden="true">↑</span>
      <strong>Upuść grafiki tutaj</strong>
      <span>albo kliknij i wybierz pliki</span>
    </label>
  </section>

  <div class="media-library__tools">
    <label class="media-search">
      <span>Szukaj grafiki</span>
      <input type="search" data-media-search placeholder="Nazwa pliku lub miejsce użycia…">
    </label>
    <p class="media-library__status" data-media-status aria-live="polite">Biblioteka jest gotowa.</p>
  </div>

  <div class="media-grid" data-media-grid>
    <?php foreach ($mediaImages as $image): ?>
      <article
        class="media-card"
        data-media-card
        data-media-name="<?= htmlspecialchars(mb_strtolower(($image['name'] ?? '') . ' ' . ($image['origin'] ?? ''))) ?>"
      >
        <a class="media-card__visual" href="<?= htmlspecialchars($image['preview_url'] ?? $image['url'] ?? '') ?>" target="_blank" rel="noopener">
          <img src="<?= htmlspecialchars($image['preview_url'] ?? $image['url'] ?? '') ?>" alt="" loading="lazy" decoding="async">
        </a>
        <div class="media-card__body">
          <strong><?= htmlspecialchars($image['name'] ?? 'grafika') ?></strong>
          <span><?= htmlspecialchars($image['origin'] ?? 'Media') ?> · <?= (int)($image['width'] ?? 0) ?>×<?= (int)($image['height'] ?? 0) ?> px</span>
          <div class="media-card__actions">
            <a href="<?= htmlspecialchars($image['preview_url'] ?? $image['url'] ?? '') ?>" target="_blank" rel="noopener">Podgląd</a>
            <button type="button" data-media-copy data-media-url="<?= htmlspecialchars($image['url'] ?? '') ?>">Kopiuj adres</button>
            <button class="media-card__delete" type="button" data-media-delete data-media-url="<?= htmlspecialchars($image['url'] ?? '') ?>">Usuń</button>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

  <div class="media-library__empty" data-media-empty <?= $mediaImages ? 'hidden' : '' ?>>
    <strong>Biblioteka jest pusta.</strong>
    <span>Dodaj pierwszą grafikę w polu powyżej.</span>
  </div>
</div>

<?php include __DIR__ . '/../layout_bottom.php'; ?>
