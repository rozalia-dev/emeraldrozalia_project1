require('dotenv').config();

const crypto = require('crypto');
const fs = require('fs');
const path = require('path');
const express = require('express');
const QRCode = require('qrcode');
const { Client, LocalAuth } = require('whatsapp-web.js');

const app = express();
app.use(express.json({ limit: '1mb' }));

const HOST = process.env.HOST || '127.0.0.1';
const PORT = Number(process.env.PORT || 3001);
const ENGINE_TOKEN = String(process.env.WHATSAPP_ENGINE_TOKEN || '').trim();
const WEBHOOK_SECRET = String(process.env.LARAVEL_WEBHOOK_SECRET || '').trim();
const INBOUND_URL = String(process.env.LARAVEL_INBOUND_URL || '').trim();
const DELIVERY_WEBHOOK_URL = String(process.env.LARAVEL_DELIVERY_WEBHOOK_URL || '').trim();
const SESSION_PATH = path.resolve(process.env.WHATSAPP_SESSION_PATH || '.wwebjs_auth');
const LEDGER_PATH = path.resolve(process.env.WHATSAPP_LEDGER_PATH || path.join(SESSION_PATH, 'delivery-ledger.json'));
const MAX_LEDGER_ENTRIES = Math.max(100, Number(process.env.WHATSAPP_LEDGER_LIMIT || 5000));

if (!ENGINE_TOKEN) throw new Error('WHATSAPP_ENGINE_TOKEN is required.');
if (!WEBHOOK_SECRET) throw new Error('LARAVEL_WEBHOOK_SECRET is required.');
if (!INBOUND_URL) throw new Error('LARAVEL_INBOUND_URL is required.');
if (!DELIVERY_WEBHOOK_URL) throw new Error('LARAVEL_DELIVERY_WEBHOOK_URL is required.');

fs.mkdirSync(SESSION_PATH, { recursive: true });

let ready = false;
let state = 'starting';
let lastError = null;
let latestQrDataUrl = null;
let ledger = new Map();
let persistChain = Promise.resolve();

function timingSafeEqual(value, expected) {
    const a = Buffer.from(String(value || ''));
    const b = Buffer.from(String(expected || ''));
    return a.length === b.length && crypto.timingSafeEqual(a, b);
}

function requireEngineToken(req, res, next) {
    const authorization = String(req.get('authorization') || '');
    if (!timingSafeEqual(authorization, `Bearer ${ENGINE_TOKEN}`)) {
        return res.status(401).json({ ok: false, error: 'Unauthorized' });
    }
    next();
}

function normalizePhone(input) {
    const value = String(input || '').trim();
    const digits = value.replace(/@.+$/, '').replace(/\D/g, '');
    return digits.length >= 7 && digits.length <= 15 ? digits : null;
}

function signBody(body) {
    return `sha256=${crypto.createHmac('sha256', WEBHOOK_SECRET).update(body).digest('hex')}`;
}

async function postSignedJson(url, payload) {
    const body = JSON.stringify(payload);
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 10000);

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Communication-Signature': signBody(body),
            },
            body,
            signal: controller.signal,
        });

        if (!response.ok) {
            const text = await response.text();
            throw new Error(`Laravel returned HTTP ${response.status}: ${text.slice(0, 300)}`);
        }

        return response;
    } finally {
        clearTimeout(timeout);
    }
}

async function postWithRetry(url, payload, attempts = 3) {
    let lastFailure = null;
    for (let attempt = 1; attempt <= attempts; attempt++) {
        try {
            await postSignedJson(url, payload);
            return;
        } catch (error) {
            lastFailure = error;
            console.error(`[Webhook] attempt ${attempt}/${attempts} failed:`, error.message);
            if (attempt < attempts) {
                await new Promise(resolve => setTimeout(resolve, attempt * 1000));
            }
        }
    }
    throw lastFailure;
}

