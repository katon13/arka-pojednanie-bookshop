<?php include __DIR__ . '/../layout_top.php'; ?>
<?php
$isEdit = $mode === 'edit' && !empty($author['id']);
$photo = trim((string)($author['photo'] ?? ''));
?>

<div class="page-heading page-heading--compact">
  <div>
    <p class="kicker"><?= $isEdit ? 'EDYCJA AUTORA' : 'NOWY AUTOR' ?></p>
    <h1><?= htmlspecialchars(($author['name'] ?? '') ?: 'Dodaj autora') ?></h1>
    <p class="muted">Profil pojawi się przed sekcją „O książce” przy każdej przypisanej pozycji.</p>
  </div>
  <a class="btn secondary" href="/authors">Wszyscy autorzy</a>
</div>

<?php if (!empty($errors)): ?><div class="error"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div><?php endif; ?>

<form class="author-editor editor-form editor-form--with-savebar" method="post" enctype="multipart/form-data" action="<?= $isEdit ? '/authors/'.(int)$author['id'] : '/authors' ?>" data-ajax-success="Autor został zapisany." data-ajax-clear-files>
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">
  <input type="hidden" name="photo" value="<?= htmlspecialchars($photo) ?>">

  <div class="editor-savebar">
    <div class="editor-savebar__copy">
      <strong><?= $isEdit ? 'Zapisz profil autora' : 'Dodaj autora' ?></strong>
      <span>Zmiany zapiszą się bez opuszczania formularza.</span>
    </div>
    <div class="editor-savebar__actions">
      <a class="btn secondary" href="/authors">Anuluj</a>
      <button class="btn" type="submit">Zapisz autora</button>
    </div>
  </div>

  <div class="author-editor__main">
    <section class="panel-section">
      <p class="section-label">DANE AUTORA</p>
      <div class="two">
        <label class="field">Imię i nazwisko<input id="author-name" name="name" required maxlength="190" value="<?= htmlspecialchars($author['name'] ?? '') ?>"></label>
        <label class="field">Adres autora (slug)<input id="author-slug" name="slug" maxlength="190" value="<?= htmlspecialchars($author['slug'] ?? '') ?>" placeholder="maciej-karwacki-niecewicz"></label>
      </div>
      <label class="field">Krótka notka <small>Najlepiej 2–4 zdania. Tekst będzie widoczny przy książkach.</small><textarea name="short_bio" rows="8" maxlength="1600" placeholder="Dziennikarz, autor książek…"><?= htmlspecialchars($author['short_bio'] ?? '') ?></textarea></label>
      <label class="field">Link do publikacji <small>Może prowadzić do strony w sklepie, np. /rekolekcje-pojednania, albo do pełnego adresu https://.</small><input name="publications_url" value="<?= htmlspecialchars($author['publications_url'] ?? '') ?>" placeholder="/rekolekcje-pojednania"></label>
    </section>

    <section class="panel-section author-preview-panel">
      <div class="section-heading">
        <div><p class="section-label">PODGLĄD</p><h2>Tak profil wygląda przy książce</h2></div>
      </div>
      <div class="author-card-preview">
        <div class="author-card-preview__photo">
          <span id="author-preview-placeholder" <?= $photo !== '' ? 'hidden' : '' ?>><?= htmlspecialchars(mb_strtoupper(mb_substr((string)(($author['name'] ?? '') ?: 'A'), 0, 1))) ?></span>
          <img id="author-preview-image" src="<?= $photo !== '' ? htmlspecialchars(\Book100\Core\AdminPresenter::publicAsset($photo)) : '' ?>" alt="" <?= $photo === '' ? 'hidden' : '' ?>>
        </div>
        <div>
          <p class="section-label">AUTOR</p>
          <h3><?= htmlspecialchars(($author['name'] ?? '') ?: 'Imię i nazwisko') ?></h3>
          <p><?= htmlspecialchars(($author['short_bio'] ?? '') ?: 'Tutaj pojawi się krótka notka o autorze.') ?></p>
          <span>Zobacz publikacje →</span>
        </div>
      </div>
    </section>
  </div>

  <aside class="author-editor__side">
    <section class="panel-section">
      <p class="section-label">ZDJĘCIE AUTORA</p>
      <div class="author-photo-preview">
        <span id="author-side-preview-placeholder" <?= $photo !== '' ? 'hidden' : '' ?>><?= htmlspecialchars(mb_strtoupper(mb_substr((string)(($author['name'] ?? '') ?: 'A'), 0, 1))) ?></span>
        <img id="author-side-preview-image" src="<?= $photo !== '' ? htmlspecialchars(\Book100\Core\AdminPresenter::publicAsset($photo)) : '' ?>" alt="Podgląd zdjęcia autora" <?= $photo === '' ? 'hidden' : '' ?>>
      </div>
      <label class="upload-zone upload-zone--compact" for="author-photo-file" tabindex="0" role="button" data-upload-zone data-upload-kind="image" data-preview-target="#author-side-preview-image" data-placeholder-target="#author-side-preview-placeholder" data-max-mb="12">
        <span class="upload-zone__icon" aria-hidden="true">↑</span>
        <strong>Przeciągnij zdjęcie tutaj</strong>
        <span>albo kliknij i wybierz plik</span>
        <em data-upload-status>JPG, PNG lub WEBP · automatyczna optymalizacja</em>
      </label>
      <input id="author-photo-file" class="visually-hidden-file" name="author_photo_file" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
      <?php if ($photo !== '' && $isEdit): ?>
        <button class="asset-remove-button" type="button" data-asset-remove
                data-asset-scope="author" data-asset-name="photo" data-asset-id="<?= (int)$author['id'] ?>"
                data-preview-target="#author-side-preview-image,#author-preview-image"
                data-placeholder-target="#author-side-preview-placeholder,#author-preview-placeholder"
                data-file-input="#author-photo-file" data-clear-field="[name='photo']">
          <span aria-hidden="true">×</span> Usuń zdjęcie
        </button>
      <?php endif; ?>
    </section>

    <section class="panel-section">
      <p class="section-label">WIDOCZNOŚĆ</p>
      <label class="field">Status<select name="status">
        <option value="active" <?= ($author['status'] ?? '') === 'active' ? 'selected' : '' ?>>Aktywny</option>
        <option value="hidden" <?= ($author['status'] ?? '') === 'hidden' ? 'selected' : '' ?>>Ukryty</option>
      </select></label>
    </section>

  </aside>
