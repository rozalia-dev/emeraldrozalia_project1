# Deployment and rollback runbook

1. Provision PostgreSQL, create the `emerald_rozalia` least-privilege database user (matching `docker-compose.yml` and `.env.example`), configure DNS and TLS, and point the site root to `public/`.
2. Upload/release the repository, copy `.env.example` to `.env`, replace every example secret, keep all external live toggles false, and run the README install commands.
3. Confirm `/up`, homepage, product, registration, cart, checkout, six order masters, Page Manager, Communication Centre and backup destination.
4. Start the queue worker and scheduler, then place the site live. Verify logs, error rate, database connections and a restore drill.
5. Connect payment/webhooks, WhatsApp, email, shipping, social, hosted 360° and try-on one at a time only after core acceptance.

Rollback: enable maintenance mode, restore the previous release, run `php artisan migrate:rollback --step=1` only when the migration is explicitly reversible and data-safe, restore the verified PostgreSQL backup if needed, clear caches, restart workers and re-run the smoke checks. Never roll back by deleting the current database.
