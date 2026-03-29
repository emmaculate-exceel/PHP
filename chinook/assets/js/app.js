/**
 * app.js
 * Client-side JavaScript for the Chinook Album Manager.
 *
 * Responsibilities:
 *  1. Delete confirmation modal (index page)
 *  2. Artist selector toggle — show/hide existing vs new artist fields
 *  3. Dynamic track row management — add and remove rows on create/edit forms
 *  4. Mark-for-deletion styling on edit form checkboxes
 */

'use strict';

/* ── 1. Delete Confirmation Modal ─────────────────────────────────────── */
(function initDeleteModal() {
    const modal     = document.getElementById('delete-modal');
    if (!modal) return; // Not on the index page — bail out

    const albumName = document.getElementById('modal-album-name');
    const albumId   = document.getElementById('modal-album-id');
    const cancelBtn = document.getElementById('modal-cancel');

    /**
     * Opens the modal and populates it with the target album's details.
     * @param {string} id    The album's AlbumId
     * @param {string} title The album's title (for display only)
     */
    function openModal(id, title) {
        albumName.textContent = title;
        albumId.value         = id;
        modal.removeAttribute('hidden');
        cancelBtn.focus();
    }

    /** Closes the modal by re-adding the hidden attribute. */
    function closeModal() {
        modal.setAttribute('hidden', '');
    }

    // Attach click listeners to all delete buttons using event delegation
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.js-delete-btn');
        if (btn) {
            openModal(btn.dataset.albumId, btn.dataset.albumTitle);
        }
    });

    // Close when Cancel is clicked
    cancelBtn.addEventListener('click', closeModal);

    // Close when clicking outside the modal box
    modal.addEventListener('click', function(e) {
        if (e.target === modal) closeModal();
    });

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !modal.hasAttribute('hidden')) {
            closeModal();
        }
    });
}());


/* ── 2. Artist Selector Toggle ────────────────────────────────────────── */
(function initArtistToggle() {
    const existingRadio = document.getElementById('artist-existing');
    const newRadio      = document.getElementById('artist-new');

    if (!existingRadio || !newRadio) return; // Not on a form page

    const existingBlock = document.querySelector('.js-existing-artist');
    const newBlock      = document.querySelector('.js-new-artist');

    /**
     * Shows or hides the appropriate artist input block
     * based on which radio button is selected.
     */
    function updateArtistFields() {
        const showNew = newRadio.checked;
        if (existingBlock) existingBlock.style.display = showNew ? 'none' : '';
        if (newBlock)      newBlock.style.display      = showNew ? ''     : 'none';
    }

    existingRadio.addEventListener('change', updateArtistFields);
    newRadio.addEventListener('change', updateArtistFields);

    // Apply correct state on page load (handles validation-error repopulation)
    updateArtistFields();
}());


/* ── 3. Dynamic Track Rows ────────────────────────────────────────────── */
(function initTrackRows() {
    const addBtn    = document.getElementById('add-track-btn');
    const container = document.getElementById('tracks-container');
    const template  = document.getElementById('track-template');

    if (!addBtn || !container || !template) return;

    /**
     * Counts how many track rows currently exist (both existing and newly added)
     * so we can assign correct sequential indices and labels.
     */
    function getRowCount() {
        return container.querySelectorAll('.track-row').length;
    }

    /**
     * Adds a new blank track row to the container.
     * Clones the hidden <template> element and replaces placeholder tokens
     * with the correct numeric index before appending.
     */
    function addTrackRow() {
        const startIndex = parseInt(addBtn.dataset.startIndex || '0', 10);
        const idx        = startIndex + getRowCount();
        const num        = getRowCount() + 1;

        // Clone the template content and fill in index / number tokens
        const clone = template.content.cloneNode(true);
        let   html  = clone.querySelector('.track-row').outerHTML
                           .replaceAll('__IDX__', idx)
                           .replaceAll('__NUM__', num);

        // Insert the rendered HTML
        container.insertAdjacentHTML('beforeend', html);

        // Focus the new track's name input for accessibility
        const rows = container.querySelectorAll('.track-row');
        const lastRow = rows[rows.length - 1];
        const nameInput = lastRow.querySelector('input[type="text"]');
        if (nameInput) nameInput.focus();
    }

    // Add new row when the "+" button is clicked
    addBtn.addEventListener('click', addTrackRow);

    /**
     * Remove a dynamically added track row when its Remove button is clicked.
     * Uses event delegation so it works for rows added after page load.
     */
    container.addEventListener('click', function(e) {
        const removeBtn = e.target.closest('.js-remove-track');
        if (!removeBtn) return;

        const row = removeBtn.closest('.track-row');
        if (row) {
            row.remove();
        }
    });
}());


/* ── 4. Mark-for-Deletion Styling on Edit Form ────────────────────────── */
(function initMarkForDeletion() {
    const container = document.getElementById('tracks-container');
    if (!container) return;

    /**
     * Listens for changes on "mark for deletion" checkboxes.
     * Dims the track row visually so the user can clearly see
     * which tracks will be removed on save.
     */
    container.addEventListener('change', function(e) {
        const cb = e.target.closest('.js-delete-track-cb');
        if (!cb) return;

        const row = cb.closest('.track-row');
        if (row) {
            row.classList.toggle('marked-for-deletion', cb.checked);
        }
    });
}());
