# Emerald Rozalia WhatsApp Web Engine

This service is the persistent Node.js process behind Project 1's existing Communication Center WhatsApp channel.

## What it does

- Runs `whatsapp-web.js` with `LocalAuth` session persistence.
- Exposes bearer-token protected `/send-message`, `/status` and `/qr-data` endpoints.
- Forwards inbound WhatsApp messages to Laravel's signed inbound webhook.
- Forwards delivery acknowledgements to Laravel's existing communication webhook pipeline.
- Persists a bounded idempotency ledger so Laravel queue retries do not duplicate already accepted sends after a Node restart.

## Production architecture

Production runs this engine as the `whatsapp-engine` service in the existing Docker Compose project. No host port is published. Laravel and its queue worker reach it only through the private Compose DNS name:

`http://whatsapp-engine:3001`

The WhatsApp auth/session state is stored in the persistent `whatsapp-session` Docker volume and therefore survives container replacement and normal Laravel deployments.

The root Laravel `.env` supplies the two shared secrets:

- `COMMUNICATION_WHATSAPP_TOKEN` -> Node `WHATSAPP_ENGINE_TOKEN`
- `COMMUNICATION_WHATSAPP_WEBHOOK_SECRET` -> Node `LARAVEL_WEBHOOK_SECRET`

## Local standalone runtime

For local development outside Docker, Node.js 18 or newer is required by `whatsapp-web.js` 1.34.7. Copy `.env.example` to `.env`, fill the secrets, then run:

```bash
npm install --omit=dev
npm run check
npm start
```

## Laravel cPanel

After the production `.env` values are configured and the Docker service is running, open:

`/admin/communication-center/whatsapp/setup`

Scan the QR code from WhatsApp -> Linked devices -> Link a device. The existing `/admin/resource/whatsapp` screen remains the operational inbox and reply screen.

## Important

This uses an unofficial WhatsApp Web automation client rather than Meta's official WhatsApp Business API. WhatsApp may change its web client or restrict/block automated sessions. Do not use this integration for unsolicited bulk messaging.
