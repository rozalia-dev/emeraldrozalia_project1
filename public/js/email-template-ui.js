(() => {
    const addStyles = () => {
        if (document.querySelector('[data-email-template-ui-style]')) return;
        const style = document.createElement('style');
        style.dataset.emailTemplateUiStyle = 'true';
        style.textContent = `
            .email-template-picker{display:grid;gap:5px}
            .email-template-picker label,.email-template-ui label{font-size:12px;font-weight:800;color:#415047}
            .email-template-picker select,.email-template-ui select,.email-template-ui input{width:100%;box-sizing:border-box;border:1px solid #cfd8d1;border-radius:8px;padding:9px 10px;background:#fff}
            .email-template-picker small,.email-template-hint,.email-template-file-state{display:block;color:#66746c;font-size:11px;line-height:1.45;margin-top:4px}
            .email-template-attachment-note{border:1px solid #dce6df;background:#f7faf8;border-radius:8px;padding:9px 10px;font-size:12px;color:#415047}
            .email-template-role-select{min-height:150px}
            .email-template-ui-field[hidden]{display:none!important}
            .email-template-ui .cc-span-2{grid-column:1/-1}
        `;
        document.head.appendChild(style);
    };

    const requestJson = async (url) => {
        const response = await fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!response.ok) throw new Error(`Request failed with ${response.status}`);
        return response.json();
    };

    const decodePayload = (value) => {
        try {
            const bytes = Uint8Array.from(atob(value || ''), (character) => character.charCodeAt(0));
            return JSON.parse(new TextDecoder().decode(bytes));
        } catch (_) {
            return {};
        }
    };

    const attachmentLabel = (template) => {
        const mode = template.attachment_mode || 'none';
        const file = template.attachment_file_name || '';
        const url = template.attachment_url || '';
        if (mode === 'file_and_link') return `Attachment: ${file || 'file'} + ${url || 'link'}`;
        if (mode === 'file') return `Attachment: ${file || 'file'}`;
        if (mode === 'link') return `Link: ${url || 'configured link'}`;
        const preference = template.attachment_preference || 'none';
        if (preference !== 'none') return `Recommended attachment mode: ${preference.replaceAll('_', ' ')}. Configure the actual file/link in Email Templates before sending.`;
        return 'No template attachment.';
    };

    const wireMailbox = async () => {
        const root = document.querySelector('.email-mailbox');
        if (!root) return;
        addStyles();

        let templates = [];
        try {
            const payload = await requestJson('/admin/communication-center/email/templates/available');
            templates = Array.isArray(payload.templates) ? payload.templates : [];
        } catch (error) {
            console.error('Unable to load email templates.', error);
            return;
        }

        const forms = [...root.querySelectorAll('form.email-form')].filter((form) => {
            const action = form.getAttribute('action') || '';
            return action.includes('/communication-center/email/compose')
                || action.includes('/communication-center/email/drafts/')
                || action.includes('/reply-mailbox');
        });

        forms.forEach((form) => {
            if (form.querySelector('[data-email-template-picker]')) return;
            const body = form.querySelector('textarea[name="body"]');
            if (!body) return;
            const subject = form.querySelector('input[name="subject"]');

            const wrapper = document.createElement('div');
            wrapper.className = 'email-template-picker';
            wrapper.dataset.emailTemplatePicker = 'true';

            const label = document.createElement('label');
            label.textContent = 'Email Template';
            const select = document.createElement('select');
            select.name = 'template_uuid';
            select.setAttribute('aria-label', 'Email template');
            const blank = document.createElement('option');
            blank.value = '';
            blank.textContent = 'No template / write manually';
            select.appendChild(blank);
            templates.forEach((template) => {
                const option = document.createElement('option');
                option.value = template.uuid;
                option.textContent = `${template.name}${template.category ? ` — ${template.category}` : ''}`;
                select.appendChild(option);
            });

            const note = document.createElement('div');
            note.className = 'email-template-attachment-note';
            note.textContent = templates.length
                ? 'Select a role-approved template to fill the message.'
                : 'No active email templates are available for your assigned role.';

            label.appendChild(select);
            wrapper.append(label, note);
            const anchor = subject?.closest('div') || body.closest('div') || body;
            anchor.parentNode.insertBefore(wrapper, anchor);

            select.addEventListener('change', () => {
                const template = templates.find((item) => item.uuid === select.value);
                if (!template) {
                    note.textContent = 'No template selected.';
                    return;
                }
                if (subject) subject.value = template.subject || '';
                body.value = template.body || '';
                note.textContent = attachmentLabel(template);
                body.dispatchEvent(new Event('input', { bubbles: true }));
            });
        });
    };

    const wireTemplateAdmin = async () => {
        const root = document.querySelector('[data-cc-root]');
        const isTemplatePage = window.location.pathname.replace(/\/$/, '') === '/admin/resource/email-templates';
        if (!root || !isTemplatePage) return;
        addStyles();

        const form = root.querySelector('[data-cc-form]');
        const grid = form?.querySelector('.cc-form-grid');
        if (!form || !grid || form.querySelector('[data-email-template-admin-fields]')) return;

        let roleOptions = {};
        try {
            const payload = await requestJson('/admin/communication-center/email-templates/editor-options');
            roleOptions = payload.role_options || {};
        } catch (error) {
            console.error('Unable to load email template editor options.', error);
        }

        form.enctype = 'multipart/form-data';
        form.classList.add('email-template-ui');

        const marker = document.createElement('div');
        marker.dataset.emailTemplateAdminFields = 'true';
        marker.className = 'cc-span-2';
        marker.hidden = true;
        grid.appendChild(marker);

        const rolesLabel = document.createElement('label');
        rolesLabel.className = 'cc-span-2';
        rolesLabel.textContent = 'Available to Roles';
        const rolesPresent = document.createElement('input');
        rolesPresent.type = 'hidden';
        rolesPresent.name = 'allowed_roles_present';
        rolesPresent.value = '1';
        const roles = document.createElement('select');
        roles.name = 'allowed_roles[]';
        roles.multiple = true;
        roles.className = 'email-template-role-select';
        Object.entries(roleOptions).forEach(([key, label]) => {
            const option = document.createElement('option');
            option.value = key;
            option.textContent = label;
            roles.appendChild(option);
        });
        const rolesHint = document.createElement('small');
        rolesHint.className = 'email-template-hint';
        rolesHint.textContent = 'Select one or more roles. Super Admin can manage the full library. Leave all unselected only for a deliberately shared template.';
        rolesLabel.append(rolesPresent, roles, rolesHint);

        const modeLabel = document.createElement('label');
        modeLabel.textContent = 'Attachment Mode';
        const mode = document.createElement('select');
        mode.name = 'attachment_mode';
        [['none', 'None'], ['file', 'File'], ['link', 'Link'], ['file_and_link', 'File + Link']].forEach(([value, text]) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = text;
            mode.appendChild(option);
        });
        const preference = document.createElement('small');
        preference.className = 'email-template-hint';
        modeLabel.append(mode, preference);

        const labelField = document.createElement('label');
        labelField.className = 'email-template-ui-field';
        labelField.textContent = 'Attachment / Link Label';
        const attachmentLabelInput = document.createElement('input');
        attachmentLabelInput.name = 'attachment_label';
        attachmentLabelInput.maxLength = 180;
        attachmentLabelInput.placeholder = 'Invoice, Media Kit, Application Portal...';
        labelField.appendChild(attachmentLabelInput);

        const linkField = document.createElement('label');
        linkField.className = 'email-template-ui-field';
        linkField.textContent = 'Link URL';
        const url = document.createElement('input');
        url.name = 'attachment_url';
        url.type = 'url';
        url.maxLength = 2048;
        url.placeholder = 'https://...';
        linkField.appendChild(url);

        const fileField = document.createElement('label');
        fileField.className = 'email-template-ui-field cc-span-2';
        fileField.textContent = 'Attachment File';
        const file = document.createElement('input');
        file.name = 'attachment_file';
        file.type = 'file';
        file.accept = '.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png,.webp,.zip';
        const fileState = document.createElement('small');
        fileState.className = 'email-template-file-state';
        fileState.textContent = 'Maximum 15 MB. Stored privately and attached by the mail worker.';
        const removeWrap = document.createElement('span');
        const remove = document.createElement('input');
        remove.type = 'checkbox';
        remove.name = 'remove_attachment_file';
        remove.value = '1';
        removeWrap.append(remove, document.createTextNode(' Remove existing file reference'));
        fileField.append(file, fileState, removeWrap);

        marker.before(rolesLabel, modeLabel, labelField, linkField, fileField);

        const syncVisibility = () => {
            const selected = mode.value;
            const needsFile = selected === 'file' || selected === 'file_and_link';
            const needsLink = selected === 'link' || selected === 'file_and_link';
            fileField.hidden = !needsFile;
            linkField.hidden = !needsLink;
            labelField.hidden = selected === 'none';
            url.required = needsLink;
        };
        mode.addEventListener('change', syncVisibility);
        syncVisibility();

        const resetCustom = () => {
            [...roles.options].forEach((option) => { option.selected = false; });
            mode.value = 'none';
            attachmentLabelInput.value = '';
            url.value = '';
            file.value = '';
            remove.checked = false;
            preference.textContent = '';
            fileState.textContent = 'Maximum 15 MB. Stored privately and attached by the mail worker.';
            syncVisibility();
        };

        root.querySelectorAll('[data-cc-create]').forEach((button) => {
            button.addEventListener('click', () => window.setTimeout(resetCustom, 0));
        });

        root.querySelectorAll('[data-cc-edit]').forEach((button) => {
            button.addEventListener('click', () => window.setTimeout(() => {
                const payload = decodePayload(button.dataset.ccEdit);
                const allowed = Array.isArray(payload._roles) ? payload._roles : [];
                [...roles.options].forEach((option) => { option.selected = allowed.includes(option.value); });

                const attachment = payload._attachment && typeof payload._attachment === 'object' ? payload._attachment : {};
                mode.value = attachment.mode || 'none';
                attachmentLabelInput.value = attachment.label || '';
                url.value = attachment.url || '';
                file.value = '';
                remove.checked = false;
                fileState.textContent = attachment.file_name
                    ? `Existing private file: ${attachment.file_name}. Choose a new file only to replace it.`
                    : 'Maximum 15 MB. Stored privately and attached by the mail worker.';
                const recommended = payload._attachment_preference || 'none';
                preference.textContent = recommended !== 'none'
                    ? `Recommended for this built-in template: ${recommended.replaceAll('_', ' ')}.`
                    : '';
                syncVisibility();
            }, 0));
        });
    };

    wireMailbox();
    wireTemplateAdmin();
})();