function loadLedger() {
    try {
        if (!fs.existsSync(LEDGER_PATH)) return;
        const parsed = JSON.parse(fs.readFileSync(LEDGER_PATH, 'utf8'));
        if (!Array.isArray(parsed)) return;
        ledger = new Map(parsed.filter(row => Array.isArray(row) && row.length === 2));
    } catch (error) {
        console.error('[Ledger] unable to load:', error.message);
        ledger = new Map();
    }
}

function rememberDelivery(key, value) {
    if (!key) return Promise.resolve();
    ledger.delete(key);
    ledger.set(key, value);
    while (ledger.size > MAX_LEDGER_ENTRIES) {
        ledger.delete(ledger.keys().next().value);
    }

    persistChain = persistChain.then(async () => {
        const tmp = `${LEDGER_PATH}.tmp`;
        await fs.promises.mkdir(path.dirname(LEDGER_PATH), { recursive: true });
        await fs.promises.writeFile(tmp, JSON.stringify([...ledger.entries()]), 'utf8');
        await fs.promises.rename(tmp, LEDGER_PATH);
    }).catch(error => console.error('[Ledger] persist failed:', error.message));

    return persistChain;
}

loadLedger();

const puppeteer = { headless: true };
if (process.env.PUPPETEER_EXECUTABLE_PATH) {
    puppeteer.executablePath = process.env.PUPPETEER_EXECUTABLE_PATH;
}
if (process.env.PUPPETEER_NO_SANDBOX === 'true') {
    puppeteer.args = ['--no-sandbox', '--disable-setuid-sandbox'];
}

const client = new Client({
    authStrategy: new LocalAuth({
        dataPath: SESSION_PATH,
        clientId: process.env.WHATSAPP_CLIENT_ID || 'emerald-rozalia',
    }),
    puppeteer,
});

client.on('qr', async qr => {
    ready = false;
    state = 'waiting_for_qr_scan';
    lastError = null;
    try {
        latestQrDataUrl = await QRCode.toDataURL(qr, { width: 340, margin: 2 });
        console.log('[WhatsApp] QR generated. Open the cPanel WhatsApp setup page to scan it.');
    } catch (error) {
        latestQrDataUrl = null;
        lastError = error.message;
        console.error('[WhatsApp] QR generation failed:', error);
    }
});

client.on('authenticated', () => {
    state = 'authenticated';
    lastError = null;
    console.log('[WhatsApp] authenticated.');
});

client.on('ready', () => {
    ready = true;
    state = 'ready';
    latestQrDataUrl = null;
    lastError = null;
    console.log('[WhatsApp] client ready.');
});

client.on('auth_failure', message => {
    ready = false;
    state = 'auth_failure';
    lastError = String(message || 'Authentication failure');
    console.error('[WhatsApp] authentication failure:', message);
});

client.on('disconnected', reason => {
    ready = false;
    state = 'disconnected';
    lastError = String(reason || 'Disconnected');
    console.warn('[WhatsApp] disconnected:', reason);
});

client.on('message', async message => {
    try {
        if (!message || message.fromMe || message.from === 'status@broadcast') return;
        if (String(message.from || '').endsWith('@g.us')) return;

        const providerMessageId = message.id?._serialized || `${message.from}-${message.timestamp}`;
        const contact = await message.getContact().catch(() => null);
        const phone = normalizePhone(contact?.number || message.from);
        if (!phone) return;

        const payload = {
            event_id: `wa-in:${providerMessageId}`,
            event_type: 'message.received',
            provider_message_id: providerMessageId,
            from_chat_id: message.from || null,
            from_phone: phone,
            sender_name: contact?.pushname || contact?.name || contact?.shortName || null,
            body: String(message.body || ''),
            message_type: String(message.type || 'chat'),
            has_media: Boolean(message.hasMedia),
            timestamp: Number(message.timestamp || Math.floor(Date.now() / 1000)),
        };

        await postWithRetry(INBOUND_URL, payload);
        console.log('[WhatsApp] inbound stored:', providerMessageId);
    } catch (error) {
        console.error('[WhatsApp] inbound forwarding failed:', error.message);
    }
});

