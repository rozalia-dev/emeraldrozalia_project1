(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-settings-import]').forEach(function (input) {
            input.addEventListener('change', function () {
                if (input.files.length && input.form) input.form.submit();
            });
        });

        document.querySelectorAll('[data-settings-form]').forEach(function (form) {
            form.addEventListener('submit', function () {
                var button = form.querySelector('button[type="submit"]');
                if (!button) return;
                button.disabled = true;
                button.dataset.originalLabel = button.innerHTML;
                button.innerHTML = 'Saving…';
            });
        });

        document.querySelectorAll('[data-settings-confirm]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                if (!window.confirm(button.dataset.settingsConfirm)) event.preventDefault();
            });
        });

        var search = document.querySelector('[data-settings-search]');
        if (search) {
            search.addEventListener('input', function () {
                var query = search.value.trim().toLowerCase();
                document.querySelectorAll('[data-settings-category]').forEach(function (card) {
                    card.hidden = query !== '' && card.dataset.settingsText.indexOf(query) === -1;
                });
            });
        }
    });
}());
