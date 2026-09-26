@php
    $destroyTemplate = route('admin.product-manager.destroy', ['product' => '__PRODUCT__']);
    $restoreTemplate = route('admin.product-manager.restore', ['product' => '__PRODUCT__']);
    $permanentTemplate = route('admin.product-manager.permanent-destroy', ['product' => '__PRODUCT__']);
    $bulkDestroyRoute = route('admin.product-manager.bulk-destroy');
    $bulkPublishRoute = route('admin.product-manager.bulk-publish');
    $publishTemplate = route('admin.product-manager.publish', ['product' => '__PRODUCT__']);
    $canDeleteProducts = (bool) (auth()->user()?->hasPermission('products.delete'));
    $canEditProducts = (bool) (auth()->user()?->hasPermission('website.products.edit'));
@endphp

<style>
    .pm-action-menu-portal{
        position:fixed;
        z-index:2147483000;
        display:none;
        min-width:190px;
        padding:6px;
        background:#fff;
        border:1px solid #d8dfda;
        border-radius:8px;
        box-shadow:0 14px 34px rgba(5,38,23,.18);
    }
    .pm-action-menu-portal.is-open{display:block}
    .pm-action-menu-portal a,
    .pm-action-menu-portal button{
        width:100%;
        display:flex;
        align-items:center;
        gap:9px;
        padding:9px 10px;
        border:0;
        border-radius:6px;
        background:transparent;
        color:#26362d;
        font:inherit;
        font-size:13px;
        line-height:1.2;
        text-align:left;
        text-decoration:none;
        white-space:nowrap;
        cursor:pointer;
    }
    .pm-action-menu-portal a:hover,
    .pm-action-menu-portal a:focus-visible,
    .pm-action-menu-portal button:hover,
    .pm-action-menu-portal button:focus-visible{
        outline:none;
        background:#f2f6f3;
    }
    .pm-action-menu-portal .pm-menu-danger{color:#b42318}
    .pm-action-menu-portal .pm-menu-danger:hover,
    .pm-action-menu-portal .pm-menu-danger:focus-visible{background:#fff2f0}
    .pm-action-menu-portal .pm-menu-restore{color:#087a48}
    .pm-action-menu-portal .pm-menu-divider{
        height:1px;
        margin:5px 4px;
        background:#e4e9e5;
    }
    .pm-row-actions button[aria-haspopup="menu"][aria-expanded="true"]{
        border-color:#0a7f45;
        background:#eef8f1;
        color:#087a48;
    }
    .pm-publish-controls{
        display:flex;
        align-items:center;
        gap:6px;
    }
    .pm-publish-controls select,
    .pm-publish-controls button{
        min-height:38px;
        border:1px solid #cfd9d2;
        border-radius:7px;
        background:#fff;
        color:#24372c;
        font:inherit;
        font-size:13px;
    }
    .pm-publish-controls select{padding:0 30px 0 10px;min-width:210px}
    .pm-publish-controls button{padding:0 12px;font-weight:700;cursor:pointer}
    .pm-publish-controls button:not(:disabled):hover,
    .pm-publish-controls button:not(:disabled):focus-visible{border-color:#087a48;background:#eef8f1;color:#087a48;outline:none}
    .pm-publish-controls button:disabled{color:#9aa5a0;background:#f7f9f8;cursor:not-allowed}
    .pm-delete-menu{position:relative}
    .pm-delete-menu>summary{
        display:flex;
        align-items:center;
        gap:6px;
        min-height:38px;
        padding:0 12px;
        border:1px solid #efb7b2;
        border-radius:7px;
        background:#fff;
        color:#b42318;
        font-size:13px;
        font-weight:700;
        cursor:pointer;
        list-style:none;
        white-space:nowrap;
    }
    .pm-delete-menu>summary::-webkit-details-marker{display:none}
    .pm-delete-menu[open]>summary{background:#fff5f4;border-color:#d92d20}
    .pm-delete-menu>div{
        position:absolute;
        z-index:30;
        top:calc(100% + 6px);
        right:0;
        min-width:190px;
        padding:6px;
        border:1px solid #ead6d3;
        border-radius:8px;
        background:#fff;
        box-shadow:0 12px 30px rgba(45,16,12,.14);
    }
    .pm-delete-menu button{
        width:100%;
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:10px;
        padding:9px 10px;
        border:0;
        border-radius:6px;
        background:transparent;
        color:#8a241b;
        font:inherit;
        font-size:13px;
        text-align:left;
        cursor:pointer;
        white-space:nowrap;
    }
    .pm-delete-menu button:hover,
    .pm-delete-menu button:focus-visible{background:#fff2f0;outline:none}
    .pm-delete-menu button:disabled{color:#9aa5a0;background:transparent;cursor:not-allowed}
    .pm-delete-menu .pm-delete-all{font-weight:700;color:#b42318}
</style>

<script data-product-delete-actions>
(() => {
    const routePath = @json(parse_url(route('admin.product-manager.index'), PHP_URL_PATH));
    const normalize = value => String(value || '').replace(/\/+$/, '');
    if (normalize(window.location.pathname) !== normalize(routePath)) return;

    const params = new URLSearchParams(window.location.search);
    const isTrash = params.get('tab') === 'trash';
    const csrf = @json(csrf_token());
    const canDeleteProducts = @json($canDeleteProducts);
    const destroyTemplate = @json($destroyTemplate);
    const restoreTemplate = @json($restoreTemplate);
    const permanentTemplate = @json($permanentTemplate);
    const bulkDestroyRoute = @json($bulkDestroyRoute);
    const bulkPublishRoute = @json($bulkPublishRoute);
    const publishTemplate = @json($publishTemplate);
    const canEditProducts = @json($canEditProducts);
    const urlFor = (template, id) => template.replace('__PRODUCT__', encodeURIComponent(id));

    const icon = name => {
        const paths = {
            eye: '<path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/>',
            pencil: '<path d="m4 16-.8 4.8L8 20l11.5-11.5a2.1 2.1 0 0 0-3-3zM14.5 6.5l3 3"/>',
            trash: '<path d="M4 7h16m-10 4v6m4-6v6M9 7V4h6v3m-9 0 1 14h10l1-14"/>',
            restore: '<path d="M3 12a9 9 0 1 0 3-6.7L3 8m0-5v5h5"/>'
        };
        return `<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${paths[name]}</svg>`;
    };

    const menu = document.createElement('div');
    menu.id = 'pm-action-menu-portal';
    menu.className = 'pm-action-menu-portal';
    menu.setAttribute('role', 'menu');
    menu.setAttribute('aria-label', 'Product actions');
    document.body.appendChild(menu);

    let activeTrigger = null;

    const closeMenu = ({ restoreFocus = false } = {}) => {
        menu.classList.remove('is-open');
        menu.style.visibility = '';
        menu.replaceChildren();
        if (activeTrigger) {
            activeTrigger.setAttribute('aria-expanded', 'false');
            if (restoreFocus) activeTrigger.focus();
        }
        activeTrigger = null;
    };

    const positionMenu = trigger => {
        menu.classList.add('is-open');
        menu.style.visibility = 'hidden';
        menu.style.left = '0px';
        menu.style.top = '0px';

        const triggerRect = trigger.getBoundingClientRect();
        const menuRect = menu.getBoundingClientRect();
        const margin = 8;
        let left = triggerRect.right - menuRect.width;
        let top = triggerRect.bottom + 6;

        left = Math.max(margin, Math.min(left, window.innerWidth - menuRect.width - margin));
        if (top + menuRect.height > window.innerHeight - margin) {
            top = Math.max(margin, triggerRect.top - menuRect.height - 6);
        }

        menu.style.left = `${Math.round(left)}px`;
        menu.style.top = `${Math.round(top)}px`;
        menu.style.visibility = 'visible';
    };

    const submitAction = ({ action, method = 'POST', confirmText, fields = {} }) => {
        if (! window.confirm(confirmText)) return;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = action;
        form.hidden = true;

        const addHidden = (name, value) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        };

        addHidden('_token', csrf);

        if (method !== 'POST') {
            addHidden('_method', method);
        }

        Object.entries(fields).forEach(([name, value]) => {
            const values = Array.isArray(value) ? value : [value];
            values.forEach(item => addHidden(name, item));
        });

        document.body.appendChild(form);
        form.submit();
    };

    const selectAll = document.getElementById('select-all-products');
    const productCheckboxes = Array.from(document.querySelectorAll('tbody input[name="products[]"]'));
    const deleteSelected = document.querySelector('[data-delete-selected]');
    const deleteAll = document.querySelector('[data-delete-all]');
    const selectedCount = document.querySelector('[data-selected-count]');
    const publishAction = document.querySelector('[data-publish-action]');
    const publishSelected = document.querySelector('[data-publish-selected]');
    const publishSelectedCount = document.querySelector('[data-publish-selected-count]');

    const syncBulkSelection = () => {
        const selected = productCheckboxes.filter(checkbox => checkbox.checked);
        if (selectedCount) selectedCount.textContent = `(${selected.length})`;
        if (publishSelectedCount) publishSelectedCount.textContent = selected.length ? `(${selected.length})` : '';
        if (deleteSelected) deleteSelected.disabled = selected.length === 0;
        if (publishSelected) publishSelected.disabled = selected.length === 0 || ! publishAction?.value;

        if (selectAll) {
            selectAll.checked = productCheckboxes.length > 0 && selected.length === productCheckboxes.length;
            selectAll.indeterminate = selected.length > 0 && selected.length < productCheckboxes.length;
        }
    };

    selectAll?.addEventListener('change', () => {
        productCheckboxes.forEach(checkbox => {
            checkbox.checked = selectAll.checked;
        });
        syncBulkSelection();
    });

    productCheckboxes.forEach(checkbox => checkbox.addEventListener('change', syncBulkSelection));
    publishAction?.addEventListener('change', syncBulkSelection);
    syncBulkSelection();

    publishSelected?.addEventListener('click', () => {
        const ids = productCheckboxes.filter(checkbox => checkbox.checked).map(checkbox => checkbox.value);
        const action = publishAction?.value || '';
        if (ids.length === 0 || ! ['publish', 'unpublish'].includes(action)) return;

        submitAction({
            action: bulkPublishRoute,
            confirmText: action === 'publish'
                ? `Publish ${ids.length} selected product${ids.length === 1 ? '' : 's'} to the public website?`
                : `Remove ${ids.length} selected product${ids.length === 1 ? '' : 's'} from the public website?`,
            fields: {
                action,
                'products[]': ids,
            },
        });
    });

    deleteSelected?.addEventListener('click', () => {
        const ids = productCheckboxes.filter(checkbox => checkbox.checked).map(checkbox => checkbox.value);
        if (ids.length === 0) return;

        submitAction({
            action: bulkDestroyRoute,
            method: 'DELETE',
            confirmText: `Move ${ids.length} selected product${ids.length === 1 ? '' : 's'} to Trash? You can restore them later.`,
            fields: {
                mode: 'selected',
                'products[]': ids,
            },
        });
    });

    deleteAll?.addEventListener('click', () => {
        const total = Number.parseInt(deleteAll.dataset.totalProducts || '0', 10);
        if (total < 1) return;

        submitAction({
            action: bulkDestroyRoute,
            method: 'DELETE',
            confirmText: `Move ALL ${total} products to Trash? This affects the entire active catalogue. You can restore them later.`,
            fields: { mode: 'all' },
        });
    });

    const addLink = ({ href, label, iconName }) => {
        const link = document.createElement('a');
        link.href = href;
        link.setAttribute('role', 'menuitem');
        link.innerHTML = `${icon(iconName)}<span>${label}</span>`;
        menu.appendChild(link);
    };

    const addButton = ({ label, iconName, className = '', onClick }) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.setAttribute('role', 'menuitem');
        if (className) button.className = className;
        button.innerHTML = `${icon(iconName)}<span>${label}</span>`;
        button.addEventListener('click', onClick);
        menu.appendChild(button);
    };

    const addDivider = () => {
        const divider = document.createElement('div');
        divider.className = 'pm-menu-divider';
        divider.setAttribute('role', 'separator');
        menu.appendChild(divider);
    };

    document.querySelectorAll('tr[id^="product-"]').forEach(row => {
        const id = row.id.replace('product-', '');
        if (! /^\d+$/.test(id)) return;

        const actions = row.querySelector('.pm-row-actions');
        if (! actions) return;

        const trigger = actions.querySelector('button[aria-label^="More actions"]') || actions.querySelector('button');
        if (! trigger) return;

        const name = row.querySelector('.pm-product-name strong')?.textContent?.trim() || 'this product';
        const links = actions.querySelectorAll('a');
        const viewHref = links[0]?.href || '';
        const editHref = links[1]?.href || '';

        actions.classList.add('has-dropdown');
        trigger.setAttribute('aria-haspopup', 'menu');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.title = `More actions for ${name}`;

        if (isTrash) {
            actions.replaceChildren(trigger);
            const status = row.querySelector('.pm-status');
            if (status) {
                status.textContent = 'Deleted';
                status.className = 'pm-status hidden';
            }
        }

        trigger.addEventListener('click', event => {
            event.preventDefault();
            event.stopPropagation();

            if (activeTrigger === trigger && menu.classList.contains('is-open')) {
                closeMenu();
                return;
            }

            closeMenu();
            activeTrigger = trigger;
            trigger.setAttribute('aria-expanded', 'true');

            if (isTrash) {
                if (canDeleteProducts) {
                    addButton({
                        label: 'Restore product',
                        iconName: 'restore',
                        className: 'pm-menu-restore',
                        onClick: () => {
                            closeMenu();
                            submitAction({
                                action: urlFor(restoreTemplate, id),
                                confirmText: `Restore ${name} to Product Manager?`,
                            });
                        },
                    });
                    addDivider();
                    addButton({
                        label: 'Permanently delete',
                        iconName: 'trash',
                        className: 'pm-menu-danger',
                        onClick: () => {
                            closeMenu();
                            submitAction({
                                action: urlFor(permanentTemplate, id),
                                method: 'DELETE',
                                confirmText: `Permanently delete ${name}? This cannot be undone.`,
                            });
                        },
                    });
                }
            } else {
                if (viewHref) addLink({ href: viewHref, label: 'View product', iconName: 'eye' });
                if (editHref) addLink({ href: editHref, label: 'Edit product', iconName: 'pencil' });
                if (canEditProducts) {
                    if (viewHref || editHref) addDivider();
                    const isPublished = row.dataset.productPublished === '1';
                    addButton({
                        label: isPublished ? 'Unpublish from public' : 'Approve for public',
                        iconName: 'eye',
                        className: isPublished ? 'pm-menu-danger' : 'pm-menu-restore',
                        onClick: () => {
                            closeMenu();
                            submitAction({
                                action: urlFor(publishTemplate, id),
                                confirmText: isPublished
                                    ? `Remove ${name} from the public website?`
                                    : `Publish ${name} to the public website?`,
                                fields: { action: isPublished ? 'unpublish' : 'publish' },
                            });
                        },
                    });
                }
                if (canDeleteProducts) {
                    if (viewHref || editHref) addDivider();
                    addButton({
                        label: 'Delete product',
                        iconName: 'trash',
                        className: 'pm-menu-danger',
                        onClick: () => {
                            closeMenu();
                            submitAction({
                                action: urlFor(destroyTemplate, id),
                                method: 'DELETE',
                                confirmText: `Move ${name} to Trash? You can restore it later.`,
                            });
                        },
                    });
                }
            }

            positionMenu(trigger);
            menu.querySelector('[role="menuitem"]')?.focus({ preventScroll: true });
        });
    });

    document.addEventListener('click', event => {
        if (! menu.classList.contains('is-open')) return;
        if (menu.contains(event.target)) return;
        if (activeTrigger?.contains(event.target)) return;
        closeMenu();
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && menu.classList.contains('is-open')) {
            event.preventDefault();
            closeMenu({ restoreFocus: true });
        }
    });

    window.addEventListener('resize', () => closeMenu());
    window.addEventListener('scroll', () => closeMenu(), true);
})();
</script>
