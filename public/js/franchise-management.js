(() => {
    const root = document.querySelector('[data-franchise-page]');
    if (!root) return;

    const section = location.pathname.match(/^\/admin\/resource\/([^/?#]+)/)?.[1] || '';
    const tableWrap = root.querySelector('.fm-table-wrap');
    const rows = [...root.querySelectorAll('.fm-table tbody tr')];
    const token = root.querySelector('input[name="_token"]')?.value || '';
    const checks = [];

    rows.forEach(row => {
        const edit = row.querySelector('[data-fm-edit][data-id]');
        const firstCell = row.querySelector('td');
        if (!edit || !firstCell) return;
        const check = document.createElement('input');
        check.type = 'checkbox';
        check.name = 'ids[]';
        check.value = edit.dataset.id;
        check.setAttribute('form', 'franchise-bulk-form');
        check.setAttribute('aria-label', 'Select franchise record');
        check.className = 'fm-bulk-check';
        firstCell.prepend(check);
        checks.push(check);
    });

    const actionConfig = section === 'franchise-applications'
        ? { value: 'close', label: 'Close selected applications', confirm: 'Close the selected franchise applications? They will be retained in history.' }
        : section === 'franchise-retail-stores'
            ? { value: 'terminate', label: 'Terminate selected stores', confirm: 'Terminate the selected franchise stores? They will be retained in history.' }
            : { value: 'trash', label: 'Move selected records to trash', confirm: 'Move the selected records to recoverable trash?' };

    if (tableWrap && token && checks.length && !['franchise-territories', 'store-setup', 'franchise-dashboard'].includes(section)) {
        const form = document.createElement('form');
        form.id = 'franchise-bulk-form';
        form.method = 'post';
        form.action = `/admin/franchise-management/${section}/bulk`;
        form.className = 'fm-search-form fm-bulk-form';
        form.innerHTML = '<input type="hidden" name="_token" value="'+token+'"><input type="hidden" name="action" value="'+actionConfig.value+'"><label class="fm-bulk-select"><input type="checkbox" data-fm-select-all> Select all visible</label><button type="submit" class="fm-filter">'+actionConfig.label+'</button><span data-fm-bulk-count>0 selected</span>';
        tableWrap.before(form);
        const selectAll = form.querySelector('[data-fm-select-all]');
        const count = form.querySelector('[data-fm-bulk-count]');
        const update = () => {
            const selected = checks.filter(check => check.checked).length;
            count.textContent = `${selected} selected`;
            selectAll.checked = selected > 0 && selected === checks.length;
            selectAll.indeterminate = selected > 0 && selected < checks.length;
        };
        selectAll.addEventListener('change', () => { checks.forEach(check => check.checked = selectAll.checked); update(); });
        checks.forEach(check => check.addEventListener('change', update));
        form.addEventListener('submit', event => {
            const selected = checks.filter(check => check.checked).length;
            if (!selected) {
                event.preventDefault();
                alert('Select at least one record first.');
                return;
            }
            if (!confirm(actionConfig.confirm)) event.preventDefault();
        });
        update();
    }

    root.querySelectorAll('form').forEach(form => {
        const method = form.querySelector('input[name="_method"][value="DELETE"]');
        if (!method || !form.action.includes('/franchise-management/')) return;
        const button = form.querySelector('button[type="submit"]');
        if (section === 'franchise-applications') {
            form.onsubmit = () => confirm('Close this application and retain it in franchise history?');
            if (button) button.title = 'Close application';
        } else if (section === 'franchise-retail-stores') {
            form.onsubmit = () => confirm('Terminate this store and retain it in franchise history?');
            if (button) button.title = 'Terminate store';
        } else if (section && section !== 'store-setup') {
            form.onsubmit = () => confirm('Move this record to recoverable trash?');
            if (button) button.title = 'Move to trash';
        }
    });

    const dialog = root.querySelector('[data-fm-dialog]');
    if (!dialog) return;
    const form = dialog.querySelector('[data-fm-form]');
    const method = form.querySelector('[data-method-override]');
    const title = dialog.querySelector('[data-fm-dialog-title]');
    const save = dialog.querySelector('[data-fm-save]');
    const createButton = root.querySelector('[data-fm-create]');
    const fields = [...form.querySelectorAll('input, select, textarea')].filter(field => field.name && !['_token', '_method'].includes(field.name));
    const decode = value => {
        const bytes = Uint8Array.from(atob(value), c => c.charCodeAt(0));
        return JSON.parse(new TextDecoder().decode(bytes));
    };
    const fill = payload => Object.entries(payload || {}).forEach(([name, value]) => {
        const field = form.elements.namedItem(name);
        if (field) field.value = value ?? '';
    });
    const editable = enabled => {
        fields.forEach(field => field.disabled = !enabled);
        save.hidden = !enabled;
    };
    const openCreate = () => {
        form.reset(); editable(true); method.disabled = true; form.action = form.dataset.storeUrl;
        title.textContent = createButton?.textContent.trim().replace(/^\+\s*/, '') || 'Add record';
        save.textContent = 'Save'; dialog.showModal();
    };
    const openEdit = button => {
        form.reset(); editable(true); method.disabled = false;
        form.action = form.dataset.updateTemplate.replace('__id__', button.dataset.id);
        fill(decode(button.dataset.fmEdit)); title.textContent = 'Edit record'; save.textContent = 'Save Changes'; dialog.showModal();
    };
    const openView = button => {
        form.reset(); method.disabled = true; fill(decode(button.dataset.fmView)); editable(false); title.textContent = 'View record'; dialog.showModal();
    };
    createButton?.addEventListener('click', openCreate);
    root.querySelectorAll('[data-fm-edit]').forEach(button => button.addEventListener('click', () => openEdit(button)));
    root.querySelectorAll('[data-fm-view]').forEach(button => button.addEventListener('click', () => openView(button)));
    root.querySelectorAll('[data-fm-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
})();
