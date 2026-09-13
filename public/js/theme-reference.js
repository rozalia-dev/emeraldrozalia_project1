(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var page = document.querySelector('[data-theme-page]');
        if (!page) return;

        var preview = page.querySelector('[data-theme-preview]');
        page.querySelectorAll('[data-theme-token]').forEach(function (input) {
            input.addEventListener('input', function () {
                if (preview && input.dataset.previewVariable) preview.style.setProperty(input.dataset.previewVariable, input.value);
            });
        });

        page.querySelectorAll('[data-theme-color-text]').forEach(function (textInput) {
            var colorInput = page.querySelector('input[type="color"][name="' + textInput.dataset.themeColorText + '"]');
            if (!colorInput) return;
            textInput.addEventListener('input', function () {
                if (/^#[0-9a-f]{6}$/i.test(textInput.value)) {
                    colorInput.value = textInput.value;
                    colorInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });
            colorInput.addEventListener('input', function () { textInput.value = colorInput.value; });
        });

        page.querySelectorAll('[data-theme-confirm]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                if (!window.confirm(button.dataset.themeConfirm)) event.preventDefault();
            });
        });

        page.querySelectorAll('[data-theme-form]').forEach(function (form) {
            form.addEventListener('submit', function () {
                var button = form.querySelector('button[type="submit"]');
                if (!button) return;
                button.disabled = true;
                button.textContent = 'Saving…';
            });
        });
    });
}());
