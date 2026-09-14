(() => {
    const isExportUrl = (value) => {
        try {
            const url = new URL(value, window.location.origin);
            return url.origin === window.location.origin
                && url.pathname.split('/').filter(Boolean).includes('export');
        } catch (error) {
            return false;
        }
    };

    const exportKey = (value) => {
        const url = new URL(value, window.location.origin);
        url.searchParams.delete('format');
        const params = [...url.searchParams.entries()].sort(([a], [b]) => a.localeCompare(b));
        url.search = '';
        params.forEach(([key, val]) => url.searchParams.append(key, val));
        return url.pathname + '?' + url.searchParams.toString();
    };

    const withFormat = (value, format) => {
        const url = new URL(value, window.location.origin);
        url.searchParams.set('format', format);
        return url.pathname + url.search + url.hash;
    };

    const makeOption = (source, format) => {
        const option = source.cloneNode(true);
        option.removeAttribute('id');
        option.href = withFormat(source.href, format);
        option.dataset.adminExportFormat = format;
        option.classList.add('admin-export-format-option', `admin-export-format-option--${format}`);
        option.setAttribute('aria-label', `Export ${format.toUpperCase()}`);
        option.setAttribute('title', `Export ${format.toUpperCase()}`);
        option.textContent = `Export ${format.toUpperCase()}`;
        return option;
    };

    const enhanceLinks = () => {
        const links = [...document.querySelectorAll('a[href]')].filter((link) => isExportUrl(link.href));
        const groups = new Map();

        links.forEach((link) => {
            if (link.closest('[data-admin-export-format-pair]')) return;
            const key = exportKey(link.href);
            if (!groups.has(key)) groups.set(key, []);
            groups.get(key).push(link);
        });

        groups.forEach((group) => {
            if (!group.length) return;
            const source = group[0];
            const wrapper = document.createElement('span');
            wrapper.className = 'admin-export-format-pair';
            wrapper.dataset.adminExportFormatPair = '';
            wrapper.setAttribute('role', 'group');
            wrapper.setAttribute('aria-label', 'Export options');
            wrapper.append(makeOption(source, 'pdf'), makeOption(source, 'csv'));
            source.replaceWith(wrapper);
            group.slice(1).forEach((duplicate) => duplicate.remove());
        });
    };

    const enhanceForms = () => {
        document.querySelectorAll('form[action]').forEach((form) => {
            if (!isExportUrl(form.action) || form.dataset.adminExportFormats === '1') return;
            if ((form.getAttribute('method') || 'get').toLowerCase() !== 'get') return;

            const submit = form.querySelector('button[type="submit"], input[type="submit"], button:not([type])');
            if (!submit) return;

            form.dataset.adminExportFormats = '1';
            form.querySelectorAll('input[name="format"]').forEach((input) => input.remove());
            const wrapper = document.createElement('span');
            wrapper.className = 'admin-export-format-pair';
            wrapper.dataset.adminExportFormatPair = '';
            wrapper.setAttribute('role', 'group');
            wrapper.setAttribute('aria-label', 'Export options');

            ['pdf', 'csv'].forEach((format) => {
                const button = submit.cloneNode(true);
                button.removeAttribute('id');
                button.setAttribute('type', 'submit');
                button.setAttribute('name', 'format');
                button.setAttribute('value', format);
                button.classList.add('admin-export-format-option', `admin-export-format-option--${format}`);
                button.textContent = `Export ${format.toUpperCase()}`;
                wrapper.append(button);
            });

            submit.replaceWith(wrapper);
        });
    };

    const enhance = () => {
        enhanceLinks();
        enhanceForms();
    };

    enhance();
    document.addEventListener('admin:content-updated', enhance);
})();
