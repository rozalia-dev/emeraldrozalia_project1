# Emerald Rozalia WhatsApp Web Engine

This service is the persistent Node.js process behind Project 1's existing Communication Center WhatsApp channel.

## What it does

- Runs `whatsapp-web.js` with `LocalAuth` session persistence.
- Exposes a private bearer-token protected `/send-message` endpoint for Laravel.
- Exposes private `/status` and `/qr-data` endpoints used by the cPanel WhatsApp setup page.
- Forwards inbound WhatsApp messages to Laravel's signed inbound webhook.
- Forwards delivery acknowledgements to Laravel's existing communication webhook pipeline.
- Persists a bounded idempotency ledger so Laravel queue retries do not duplicate already accepted sends after a Node restart.

## Security boundary

Keep the engine bound to `127.0.0.1`. Do not expose port `3001` publicly. The Laravel application is the only intended caller.

`WHATSAPP_ENGINE_TOKEN` must match Laravel `COMMUNICATION_WHATSAPP_TOKEN`.

`LARAVEL_WEBHOOK_SECRET` must match Laravel `COMMUNICATION_WHATSAPP_WEBHOOK_SECRET`.

## Runtime

Node.js 18 or newer is required by `whatsapp-web.js` 1.34.7.

Copy `.env.example` to `.env`, fill the secrets, then run:

```bash
npm install --omit=dev
npm run check
npm start
```

For production, install `whatsapp-engine.service.example` as a systemd unit after adjusting the project path and Linux service user if necessary.

## Laravel cPanel

After the Laravel environment values are configured and the Node service is running, open:

`/admin/communication-center/whatsapp/setup`

Scan the QR code from WhatsApp -> Linked devices -> Link a device. The existing `/admin/resource/whatsapp` screen remains the operational inbox and reply screen.

## Important

This uses an unofficial WhatsApp Web automation client rather than Meta's official WhatsApp Business API. WhatsApp may change its web client or restrict/block automated sessions. Do not use this integration for unsolicited bulk messaging.
