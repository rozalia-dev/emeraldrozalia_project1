(() => {
    const adminHeading = document.querySelector('.admin-heading strong');
    if (adminHeading) {
        const activeNav = document.querySelector('.admin-sidebar a.active, .admin-nav-subgroup > summary.active');
        const activeLabel = activeNav?.querySelector('.admin-nav-item-label > span:last-child, .admin-nav-parent-label > span:last-child')?.textContent?.trim()
            || activeNav?.textContent?.replace(/\s+/g, ' ')?.trim();
        const documentPageTitle = document.title.replace(/\s*-\s*Emerald Rozalia cPanel\s*$/i, '').trim();
        adminHeading.textContent = activeLabel || documentPageTitle || 'Dashboard';
    }

    const style = document.createElement('style');
    style.textContent = `
        .admin-bulk-toolbar{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin:14px 0;padding:11px 12px;border:1px solid #d7e2d8;border-radius:10px;background:#f7faf7}
        .admin-bulk-toolbar label{display:inline-flex;align-items:center;gap:7px;margin:0;font-size:12px;font-weight:700;color:#23402d}
        .admin-bulk-toolbar select{min-height:36px;padding:7px 30px 7px 9px;border:1px solid #c8d6ca;border-radius:7px;background:#fff;color:#183220}
        .admin-bulk-toolbar button{min-height:36px;padding:7px 14px;border:1px solid #0b6b38;border-radius:7px;background:#0b6b38;color:#fff;font-weight:800;cursor:pointer}
        .admin-bulk-toolbar button:hover,.admin-bulk-toolbar button:focus-visible{background:#07572e}
        .admin-bulk-toolbar [data-admin-bulk-count]{margin-left:auto;font-size:12px;color:#607066}
        .admin-card-select{position:absolute;z-index:8;top:10px;left:10px;display:grid;place-items:center;width:30px;height:30px;margin:0;border:1px solid rgba(255,255,255,.82);border-radius:8px;background:rgba(5,32,18,.82);box-shadow:0 3px 12px rgba(0,0,0,.16)}
        .admin-card-select input{width:16px;height:16px;margin:0;accent-color:#7fbd42;cursor:pointer}
        .mm-card-preview,.media-asset-preview{position:relative}
        .dc-bulk-check,.fm-bulk-check,.us-bulk-check{width:15px;height:15px;margin:0 7px 0 0;accent-color:#08753b;vertical-align:middle}
        @media(max-width:720px){.admin-bulk-toolbar{align-items:stretch}.admin-bulk-toolbar label{width:100%}.admin-bulk-toolbar select{flex:1}.admin-bulk-toolbar [data-admin-bulk-count]{width:100%;margin-left:0}}
    `;
    document.head.appendChild(style);

    const csrfToken = (root) => root.querySelector('input[name="_token"]')?.value || document.querySelector('input[name="_token"]')?.value || '';

    const makeToolbar = ({ root, insertBefore, formId, action, hidden = {}, options, checkboxes, destructive = [] }) => {
        if (!root || !insertBefore || !checkboxes.length || document.getElementById(formId)) return;
        const token = csrfToken(root);
        if (!token) return;

        const form = document.createElement('form');
        form.id = formId;
        form.className = 'admin-bulk-toolbar';
        form.method = 'post';
        form.action = action;

        const tokenInput = document.createElement('input');
        tokenInput.type = 'hidden';
        tokenInput.name = '_token';
        tokenInput.value = token;
        form.appendChild(tokenInput);

        Object.entries(hidden).forEach(([name, value]) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = String(value);
            form.appendChild(input);
        });

        const selectAllLabel = document.createElement('label');
        const selectAll = document.createElement('input');
        selectAll.type = 'checkbox';
        selectAll.setAttribute('aria-label', 'Select all visible records');
        selectAllLabel.append(selectAll, document.createTextNode(' Select all'));
        form.appendChild(selectAllLabel);

        const actionLabel = document.createElement('label');
        actionLabel.appendChild(document.createTextNode('Bulk action '));
        const actionSelect = document.createElement('select');
        actionSelect.name = 'action';
        actionSelect.required = true;
        options.forEach(([value, label]) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = label;
            actionSelect.appendChild(option);
        });
        actionLabel.appendChild(actionSelect);
        form.appendChild(actionLabel);

        const apply = document.createElement('button');
        apply.type = 'submit';
        apply.textContent = 'Apply';
        form.appendChild(apply);

        const count = document.createElement('span');
        count.dataset.adminBulkCount = '';
        count.textContent = '0 selected';
        form.appendChild(count);

        insertBefore.parentNode.insertBefore(form, insertBefore);

        const update = () => {
            const selected = checkboxes.filter((checkbox) => checkbox.checked).length;
            count.textContent = `${selected} selected`;
            selectAll.checked = selected > 0 && selected === checkboxes.length;
            selectAll.indeterminate = selected > 0 && selected < checkboxes.length;
        };

        selectAll.addEventListener('change', () => {
            checkboxes.forEach((checkbox) => { checkbox.checked = selectAll.checked; });
            update();
        });
        checkboxes.forEach((checkbox) => checkbox.addEventListener('change', update));

        form.addEventListener('submit', (event) => {
            const selected = checkboxes.filter((checkbox) => checkbox.checked).length;
            if (!selected) {
                event.preventDefault();
                window.alert('Select at least one record first.');
                return;
            }
            const selectedAction = actionSelect.value;
            if (destructive.includes(selectedAction)) {
                const wording = selectedAction === 'archive'
                    ? `Archive ${selected} selected record${selected === 1 ? '' : 's'}?`
                    : (selectedAction === 'delete' || selectedAction === 'permanent_delete')
                        ? `Permanently delete ${selected} selected record${selected === 1 ? '' : 's'}? This cannot be undone.`
                        : `Move ${selected} selected record${selected === 1 ? '' : 's'} to trash?`;
                if (!window.confirm(wording)) event.preventDefault();
            }
        });
        update();
    };

    const wireProductMedia = () => {
        const root = document.querySelector('[data-media-manager]');
        if (!root) return;
        const grid = root.querySelector('[data-mm-grid]');
        const productId = root.querySelector('#mm-product-select')?.value || root.querySelector('input[name="product_id"]')?.value;
        if (!grid || !productId) return;

        const checkboxes = [];
        root.querySelectorAll('[data-mm-card]').forEach((card) => {
            const id = card.querySelector('[data-media-id]')?.dataset.mediaId;
            const preview = card.querySelector('.mm-card-preview');
            if (!id || !preview || preview.querySelector('[data-admin-media-select]')) return;

            const label = document.createElement('label');
            label.className = 'admin-card-select';
            label.title = 'Select media';
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.name = 'ids[]';
            checkbox.value = id;
            checkbox.setAttribute('form', 'product-media-bulk-form');
            checkbox.setAttribute('aria-label', 'Select this media item');
            checkbox.dataset.adminMediaSelect = '';
            label.appendChild(checkbox);
            preview.appendChild(label);
            checkboxes.push(checkbox);
        });

        makeToolbar({
            root,
            insertBefore: grid,
            formId: 'product-media-bulk-form',
            action: '/admin/table-actions/product-media',
            hidden: { product_id: productId },
            options: [
                ['activate', 'Activate selected'],
                ['deactivate', 'Deactivate selected'],
                ['approve', 'Approve selected'],
                ['reject', 'Reject selected'],
                ['delete', 'Delete selected'],
            ],
            checkboxes,
            destructive: ['delete'],
        });
    };

    const wireSiteMedia = () => {
        const root = document.querySelector('[data-site-media-library]');
        if (!root) return;
        const grid = root.querySelector('.media-library-grid');
        if (!grid) return;
        const isTrash = window.location.pathname.replace(/\/$/, '').endsWith('/trash');
        const uuidPattern = /[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}/i;
        const checkboxes = [];

        root.querySelectorAll('[data-media-asset-card]').forEach((card) => {
            const preview = card.querySelector('.media-asset-preview');
            const candidates = [...card.querySelectorAll('[action],[href]')]
                .map((element) => element.getAttribute('action') || element.getAttribute('href') || '');
            const uuid = candidates.map((value) => value.match(uuidPattern)?.[0]).find(Boolean);
            if (!uuid || !preview || preview.querySelector('[data-admin-site-media-select]')) return;

            const label = document.createElement('label');
            label.className = 'admin-card-select';
            label.title = 'Select media asset';
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.name = 'ids[]';
            checkbox.value = uuid;
            checkbox.setAttribute('form', 'site-media-bulk-form');
            checkbox.setAttribute('aria-label', 'Select this public media asset');
            checkbox.dataset.adminSiteMediaSelect = '';
            label.appendChild(checkbox);
            preview.appendChild(label);
            checkboxes.push(checkbox);
        });

        makeToolbar({
            root,
            insertBefore: grid,
            formId: 'site-media-bulk-form',
            action: '/admin/table-actions/site-media',
            options: isTrash
                ? [['restore', 'Restore selected'], ['permanent_delete', 'Permanently delete selected']]
                : [['approve', 'Approve selected'], ['reject', 'Reject selected'], ['archive', 'Archive selected'], ['trash', 'Move selected to trash']],
            checkboxes,
            destructive: isTrash ? ['permanent_delete'] : ['trash'],
        });
    };

    const wirePages = () => {
        const root = document.querySelector('[data-pages-screen]');
        if (!root) return;
        const tableWrap = root.querySelector('.pages-table-wrap');
        const checkboxes = [...root.querySelectorAll('[data-page-select]')];
        if (!tableWrap || !checkboxes.length) return;
        checkboxes.forEach((checkbox) => {
            checkbox.name = 'ids[]';
            checkbox.setAttribute('form', 'pages-bulk-form');
        });
        const params = new URLSearchParams(window.location.search);
        const isTrash = params.get('tab') === 'trash' || params.get('status') === 'trash';
        makeToolbar({
            root,
            insertBefore: tableWrap,
            formId: 'pages-bulk-form',
            action: '/admin/pages/bulk-actions',
            options: isTrash
                ? [['restore', 'Restore selected'], ['permanent_delete', 'Permanently delete selected']]
                : [['publish', 'Publish selected'], ['unpublish', 'Unpublish selected'], ['archive', 'Archive selected'], ['trash', 'Move selected to trash']],
            checkboxes,
            destructive: isTrash ? ['permanent_delete'] : ['trash'],
        });
        const existingSelectAll = root.querySelector('[data-pages-select-all]');
        existingSelectAll?.addEventListener('change', () => {
            checkboxes.forEach((checkbox) => {
                checkbox.checked = existingSelectAll.checked;
                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
        checkboxes.forEach((checkbox) => checkbox.addEventListener('change', () => {
            const selected = checkboxes.filter((item) => item.checked).length;
            if (existingSelectAll) {
                existingSelectAll.checked = selected > 0 && selected === checkboxes.length;
                existingSelectAll.indeterminate = selected > 0 && selected < checkboxes.length;
            }
        }));
    };

    const wireDiscounts = () => {
        const root = document.querySelector('.dc-page');
        if (!root) return;
        const tableWrap = root.querySelector('.dc-table-wrap');
        if (!tableWrap) return;
        const checkboxes = [];
        root.querySelectorAll('.dc-table tbody tr').forEach((row) => {
            const edit = [...row.querySelectorAll('a[href]')].find((link) => /\/admin\/resource\/discounts-coupons\/\d+\/edit(?:$|[?#])/.test(link.href));
            const id = edit?.href.match(/\/discounts-coupons\/(\d+)\/edit/)?.[1];
            const firstCell = row.querySelector('td');
            if (!id || !firstCell) return;
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.name = 'ids[]';
            checkbox.value = id;
            checkbox.className = 'dc-bulk-check';
            checkbox.setAttribute('form', 'discounts-bulk-form');
            checkbox.setAttribute('aria-label', 'Select discount or coupon');
            firstCell.prepend(checkbox);
            checkboxes.push(checkbox);
        });
        makeToolbar({
            root,
            insertBefore: tableWrap,
            formId: 'discounts-bulk-form',
            action: '/admin/table-actions/discounts',
            options: [['activate', 'Activate selected'], ['pause', 'Pause selected'], ['expire', 'Expire selected'], ['archive', 'Archive selected']],
            checkboxes,
            destructive: ['archive'],
        });
    };

    wireProductMedia();
    wireSiteMedia();
    wirePages();
    wireDiscounts();
})();
