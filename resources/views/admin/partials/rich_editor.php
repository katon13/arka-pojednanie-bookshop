<?php
$richEditorName = (string)($richEditorName ?? 'content');
$richEditorHtml = \Book100\Core\ContentFormatter::richHtml((string)($richEditorHtml ?? ''));
$richEditorLabel = (string)($richEditorLabel ?? 'Treść');
$richEditorHelp = (string)($richEditorHelp ?? 'Zaznacz tekst i wybierz formatowanie.');
$richEditorPlaceholder = (string)($richEditorPlaceholder ?? 'Zacznij pisać…');
$richEditorAria = (string)($richEditorAria ?? $richEditorLabel);
$richEditorScope = in_array(($richEditorScope ?? ''), ['pages', 'events'], true)
    ? (string)$richEditorScope
    : 'books';
?>
<div class="field">
  <span><?= htmlspecialchars($richEditorLabel) ?></span>
  <small><?= htmlspecialchars($richEditorHelp) ?></small>
  <div
    class="rich-editor"
    data-rich-editor
    data-rich-upload-url="/media/upload"
    data-rich-media-scope="<?= htmlspecialchars($richEditorScope) ?>"
    data-rich-drop-label="Upuść grafikę w tym miejscu"
  >
    <div class="rich-editor__toolbar" role="toolbar" aria-label="Formatowanie treści">
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
      aria-label="<?= htmlspecialchars($richEditorAria) ?>"
      data-rich-surface
      data-placeholder="<?= htmlspecialchars($richEditorPlaceholder) ?>"
    ><?= $richEditorHtml ?></div>
    <textarea name="<?= htmlspecialchars($richEditorName) ?>" data-rich-input hidden><?= htmlspecialchars($richEditorHtml) ?></textarea>
  </div>
</div>
