<?php include __DIR__ . '/../layout_top.php'; ?>
<?php
$isEdit = $mode === 'edit';
$publicBase = \Book100\Core\StoreUrl::base();
$publicUrl = $publicBase . '/' . ($page['slug'] ?? '');
$seoSuffix = trim((string)($adminStorefront['seo_title_suffix'] ?? $adminShopName ?? 'ARKA')) ?: 'ARKA';
?>

<div class="page-heading page-heading--compact">
  <div>
    <a class="back-link" href="/pages">← Strony</a>
    <p class="kicker"><?= $isEdit ? 'EDYCJA STRONY' : 'NOWA STRONA' ?></p>
    <h1><?= $isEdit ? htmlspecialchars($page['title']) : 'Dodaj stronę' ?></h1>
    <p class="muted">Treść, grafiki i SEO zapisują się bez opuszczania formularza.</p>
  </div>
  <?php if ($isEdit && ($page['status'] ?? '') === 'published'): ?><a class="btn secondary" href="<?= htmlspecialchars($publicUrl) ?>" target="_blank" rel="noopener">Zobacz stronę ↗</a><?php endif; ?>
</div>

<?php if (!empty($errors)): ?><div class="error"><strong>Popraw dane:</strong><ul><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<form class="book-editor page-editor editor-form editor-form--with-savebar" method="post" enctype="multipart/form-data" action="<?= $mode === 'create' ? '/pages' : '/pages/'.(int)$page['id'] ?>" data-ajax-success="Strona została zapisana." data-ajax-clear-files>
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">
  <input type="hidden" name="old_wp_id" value="<?= htmlspecialchars((string)($page['old_wp_id'] ?? '')) ?>">

  <div class="editor-savebar">
    <div class="editor-savebar__copy">
      <strong><?= $isEdit ? 'Zapisz zmiany na stronie' : 'Dodaj stronę' ?></strong>
      <span>Treść i SEO zapiszą się bez opuszczania formularza.</span>
    </div>
    <div class="editor-savebar__actions">
      <a class="btn secondary" href="/pages">Anuluj</a>
      <button class="btn" type="submit">Zapisz stronę</button>
    </div>
  </div>

  <div class="book-editor__main">
    <section class="panel-section">
      <div class="section-heading"><div><p class="section-label">PODSTAWOWE</p><h2>Nazwa i treść</h2></div></div>
      <label class="field">Tytuł strony<input id="page-title" name="title" required maxlength="255" value="<?= htmlspecialchars($page['title'] ?? '') ?>"></label>
      <div class="two">
        <label class="field">Autor
          <select name="author_id" required>
            <option value="">Wybierz autora</option>
            <?php foreach (($authors ?? []) as $authorOption): ?>
              <option value="<?= (int)$authorOption['id'] ?>" <?= (int)($page['author_id'] ?? 0) === (int)$authorOption['id'] ? 'selected' : '' ?>><?= htmlspecialchars($authorOption['name']) ?><?= ($authorOption['status'] ?? 'active') === 'hidden' ? ' (ukryty)' : '' ?></option>
            <?php endforeach; ?>
          </select>
          <small>Profil wybierany z kartoteki Autorzy. <a href="/authors/new" target="_blank">Dodaj nowego autora ↗</a></small>
        </label>
        <label class="field">Adres strony (slug)<input id="page-slug" name="slug" value="<?= htmlspecialchars($page['slug'] ?? '') ?>" placeholder="utworzy się z tytułu"></label>
      </div>
      <label class="field">Wprowadzenie <small>Krótki tekst widoczny pod tytułem oraz używany jako domyślny opis SEO.</small><textarea name="excerpt" rows="4"><?= htmlspecialchars($page['excerpt'] ?? '') ?></textarea></label>
      <?php
      $richEditorName = 'content';
      $richEditorHtml = (string)($page['content'] ?? '');
      $richEditorLabel = 'Pełna treść strony';
      $richEditorHelp = 'Dodawaj nagłówki, listy, linki, grafiki i filmy YouTube. Grafika pojawi się od razu po wyborze pliku.';
      $richEditorPlaceholder = 'Zacznij pisać treść strony…';
      $richEditorAria = 'Pełna treść strony';
      $richEditorScope = 'pages';
      include __DIR__ . '/../partials/rich_editor.php';
      ?>
    </section>

    <section class="panel-section seo-editor">
      <div class="section-heading">
        <div><p class="section-label">SEO</p><h2>Wygląd w Google</h2></div>
        <span class="pill pill--success">Sitemap i canonical</span>
      </div>
      <div class="seo-preview">
        <span id="seo-preview-url"><?= htmlspecialchars($publicUrl) ?></span>
        <strong id="seo-preview-title"><?= htmlspecialchars(($page['seo_title'] ?? '') ?: (($page['title'] ?? 'Tytuł strony') . ' — ' . $seoSuffix)) ?></strong>
        <p id="seo-preview-description"><?= htmlspecialchars(($page['seo_description'] ?? '') ?: (($page['excerpt'] ?? '') ?: 'Opis strony wyświetlany w wynikach wyszukiwania.')) ?></p>
      </div>
      <label class="field">Tytuł SEO <small><span id="seo-title-count">0</span>/60 znaków.</small><input id="seo-title" name="seo_title" maxlength="255" value="<?= htmlspecialchars($page['seo_title'] ?? '') ?>"></label>
      <label class="field">Opis SEO <small><span id="seo-description-count">0</span>/160 znaków.</small><textarea id="seo-description" name="seo_description" maxlength="320" rows="4"><?= htmlspecialchars($page['seo_description'] ?? '') ?></textarea></label>
      <label class="field">Adres kanoniczny <small>Zostaw pusty, aby użyć zwykłego adresu strony.</small><input name="canonical_url" type="url" value="<?= htmlspecialchars($page['canonical_url'] ?? '') ?>" placeholder="<?= htmlspecialchars($publicUrl) ?>"></label>
    </section>
  </div>

  <aside class="book-editor__side">
    <section class="panel-section page-image-editor">
      <p class="section-label">OBRAZ WYRÓŻNIAJĄCY</p>
      <div class="page-image-preview">
        <img id="page-image-preview" src="<?= !empty($page['featured_image']) ? htmlspecialchars(\Book100\Core\AdminPresenter::publicAsset($page['featured_image'])) : '' ?>" alt="Podgląd obrazu wyróżniającego" <?= empty($page['featured_image']) ? 'hidden' : '' ?>>
        <span id="page-image-placeholder" <?= !empty($page['featured_image']) ? 'hidden' : '' ?>>Podgląd grafiki</span>
      </div>
      <label
        class="upload-zone"
        for="featured-image-file"
        tabindex="0"
        role="button"
        data-upload-zone
        data-upload-kind="image"
        data-preview-target="#page-image-preview"
        data-placeholder-target="#page-image-placeholder"
        data-max-mb="12"
      >
        <span class="upload-zone__icon" aria-hidden="true">↑</span>
        <strong>Przeciągnij grafikę tutaj</strong>
        <span>albo kliknij i wybierz plik</span>
        <em data-upload-status>JPG, PNG lub WEBP · podgląd i optymalizacja</em>
      </label>
      <input id="featured-image-file" class="visually-hidden-file" name="featured_image_file" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
      <?php if (!empty($page['featured_image']) && $isEdit): ?>
        <button class="asset-remove-button" type="button" data-asset-remove
                data-asset-scope="page" data-asset-name="featured_image" data-asset-id="<?= (int)$page['id'] ?>"
                data-preview-target="#page-image-preview" data-placeholder-target="#page-image-placeholder"
                data-file-input="#featured-image-file">
          <span aria-hidden="true">×</span> Usuń obraz
        </button>
      <?php endif; ?>
    </section>

    <section class="panel-section publish-editor">
      <p class="section-label">PUBLIKACJA</p>
      <label class="field">Formularz na końcu strony
        <select name="registration_form_id">
          <option value="">Bez formularza</option>
          <?php foreach (($forms ?? []) as $formOption): ?>
            <option value="<?= (int)$formOption['id'] ?>" <?= (int)($page['registration_form_id'] ?? 0) === (int)$formOption['id'] ? 'selected' : '' ?>><?= htmlspecialchars($formOption['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <small>Wybrany formularz pojawi się pod treścią strony.</small>
      </label>
      <label class="field">Status<select name="status">
        <option value="draft" <?= ($page['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Szkic</option>
        <option value="published" <?= ($page['status'] ?? '') === 'published' ? 'selected' : '' ?>>Opublikowana</option>
        <option value="hidden" <?= ($page['status'] ?? '') === 'hidden' ? 'selected' : '' ?>>Ukryta</option>
      </select></label>
      <p class="muted">Tylko opublikowana strona jest widoczna dla klientów i trafia do sitemap.xml.</p>
    </section>

  </aside>
</form>

<?php if ($isEdit): ?>
  <details class="danger-zone">
    <summary>Ukrywanie i usuwanie strony</summary>
    <p class="muted">Ukrycie można później cofnąć przez zmianę statusu. Trwałego usunięcia nie można cofnąć.</p>
    <form method="post" action="/pages/<?= (int)$page['id'] ?>/archive" data-ajax-success="Strona została ukryta." data-ajax-refresh onsubmit="return confirm('Ukryć tę stronę?')">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">
      <button class="danger" type="submit">Ukryj stronę</button>
    </form>
    <form method="post" action="/pages/<?= (int)$page['id'] ?>/delete" data-ajax-success="Strona została trwale usunięta." onsubmit="return confirm('Trwale usunąć tę stronę? Tej operacji nie można cofnąć.')">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">
      <button class="danger" type="submit">Usuń stronę na stałe</button>
    </form>
    <small class="muted">Powiązane zgłoszenia pozostaną zapisane w formularzu. Grafiki pozostaną w bibliotece Media.</small>
  </details>
<?php endif; ?>

<script>
(() => {
  const title = document.getElementById('page-title');
  const slug = document.getElementById('page-slug');
  const seoTitle = document.getElementById('seo-title');
  const seoDescription = document.getElementById('seo-description');
  const previewTitle = document.getElementById('seo-preview-title');
  const previewDescription = document.getElementById('seo-preview-description');
  const previewUrl = document.getElementById('seo-preview-url');
  const titleCount = document.getElementById('seo-title-count');
  const descriptionCount = document.getElementById('seo-description-count');
  const baseUrl = <?= json_encode($publicBase . '/', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const seoSuffix = <?= json_encode($seoSuffix, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

  const refreshSeo = () => {
    previewTitle.textContent = seoTitle.value.trim() || ((title.value.trim() || 'Tytuł strony') + ' — ' + seoSuffix);
    previewDescription.textContent = seoDescription.value.trim() || 'Opis strony wyświetlany w wynikach wyszukiwania.';
    previewUrl.textContent = baseUrl + (slug.value.trim() || 'adres-strony');
    titleCount.textContent = seoTitle.value.length;
    descriptionCount.textContent = seoDescription.value.length;
  };
  [title, slug, seoTitle, seoDescription].forEach((input) => input?.addEventListener('input', refreshSeo));
  refreshSeo();
})();
</script>

<?php include __DIR__ . '/../layout_bottom.php'; ?>
