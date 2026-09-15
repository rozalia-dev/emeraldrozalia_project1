@php
    $destroyTemplate = route('admin.product-manager.destroy', ['product' => '__PRODUCT__']);
    $restoreTemplate = route('admin.product-manager.restore', ['product' => '__PRODUCT__']);
    $permanentTemplate = route('admin.product-manager.permanent-destroy', ['product' => '__PRODUCT__']);
@endphp

<style>
    .pm-row-actions form{display:inline-flex;margin:0}
    .pm-row-actions .pm-delete-product{color:#b42318}
    .pm-row-actions .pm-restore-product{color:#087a48}
    .pm-row-actions .pm-permanent-delete{color:#b42318}
</style>
<script data-product-delete-actions>
(() => {
    const routePath = @json(parse_url(route('admin.product-manager.index'), PHP_URL_PATH));
    const normalize = value => String(value || '').replace(/\/+$/, '');
    if (normalize(window.location.pathname) !== normalize(routePath)) return;

    const params = new URLSearchParams(window.location.search);
    const isTrash = params.get('tab') === 'trash';
    const csrf = @json(csrf_token());
    const destroyTemplate = @json($destroyTemplate);
    const restoreTemplate = @json($restoreTemplate);
    const permanentTemplate = @json($permanentTemplate);
    const urlFor = (template, id) => template.replace('__PRODUCT__', encodeURIComponent(id));

    const icon = name => {
        const paths = {
            trash: '<path d="M4 7h16m-10 4v6m4-6v6M9 7V4h6v3m-9 0 1 14h10l1-14"/>',
            restore: '<path d="M3 12a9 9 0 1 0 3-6.7L3 8m0-5v5h5"/>'
        };
        return `<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${paths[name]}</svg>`;
    };

    const makeForm = ({ action, method = 'POST', title, label, className, iconName, confirmText }) => {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = action;

        const token = document.createElement('input');
        token.type = 'hidden';
        token.name = '_token';
        token.value = csrf;
        form.appendChild(token);

        if (method !== 'POST') {
            const methodInput = document.createElement('input');
            methodInput.type = 'hidden';
            methodInput.name = '_method';
            methodInput.value = method;
            form.appendChild(methodInput);
        }

        const button = document.createElement('button');
        button.type = 'submit';
        button.title = title;
        button.setAttribute('aria-label', label);
        button.className = className;
        button.innerHTML = icon(iconName);
        form.appendChild(button);

        form.addEventListener('submit', event => {
            if (! window.confirm(confirmText)) event.preventDefault();
        });

        return form;
    };

    document.querySelectorAll('tr[id^="product-"]').forEach(row => {
        const id = row.id.replace('product-', '');
        if (! /^\d+$/.test(id)) return;

        const actions = row.querySelector('.pm-row-actions');
        if (! actions) return;

        const name = row.querySelector('.pm-product-name strong')?.textContent?.trim() || 'this product';

        if (isTrash) {
            actions.replaceChildren();
            const status = row.querySelector('.pm-status');
            if (status) {
                status.textContent = 'Deleted';
                status.className = 'pm-status hidden';
            }

            actions.appendChild(makeForm({
                action: urlFor(restoreTemplate, id),
                title: `Restore ${name}`,
                label: `Restore ${name}`,
                className: 'pm-restore-product',
                iconName: 'restore',
                confirmText: `Restore ${name} to Product Manager?`,
            }));

            actions.appendChild(makeForm({
                action: urlFor(permanentTemplate, id),
                method: 'DELETE',
                title: `Permanently delete ${name}`,
                label: `Permanently delete ${name}`,
                className: 'pm-permanent-delete',
                iconName: 'trash',
                confirmText: `Permanently delete ${name}? This cannot be undone.`,
            }));
            return;
        }

        if (actions.querySelector('.pm-delete-product')) return;
        actions.appendChild(makeForm({
            action: urlFor(destroyTemplate, id),
            method: 'DELETE',
            title: `Delete ${name}`,
            label: `Delete ${name}`,
            className: 'pm-delete-product',
            iconName: 'trash',
            confirmText: `Move ${name} to Trash? You can restore it later.`,
        }));
    });
})();
</script>
