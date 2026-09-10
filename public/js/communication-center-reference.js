(() => {
    const root = document.querySelector('[data-cc-root]');
    if (!root) return;

    const dialog = root.querySelector('[data-cc-dialog]');
    if (!dialog) return;

    const form = dialog.querySelector('[data-cc-form]');
    const method = form?.querySelector('[data-cc-method]');
    const title = dialog.querySelector('[data-cc-dialog-title]');
    const createButton = root.querySelector('[data-cc-create]');

    const decode = (value) => {
        try {
            const bytes = Uint8Array.from(atob(value), (c) => c.charCodeAt(0));
            return JSON.parse(new TextDecoder().decode(bytes));
        } catch (_) {
            return {};
        }
    };

    const editableFields = () => [...form.querySelectorAll('input, select, textarea')]
        .filter((field) => field.name && !['_token', '_method'].includes(field.name));

    const reset = () => {
        form.reset();
        if (method) method.disabled = true;
        form.action = form.dataset.storeUrl;
        editableFields().forEach((field) => field.disabled = false);
    };

    const normalizeDateTime = (value) => {
        if (!value || typeof value !== 'string') return value ?? '';
        if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}/.test(value)) return value.replace(' ', 'T').slice(0, 16);
        if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(value)) return value.slice(0, 16);
        return value;
    };

    const fill = (payload) => {
        Object.entries(payload || {}).forEach(([name, raw]) => {
            const field = form.elements.namedItem(name);
            if (!field) return;
            const value = field.type === 'datetime-local' ? normalizeDateTime(raw) : (raw ?? '');
            field.value = value;
        });
    };

    createButton?.addEventListener('click', () => {
        reset();
        title.textContent = createButton.textContent.trim().replace(/^\+\s*/, '');
        dialog.showModal();
    });

    root.querySelectorAll('[data-cc-edit]').forEach((button) => {
        button.addEventListener('click', () => {
            reset();
            if (method) method.disabled = false;
            form.action = form.dataset.updateTemplate.replace('__id__', button.dataset.id);
            fill(decode(button.dataset.ccEdit));
            title.textContent = 'Edit Record';
            dialog.showModal();
        });
    });

    root.querySelectorAll('[data-cc-close]').forEach((button) => {
        button.addEventListener('click', () => dialog.close());
    });

    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && dialog.open) dialog.close();
    });
})();
