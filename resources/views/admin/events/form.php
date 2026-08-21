<?php include __DIR__ . '/../layout_top.php'; ?>
<?php
$isEdit = $mode === 'edit';
$publicUrl = \Book100\Core\StoreUrl::to('/wydarzenia/' . ($event['slug'] ?? ''));
$dateValue = static fn(mixed $value): string => !empty($value) ? date('Y-m-d\TH:i', strtotime((string)$value)) : '';
?>

<div class="page-heading page-heading--compact">
  <div>
    <a class="back-link" href="/events">← Wydarzenia</a>
    <p class="kicker"><?= $isEdit ? 'EDYCJA WYDARZENIA' : 'NOWE WYDARZENIE' ?></p>
    <h1><?= $isEdit ? htmlspecialchars($event['title']) : 'Dodaj wydarzenie' ?></h1>
    <p class="muted">Autor, krótki anons, termin, miejsce i formularz zgłoszeń.</p>
  </div>
  <?php if ($isEdit && in_array(($event['status'] ?? ''), ['published', 'archived'], true)): ?><a class="btn secondary" href="<?= htmlspecialchars($publicUrl) ?>" target="_blank" rel="noopener">Zobacz wydarzenie ↗</a><?php endif; ?>
</div>

<?php if (!empty($errors)): ?><div class="error"><strong>Popraw dane:</strong><ul><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<form class="book-editor event-editor editor-form editor-form--with-savebar" method="post" enctype="multipart/form-data" action="<?= $isEdit ? '/events/' . (int)$event['id'] : '/events' ?>" data-ajax-success="Wydarzenie zostało zapisane." data-ajax-clear-files>
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">
  <div class="editor-savebar">
    <div class="editor-savebar__copy"><strong>Zapisz wydarzenie</strong><span>Anons i lista zgłoszeń pozostaną w jednym miejscu.</span></div>
    <button class="btn" type="submit">Zapisz</button>
  </div>

  <div class="book-editor__main">
    <section class="panel-section">
      <div class="section-heading"><div><p class="section-label">ANONS</p><h2>Nazwa i opis</h2></div></div>
      <label class="field">Nazwa wydarzenia<input id="event-title" name="title" required maxlength="255" value="<?= htmlspecialchars($event['title'] ?? '') ?>"></label>
      <div class="two">
        <label class="field">Autor
          <select name="author_id" required>
            <option value="">Wybierz autora</option>
            <?php foreach ($authors as $author): ?><option value="<?= (int)$author['id'] ?>" <?= (int)($event['author_id'] ?? 0) === (int)$author['id'] ? 'selected' : '' ?>><?= htmlspecialchars($author['name']) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label class="field">Adres wydarzenia (slug)<input name="slug" value="<?= htmlspecialchars($event['slug'] ?? '') ?>" placeholder="utworzy się z nazwy"></label>
      </div>
      <label class="field">Krótki opis <small>Widoczny na liście wydarzeń i w promocji.</small><textarea name="excerpt" rows="4" maxlength="900" required><?= htmlspecialchars($event['excerpt'] ?? '') ?></textarea></label>
      <?php
      $richEditorName = 'content';
      $richEditorHtml = (string)($event['content'] ?? '');
      $richEditorLabel = 'Pełny opis wydarzenia';
      $richEditorHelp = 'Najważniejsze informacje dla uczestnika.';
      $richEditorPlaceholder = 'Opisz wydarzenie…';
      $richEditorScope = 'events';
      include __DIR__ . '/../partials/rich_editor.php';
      ?>
    </section>
  </div>

  <aside class="book-editor__side">
    <section class="panel-section">
      <p class="section-label">TERMIN</p>
      <label class="field">Początek<input type="datetime-local" name="starts_at" required value="<?= htmlspecialchars($dateValue($event['starts_at'] ?? '')) ?>"></label>
      <label class="field">Zakończenie<input type="datetime-local" name="ends_at" value="<?= htmlspecialchars($dateValue($event['ends_at'] ?? '')) ?>"></label>
      <label class="field">Miejsce<input name="location" maxlength="255" value="<?= htmlspecialchars($event['location'] ?? '') ?>"></label>
      <label class="field">Organizator<input name="organizer" maxlength="190" value="<?= htmlspecialchars($event['organizer'] ?? 'Wydawnictwo Katolickie ARKA') ?>"></label>
    </section>
    <section class="panel-section">
      <p class="section-label">ZGŁOSZENIA</p>
      <label class="field">Formularz<select name="registration_form_id"><option value="">Bez formularza</option><?php foreach ($forms as $formOption): ?><option value="<?= (int)$formOption['id'] ?>" <?= (int)($event['registration_form_id'] ?? 0) === (int)$formOption['id'] ? 'selected' : '' ?>><?= htmlspecialchars($formOption['name']) ?></option><?php endforeach; ?></select></label>
      <small class="muted">Formularz pojawi się na końcu wydarzenia.</small>
    </section>
    <section class="panel-section page-image-editor">
      <p class="section-label">GRAFIKA</p>
      <div class="page-image-preview">
        <img id="event-image-preview" src="<?= !empty($event['featured_image']) ? htmlspecialchars(\Book100\Core\AdminPresenter::publicAsset($event['featured_image'])) : '' ?>" alt="" <?= empty($event['featured_image']) ? 'hidden' : '' ?>>
        <span id="event-image-placeholder" <?= !empty($event['featured_image']) ? 'hidden' : '' ?>>Podgląd grafiki</span>
      </div>
      <label class="upload-zone upload-zone--compact" for="event-image-file" tabindex="0" role="button" data-upload-zone data-upload-kind="image" data-preview-target="#event-image-preview" data-placeholder-target="#event-image-placeholder" data-max-mb="12">
        <span class="upload-zone__icon" aria-hidden="true">↑</span><strong>Wybierz grafikę</strong><span>JPG, PNG lub WEBP</span><em data-upload-status>Podgląd od razu</em>
      </label>
      <input id="event-image-file" class="visually-hidden-file" name="featured_image_file" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
      <?php if (!empty($event['featured_image']) && $isEdit): ?>
        <button class="asset-remove-button" type="button" data-asset-remove
                data-asset-scope="event" data-asset-name="featured_image" data-asset-id="<?= (int)$event['id'] ?>"
                data-preview-target="#event-image-preview" data-placeholder-target="#event-image-placeholder"
                data-file-input="#event-image-file">
          <span aria-hidden="true">×</span> Usuń grafikę
        </button>
      <?php endif; ?>
    </section>
    <section class="panel-section">
      <p class="section-label">PUBLIKACJA</p>
      <label class="field">Status<select name="status"><option value="draft" <?= ($event['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Szkic</option><option value="published" <?= ($event['status'] ?? '') === 'published' ? 'selected' : '' ?>>Opublikowane</option><option value="archived" <?= ($event['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Archiwum</option></select></label>
    </section>
  </aside>
</form>

<?php if ($isEdit): ?>
  <section class="panel-section registrations-panel">
    <div class="section-heading"><div><p class="section-label">UCZESTNICY</p><h2>Osoby zgłoszone</h2></div><span class="pill pill--neutral"><?= count($registrations ?? []) ?></span></div>
    <details class="manual-registration">
      <summary>Dodaj osobę ręcznie</summary>
      <form method="post" action="/events/<?= (int)$event['id'] ?>/registrations" class="settings-grid" data-ajax-success="Osoba została dodana." data-ajax-refresh>
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>">
        <label class="field">Imię<input name="first_name"></label>
        <label class="field">Nazwisko<input name="last_name"></label>
        <label class="field">E-mail<input type="email" name="email"></label>
        <label class="field">Telefon<input name="phone"></label>
        <label class="field">Notatka<input name="admin_note"></label>
        <button class="btn small" type="submit">Dodaj osobę</button>
      </form>
    </details>
    <?php include __DIR__ . '/../partials/registrations_table.php'; ?>
  </section>
  <?php if (($event['status'] ?? '') !== 'archived'): ?>
    <details class="danger-zone"><summary>Archiwizacja i usuwanie</summary><p class="muted">Archiwum zachowuje publiczny anons. Trwałego usunięcia nie można cofnąć.</p><form method="post" action="/events/<?= (int)$event['id'] ?>/archive" data-ajax-success="Wydarzenie przeniesiono do archiwum." data-ajax-refresh onsubmit="return confirm('Przenieść wydarzenie do archiwum?')"><input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>"><button class="danger" type="submit">Przenieś do archiwum</button></form><form method="post" action="/events/<?= (int)$event['id'] ?>/delete" data-ajax-success="Wydarzenie zostało trwale usunięte." onsubmit="return confirm('Trwale usunąć to wydarzenie? Tej operacji nie można cofnąć.')"><input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>"><button class="danger" type="submit">Usuń wydarzenie na stałe</button></form><small class="muted">Lista zgłoszeń pozostanie zapisana w formularzu. Grafika pozostanie w bibliotece Media.</small></details>
  <?php else: ?>
    <details class="danger-zone"><summary>Trwałe usunięcie</summary><p class="muted">Usunięcia nie można cofnąć. Lista zgłoszeń pozostanie zapisana w formularzu.</p><form method="post" action="/events/<?= (int)$event['id'] ?>/delete" data-ajax-success="Wydarzenie zostało trwale usunięte." onsubmit="return confirm('Trwale usunąć to wydarzenie? Tej operacji nie można cofnąć.')"><input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Book100\Core\Csrf::token()) ?>"><button class="danger" type="submit">Usuń wydarzenie na stałe</button></form><small class="muted">Grafika pozostanie w bibliotece Media.</small></details>
  <?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/../layout_bottom.php'; ?>
