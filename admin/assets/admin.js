(() => {
  const stack = document.querySelector('[data-toast-stack]');
  if (!stack) return;

  const adminBase = (document.body?.dataset.adminBase || '').replace(/\/+$/, '');
  const adminUrl = (path) => {
    if (!path || !path.startsWith('/') || path.startsWith('//')) return path;
    if (!adminBase || path === adminBase || path.startsWith(`${adminBase}/`)) return path;
    return `${adminBase}${path}`;
  };

  const storageKey = 'book100-admin-toast';
  const activeForms = new WeakSet();

  const showToast = (title, message, tone = 'success') => {
    const toast = document.createElement('div');
    toast.className = `admin-toast admin-toast--${tone}`;
    toast.setAttribute('role', tone === 'error' ? 'alert' : 'status');

    const content = document.createElement('div');
    const heading = document.createElement('strong');
    const body = document.createElement('span');
    heading.textContent = title || (tone === 'error' ? 'Nie zapisano' : 'Zapisano');
    body.textContent = message || '';
    content.append(heading, body);

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'admin-toast__close';
    close.setAttribute('aria-label', 'Zamknij komunikat');
    close.textContent = '×';
    close.addEventListener('click', () => toast.remove());

    toast.append(content, close);
    stack.append(toast);
    window.setTimeout(() => {
      toast.classList.add('is-leaving');
      window.setTimeout(() => toast.remove(), 220);
    }, tone === 'error' ? 8000 : 4500);
  };

  const rememberToast = (payload) => {
    try {
      sessionStorage.setItem(storageKey, JSON.stringify(payload));
    } catch (_) {
      // Brak sessionStorage nie może blokować operacji administratora.
    }
  };

  try {
    const saved = sessionStorage.getItem(storageKey);
    if (saved) {
      sessionStorage.removeItem(storageKey);
      const payload = JSON.parse(saved);
      showToast(payload.title, payload.message, payload.tone);
    }
  } catch (_) {
    // Uszkodzony wpis jest bezpiecznie pomijany.
  }

  const formatBytes = (bytes) => {
    if (!Number.isFinite(Number(bytes)) || Number(bytes) <= 0) return '—';
    if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1).replace('.', ',')} MB`;
  };

  document.querySelectorAll('[data-status-select]').forEach((select) => {
    const wrapper = select.closest('[data-status-tone]');
    if (!(select instanceof HTMLSelectElement) || !(wrapper instanceof HTMLElement)) return;

    const syncTone = () => {
      const option = select.options[select.selectedIndex];
      wrapper.dataset.statusTone = option?.dataset.tone || 'neutral';
    };

    select.addEventListener('change', syncTone);
    syncTone();
  });

  document.querySelectorAll('[data-color-input]').forEach((input) => {
    if (!(input instanceof HTMLInputElement)) return;
    const value = input.closest('.color-setting')?.querySelector('[data-color-value]');
    const syncColor = () => {
      if (value) value.textContent = input.value.toLowerCase();
    };
    input.addEventListener('input', syncColor);
    syncColor();
  });

  document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-asset-remove]');
    if (!(button instanceof HTMLButtonElement)) return;
    event.preventDefault();
    if (button.disabled) return;

    const form = button.closest('form');
    const csrf = form?.querySelector('input[name="_csrf"]')?.value
      || document.querySelector('input[name="_csrf"]')?.value
      || '';
    const originalHtml = button.innerHTML;
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    button.innerHTML = '<span aria-hidden="true">…</span> Usuwam';

    try {
      const data = new FormData();
      data.append('_csrf', csrf);
      data.append('scope', button.dataset.assetScope || '');
      data.append('asset', button.dataset.assetName || '');
      data.append('id', button.dataset.assetId || '0');

      const response = await fetch(adminUrl('/assets/remove'), {
        method: 'POST',
        body: data,
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
        },
        credentials: 'same-origin',
      });
      const contentType = response.headers.get('content-type') || '';
      const payload = contentType.includes('application/json')
        ? await response.json()
        : { ok: false, title: 'Nie usunięto', message: await response.text() };
      if (!response.ok || payload.ok === false) {
        throw Object.assign(
          new Error(payload.message || 'Nie udało się usunąć pliku.'),
          { title: payload.title }
        );
      }

      let fallbackSrc = button.dataset.fallbackSrc || '';
      const fallbackSelect = button.dataset.fallbackSelect
        ? document.querySelector(button.dataset.fallbackSelect)
        : null;
      if (fallbackSelect instanceof HTMLSelectElement) {
        fallbackSelect.dataset.customImage = '';
        fallbackSrc = fallbackSelect.options[fallbackSelect.selectedIndex]?.dataset.image || '';
      }

      if (button.dataset.previewTarget) {
        document.querySelectorAll(button.dataset.previewTarget).forEach((preview) => {
          if (!(preview instanceof HTMLImageElement)) return;
          if (fallbackSrc) {
            preview.src = fallbackSrc;
            preview.hidden = false;
          } else {
            preview.removeAttribute('src');
            preview.hidden = true;
          }
        });
      }
      if (button.dataset.placeholderTarget) {
        document.querySelectorAll(button.dataset.placeholderTarget).forEach((placeholder) => {
          if (placeholder instanceof HTMLElement) placeholder.hidden = Boolean(fallbackSrc);
        });
      }

      const fileInput = button.dataset.fileInput
        ? document.querySelector(button.dataset.fileInput)
        : null;
      if (fileInput instanceof HTMLInputElement) {
        fileInput.value = '';
        fileInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
      const clearField = button.dataset.clearField
        ? document.querySelector(button.dataset.clearField)
        : null;
      if (clearField instanceof HTMLInputElement) clearField.value = '';

      const emptyTarget = button.dataset.emptyTarget
        ? document.querySelector(button.dataset.emptyTarget)
        : null;
      if (emptyTarget instanceof HTMLElement) emptyTarget.hidden = false;

      const current = button.closest('[data-asset-current]');
      if (current) current.remove();
      else button.remove();

      showToast(payload.title || 'Usunięto', payload.message || 'Plik został usunięty.', 'success');
    } catch (error) {
      button.disabled = false;
      button.removeAttribute('aria-busy');
      button.innerHTML = originalHtml;
      showToast(
        error.title || 'Nie usunięto',
        error.message || 'Nie udało się połączyć z serwerem.',
        'error'
      );
    }
  });

  const matchesAccept = (file, input) => {
    const accepted = (input.accept || '').split(',').map((value) => value.trim().toLowerCase()).filter(Boolean);
    if (!accepted.length) return true;
    const name = file.name.toLowerCase();
    const type = (file.type || '').toLowerCase();
    return accepted.some((rule) => {
      if (rule.startsWith('.')) return name.endsWith(rule);
      if (rule.endsWith('/*')) return type.startsWith(rule.slice(0, -1));
      return type === rule;
    });
  };

  document.querySelectorAll('[data-upload-zone]').forEach((zone) => {
    const inputId = zone.getAttribute('for');
    const input = inputId ? document.getElementById(inputId) : null;
    const status = zone.querySelector('[data-upload-status]');
    if (!(input instanceof HTMLInputElement) || input.type !== 'file') return;

    const initialStatus = status?.textContent || '';
    let previewUrl = '';

    const setStatus = (message, tone = '') => {
      if (status) status.textContent = message;
      zone.classList.toggle('has-file', tone === 'success');
      zone.classList.toggle('is-invalid', tone === 'error');
    };

    const showPreview = (file) => {
      if (zone.dataset.uploadKind !== 'image') return;
      const preview = zone.dataset.previewTarget
        ? document.querySelector(zone.dataset.previewTarget)
        : null;
      const placeholder = zone.dataset.placeholderTarget
        ? document.querySelector(zone.dataset.placeholderTarget)
        : null;
      if (!(preview instanceof HTMLImageElement)) return;
      if (previewUrl) URL.revokeObjectURL(previewUrl);
      previewUrl = URL.createObjectURL(file);
      preview.src = previewUrl;
      preview.hidden = false;
      if (placeholder instanceof HTMLElement) placeholder.hidden = true;

      preview.addEventListener('load', () => {
        setStatus(
          `${file.name} · ${preview.naturalWidth}×${preview.naturalHeight} px · ${formatBytes(file.size)} · zostanie zapisany jako WEBP`,
          'success'
        );
      }, { once: true });
    };

    const validateAndPresent = () => {
      const file = input.files?.[0];
      if (!file) {
        setStatus(initialStatus);
        return;
      }
      const maxBytes = Math.max(1, Number(zone.dataset.maxMb || 10)) * 1024 * 1024;
      if (file.size > maxBytes) {
        input.value = '';
        setStatus(`Plik jest za duży. Maksymalny rozmiar: ${zone.dataset.maxMb || 10} MB.`, 'error');
        return;
      }
      if (!matchesAccept(file, input)) {
        input.value = '';
        setStatus('Nieobsługiwany format pliku.', 'error');
        return;
      }
      setStatus(`${file.name} · ${formatBytes(file.size)}`, 'success');
      showPreview(file);
    };

    input.addEventListener('change', validateAndPresent);
    zone.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter' && event.key !== ' ') return;
      event.preventDefault();
      input.click();
    });
    zone.addEventListener('dragover', (event) => {
      event.preventDefault();
      event.dataTransfer.dropEffect = 'copy';
      zone.classList.add('is-dragover');
    });
    zone.addEventListener('dragleave', (event) => {
      if (!zone.contains(event.relatedTarget)) zone.classList.remove('is-dragover');
    });
    zone.addEventListener('drop', (event) => {
      event.preventDefault();
      zone.classList.remove('is-dragover');
      const file = event.dataTransfer.files?.[0];
      if (!file) return;
      const transfer = new DataTransfer();
      transfer.items.add(file);
      input.files = transfer.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
    });
  });

  let mediaLibraryCache = null;
  let mediaPreviewDialog = null;

  const openMediaPreview = (image) => {
    if (!mediaPreviewDialog) {
      const dialog = document.createElement('dialog');
      dialog.className = 'media-preview';
      dialog.innerHTML = `
        <div class="media-preview__bar">
          <div><p class="section-label">PODGLĄD</p><h2 data-media-preview-title>Grafika</h2><p data-media-preview-meta></p></div>
          <button type="button" class="media-picker__close" data-media-preview-close aria-label="Zamknij">×</button>
        </div>
        <div class="media-preview__canvas"><img data-media-preview-image alt=""></div>
        <div class="media-preview__footer">
          <span data-media-preview-url></span>
          <a class="btn btn--secondary" data-media-preview-open target="_blank" rel="noopener">Otwórz oryginał</a>
        </div>
      `;
      document.body.append(dialog);
      dialog.querySelector('[data-media-preview-close]')?.addEventListener('click', () => dialog.close());
      dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
      });
      mediaPreviewDialog = dialog;
    }

    const previewUrl = image.preview_url || image.url;
    const previewImage = mediaPreviewDialog.querySelector('[data-media-preview-image]');
    const previewTitle = mediaPreviewDialog.querySelector('[data-media-preview-title]');
    const previewMeta = mediaPreviewDialog.querySelector('[data-media-preview-meta]');
    const previewPath = mediaPreviewDialog.querySelector('[data-media-preview-url]');
    const previewOpen = mediaPreviewDialog.querySelector('[data-media-preview-open]');
    if (previewImage instanceof HTMLImageElement) previewImage.src = previewUrl;
    if (previewTitle) previewTitle.textContent = image.name || 'Grafika';
    if (previewMeta) previewMeta.textContent = `${image.origin || 'Media'} · ${Number(image.width) || 0}×${Number(image.height) || 0} px · ${formatBytes(Number(image.bytes) || 0)}`;
    if (previewPath) previewPath.textContent = image.url || '';
    if (previewOpen instanceof HTMLAnchorElement) previewOpen.href = previewUrl;
    mediaPreviewDialog.dataset.mediaUrl = image.url || '';
    mediaPreviewDialog.showModal();
  };

  const mediaCard = (image, selectable, onSelect) => {
    const card = document.createElement('article');
    card.className = `media-card${selectable ? ' media-card--selectable' : ''}`;
    card.dataset.mediaCard = '';
    card.dataset.mediaName = `${image.name || ''} ${image.origin || ''}`.toLocaleLowerCase('pl');

    const visual = document.createElement('button');
    visual.className = 'media-card__visual';
    visual.type = 'button';
    if (selectable) {
      visual.setAttribute('aria-label', `Wstaw grafikę ${image.name || ''}`);
      visual.addEventListener('click', () => onSelect?.(image));
    } else {
      visual.setAttribute('aria-label', `Pokaż grafikę ${image.name || ''}`);
      visual.addEventListener('click', () => openMediaPreview(image));
    }
    const thumbnail = document.createElement('img');
    thumbnail.src = image.preview_url || image.url;
    thumbnail.alt = '';
    thumbnail.loading = 'lazy';
    thumbnail.decoding = 'async';
    visual.append(thumbnail);

    const body = document.createElement('div');
    body.className = 'media-card__body';
    const name = document.createElement('strong');
    name.textContent = image.name || 'grafika';
    const meta = document.createElement('span');
    meta.textContent = `${image.origin || 'Media'} · ${Number(image.width) || 0}×${Number(image.height) || 0} px`;
    const actions = document.createElement('div');
    actions.className = 'media-card__actions';
    if (selectable) {
      const action = document.createElement('button');
      action.type = 'button';
      action.textContent = 'Wstaw do tekstu';
      action.addEventListener('click', () => onSelect?.(image));
      actions.append(action);
    } else {
      const preview = document.createElement('button');
      const copy = document.createElement('button');
      const remove = document.createElement('button');
      preview.type = copy.type = remove.type = 'button';
      preview.textContent = 'Podgląd';
      preview.addEventListener('click', () => openMediaPreview(image));
      copy.textContent = 'Kopiuj adres';
      copy.dataset.mediaCopy = '';
      copy.dataset.mediaUrl = image.url;
      remove.textContent = 'Usuń';
      remove.className = 'media-card__delete';
      remove.dataset.mediaDelete = '';
      remove.dataset.mediaUrl = image.url;
      actions.append(preview, copy, remove);
    }
    body.append(name, meta, actions);
    card.append(visual, body);
    return card;
  };

  const initMediaBrowser = (root, options = {}) => {
    if (!(root instanceof HTMLElement) || root.dataset.mediaReady === 'true') return root._mediaBrowser || null;
    root.dataset.mediaReady = 'true';

    const grid = root.querySelector('[data-media-grid]');
    const input = root.querySelector('[data-media-file-input]');
    const dropZone = root.querySelector('[data-media-drop-zone]');
    const search = root.querySelector('[data-media-search]');
    const status = root.querySelector('[data-media-status]');
    const empty = root.querySelector('[data-media-empty]');
    const count = root.querySelector('[data-media-count]');
    const selectable = options.selectable ?? root.dataset.mediaSelectable === 'true';
    let images = [];
    let onSelect = options.onSelect || null;
    let csrf = options.csrf || root.dataset.mediaCsrf || '';

    const setStatus = (message, tone = '') => {
      if (!status) return;
      status.textContent = message;
      status.classList.toggle('is-error', tone === 'error');
      status.classList.toggle('is-working', tone === 'working');
    };

    const render = () => {
      if (!(grid instanceof HTMLElement)) return;
      const query = (search?.value || '').trim().toLocaleLowerCase('pl');
      const filtered = images.filter((image) => {
        if (!query) return true;
        return `${image.name || ''} ${image.origin || ''}`.toLocaleLowerCase('pl').includes(query);
      });
      grid.replaceChildren(...filtered.map((image) => mediaCard(image, selectable, onSelect)));
      if (empty instanceof HTMLElement) empty.hidden = filtered.length > 0;
      if (count instanceof HTMLElement) count.textContent = String(images.length);
    };

    const load = async (force = false) => {
      setStatus('Wczytuję bibliotekę…', 'working');
      try {
        if (!mediaLibraryCache || force) {
          const response = await fetch(adminUrl('/media/library'), {
            headers: {'Accept': 'application/json'},
            credentials: 'same-origin',
          });
          const payload = await response.json();
          if (!response.ok || payload.ok === false || !Array.isArray(payload.images)) {
            throw new Error(payload.message || 'Nie udało się pobrać biblioteki.');
          }
          mediaLibraryCache = payload.images;
        }
        images = [...mediaLibraryCache];
        render();
        setStatus(images.length ? `Dostępne grafiki: ${images.length}.` : 'Biblioteka jest pusta.');
      } catch (error) {
        setStatus(error.message || 'Nie udało się pobrać biblioteki.', 'error');
      }
    };

    const temporaryPreview = (file) => {
      if (!(grid instanceof HTMLElement)) return {remove: () => {}};
      const url = URL.createObjectURL(file);
      const card = document.createElement('article');
      card.className = 'media-card media-card--uploading';
      const visual = document.createElement('span');
      visual.className = 'media-card__visual';
      const image = document.createElement('img');
      image.src = url;
      image.alt = '';
      visual.append(image);
      const body = document.createElement('div');
      body.className = 'media-card__body';
      const name = document.createElement('strong');
      name.textContent = file.name;
      const meta = document.createElement('span');
      meta.textContent = 'Optymalizuję i zapisuję…';
      body.append(name, meta);
      card.append(visual, body);
      grid.prepend(card);
      return {
        remove: () => {
          URL.revokeObjectURL(url);
          card.remove();
        },
      };
    };

    const uploadFiles = async (fileList) => {
      const files = [...(fileList || [])];
      if (!files.length) return;
      let completed = 0;
      for (const file of files) {
        if (!(input instanceof HTMLInputElement) || !matchesAccept(file, input)) {
          setStatus(`${file.name}: obsługiwane są tylko JPG, PNG i WEBP.`, 'error');
          continue;
        }
        if (file.size > 12 * 1024 * 1024) {
          setStatus(`${file.name}: plik przekracza 12 MB.`, 'error');
          continue;
        }

        const preview = temporaryPreview(file);
        setStatus(`Optymalizuję ${file.name}…`, 'working');
        try {
          const data = new FormData();
          data.append('_csrf', csrf);
          data.append('media_image', file);
          const response = await fetch(adminUrl('/media/upload'), {
            method: 'POST',
            body: data,
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'application/json',
            },
            credentials: 'same-origin',
          });
          const payload = await response.json();
          if (!response.ok || payload.ok === false || !payload.image?.url) {
            throw new Error(payload.message || 'Nie udało się zapisać grafiki.');
          }
          completed += 1;
          mediaLibraryCache = [payload.image, ...(mediaLibraryCache || []).filter((item) => item.url !== payload.image.url)];
          images = [...mediaLibraryCache];
          render();
          setStatus(`Zapisano ${completed} z ${files.length}: ${file.name}.`);
          showToast('Grafika zapisana', 'Obraz jest zoptymalizowany i dostępny w bibliotece Media.', 'success');
        } catch (error) {
          setStatus(`${file.name}: ${error.message || 'nie udało się wgrać pliku.'}`, 'error');
          showToast('Nie wgrano grafiki', error.message || 'Nie udało się połączyć z serwerem.', 'error');
        } finally {
          preview.remove();
        }
      }
      if (input instanceof HTMLInputElement) input.value = '';
    };

    input?.addEventListener('change', () => uploadFiles(input.files));
    search?.addEventListener('input', render);
    dropZone?.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter' && event.key !== ' ') return;
      event.preventDefault();
      input?.click();
    });
    dropZone?.addEventListener('dragover', (event) => {
      event.preventDefault();
      event.dataTransfer.dropEffect = 'copy';
      dropZone.classList.add('is-dragover');
    });
    dropZone?.addEventListener('dragleave', (event) => {
      if (!dropZone.contains(event.relatedTarget)) dropZone.classList.remove('is-dragover');
    });
    dropZone?.addEventListener('drop', (event) => {
      event.preventDefault();
      dropZone.classList.remove('is-dragover');
      uploadFiles(event.dataTransfer?.files);
    });
    root.addEventListener('click', async (event) => {
      const copy = event.target.closest('[data-media-copy]');
      if (copy) {
        const url = copy.dataset.mediaUrl || '';
        try {
          await navigator.clipboard.writeText(new URL(url, window.location.origin).href);
          showToast('Adres skopiowany', 'Możesz wkleić go w dowolnym miejscu.', 'success');
        } catch (_) {
          window.prompt('Skopiuj adres grafiki:', new URL(url, window.location.origin).href);
        }
        return;
      }

      const remove = event.target.closest('[data-media-delete]');
      if (!remove) return;
      const url = remove.dataset.mediaUrl || '';
      remove.disabled = true;
      setStatus('Usuwam grafikę…', 'working');
      try {
        const data = new FormData();
        data.append('_csrf', csrf);
        data.append('url', url);
        const response = await fetch(adminUrl('/media/delete'), {
          method: 'POST',
          body: data,
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
          },
          credentials: 'same-origin',
        });
        const payload = await response.json();
        if (!response.ok || payload.ok === false) {
          throw Object.assign(new Error(payload.message || 'Nie udało się usunąć grafiki.'), {title: payload.title});
        }
        mediaLibraryCache = (mediaLibraryCache || []).filter((image) => image.url !== url);
        images = images.filter((image) => image.url !== url);
        if (mediaPreviewDialog?.open && mediaPreviewDialog.dataset.mediaUrl === url) mediaPreviewDialog.close();
        render();
        setStatus(`Grafika została usunięta. Pozostało: ${images.length}.`);
        showToast('Grafika usunięta', 'Plik zniknął z biblioteki Media.', 'success');
      } catch (error) {
        remove.disabled = false;
        setStatus(error.message || 'Nie udało się usunąć grafiki.', 'error');
        showToast(error.title || 'Nie usunięto grafiki', error.message || 'Nie udało się wykonać operacji.', 'error');
      }
    });

    const api = {
      load,
      setOnSelect(handler) {
        onSelect = handler;
        render();
      },
      setCsrf(token) {
        csrf = token || csrf;
      },
    };
    root._mediaBrowser = api;
    load();
    return api;
  };

  document.querySelectorAll('[data-media-browser]').forEach((browser) => initMediaBrowser(browser));

  let mediaPicker = null;
  const openMediaPicker = (csrf, onSelect) => {
    if (!mediaPicker) {
      const dialog = document.createElement('dialog');
      dialog.className = 'media-picker';
      dialog.dataset.mediaBrowser = '';
      dialog.dataset.mediaSelectable = 'true';
      dialog.innerHTML = `
        <div class="media-picker__header">
          <div><p class="section-label">BIBLIOTEKA MEDIA</p><h2>Wstaw grafikę</h2><p>Wybierz istniejącą grafikę albo dodaj nową.</p></div>
          <button type="button" class="media-picker__close" data-media-picker-close aria-label="Zamknij">×</button>
        </div>
        <div class="media-picker__upload">
          <label class="media-drop-zone media-drop-zone--compact" data-media-drop-zone tabindex="0">
            <input type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple data-media-file-input hidden>
            <span class="media-drop-zone__icon" aria-hidden="true">↑</span>
            <strong>Dodaj nowe grafiki</strong>
            <span>Przeciągnij albo wybierz z dysku</span>
          </label>
          <label class="media-search"><span>Szukaj</span><input type="search" data-media-search placeholder="Wpisz nazwę…"></label>
        </div>
        <p class="media-library__status" data-media-status aria-live="polite">Wczytuję bibliotekę…</p>
        <div class="media-grid media-grid--picker" data-media-grid></div>
        <div class="media-library__empty" data-media-empty hidden><strong>Brak grafik.</strong><span>Dodaj pierwszą grafikę powyżej.</span></div>
      `;
      document.body.append(dialog);
      const browser = initMediaBrowser(dialog, {selectable: true, csrf});
      dialog.querySelector('[data-media-picker-close]')?.addEventListener('click', () => dialog.close());
      dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
      });
      mediaPicker = {dialog, browser};
    }
    mediaPicker.browser.setCsrf(csrf);
    mediaPicker.browser.setOnSelect((image) => {
      mediaPicker.dialog.close();
      onSelect(image);
    });
    mediaPicker.dialog.showModal();
    mediaPicker.browser.load(true);
  };

  document.querySelectorAll('[data-rich-editor]').forEach((editor) => {
    const surface = editor.querySelector('[data-rich-surface]');
    const input = editor.querySelector('[data-rich-input]');
    const blockSelect = editor.querySelector('[data-rich-block]');
    const colorInput = editor.querySelector('[data-rich-color]');
    if (!(surface instanceof HTMLElement) || !(input instanceof HTMLTextAreaElement)) return;

    const publicOrigin = (document.body.dataset.publicOrigin || '').replace(/\/+$/, '');
    const storedImageSource = (image) => {
      const explicit = image.dataset.publicSrc || '';
      if (explicit) return explicit;
      const raw = image.getAttribute('src') || '';
      if (!publicOrigin || !/^https?:\/\//i.test(raw)) return raw;
      try {
        const parsed = new URL(raw);
        if (parsed.origin === new URL(publicOrigin).origin && parsed.pathname.startsWith('/uploads/')) {
          return parsed.pathname + parsed.search;
        }
      } catch (_) {}
      return raw;
    };
    const adminPreviewSource = (source) => (
      publicOrigin && source.startsWith('/uploads/')
        ? `${publicOrigin}${source}`
        : source
    );
    const prepareSurfaceImages = () => {
      surface.querySelectorAll('img').forEach((image) => {
        const stored = storedImageSource(image);
        if (!stored.startsWith('/uploads/')) return;
        image.dataset.publicSrc = stored;
        const preview = adminPreviewSource(stored);
        if (image.getAttribute('src') !== preview) image.src = preview;
      });
    };

    let savedRange = null;
    const selectionBelongsToEditor = (selection) => {
      const node = selection?.anchorNode;
      return Boolean(node && (node === surface || surface.contains(node)));
    };
    const rememberSelection = () => {
      const selection = window.getSelection();
      if (!selection || selection.rangeCount === 0 || !selectionBelongsToEditor(selection)) return;
      savedRange = selection.getRangeAt(0).cloneRange();
    };
    const restoreSelection = () => {
      surface.focus();
      const selection = window.getSelection();
      if (!savedRange) {
        const range = document.createRange();
        range.selectNodeContents(surface);
        range.collapse(false);
        savedRange = range;
      }
      try {
        selection.removeAllRanges();
        selection.addRange(savedRange);
      } catch (_) {
        const range = document.createRange();
        range.selectNodeContents(surface);
        range.collapse(false);
        selection.removeAllRanges();
        selection.addRange(range);
        savedRange = range;
      }
    };
    const syncInput = () => {
      const visibleText = (surface.textContent || '').replace(/\u200b/g, '').trim();
      const hasMedia = Boolean(surface.querySelector('img, iframe'));
      if (visibleText === '' && !hasMedia) {
        input.value = '';
        return;
      }
      const cleanSurface = surface.cloneNode(true);
      cleanSurface.querySelectorAll('img').forEach((image) => {
        const stored = image.dataset.publicSrc || image.getAttribute('src') || '';
        if (stored) image.setAttribute('src', stored);
        delete image.dataset.publicSrc;
        delete image.dataset.uploadToken;
        image.classList.remove('is-uploading', 'is-rich-image-selected');
      });
      input.value = cleanSurface.innerHTML.trim();
    };
    const refreshToolbar = () => {
      editor.querySelectorAll('[data-rich-command]').forEach((button) => {
        const command = button.dataset.richCommand;
        if (!command || ['undo', 'redo', 'removeFormat', 'unlink'].includes(command)) return;
        try {
          button.classList.toggle('is-active', document.queryCommandState(command));
        } catch (_) {
          button.classList.remove('is-active');
        }
      });
      if (blockSelect instanceof HTMLSelectElement) {
        const block = String(document.queryCommandValue('formatBlock') || 'p')
          .toLowerCase()
          .replace(/[<>]/g, '');
        if ([...blockSelect.options].some((option) => option.value === block)) {
          blockSelect.value = block;
        }
      }
    };
    const runCommand = (command, value = null) => {
      restoreSelection();
      try {
        document.execCommand(command, false, value);
      } catch (_) {
        showToast('Nie zmieniono formatowania', 'Przeglądarka nie obsłużyła tej operacji.', 'error');
      }
      rememberSelection();
      syncInput();
      refreshToolbar();
    };
    const insertMediaBlock = (element) => {
      const block = document.createElement('div');
      block.append(element);
      const spacer = document.createElement('p');
      spacer.append(document.createElement('br'));
      runCommand('insertHTML', block.outerHTML + spacer.outerHTML);
    };
    const youtubeEmbedUrl = (rawUrl) => {
      try {
        const url = new URL(rawUrl.trim());
        const host = url.hostname.toLowerCase().replace(/^www\./, '');
        const parts = url.pathname.split('/').filter(Boolean);
        let videoId = '';
        if (host === 'youtu.be') {
          videoId = parts[0] || '';
        } else if (['youtube.com', 'm.youtube.com', 'youtube-nocookie.com'].includes(host)) {
          if (parts[0] === 'watch') {
            videoId = url.searchParams.get('v') || '';
          } else if (['embed', 'shorts', 'live'].includes(parts[0])) {
            videoId = parts[1] || '';
          }
        }
        return /^[A-Za-z0-9_-]{6,20}$/.test(videoId)
          ? `https://www.youtube-nocookie.com/embed/${videoId}`
          : '';
      } catch (_) {
        return '';
      }
    };

    editor.querySelectorAll('button[data-rich-command]').forEach((button) => {
      button.addEventListener('mousedown', (event) => event.preventDefault());
      button.addEventListener('click', () => runCommand(button.dataset.richCommand));
    });

    if (blockSelect instanceof HTMLSelectElement) {
      blockSelect.addEventListener('change', () => {
        runCommand('formatBlock', `<${blockSelect.value}>`);
      });
    }

    if (colorInput instanceof HTMLInputElement) {
      colorInput.addEventListener('input', () => runCommand('foreColor', colorInput.value));
    }

    editor.querySelector('[data-rich-action="link"]')?.addEventListener('mousedown', (event) => {
      event.preventDefault();
    });
    editor.querySelector('[data-rich-action="link"]')?.addEventListener('click', () => {
      restoreSelection();
      const selection = window.getSelection();
      if (!selection || selection.rangeCount === 0 || selection.isCollapsed) {
        showToast('Najpierw zaznacz tekst', 'Zaznacz fragment opisu, który ma prowadzić do strony.', 'error');
        return;
      }
      let url = window.prompt('Podaj adres linku, np. https://example.com');
      if (url === null) return;
      url = url.trim();
      if (url && !/^(?:https?:\/\/|mailto:|\/|#)/i.test(url)) {
        url = `https://${url}`;
      }
      if (!/^(?:https?:\/\/|mailto:|\/|#)/i.test(url)) {
        showToast('Nieprawidłowy link', 'Podaj pełny adres rozpoczynający się od https://.', 'error');
        return;
      }
      runCommand('createLink', url);
    });

    const imageButton = editor.querySelector('[data-rich-action="image"]');
    const imageInput = editor.querySelector('[data-rich-image-input]');
    const editorForm = editor.closest('form');
    editorForm?.addEventListener('submit', (event) => {
      if (editor.dataset.richUploading !== 'true') return;
      event.preventDefault();
      showToast('Grafika jeszcze się zapisuje', 'Poczekaj chwilę na zakończenie optymalizacji obrazu.', 'error');
    }, { capture: true });
    imageButton?.addEventListener('mousedown', (event) => event.preventDefault());
    imageButton?.addEventListener('click', () => {
      rememberSelection();
      const csrf = editorForm?.querySelector('input[name="_csrf"]')?.value || '';
      openMediaPicker(csrf, (image) => {
        const insertedImage = document.createElement('img');
        insertedImage.src = image.preview_url || adminPreviewSource(image.url);
        insertedImage.dataset.publicSrc = image.url;
        insertedImage.alt = image.name || 'Grafika w treści';
        insertedImage.loading = 'lazy';
        insertedImage.decoding = 'async';
        insertMediaBlock(insertedImage);
        syncInput();
        showToast('Grafika wstawiona', 'Obraz z biblioteki Media został dodany w miejscu kursora.', 'success');
      });
    });
    imageInput?.addEventListener('change', async () => {
      const file = imageInput.files?.[0];
      if (!file) return;
      if (!matchesAccept(file, imageInput)) {
        showToast('Nieobsługiwany format', 'Wybierz grafikę JPG, PNG albo WEBP.', 'error');
        imageInput.value = '';
        return;
      }
      if (file.size > 12 * 1024 * 1024) {
        showToast('Grafika jest za duża', 'Maksymalny rozmiar pliku to 12 MB.', 'error');
        imageInput.value = '';
        return;
      }
      const form = editor.closest('form');
      const csrf = form?.querySelector('input[name="_csrf"]')?.value || '';
      const slug = form?.querySelector('input[name="slug"]')?.value || '';
      const title = form?.querySelector('input[name="title"]')?.value || '';
      const scope = ['pages', 'events'].includes(editor.dataset.richMediaScope)
        ? editor.dataset.richMediaScope
        : 'books';
      const formData = new FormData();
      formData.append('_csrf', csrf);
      formData.append('slug', slug);
      formData.append('title', title);
      formData.append('scope', scope);
      formData.append('description_image', file);
      const uploadToken = `rich-${Date.now()}-${Math.random().toString(16).slice(2)}`;
      const previewUrl = URL.createObjectURL(file);
      const previewImage = document.createElement('img');
      previewImage.src = previewUrl;
      previewImage.alt = title.trim()
        || (scope === 'pages'
          ? 'Grafika w treści strony'
          : (scope === 'events' ? 'Grafika w opisie wydarzenia' : 'Grafika w opisie książki'));
      previewImage.dataset.uploadToken = uploadToken;
      previewImage.classList.add('is-uploading');
      insertMediaBlock(previewImage);
      editor.dataset.richUploading = 'true';
      imageButton?.setAttribute('aria-busy', 'true');
      showToast('Grafika dodana do podglądu', 'Trwa optymalizacja i zapisywanie pliku.', 'success');

      try {
        const response = await fetch(editor.dataset.richUploadUrl || adminUrl('/media/rich-image'), {
          method: 'POST',
          body: formData,
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
          },
          credentials: 'same-origin',
        });
        const payload = await response.json();
        if (!response.ok || payload.ok === false || !payload.media_url) {
          throw new Error(payload.message || 'Nie udało się wgrać grafiki.');
        }
        const savedPreview = surface.querySelector(`img[data-upload-token="${uploadToken}"]`);
        if (savedPreview instanceof HTMLImageElement) {
          savedPreview.src = payload.image?.preview_url || adminPreviewSource(payload.media_url);
          savedPreview.dataset.publicSrc = payload.media_url;
          savedPreview.loading = 'lazy';
          savedPreview.decoding = 'async';
          savedPreview.classList.remove('is-uploading');
          delete savedPreview.dataset.uploadToken;
          syncInput();
        }
        showToast('Grafika zapisana', 'Obraz został zoptymalizowany i jest już częścią treści.', 'success');
      } catch (error) {
        const failedPreview = surface.querySelector(`img[data-upload-token="${uploadToken}"]`);
        if (failedPreview instanceof HTMLImageElement) {
          const block = failedPreview.parentElement;
          if (block?.parentElement === surface) block.remove();
          else failedPreview.remove();
          syncInput();
        }
        showToast('Nie wgrano grafiki', error.message || 'Nie udało się połączyć z serwerem.', 'error');
      } finally {
        URL.revokeObjectURL(previewUrl);
        imageInput.value = '';
        imageButton?.removeAttribute('aria-busy');
        delete editor.dataset.richUploading;
      }
    });

    const imageTools = document.createElement('div');
    imageTools.className = 'rich-editor__image-tools';
    imageTools.hidden = true;
    imageTools.innerHTML = `
      <span>Wybrana grafika</span>
      <button type="button" data-rich-image-preview>Podgląd</button>
      <button type="button" class="is-danger" data-rich-image-remove>Usuń z opisu</button>
    `;
    editor.append(imageTools);
    let selectedRichImage = null;

    const clearRichImageSelection = () => {
      selectedRichImage?.classList.remove('is-rich-image-selected');
      selectedRichImage = null;
      imageTools.hidden = true;
    };
    const positionImageTools = () => {
      if (!(selectedRichImage instanceof HTMLImageElement) || imageTools.hidden) return;
      const editorRect = editor.getBoundingClientRect();
      const imageRect = selectedRichImage.getBoundingClientRect();
      const toolsWidth = imageTools.offsetWidth || 235;
      const left = Math.max(10, Math.min(
        imageRect.right - editorRect.left - toolsWidth - 10,
        editor.clientWidth - toolsWidth - 10
      ));
      imageTools.style.left = `${left}px`;
      imageTools.style.top = `${Math.max(62, imageRect.top - editorRect.top + 10)}px`;
    };
    const selectRichImage = (image) => {
      if (selectedRichImage !== image) selectedRichImage?.classList.remove('is-rich-image-selected');
      selectedRichImage = image;
      selectedRichImage.classList.add('is-rich-image-selected');
      imageTools.hidden = false;
      surface.focus({preventScroll: true});
      positionImageTools();
    };
    const removeSelectedRichImage = () => {
      if (!(selectedRichImage instanceof HTMLImageElement)) return;
      const image = selectedRichImage;
      let removable = image;
      if (
        image.parentElement
        && image.parentElement !== surface
        && ['A', 'PICTURE'].includes(image.parentElement.tagName)
        && image.parentElement.querySelectorAll('img').length === 1
      ) {
        removable = image.parentElement;
      }
      const container = removable.parentElement;
      removable.remove();
      if (
        container
        && container !== surface
        && !container.textContent.trim()
        && !container.querySelector('img, iframe')
      ) {
        container.remove();
      }
      clearRichImageSelection();
      syncInput();
      surface.focus();
      showToast('Grafika usunięta z opisu', 'Plik pozostał w bibliotece Media i może zostać użyty ponownie.', 'success');
    };

    imageTools.querySelector('[data-rich-image-preview]')?.addEventListener('click', () => {
      if (!(selectedRichImage instanceof HTMLImageElement)) return;
      const source = storedImageSource(selectedRichImage);
      const name = selectedRichImage.alt || source.split('/').pop()?.replace(/\.[a-z0-9]+$/i, '') || 'Grafika';
      openMediaPreview({
        url: source,
        preview_url: selectedRichImage.currentSrc || selectedRichImage.src,
        name,
        origin: 'Opis książki',
        width: selectedRichImage.naturalWidth,
        height: selectedRichImage.naturalHeight,
        bytes: 0,
      });
    });
    imageTools.querySelector('[data-rich-image-remove]')?.addEventListener('click', removeSelectedRichImage);
    surface.addEventListener('mousedown', (event) => {
      if (event.target instanceof HTMLImageElement) event.preventDefault();
    });
    surface.addEventListener('click', (event) => {
      if (event.target instanceof HTMLImageElement) {
        selectRichImage(event.target);
        return;
      }
      clearRichImageSelection();
    });
    window.addEventListener('resize', positionImageTools);

    const dragHasFiles = (event) => [...(event.dataTransfer?.types || [])].includes('Files');
    surface.addEventListener('dragenter', (event) => {
      if (!dragHasFiles(event)) return;
      event.preventDefault();
      editor.classList.add('is-file-dragover');
    });
    surface.addEventListener('dragover', (event) => {
      if (!dragHasFiles(event)) return;
      event.preventDefault();
      event.dataTransfer.dropEffect = 'copy';
      const pointRange = document.caretRangeFromPoint?.(event.clientX, event.clientY);
      if (pointRange && (pointRange.startContainer === surface || surface.contains(pointRange.startContainer))) {
        savedRange = pointRange.cloneRange();
      }
      editor.classList.add('is-file-dragover');
    });
    surface.addEventListener('dragleave', (event) => {
      if (!editor.contains(event.relatedTarget)) editor.classList.remove('is-file-dragover');
    });
    surface.addEventListener('drop', (event) => {
      if (!dragHasFiles(event)) return;
      event.preventDefault();
      editor.classList.remove('is-file-dragover');
      const file = event.dataTransfer?.files?.[0];
      if (!file || !(imageInput instanceof HTMLInputElement)) return;
      const pointRange = document.caretRangeFromPoint?.(event.clientX, event.clientY);
      if (pointRange && (pointRange.startContainer === surface || surface.contains(pointRange.startContainer))) {
        savedRange = pointRange.cloneRange();
      }
      const transfer = new DataTransfer();
      transfer.items.add(file);
      imageInput.files = transfer.files;
      imageInput.dispatchEvent(new Event('change', { bubbles: true }));
    });

    const youtubeButton = editor.querySelector('[data-rich-action="youtube"]');
    youtubeButton?.addEventListener('mousedown', (event) => event.preventDefault());
    youtubeButton?.addEventListener('click', () => {
      const url = window.prompt('Wklej adres filmu z YouTube');
      if (url === null) return;
      const embedUrl = youtubeEmbedUrl(url);
      if (!embedUrl) {
        showToast('Nieprawidłowy adres YouTube', 'Wklej adres filmu, Shorts albo youtu.be.', 'error');
        return;
      }
      const iframe = document.createElement('iframe');
      iframe.src = embedUrl;
      iframe.title = 'Film YouTube';
      iframe.loading = 'lazy';
      iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
      iframe.allowFullscreen = true;
      iframe.referrerPolicy = 'strict-origin-when-cross-origin';
      insertMediaBlock(iframe);
      showToast('Film dodany', 'YouTube został wstawiony do pełnego opisu.', 'success');
    });

    surface.addEventListener('input', () => {
      rememberSelection();
      syncInput();
      refreshToolbar();
    });
    surface.addEventListener('keyup', rememberSelection);
    surface.addEventListener('mouseup', () => {
      rememberSelection();
      refreshToolbar();
    });
    surface.addEventListener('focus', () => {
      rememberSelection();
      refreshToolbar();
    });
    surface.addEventListener('keydown', (event) => {
      if (selectedRichImage && (event.key === 'Delete' || event.key === 'Backspace')) {
        event.preventDefault();
        removeSelectedRichImage();
        return;
      }
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        editor.querySelector('[data-rich-action="link"]')?.click();
      }
    });
    document.addEventListener('selectionchange', () => {
      const selection = window.getSelection();
      if (!selectionBelongsToEditor(selection)) return;
      rememberSelection();
      refreshToolbar();
    });
    editor.closest('form')?.addEventListener('submit', syncInput);
    prepareSurfaceImages();
    syncInput();
  });

  const responseMessageFromHtml = (html) => {
    const documentResponse = new DOMParser().parseFromString(html, 'text/html');
    const error = documentResponse.querySelector('.error');
    const notice = documentResponse.querySelector('.notice');
    const heading = documentResponse.querySelector('h1');
    return {
      title: heading?.textContent?.trim() || 'Nie wykonano operacji',
      message: error?.textContent?.trim() || notice?.textContent?.trim() || 'Serwer nie zwrócił potwierdzenia.',
      isError: Boolean(error),
    };
  };

  document.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    if ((form.method || 'get').toLowerCase() !== 'post') return;
    if (form.hasAttribute('data-no-ajax')) return;
    if (event.defaultPrevented) return;

    const target = (form.getAttribute('target') || '').toLowerCase();
    if (target && target !== '_self') return;

    const url = new URL(form.action || window.location.href, window.location.href);
    if (url.origin !== window.location.origin) return;
    if (activeForms.has(form)) {
      event.preventDefault();
      return;
    }

    event.preventDefault();
    activeForms.add(form);
    form.classList.add('is-saving');
    const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;
    if (submitter) submitter.setAttribute('aria-busy', 'true');

    try {
      const formData = event.submitter
        ? new FormData(form, event.submitter)
        : new FormData(form);
      const response = await fetch(url, {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json, text/html;q=0.8',
        },
        credentials: 'same-origin',
      });
      const contentType = response.headers.get('content-type') || '';
      let payload;

      if (contentType.includes('application/json')) {
        payload = await response.json();
      } else {
        const html = await response.text();
        const parsed = responseMessageFromHtml(html);
        showToast(parsed.title, parsed.message, response.ok && !parsed.isError ? 'success' : 'error');
        return;
      }

      const tone = payload.ok === false || !response.ok ? 'error' : 'success';
      const fallbackSuccess = form.dataset.ajaxSuccess || 'Zmiany zostały zapisane.';
      const title = payload.title || (tone === 'error' ? 'Nie zapisano' : 'Zapisano');
      const message = payload.message || fallbackSuccess;

      if (tone === 'success' && payload.form_action) {
        const formAction = new URL(payload.form_action, window.location.href);
        if (formAction.origin === window.location.origin) {
          form.action = formAction.href;
        }
      }

      if (tone === 'success' && payload.replace_url) {
        const replaceUrl = new URL(payload.replace_url, window.location.href);
        if (replaceUrl.origin === window.location.origin) {
          window.history.replaceState({}, '', replaceUrl.pathname + replaceUrl.search + replaceUrl.hash);
        }
      }

      if (tone === 'success' && payload.page_title) {
        const pageTitle = document.querySelector('.page-heading h1');
        if (pageTitle) pageTitle.textContent = payload.page_title;
      }

      if (tone === 'success' && payload.page_kicker) {
        const pageKicker = document.querySelector('.page-heading .kicker');
        if (pageKicker) pageKicker.textContent = payload.page_kicker;
      }

      if (tone === 'success' && payload.remove_selector) {
        const removedRow = document.querySelector(payload.remove_selector);
        if (removedRow) {
          const list = removedRow.closest('.book-admin-list');
          removedRow.remove();
          if (list && !list.querySelector('.book-admin-row')) {
            const empty = document.createElement('div');
            empty.className = 'empty-state';
            empty.textContent = 'Nie ma jeszcze żadnej książki.';
            list.append(empty);
          }
        }
      }

      if (tone === 'success' && payload.status_selector) {
        const status = document.querySelector(payload.status_selector);
        if (status) {
          status.textContent = payload.status_label || 'Ukryta';
          status.className = `pill pill--${payload.status_tone || 'neutral'}`;
        }
      }

      if (payload.redirect) {
        if (form.dataset.ajaxSuccess) {
          rememberToast({ title, message, tone });
        }
        window.location.assign(payload.redirect);
        return;
      }

      if (form.hasAttribute('data-ajax-refresh') && tone === 'success') {
        rememberToast({ title, message, tone });
        window.location.reload();
        return;
      }

      showToast(title, message, tone);
      if (tone === 'success' && form.hasAttribute('data-ajax-clear-files')) {
        form.querySelectorAll('input[type="file"]').forEach((input) => {
          input.value = '';
          input.dispatchEvent(new Event('change', { bubbles: true }));
        });
      }
      if (tone === 'success' && form.hasAttribute('data-ajax-reset')) {
        form.reset();
      }
    } catch (error) {
      showToast('Błąd połączenia', 'Nie udało się połączyć z serwerem. Spróbuj ponownie.', 'error');
    } finally {
      activeForms.delete(form);
      form.classList.remove('is-saving');
      if (submitter) submitter.removeAttribute('aria-busy');
    }
  });
})();
