(() => {
  const list = document.querySelector('[data-sortable-books]');
  if (!list) return;

  let draggedRow = null;

  const rows = () => Array.from(list.querySelectorAll('[data-book-row]'));
  const saveOrder = () => {
    rows().forEach((row, index) => {
      const input = row.querySelector('[data-order-input]');
      if (input) input.value = String(index + 1);
    });
  };

  const moveWithKeyboard = (handle, direction) => {
    const row = handle.closest('[data-book-row]');
    if (!row) return;
    const sibling = direction < 0 ? row.previousElementSibling : row.nextElementSibling;
    if (!sibling) return;
    if (direction < 0) {
      list.insertBefore(row, sibling);
    } else {
      list.insertBefore(sibling, row);
    }
    saveOrder();
    handle.focus();
    row.classList.add('is-just-moved');
    window.setTimeout(() => row.classList.remove('is-just-moved'), 450);
  };

  list.querySelectorAll('[data-drag-handle]').forEach((handle) => {
    handle.addEventListener('dragstart', (event) => {
      draggedRow = handle.closest('[data-book-row]');
      if (!draggedRow) return;
      draggedRow.classList.add('is-dragging');
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('text/plain', draggedRow.dataset.bookId || '');
    });

    handle.addEventListener('dragend', () => {
      if (draggedRow) draggedRow.classList.remove('is-dragging');
      draggedRow = null;
      saveOrder();
    });

    handle.addEventListener('keydown', (event) => {
      if (event.key !== 'ArrowUp' && event.key !== 'ArrowDown') return;
      event.preventDefault();
      moveWithKeyboard(handle, event.key === 'ArrowUp' ? -1 : 1);
    });
  });

  list.addEventListener('dragover', (event) => {
    if (!draggedRow) return;
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
    const target = event.target.closest('[data-book-row]');
    if (!target || target === draggedRow) return;
    const box = target.getBoundingClientRect();
    const insertAfter = event.clientY > box.top + box.height / 2;
    list.insertBefore(draggedRow, insertAfter ? target.nextElementSibling : target);
  });

  list.addEventListener('drop', (event) => {
    if (!draggedRow) return;
    event.preventDefault();
    const movedRow = draggedRow;
    movedRow.classList.remove('is-dragging');
    movedRow.classList.add('is-just-moved');
    window.setTimeout(() => movedRow.classList.remove('is-just-moved'), 450);
    saveOrder();
  });

  saveOrder();
})();
