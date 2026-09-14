(() => {
    const page = document.querySelector('[data-shop-page]');
    if (!page) return;

    // Shop has dense page-specific styling, so enforce the keyboard focus ring
    // at the element level. Inline !important declarations outrank any later
    // stylesheet reset while the :focus-visible check keeps mouse focus clean.
    const focusableSelector = 'a[href],button,input,select,textarea,summary,[role="button"],[tabindex]:not([tabindex="-1"])';
    const focusProxy = (element) => {
        if (!(element instanceof HTMLInputElement) || !['checkbox', 'radio'].includes(element.type)) return null;
        const proxy = element.nextElementSibling;
        return proxy instanceof HTMLElement ? proxy : null;
    };
    const clearFocusRing = (element) => {
        if (!(element instanceof HTMLElement)) return;
        element.style.removeProperty('outline');
        element.style.removeProperty('outline-offset');
        const proxy = focusProxy(element);
        if (proxy) proxy.style.removeProperty('box-shadow');
    };
    page.addEventListener('focusin', (event) => {
        const element = event.target;
        if (!(element instanceof HTMLElement) || !element.matches(focusableSelector)) return;
        if (!element.matches(':focus-visible')) return;

        element.style.setProperty('outline', '2px solid #8cc63e', 'important');
        element.style.setProperty('outline-offset', '3px', 'important');

        const proxy = focusProxy(element);
        if (proxy) proxy.style.setProperty('box-shadow', '0 0 0 3px #8cc63e', 'important');
    }, true);
    page.addEventListener('focusout', (event) => clearFocusRing(event.target), true);

    const filters = page.querySelector('[data-shop-filters]');
    const filterToggle = page.querySelector('[data-shop-filter-toggle]');
    if (filters && filterToggle) {
        filterToggle.addEventListener('click', () => {
            const open = filters.classList.toggle('is-open');
            filterToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    const form = document.getElementById('shop-filter-form');
    const sort = page.querySelector('[data-shop-sort]');
    const perPage = page.querySelector('[data-shop-per-page]');
    const sortHidden = page.querySelector('[data-shop-sort-hidden]');
    const perPageHidden = page.querySelector('[data-shop-per-page-hidden]');

    if (form && sort && sortHidden) {
        sort.addEventListener('change', () => {
            sortHidden.value = sort.value;
            form.submit();
        });
    }

    if (form && perPage && perPageHidden) {
        perPage.addEventListener('change', () => {
            perPageHidden.value = perPage.value;
            form.submit();
        });
    }

    const range = page.querySelector('.shop-price-range');
    const mirror = page.querySelector('[data-shop-max-price-mirror]');
    const priceLabel = document.getElementById('shop-price-value');
    if (range && mirror) {
        mirror.addEventListener('input', () => {
            const raw = Number(mirror.value || range.max || 0);
            const value = Math.max(Number(range.min || 0), Math.min(Number(range.max || raw), raw));
            range.value = String(value);
            if (priceLabel) priceLabel.textContent = `€${value}`;
        });
        range.addEventListener('input', () => {
            mirror.value = range.value;
        });
    }

    const products = page.querySelector('[data-shop-products]');
    const grid = page.querySelector('[data-shop-grid]');
    const list = page.querySelector('[data-shop-list]');
    if (products && grid && list) {
        const applyLayout = (mode) => {
            const isList = mode === 'list';
            products.classList.toggle('is-list', isList);
            grid.classList.toggle('is-active', !isList);
            list.classList.toggle('is-active', isList);
            try { localStorage.setItem('emerald-shop-layout', mode); } catch (_) {}
        };
        grid.addEventListener('click', () => applyLayout('grid'));
        list.addEventListener('click', () => applyLayout('list'));
        try {
            if (localStorage.getItem('emerald-shop-layout') === 'list') applyLayout('list');
        } catch (_) {}
    }
})();
