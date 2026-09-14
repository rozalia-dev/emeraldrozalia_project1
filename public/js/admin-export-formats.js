(() => {
    const isExportUrl = (value) => {
        try {
            const url = new URL(value, window.location.origin);
            return url.origin === window.location.origin
                && url.pathname.split('/').filter(Boolean).some((segment) => segment === 'export' || segment.endsWith('-export') || segment.startsWith('export-'));
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
        const processed = new Set();

        links.forEach((source) => {
            if (processed.has(source) || !source.isConnected || source.closest('[data-admin-export-format-pair]')) return;

            const key = exportKey(source.href);
            const siblings = source.parentElement
                ? [...source.parentElement.children].filter((candidate) => candidate.matches?.('a[href]') && isExportUrl(candidate.href) && exportKey(candidate.href) === key)
                : [source];
            siblings.forEach((candidate) => processed.add(candidate));

            const wrapper = document.createElement('span');
            wrapper.className = 'admin-export-format-pair';
            wrapper.dataset.adminExportFormatPair = '';
            wrapper.setAttribute('role', 'group');
            wrapper.setAttribute('aria-label', 'Export options');
            wrapper.append(makeOption(source, 'pdf'), makeOption(source, 'csv'));
            source.replaceWith(wrapper);
            siblings.filter((duplicate) => duplicate !== source).forEach((duplicate) => duplicate.remove());
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

    let scheduled = false;
    const enhance = () => {
        scheduled = false;
        enhanceLinks();
        enhanceForms();
    };
    const scheduleEnhance = () => {
        if (scheduled) return;
        scheduled = true;
        queueMicrotask(enhance);
    };

    enhance();
    document.addEventListener('admin:content-updated', scheduleEnhance);
    if (document.body && 'MutationObserver' in window) {
        new MutationObserver((mutations) => {
            if (mutations.some((mutation) => mutation.addedNodes.length > 0)) scheduleEnhance();
        }).observe(document.body, {childList: true, subtree: true});
    }
})();