</form>

<?php if ($isEdit && ($author['status'] ?? '') === 'active'): ?>
  <details class="danger-zone danger-zone--soft">
    <summary>Ukryj profil autora</summary>
    <p class="muted">Książki pozostaną w sklepie, ale blok autora nie będzie przy nich wyświetlany.</p>
    <form method="post" action="/authors/<?= (int)$author['id'] ?>/archive" data-ajax-success="Profil autora został ukryty." data-ajax-refresh>
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">
      <button class="btn secondary" type="submit">Ukryj profil</button>
    </form>
  </details>
<?php endif; ?>

<script>
(() => {
  const name = document.getElementById('author-name');
  const slug = document.getElementById('author-slug');
  let touched = Boolean(slug?.value);
  slug?.addEventListener('input', () => { touched = true; });
  name?.addEventListener('input', () => {
    if (touched || !slug) return;
    slug.value = name.value.toLocaleLowerCase('pl')
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .replace(/ł/g, 'l').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  });
  const file = document.getElementById('author-photo-file');
  const mainPreview = document.getElementById('author-preview-image');
  const mainPlaceholder = document.getElementById('author-preview-placeholder');
  file?.addEventListener('change', () => {
    const selected = file.files?.[0];
    if (!selected || !mainPreview) return;
    mainPreview.src = URL.createObjectURL(selected);
    mainPreview.hidden = false;
    if (mainPlaceholder) mainPlaceholder.hidden = true;
  });
})();
</script>

<?php include __DIR__ . '/../layout_bottom.php'; ?>
