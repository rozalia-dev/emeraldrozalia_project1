(function () {
    'use strict';

    var root = document.querySelector('[data-banner-root]');
    if (!root) return;

    var modals = Array.prototype.slice.call(root.parentElement.querySelectorAll('[data-banner-modal]'));
    var editor = root.parentElement.querySelector('[data-banner-modal="editor"]');
    var editorForm = editor ? editor.querySelector('[data-banner-editor-form]') : null;
    var editorTitle = editor ? editor.querySelector('[data-banner-editor-title]') : null;

    function closeModals() {
        modals.forEach(function (modal) { modal.hidden = true; });
        document.body.classList.remove('banner-modal-open');
    }

    function setEditorMode(mode) {
        if (!editor || !editorForm) return;
        var updateAction = editorForm.getAttribute('data-update-action');
        var createAction = editorForm.getAttribute('data-create-action');
        var isEdit = mode === 'edit' && updateAction;
        editorForm.action = isEdit ? updateAction : createAction;
        var method = editorForm.querySelector('[data-banner-method]');
        if (isEdit && !method) {
            method = document.createElement('input');
            method.type = 'hidden';
            method.name = '_method';
            method.value = 'PUT';
            method.setAttribute('data-banner-method', '');
            editorForm.insertBefore(method, editorForm.firstChild);
        }
        if (!isEdit && method) method.remove();
        if (editorTitle) editorTitle.textContent = isEdit ? 'Edit Banner / Slider' : 'Create Banner / Slider';

        if (mode === 'create') {
            editorForm.querySelectorAll('[data-banner-field]').forEach(function (field) {
                if (field.type === 'checkbox') field.checked = true;
                else if (field.name === 'type') field.value = 'slider';
                else if (field.name === 'position') field.value = 'Home - Main Slider';
                else if (field.name === 'status') field.value = 'draft';
                else if (field.name === 'target_url') field.value = '/';
                else if (field.name === 'priority') field.value = '1';
                else if (field.name === 'animation') field.value = 'fade';
                else if (field.name === 'autoplay_speed') field.value = '5';
                else if (field.name === 'subtitle' || field.name === 'starts_at' || field.name === 'ends_at' || field.name === 'image_path' || field.name === 'alt_text' || field.name === 'title_text' || field.name === 'aria_label' || field.name === 'specific_pages') field.value = '';
            });
            editorForm.querySelectorAll('[data-banner-device]').forEach(function (field) { field.checked = true; });
            var file = editorForm.querySelector('[data-banner-file]');
            if (file) file.value = '';
            var fileName = editorForm.querySelector('[data-banner-file-name]');
            if (fileName) fileName.textContent = 'No new image selected';
        }
    }

    function openModal(kind) {
        closeModals();
        var modalKind = kind === 'create' || kind === 'edit' ? 'editor' : kind;
        var modal = root.parentElement.querySelector('[data-banner-modal="' + modalKind + '"]');
        if (!modal) return;
        if (modalKind === 'editor') setEditorMode(kind);
        modal.hidden = false;
        document.body.classList.add('banner-modal-open');
        var focusTarget = modal.querySelector('input:not([type="hidden"]), select, button');
        if (focusTarget) window.setTimeout(function () { focusTarget.focus(); }, 25);
    }

    root.parentElement.addEventListener('click', function (event) {
        var opener = event.target.closest('[data-banner-modal-open]');
        if (opener) {
            event.preventDefault();
            openModal(opener.getAttribute('data-banner-modal-open'));
            return;
        }
        if (event.target.closest('[data-banner-modal-close]')) {
            event.preventDefault();
            closeModals();
            return;
        }
        var copyButton = event.target.closest('[data-copy]');
        if (copyButton) {
            var value = copyButton.getAttribute('data-copy') || '';
            if (value && navigator.clipboard) navigator.clipboard.writeText(value);
            copyButton.classList.add('is-copied');
            window.setTimeout(function () { copyButton.classList.remove('is-copied'); }, 900);
        }
        var confirmTarget = event.target.closest('[data-confirm]');
        if (confirmTarget && !window.confirm(confirmTarget.getAttribute('data-confirm'))) {
            event.preventDefault();
        }
    });

    modals.forEach(function (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) closeModals();
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeModals();
    });

    var selectAll = root.querySelector('[data-banner-select-all]');
    var rowChecks = function () { return Array.prototype.slice.call(root.querySelectorAll('[data-banner-row-check]:not(:disabled)')); };
    var selectionCount = root.querySelector('[data-banner-selection-count]');
    function updateSelection() {
        var rows = rowChecks();
        var checked = rows.filter(function (checkbox) { return checkbox.checked; });
        if (selectionCount) selectionCount.textContent = checked.length;
        if (selectAll) {
            selectAll.checked = rows.length > 0 && checked.length === rows.length;
            selectAll.indeterminate = checked.length > 0 && checked.length < rows.length;
        }
    }
    if (selectAll) selectAll.addEventListener('change', function () {
        rowChecks().forEach(function (checkbox) { checkbox.checked = selectAll.checked; });
        updateSelection();
    });
    rowChecks().forEach(function (checkbox) { checkbox.addEventListener('change', updateSelection); });
    updateSelection();

    var fileInput = editor ? editor.querySelector('[data-banner-file]') : null;
    if (fileInput) fileInput.addEventListener('change', function () {
        var fileName = editor.querySelector('[data-banner-file-name]');
        if (fileName) fileName.textContent = fileInput.files && fileInput.files[0] ? fileInput.files[0].name : 'No new image selected';
    });

    var perPage = root.querySelector('[data-banner-per-page]');
    if (perPage) perPage.addEventListener('change', function () {
        var url = new URL(window.location.href);
        url.searchParams.set('per_page', perPage.value);
        url.searchParams.delete('page');
        window.location.assign(url.toString());
    });

    var initialModal = root.getAttribute('data-open-modal');
    if (initialModal) window.setTimeout(function () { openModal(initialModal); }, 30);
}());
