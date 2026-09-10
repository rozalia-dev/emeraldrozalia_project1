(function () {
    'use strict';

    var root = document.querySelector('[data-sales-report-root]');
    if (!root) return;

    root.querySelectorAll('[data-sales-report-reset]').forEach(function (link) {
        link.addEventListener('click', function () {
            window.location.assign(link.getAttribute('href'));
        });
    });

    root.querySelectorAll('a[href="#sales-report-save-view"]').forEach(function (link) {
        link.addEventListener('click', function () {
            window.setTimeout(function () {
                var input = document.getElementById('sales-report-view-name');
                if (input) input.focus();
            }, 30);
        });
    });
}());
