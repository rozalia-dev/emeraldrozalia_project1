# Emerald Rozalia Project 1

## Current implementation status

The repository is an active foundation, not a completed pixel-accurate release. The local Herd/MySQL baseline is booting and has passed migration/seed re-run verification, the UUID/domain migration is portable and seeded locally, local HTTP session cookies now support CSRF/login, cart, tenant isolation and foundation smoke routes are tested, deployment credentials/health ordering are aligned for PostgreSQL Docker, and the shared public visual foundation (header/footer/tokens), cPanel shell/dashboard, dedicated homepage/catalogue structure, variant-aware product detail, product media manager, 360° viewer contract, local-only Try-On studio and dedicated informational screens are in progress. The approved homepage reference is now wired into the live homepage as a branded visual crop baseline while Laravel links and catalogue data remain active. The approved guide still contains unimplemented or unverified public and cPanel screens; completion requires the ordered tasks and screenshot evidence described in `docs/AGENTS.md`.

Laravel 13 full-stack storefront and single admin cPanel for Emerald Rozalia Limited. The repository is deployable with PostgreSQL 17 and contains no Production, Finance, Payroll, HR or POS module.

## Delivered scope

- Pixel-oriented responsive storefront, supplied wordmark and product badge assets
- Catalogue, categories, variants, search, cart, wishlist, reviews, discounts and inventory movements
- Customer registration, verification, profile, addresses, checkout, orders, invoices, rewards and returns
- P4.1 customer auth/dashboard contract: rate-limited auth routes, session rotation, non-enumerating reset request, verification state and owner-authorized account resources (see docs/AUTH-CUSTOMER-EVIDENCE.md)
- P4.2 cart/checkout contract: saved/manual addresses, active shipping, validated discounts, provider-neutral payment choices, atomic totals and confirmation (see docs/CHECKOUT-CART-EVIDENCE.md)
- P4.3 money/inventory contract: EUR decimal precision, round-half-up totals, locked stock rechecks, exact decrements and sale movements (see docs/MONEY-INVENTORY-EVIDENCE.md)
- P4.4 order/ledger contract: owner-protected invoices, payment history, return eligibility/deduplication, provider-neutral payment transitions and audit records (see docs/ORDER-LEDGER-EVIDENCE.md)
- P4.5 six isolated order masters: type-filtered queues for online, corporate, bulk, franchise, franchise retail and buyer with metrics, detail, invoice, lifecycle and return/payment visibility (see docs/ORDER-MASTERS-EVIDENCE.md)
- Franchise applications, retail-store onboarding data and milestone schema
- Communication Centre persistence for web enquiries, messages, assignments and follow-ups
- Page Manager with drafts, review, scheduling, publishing, duplication, revision snapshots, archive, trash and restore
- Product image/video/360/try-on media schema and browser-side try-on preview
- Users, roles, permissions, audit log, reports, settings, integrations, automation, backups and maintenance surfaces
- Company, language and currency context
- CSV/XLSX bulk product import

External payment/webhook, WhatsApp, email delivery, shipping, social, hosted 360° and hosted try-on connections are disabled by default. Enable each only after core live-site verification.

## Requirements

- PHP 8.3–8.5 with `pdo_pgsql`, intl, zip, bcmath and GD
- Composer 2
- PostgreSQL 15+ (Docker uses 17)
- Nginx or Apache with document root set to `public/`

## Docker deployment

```bash
cp .env.example .env
# Set database and unique ADMIN_* secrets.
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed --force
docker compose exec app php artisan storage:link
docker compose exec app php artisan optimize
```

## Linux/cPanel deployment

```bash
cp .env.example .env
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan key:generate
php artisan migrate --seed --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Set the web root to `/path/to/project/public`. Make `storage/` and `bootstrap/cache/` writable by the PHP user. Run `php artisan queue:work --sleep=3 --tries=3 --max-time=3600` under Supervisor and schedule `php artisan schedule:run` every minute. Templates are included in `deploy/`.

Before seeding, replace `ADMIN_EMAIL` and `ADMIN_PASSWORD`. Back up PostgreSQL and `storage/app/public`; test recovery before launch. Use HTTPS, `APP_DEBUG=false`, secure cookies and a strong generated `APP_KEY`.

## External-service go-live rule

Every `*_LIVE_ENABLED` variable remains `false` for deployment and initial launch. After the core storefront, checkout, admin and database backups pass acceptance checks, enter one provider's credentials, test its health/webhook in staging, enable only that service, deploy and monitor. Never commit `.env`.

## Verification

```bash
composer validate --strict
php artisan migrate:fresh --seed --force
php artisan route:list
php artisan test
```

CI executes the install, PostgreSQL migration/seed and tests on every push. See `docs/IMPLEMENTATION-MATRIX.md` and `docs/DEPLOYMENT-RUNBOOK.md` for specification traceability and deployment/rollback.

Official footer contact values are loaded from the live server's `BRAND_*` environment variables and are never committed.
