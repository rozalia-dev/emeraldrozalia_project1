(() => {
    const page = document.querySelector('[data-category-page]');
    if (!page) return;

    const dialog = page.querySelector('[data-category-dialog]');
    const form = page.querySelector('[data-category-form]');
    const methodField = page.querySelector('[data-method-field]');
    const title = page.querySelector('[data-dialog-title]');
    const kicker = page.querySelector('[data-dialog-kicker]');
    const submitLabel = page.querySelector('[data-submit-label]');
    const fields = Object.fromEntries([...page.querySelectorAll('[data-field]')].map(el => [el.dataset.field, el]));
    const createAction = form?.action;

    const slugify = value => value.toLowerCase().trim()
        .normalize('NFKD').replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');

    let slugTouched = false;
    fields.slug?.addEventListener('input', () => { slugTouched = true; });
    fields.name?.addEventListener('input', () => {
        if (!slugTouched && methodField?.value === 'POST') fields.slug.value = slugify(fields.name.value);
    });

    function resetForm() {
        form.reset();
        form.action = createAction;
        methodField.value = 'POST';
        fields.status.value = 'active';
        fields.is_visible.value = '1';
        fields.sort_order.value = '0';
        slugTouched = false;
        [...fields.parent_id.options].forEach(option => option.disabled = false);
    }

    function openCreate(parentId = '', parentName = '') {
        resetForm();
        fields.parent_id.value = parentId || '';
        title.textContent = parentId ? 'Add Sub-Category' : 'Add Category';
        kicker.textContent = parentId ? `PARENT: ${parentName || 'SELECTED CATEGORY'}` : 'NEW CATEGORY';
        submitLabel.textContent = parentId ? 'Save Sub-Category' : 'Save Category';
        dialog.showModal();
        setTimeout(() => fields.name.focus(), 30);
    }

    function openEdit(button) {
        resetForm();
        form.action = button.dataset.action;
        methodField.value = 'PATCH';
        title.textContent = 'Edit Category';
        kicker.textContent = 'CATEGORY DETAILS';
        submitLabel.textContent = 'Update Category';
        fields.name.value = button.dataset.name || '';
        fields.slug.value = button.dataset.slug || '';
        fields.parent_id.value = button.dataset.parentId || '';
        fields.status.value = button.dataset.status || 'active';
        fields.is_visible.value = button.dataset.visible || '1';
        fields.sort_order.value = button.dataset.sortOrder || '0';
        fields.description.value = button.dataset.description || '';
        fields.meta_title.value = button.dataset.metaTitle || '';
        fields.meta_description.value = button.dataset.metaDescription || '';
        const selfOption = fields.parent_id.querySelector(`option[value="${button.dataset.id}"]`);
        if (selfOption) selfOption.disabled = true;
        slugTouched = true;
        dialog.showModal();
    }

    page.querySelectorAll('.js-add-category').forEach(button => button.addEventListener('click', () => openCreate()));
    page.querySelectorAll('.js-add-subcategory').forEach(button => button.addEventListener('click', () => {
        if (button.disabled || !button.dataset.parentId) return;
        openCreate(button.dataset.parentId, button.dataset.parentName);
    }));
    page.querySelectorAll('.js-edit-category').forEach(button => button.addEventListener('click', () => openEdit(button)));
    page.querySelectorAll('[data-dialog-close]').forEach(button => button.addEventListener('click', () => dialog.close()));

    function collapseBranch(uuid) {
        page.querySelectorAll(`[data-category-row][data-parent="${CSS.escape(uuid)}"]`).forEach(row => {
            row.classList.add('cat-row-collapsed');
            const childToggle = row.querySelector('[data-tree-toggle]');
            if (childToggle) childToggle.setAttribute('aria-expanded', 'false');
            collapseBranch(row.dataset.uuid);
        });
    }

    function expandDirect(uuid) {
        page.querySelectorAll(`[data-category-row][data-parent="${CSS.escape(uuid)}"]`).forEach(row => row.classList.remove('cat-row-collapsed'));
    }

    page.querySelectorAll('[data-tree-toggle]').forEach(toggle => toggle.addEventListener('click', () => {
        const expanded = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        expanded ? collapseBranch(toggle.dataset.treeToggle) : expandDirect(toggle.dataset.treeToggle);
    }));

    page.querySelectorAll('[data-expand-all]').forEach(button => button.addEventListener('click', () => {
        page.querySelectorAll('[data-category-row]').forEach(row => row.classList.remove('cat-row-collapsed'));
        page.querySelectorAll('[data-tree-toggle]').forEach(toggle => toggle.setAttribute('aria-expanded', 'true'));
    }));
    page.querySelectorAll('[data-collapse-all]').forEach(button => button.addEventListener('click', () => {
        page.querySelectorAll('[data-category-row]').forEach(row => row.classList.toggle('cat-row-collapsed', Number(row.dataset.depth) > 0));
        page.querySelectorAll('[data-tree-toggle]').forEach(toggle => toggle.setAttribute('aria-expanded', 'false'));
    }));

    const bulkForm = page.querySelector('#category-bulk-form');
    const checkAll = page.querySelector('[data-check-all]');
    const rowChecks = [...page.querySelectorAll('input[name="categories[]"]')];
    const selectedCount = page.querySelector('[data-selected-count]');
    function refreshBulk() {
        const count = rowChecks.filter(input => input.checked).length;
        selectedCount.textContent = count;
        bulkForm.hidden = count === 0;
        if (checkAll) checkAll.checked = count > 0 && count === rowChecks.length;
    }
    rowChecks.forEach(input => input.addEventListener('change', refreshBulk));
    checkAll?.addEventListener('change', () => {
        rowChecks.forEach(input => {
            const row = input.closest('[data-category-row]');
            if (!row.classList.contains('cat-row-collapsed')) input.checked = checkAll.checked;
        });
        refreshBulk();
    });
    page.querySelector('[data-focus-bulk]')?.addEventListener('click', () => {
        const first = rowChecks.find(input => !input.closest('[data-category-row]').classList.contains('cat-row-collapsed'));
        if (first) { first.checked = true; refreshBulk(); bulkForm.scrollIntoView({behavior:'smooth', block:'center'}); }
    });

    const importInput = page.querySelector('[data-import-input]');
    const importForm = page.querySelector('[data-import-form]');
    page.querySelector('[data-import-trigger]')?.addEventListener('click', () => importInput?.click());
    importInput?.addEventListener('change', () => { if (importInput.files.length) importForm.submit(); });

    page.querySelectorAll('[data-copy]').forEach(button => button.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(button.dataset.copy);
            const old = button.innerHTML;
            button.textContent = '✓';
            setTimeout(() => { button.innerHTML = old; }, 1200);
        } catch (_) {
            window.prompt('Copy category UUID:', button.dataset.copy);
        }
    }));

    const guide = page.querySelector('[data-guide-dialog]');
    page.querySelector('[data-guide-trigger]')?.addEventListener('click', () => guide.showModal());
    page.querySelector('[data-guide-close]')?.addEventListener('click', () => guide.close());

    let dragged = null;
    page.querySelectorAll('[data-category-row]').forEach(row => {
        row.addEventListener('dragstart', event => {
            if (event.target.closest('button, a, input, summary')) { event.preventDefault(); return; }
            dragged = row;
            row.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', row.dataset.uuid);
        });
        row.addEventListener('dragend', () => {
            row.classList.remove('is-dragging');
            page.querySelectorAll('.is-drop-target').forEach(target => target.classList.remove('is-drop-target'));
            dragged = null;
        });
        row.addEventListener('dragover', event => {
            if (!dragged || dragged === row || dragged.dataset.parent !== row.dataset.parent) return;
            event.preventDefault();
            row.classList.add('is-drop-target');
        });
        row.addEventListener('dragleave', () => row.classList.remove('is-drop-target'));
        row.addEventListener('drop', async event => {
            row.classList.remove('is-drop-target');
            if (!dragged || dragged === row || dragged.dataset.parent !== row.dataset.parent) return;
            event.preventDefault();
            const parent = dragged.dataset.parent;
            const siblings = [...page.querySelectorAll('[data-category-row]')].filter(item => item.dataset.parent === parent);
            const order = siblings.map(item => item.dataset.uuid);
            const from = order.indexOf(dragged.dataset.uuid);
            const to = order.indexOf(row.dataset.uuid);
            order.splice(from, 1);
            order.splice(to, 0, dragged.dataset.uuid);
            try {
                const response = await fetch(page.dataset.reorderUrl, {
                    method: 'POST',
                    headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':page.dataset.csrf},
                    body: JSON.stringify({order})
                });
                if (!response.ok) throw new Error('Reorder failed');
                window.location.reload();
            } catch (error) {
                alert('The category order could not be saved. Please refresh and try again.');
            }
        });
    });

    document.addEventListener('click', event => {
        page.querySelectorAll('.cat-row-menu[open]').forEach(menu => {
            if (!menu.contains(event.target)) menu.removeAttribute('open');
        });
    });
})();
