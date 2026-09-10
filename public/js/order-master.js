(function () {
    'use strict';
    var page = document.querySelector('[data-order-master]');
    if (!page) return;

    function modal(name, open) {
        var el = document.querySelector('[data-modal="' + name + '"]');
        if (!el) return;
        el.classList.toggle('is-open', open);
        el.setAttribute('aria-hidden', open ? 'false' : 'true');
        document.body.style.overflow = open ? 'hidden' : '';
    }

    document.querySelectorAll('[data-modal-open]').forEach(function (button) {
        button.addEventListener('click', function () { modal(button.dataset.modalOpen, true); });
    });
    document.querySelectorAll('[data-modal-close]').forEach(function (button) {
        button.addEventListener('click', function () {
            var parent = button.closest('[data-modal]');
            if (parent) modal(parent.dataset.modal, false);
        });
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') document.querySelectorAll('[data-modal].is-open').forEach(function (el) { modal(el.dataset.modal, false); });
    });

    document.querySelectorAll('[data-advanced-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var panel = document.querySelector('[data-advanced-panel]');
            if (!panel) return;
            panel.classList.toggle('is-open');
            if (panel.classList.contains('is-open')) panel.scrollIntoView({behavior: 'smooth', block: 'nearest'});
        });
    });

    var selectAll = document.querySelector('[data-select-all]');
    if (selectAll) selectAll.addEventListener('change', function () {
        document.querySelectorAll('.om-table tbody input[type="checkbox"]').forEach(function (box) { box.checked = selectAll.checked; });
    });

    document.querySelectorAll('[data-print-page]').forEach(function (button) {
        button.addEventListener('click', function () { window.print(); });
    });

    var groups = Array.prototype.slice.call(document.querySelectorAll('.admin-nav-group'));
    var onlineSales = groups.find(function (group) {
        var summary = group.querySelector(':scope > summary');
        return summary && summary.textContent.trim().indexOf('ONLINE SALES') === 0;
    });
    var legacyOrderGroup = groups.find(function (group) {
        var summary = group.querySelector(':scope > summary');
        return summary && summary.textContent.trim().indexOf('ORDER MANAGEMENT') === 0;
    });
    if (onlineSales && legacyOrderGroup) {
        var target = onlineSales.querySelector('.admin-nav-items');
        var existing = target && Array.prototype.slice.call(target.children).find(function (node) { return node.textContent.indexOf('Orders (6 Categories)') !== -1; });
        if (existing && !target.querySelector('.om-side-nav-generated')) {
            existing.style.display = 'none';
            var wrap = document.createElement('div');
            wrap.className = 'om-side-nav-generated admin-nav-subitems';
            var overview = document.createElement('a');
            overview.className = 'active'; overview.href = page.dataset.overviewUrl || window.location.pathname; overview.textContent = 'Order Master Overview';
            wrap.appendChild(overview);
            legacyOrderGroup.querySelectorAll('.admin-nav-items > a').forEach(function (link) { wrap.appendChild(link.cloneNode(true)); });
            target.insertBefore(wrap, existing.nextSibling);
            legacyOrderGroup.style.display = 'none';
        }
    }
})();
