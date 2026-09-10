(() => {
    const root = document.querySelector('[data-franchise-page]');
    if (!root) return;
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