client.on('message_ack', async (message, ack) => {
    try {
        if (!message?.fromMe) return;
        const providerMessageId = message.id?._serialized;
        if (!providerMessageId) return;

        const deliveryStatus = Number(ack) < 0
            ? 'failed'
            : Number(ack) >= 2
                ? 'delivered'
                : 'queued';

        await postWithRetry(DELIVERY_WEBHOOK_URL, {
            event_id: `wa-ack:${providerMessageId}:${ack}`,
            event_type: 'message.ack',
            provider_message_id: providerMessageId,
            delivery_status: deliveryStatus,
        });
    } catch (error) {
        console.error('[WhatsApp] ack webhook failed:', error.message);
    }
});

app.get('/status', requireEngineToken, async (req, res) => {
    let whatsappState = null;
    try {
        if (ready) whatsappState = await client.getState();
    } catch (_) {
        // Status remains useful even if getState temporarily fails.
    }

    return res.json({
        ok: true,
        ready,
        state,
        whatsapp_state: whatsappState,
        has_qr: Boolean(latestQrDataUrl),
        last_error: lastError,
    });
});

app.get('/qr-data', requireEngineToken, (req, res) => {
    return res.json({
        ok: true,
        ready,
        state,
        qr: latestQrDataUrl,
        last_error: lastError,
    });
});

app.post('/send-message', requireEngineToken, async (req, res) => {
    try {
        if (!ready) {
            return res.status(503).json({ ok: false, status: 'failed', error_code: 'whatsapp_not_ready' });
        }

        const phone = normalizePhone(req.body.to || req.body.phone);
        const body = String(req.body.body || req.body.message || '').trim();
        const idempotencyKey = String(req.get('Idempotency-Key') || req.body.idempotency_key || '').trim();

        if (!phone) return res.status(422).json({ ok: false, status: 'failed', error_code: 'invalid_phone' });
        if (!body || body.length > 4096) {
            return res.status(422).json({ ok: false, status: 'failed', error_code: 'invalid_message' });
        }

        if (idempotencyKey && ledger.has(idempotencyKey)) {
            return res.json({ ok: true, ...ledger.get(idempotencyKey), duplicate: true });
        }

        const numberId = await client.getNumberId(phone);
        if (!numberId?._serialized) {
            return res.status(404).json({ ok: false, status: 'failed', error_code: 'not_registered' });
        }

        const sent = await client.sendMessage(numberId._serialized, body);
        const providerMessageId = sent.id?._serialized;
        const responsePayload = {
            status: 'accepted',
            provider_message_id: providerMessageId || null,
            chat_id: numberId._serialized,
            phone,
        };

        if (idempotencyKey) await rememberDelivery(idempotencyKey, responsePayload);
        return res.json({ ok: true, ...responsePayload, duplicate: false });
    } catch (error) {
        console.error('[WhatsApp] send failed:', error);
        return res.status(500).json({
            ok: false,
            status: 'failed',
            error_code: 'engine_exception',
            error: 'WhatsApp engine failed to send the message.',
        });
    }
});

client.initialize().catch(error => {
    ready = false;
    state = 'initialization_failed';
    lastError = error.message;
    console.error('[WhatsApp] initialization failed:', error);
});

const server = app.listen(PORT, HOST, () => {
    console.log(`[WhatsApp] engine listening on http://${HOST}:${PORT}`);
});

async function shutdown(signal) {
    console.log(`[WhatsApp] ${signal}; shutting down.`);
    server.close();
    try { await client.destroy(); } catch (_) {}
    process.exit(0);
}

process.on('SIGINT', () => shutdown('SIGINT'));
process.on('SIGTERM', () => shutdown('SIGTERM'));
