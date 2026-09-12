(function () {
    'use strict';

    var page = document.querySelector('[data-orders-dashboard]');
    if (!page) return;

    function modal(name, open) {
        var element = document.querySelector('[data-order-modal="' + name + '"]');
        if (!element) return;
        element.classList.toggle('is-open', open);
        element.setAttribute('aria-hidden', open ? 'false' : 'true');
        document.body.style.overflow = open ? 'hidden' : '';
        if (open) {
            var first = element.querySelector('input:not([type="hidden"]), select, textarea');
            if (first) window.setTimeout(function () { first.focus(); }, 30);
        }
    }

    document.querySelectorAll('[data-order-modal-open]').forEach(function (button) {
        button.addEventListener('click', function () { modal(button.dataset.orderModalOpen, true); });
    });
    document.querySelectorAll('[data-order-modal-close]').forEach(function (button) {
        button.addEventListener('click', function () {
            var parent = button.closest('[data-order-modal]');
            if (parent) modal(parent.dataset.orderModal, false);
        });
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') document.querySelectorAll('[data-order-modal].is-open').forEach(function (element) { modal(element.dataset.orderModal, false); });
    });

    document.querySelectorAll('[data-orders-filter-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var drawer = document.querySelector('[data-orders-filter-drawer]');
            if (!drawer) return;
            drawer.classList.toggle('is-open');
            if (drawer.classList.contains('is-open')) drawer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    });

    var selectAll = document.querySelector('[data-orders-select-all]');
    if (selectAll) selectAll.addEventListener('change', function () {
        document.querySelectorAll('.orders-table tbody input[type="checkbox"]').forEach(function (box) { box.checked = selectAll.checked; });
    });

    var perPage = document.querySelector('[data-orders-per-page]');
    if (perPage) perPage.addEventListener('change', function () {
        var url = new URL(window.location.href);
        url.searchParams.set('per_page', perPage.value);
        url.searchParams.delete('page');
        window.location.assign(url.toString());
    });

    document.querySelectorAll('[data-orders-print]').forEach(function (button) {
        button.addEventListener('click', function () { window.print(); });
    });
}());
