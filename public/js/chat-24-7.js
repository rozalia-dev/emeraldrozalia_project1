(() => {
    const root = document.querySelector('[data-chat-24-7-widget]');
    if (!root) return;

    const toggle = root.querySelector('[data-chat24-toggle]');
    const close = root.querySelector('[data-chat24-close]');
    const panel = root.querySelector('.chat24-panel');
    const messagesEl = root.querySelector('[data-chat24-messages]');
    const productsEl = root.querySelector('[data-chat24-products]');
    const quickEl = root.querySelector('[data-chat24-quick]');
    const statusEl = root.querySelector('[data-chat24-status]');
    const form = root.querySelector('[data-chat24-form]');
    const input = root.querySelector('[data-chat24-input]');
    const humanButton = root.querySelector('[data-chat24-human]');

    const csrf = root.dataset.csrf || '';
    const startUrl = root.dataset.startUrl;
    const baseUrl = root.dataset.baseUrl.replace(/\/$/, '');
    const contextProductSlug = root.dataset.productSlug || '';
    const storageKey = 'emerald-rozalia-chat24-conversation';

    let conversation = null;
    let lastId = 0;
    let polling = null;
    let started = false;

    try { conversation = sessionStorage.getItem(storageKey) || null; } catch (_) {}

    const request = async (url, options = {}) => {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                ...(options.headers || {}),
            },
            ...options,
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(payload.message || 'Chat request failed.');
        return payload;
    };

    const scrollMessages = () => { messagesEl.scrollTop = messagesEl.scrollHeight; };

    const appendMessage = (message) => {
        if (!message || document.querySelector(`[data-chat24-message-id="${message.id}"]`)) return;
        const row = document.createElement('div');
        row.className = `chat24-message ${message.direction || 'outbound'} ${message.actor || ''}`;
        row.dataset.chat24MessageId = message.id;
        const bubble = document.createElement('div');
        bubble.className = 'chat24-bubble';
        bubble.textContent = message.body || '';
        row.appendChild(bubble);
        messagesEl.appendChild(row);
        lastId = Math.max(lastId, Number(message.id || 0));
        renderProducts(message.products || []);
        scrollMessages();
    };

    const renderProducts = (products) => {
        if (!Array.isArray(products) || products.length === 0) return;
        productsEl.replaceChildren();
        products.forEach((product) => {
            const link = document.createElement('a');
            link.className = 'chat24-product';
            link.href = product.url || '#';
            const copy = document.createElement('div');
            const name = document.createElement('strong');
            name.textContent = product.name || 'Product';
            const meta = document.createElement('span');
            const bits = [];
            if (product.price) bits.push(product.price);
            if (product.material) bits.push(product.material);
            meta.textContent = bits.join(' · ');
            copy.append(name, meta);
            const arrow = document.createElement('span');
            arrow.textContent = 'View →';
            link.append(copy, arrow);
            productsEl.appendChild(link);
        });
        productsEl.hidden = false;
    };

    const renderQuick = (items) => {
        quickEl.replaceChildren();
        (items || []).forEach((label) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = label;
            button.addEventListener('click', () => {
                if (label === 'Talk to a Person') return requestHuman();
                input.value = label;
                form.requestSubmit();
            });
            quickEl.appendChild(button);
        });
    };

    const setBusy = (busy, message = '') => {
        input.disabled = busy;
        form.querySelector('.chat24-send').disabled = busy;
        statusEl.textContent = message;
    };

    const start = async () => {
        if (started) return;
        started = true;
        setBusy(true, 'Connecting…');
        try {
            if (!conversation) {
                const payload = await request(startUrl, {
                    method: 'POST',
                    body: JSON.stringify({ context_product_slug: contextProductSlug || null }),
                });
                conversation = payload.conversation_uuid;
                try { sessionStorage.setItem(storageKey, conversation); } catch (_) {}
                (payload.messages || []).forEach(appendMessage);
                renderQuick(payload.quick_actions || []);
            } else {
                await poll();
                if (!messagesEl.children.length) {
                    conversation = null;
                    try { sessionStorage.removeItem(storageKey); } catch (_) {}
                    started = false;
                    return start();
                }
            }
            statusEl.textContent = 'Ask a product question or request a human agent.';
            beginPolling();
        } catch (error) {
            conversation = null;
            try { sessionStorage.removeItem(storageKey); } catch (_) {}
            statusEl.textContent = error.message || 'Unable to start chat.';
        } finally {
            setBusy(false, statusEl.textContent);
        }
    };

    const send = async (text) => {
        if (!conversation) await start();
        if (!conversation) return;
        setBusy(true, 'Checking our product data…');
        try {
            const payload = await request(`${baseUrl}/${conversation}/messages`, {
                method: 'POST',
                body: JSON.stringify({ message: text, context_product_slug: contextProductSlug || null }),
            });
            (payload.messages || []).forEach(appendMessage);
            renderQuick(payload.quick_actions || []);
            statusEl.textContent = payload.human_requested
                ? 'A team member has been requested. Keep this chat open for their reply.'
                : 'AI answers use currently recorded Emerald Rozalia product data.';
        } catch (error) {
            statusEl.textContent = error.message || 'Message could not be sent.';
        } finally {
            setBusy(false, statusEl.textContent);
        }
    };

    const poll = async () => {
        if (!conversation) return;
        try {
            const response = await fetch(`${baseUrl}/${conversation}/messages?after_id=${lastId}`, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            });
            if (response.status === 404) throw new Error('expired');
            if (!response.ok) return;
            const payload = await response.json();
            (payload.messages || []).forEach(appendMessage);
            if (payload.human_requested || payload.ai_paused) {
                statusEl.textContent = 'Human support is active for this conversation.';
            }
        } catch (error) {
            if (error.message === 'expired') {
                conversation = null;
                try { sessionStorage.removeItem(storageKey); } catch (_) {}
            }
        }
    };

    const beginPolling = () => {
        if (polling) return;
        polling = window.setInterval(poll, 2500);
    };

    const requestHuman = async () => {
        if (!conversation) await start();
        if (!conversation) return;
        humanButton.disabled = true;
        statusEl.textContent = 'Requesting a team member…';
        try {
            const payload = await request(`${baseUrl}/${conversation}/human`, { method: 'POST', body: '{}' });
            appendMessage(payload.message);
            statusEl.textContent = 'A team member has been requested. Keep this chat open for their reply.';
        } catch (error) {
            statusEl.textContent = error.message || 'Unable to request a team member.';
        } finally {
            humanButton.disabled = false;
        }
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const text = input.value.trim();
        if (!text) return;
        input.value = '';
        await send(text);
        input.focus();
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form.requestSubmit();
        }
    });

    humanButton.addEventListener('click', requestHuman);
    toggle.addEventListener('click', async () => {
        const open = panel.hidden;
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
        if (open) {
            await start();
            input.focus();
        }
    });
    close.addEventListener('click', () => {
        panel.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
        toggle.focus();
    });
})();
