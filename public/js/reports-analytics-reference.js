document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-report-analytics-root]');
    if (!root) return;

    const panel = root.querySelector('[data-ra-filter-panel]');
    root.querySelectorAll('[data-ra-filter-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!panel) return;
            panel.classList.toggle('is-open');
            panel.removeAttribute('data-filter-open');
            button.setAttribute('aria-expanded', panel.classList.contains('is-open') ? 'true' : 'false');
        });
    });

    root.querySelectorAll('[data-ra-period]').forEach((select) => {
        select.addEventListener('change', () => {
            const url = new URL(window.location.href);
            url.searchParams.set('breakdown', select.value.toLowerCase());
            window.location.assign(url.toString());
        });
    });

    root.querySelectorAll('form[method="post"]').forEach((form) => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('button[type="submit"]');
            if (!button || button.dataset.busy) return;
            button.dataset.busy = '1';
            button.dataset.label = button.textContent;
            button.textContent = 'Working…';
            button.setAttribute('aria-disabled', 'true');
        });
    });
});
