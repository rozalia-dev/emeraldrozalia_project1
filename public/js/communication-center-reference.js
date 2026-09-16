(() => {
    const root = document.querySelector('[data-cc-root]');
    if (!root) return;

    const installInboxCategoryFilters = () => {
        const filterbar = root.querySelector('.cc-filterbar');
        if (!filterbar || filterbar.querySelector('[data-cc-inbox-category-filters]')) return;

        const form = filterbar.closest('form');
        if (!form) return;
        const filterPath = new URL(form.action, window.location.href).pathname.replace(/\/$/, '');
        if (filterPath !== '/admin/resource/inbox') return;

        const search = filterbar.querySelector('.cc-search');
        if (!search) return;

        const params = new URLSearchParams(window.location.search);
        const pageCategories = [
            ['Shop', 'Shop'],
            ['Collections', 'Collections'],
            ['Catalog', 'Catalog'],
            ['New Arrival', 'New Arrival'],
            ['Corporate Order', 'Corporate Order'],
            ['Bulk Order', 'Bulk Order'],
            ['Franchise Apply', 'Franchise Apply'],
            ['Hiring Apply', 'Hiring Apply'],
            ['Contact Us', 'Contact Us'],
        ];
        const productCategories = [
            ['Baseball Caps', 'Baseball Caps'],
            ['Caps', 'Caps'],
            ['Test Category', 'Test Category'],
            ['Bucket Hats', 'Bucket Hats'],
            ['Snapbacks', 'Snapbacks'],
            ['Irish Traditional Flat Caps', 'Irish Traditional Flat Caps'],
            ['Irish Heritage Hats', 'Irish Heritage Hats'],
            ['Beanies & More', 'Beanies & More'],
            ['GAA Baseball Caps', 'GAA Baseball Caps'],
            ['GAA Bucket Hats', 'GAA Bucket Hats'],
            ['GAA Beanie Hats', 'GAA Beanie Hats'],
            ['Spring Summer 2025', 'Spring Summer 2025'],
            ['Best Sellers', 'Best Sellers'],
            ['New Arrivals', 'New Arrivals'],
            ['Premium Collection', 'Premium Collection'],
            ['Wedding Collection', 'Wedding Collection'],
            ['Corporate Gifting', 'Corporate Gifting'],
            ['Limited Edition', 'Limited Edition'],
            ['Back to College', 'Back to College'],
            ['Gift for Her', 'Gift for Her'],
        ];

        if (!document.querySelector('[data-cc-inbox-category-style]')) {
            const style = document.createElement('style');
            style.dataset.ccInboxCategoryStyle = 'true';
            style.textContent = `
                .cc-inbox-category-filter{min-width:154px;max-width:205px}
                .cc-inbox-category-filter[data-filter-kind="product"]{min-width:188px;max-width:238px}
                @media(max-width:1180px){.cc-inbox-category-filter{flex:1 1 160px;max-width:none}.cc-inbox-category-filter[data-filter-kind="product"]{flex:1 1 210px;max-width:none}}
                @media(max-width:700px){.cc-inbox-category-filter,.cc-inbox-category-filter[data-filter-kind="product"]{width:100%;max-width:none;flex:1 1 100%}}
            `;
            document.head.append(style);
        }

        const createSelect = (name, label, kind, options) => {
            const select = document.createElement('select');
            select.name = name;
            select.className = 'cc-inbox-category-filter';
            select.dataset.filterKind = kind;
            select.setAttribute('aria-label', label);

            const all = document.createElement('option');
            all.value = '';
            all.textContent = label;
            select.append(all);

            options.forEach(([value, text]) => {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = text;
                select.append(option);
            });

            const selected = params.get(name) || '';
            if ([...select.options].some((option) => option.value === selected)) {
                select.value = selected;
            }

            return select;
        };

        const pageSelect = createSelect('page_category', 'All Page Categories', 'page', pageCategories);
        const productSelect = createSelect('product_category', 'All Product Categories', 'product', productCategories);
        pageSelect.dataset.ccInboxCategoryFilters = 'true';

        search.after(pageSelect, productSelect);
    };

    installInboxCategoryFilters();

    const installInboxDateRangeFilter = () => {
        const filterbar = root.querySelector('.cc-filterbar');
        if (!filterbar || !filterbar.querySelector('select[name="channel"]') || filterbar.querySelector('[data-cc-date-range-filter]')) return;

        const form = filterbar.closest('form');
        if (!form) return;
        const filterPath = new URL(form.action, window.location.href).pathname.replace(/\/$/, '');
        if (filterPath !== '/admin/resource/inbox') return;

        const resetLink = [...filterbar.querySelectorAll('a')]
            .find((link) => link.textContent.trim().toLowerCase() === 'reset');
        const currentExportLinks = () => [...filterbar.querySelectorAll('a[href]')]
            .filter((link) => link.classList.contains('cc-export')
                || link.dataset.adminExportFormat
                || /^export\b/i.test(link.textContent.trim()));
        const params = new URLSearchParams(window.location.search);

        if (!document.querySelector('[data-cc-date-range-style]')) {
            const style = document.createElement('style');
            style.dataset.ccDateRangeStyle = 'true';
            style.textContent = `
                .cc-date-range-filter{display:inline-flex;align-items:center;gap:6px;white-space:nowrap}
                .cc-date-range-filter label{display:inline-flex;align-items:center;gap:4px;font-size:7.5px;font-weight:700;color:#3e4943}
                .cc-date-range-filter input[type="date"]{width:120px;min-width:120px;padding:0 6px}
                @media(max-width:1180px){.cc-date-range-filter{flex:1 1 278px}}
                @media(max-width:800px){.cc-date-range-filter{flex:1 1 100%;display:grid;grid-template-columns:1fr 1fr}.cc-date-range-filter label{display:grid;grid-template-columns:auto minmax(0,1fr)}.cc-date-range-filter input[type="date"]{width:100%;min-width:0}}
                @media(max-width:520px){.cc-date-range-filter{grid-template-columns:1fr}.cc-date-range-filter label{grid-template-columns:34px minmax(0,1fr)}}
            `;
            document.head.append(style);
        }

        const group = document.createElement('span');
        group.className = 'cc-date-range-filter';
        group.dataset.ccDateRangeFilter = 'true';
        group.setAttribute('role', 'group');
        group.setAttribute('aria-label', 'Conversation date range');

        const createField = (name, labelText) => {
            const label = document.createElement('label');
            const text = document.createElement('span');
            const input = document.createElement('input');

            text.textContent = labelText;
            input.type = 'date';
            input.name = name;
            input.value = params.get(name) || '';
            input.setAttribute('aria-label', `${labelText} date`);
            input.autocomplete = 'off';

            label.append(text, input);
            group.append(label);
            return input;
        };

        const fromInput = createField('date_from', 'From');
        const toInput = createField('date_to', 'To');

        const exportPair = filterbar.querySelector('[data-admin-export-format-pair]');
        const firstExport = currentExportLinks()[0] || null;
        const insertBefore = exportPair
            || (firstExport?.parentElement === filterbar ? firstExport : firstExport?.closest('[data-admin-export-format-pair]'));
        if (insertBefore?.parentElement === filterbar) {
            filterbar.insertBefore(group, insertBefore);
        } else if (resetLink) {
            resetLink.after(group);
        } else {
            filterbar.append(group);
        }

        const syncExportDates = () => {
            currentExportLinks().forEach((link) => {
                const url = new URL(link.href, window.location.href);
                if (fromInput.value) url.searchParams.set('date_from', fromInput.value);
                else url.searchParams.delete('date_from');
                if (toInput.value) url.searchParams.set('date_to', toInput.value);
                else url.searchParams.delete('date_to');
                link.href = url.toString();
            });
        };

        const validateRange = () => {
            fromInput.max = toInput.value || '';
            toInput.min = fromInput.value || '';
            const invalid = Boolean(fromInput.value && toInput.value && fromInput.value > toInput.value);
            toInput.setCustomValidity(invalid ? 'To date must be on or after From date.' : '');
            syncExportDates();
        };

        fromInput.addEventListener('change', validateRange);
        toInput.addEventListener('change', validateRange);
        validateRange();
    };

    installInboxDateRangeFilter();

    const decode = (value) => {
        try {
            const bytes = Uint8Array.from(atob(value), (character) => character.charCodeAt(0));
            return JSON.parse(new TextDecoder().decode(bytes));
        } catch (_) {
            return {};
        }
    };

    const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    root.querySelectorAll('[data-cc-kpi]').forEach((card) => {
        const output = card.querySelector('[data-cc-kpi-value]');
        const target = Number(card.dataset.ccValue);
        if (!output || !Number.isFinite(target)) return;
        const format = new Intl.NumberFormat();
        if (reducedMotion || target === 0) {
            output.textContent = format.format(target);
            return;
        }
        const started = performance.now();
        const duration = 600;
        const tick = (now) => {
            const progress = Math.min(1, (now - started) / duration);
            const eased = 1 - ((1 - progress) ** 3);
            output.textContent = format.format(Math.round(target * eased));
            if (progress < 1) window.requestAnimationFrame(tick);
        };
        window.requestAnimationFrame(tick);
    });

    const dialog = root.querySelector('[data-cc-dialog]');
    if (dialog) {
        const form = dialog.querySelector('[data-cc-form]');
        const method = form?.querySelector('[data-cc-method]');
        const title = dialog.querySelector('[data-cc-dialog-title]');
        const createButtons = [...root.querySelectorAll('[data-cc-create]')];

        const editableFields = () => [...form.querySelectorAll('input, select, textarea')]
            .filter((field) => field.name && !['_token', '_method'].includes(field.name));

        const reset = () => {
            form.reset();
            if (method) method.disabled = true;
            form.action = form.dataset.storeUrl;
            editableFields().forEach((field) => { field.disabled = false; });
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
                field.value = field.type === 'datetime-local' ? normalizeDateTime(raw) : (raw ?? '');
            });
        };

        createButtons.forEach((createButton) => createButton.addEventListener('click', () => {
            reset();
            title.textContent = createButton.textContent.trim().replace(/^\+\s*/, '');
            dialog.showModal();
        }));

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

        if (root.dataset.ccOpenCreate === 'true' && createButtons[0]) {
            window.requestAnimationFrame(() => createButtons[0].click());
        }

        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close();
        });
    }

    root.querySelectorAll('[data-cc-close]').forEach((button) => {
        button.addEventListener('click', () => button.closest('dialog')?.close());
    });

    root.querySelectorAll('[data-cc-message-mode]').forEach((button) => {
        button.addEventListener('click', () => {
            const composer = button.closest('.cc-composer');
            const mode = button.dataset.ccMessageMode || 'reply';
            composer?.querySelectorAll('[data-cc-message-mode]').forEach((tab) => {
                const active = tab === button;
                tab.classList.toggle('active', active);
                tab.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            const input = composer?.querySelector('[data-cc-message-mode-input]');
            const body = composer?.querySelector('[data-cc-message-body]');
            const submit = composer?.querySelector('button[type="submit"]');
            if (input) input.value = mode;
            if (body) body.placeholder = mode === 'internal_note' ? 'Add an internal note for cPanel operators...' : 'Type your message...';
            if (submit) submit.textContent = mode === 'internal_note' ? 'Add Note' : 'Send';
        });
    });

    const auditDialog = root.querySelector('[data-cc-audit-dialog]');
    const auditContent = auditDialog?.querySelector('[data-cc-audit-content]');
    const appendField = (parent, label, value) => {
        const wrapper = document.createElement('div');
        const term = document.createElement('dt');
        const detail = document.createElement('dd');
        term.textContent = label;
        detail.textContent = value ?? '—';
        wrapper.append(term, detail);
        parent.append(wrapper);
    };

    root.querySelectorAll('[data-cc-audit]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!auditDialog || !auditContent) return;
            const payload = decode(button.dataset.ccAudit);
            auditContent.replaceChildren();
            const details = document.createElement('dl');
            appendField(details, 'Audit UUID', payload.uuid);
            appendField(details, 'Action', payload.action);
            appendField(details, 'Subject', `${payload.subject || 'System'}${payload.subject_id ? ` #${payload.subject_id}` : ''}`);
            appendField(details, 'Request ID', payload.request_id);
            appendField(details, 'IP address', payload.ip_address);
            auditContent.append(details);
            const changes = document.createElement('pre');
            changes.textContent = JSON.stringify({ before: payload.before || null, after: payload.after || null }, null, 2);
            auditContent.append(changes);
            auditDialog.showModal();
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        root.querySelectorAll('dialog[open]').forEach((openDialog) => openDialog.close());
    });
})();
