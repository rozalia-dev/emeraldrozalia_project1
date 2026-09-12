# Emerald Rozalia Project 1

## Current implementation status

The audited release baseline is c54e933e36c68f2951e5e05afb32119ea02ea467; GitHub Actions run #277 passed the PostgreSQL, container/release and Hetzner deployment jobs. The repository remains a partial foundation against the 519-page guide, not a completed 167-visual acceptance release. Batch 0 evidence is recorded in the PROJECT1-*.md matrices.

The repository now contains the Laravel 13 Project 1 full-stack package, including the responsive Emerald Rozalia storefront, single admin cPanel, SEO & Content workspace, PostgreSQL Docker stack, persistent public media volume, CI validation and guarded production deployment scripts. The SEO reference screen is functional: metadata, audits, issue fixing, broken-link checks, keywords, redirects, sitemap, robots.txt, schema and UUID-backed audit logging are connected to Laravel routes and database tables. Runtime verification remains an environment check: this workspace does not include PHP, Composer or Docker, so the authoritative migration, route, PHPUnit and container checks run in GitHub Actions.

The Project 1 Batch 1 foundation candidate adds live model-backed Reviews & Ratings metrics/search, published Banner delivery on the approved homepage and `/api/v1/banners`, a versioned catalog API with Form Requests, Resources, policies and OpenAPI documentation, plus correlation IDs and idempotent public enquiry linking across Inquiry, Franchise Application and Communication Centre records. This is a bounded implementation slice, not a completion claim for the 519-page guide or 167-reference visual acceptance set. The candidate commit `0095b773144c788345c159f07eca9c364e5592fa` passed PostgreSQL, container and production deployment validation in GitHub Actions [run #280](https://github.com/rozalia-dev/emeraldrozalia_project1/actions/runs/34703589945). See [Batch 1 foundation evidence](docs/PROJECT1-BATCH1-FOUNDATION-EVIDENCE.md).

Batch 2 now hardens the Users & Roles boundary with action-specific permissions, active/non-expired role assignments, company membership checks and product policy authorization. The merged release `6a8fbdbf8f12303a33be89d95eb604364eb1e335` passed the pull-request gate and the full main validation/deployment workflow [run #286](https://github.com/rozalia-dev/emeraldrozalia_project1/actions/runs/34705242883). See [Batch 2 authorization evidence](docs/PROJECT1-BATCH2-AUTHORIZATION-EVIDENCE.md).

The shared cPanel operations layer now supports audited create/search/filter/edit/delete records, global admin search across Project 1 domains, Communication Center assignment/status/follow-up and saved replies, plus Product Manager edit/update without duplicate products. See [CPANEL-OPERATIONS-EVIDENCE.md](docs/CPANEL-OPERATIONS-EVIDENCE.md) for the exact scope and verification status.

Batch 3 now hardens the UUID/domain migration contract for clean, partial and repeated application, preserves existing public identifiers, repairs duplicate/null values before constraints and adds a forward repair migration for already-recorded schemas. Release a2464ddf7258eb436ecd7c5361dc7ba40351e74f passed the PostgreSQL, container and production deployment gates in [run #290](https://github.com/rozalia-dev/emeraldrozalia_project1/actions/runs/34706590537). See [Batch 3 migration evidence](docs/PROJECT1-BATCH3-MIGRATION-EVIDENCE.md).

Batch 4 now hardens the multi-company migration lifecycle: replay-safe table/column creation, dependency-safe tenant-column rollback, schema-contract coverage and a full disposable PostgreSQL rollback/re-migrate/seed/status rehearsal. Merged release `14ba4142ed98816cec1787097944682beaf32534` passed PostgreSQL, media-browser, container/release and production deployment gates in [run #293](https://github.com/rozalia-dev/emeraldrozalia_project1/actions/runs/34707758475). See [Batch 4 migration lifecycle evidence](docs/PROJECT1-BATCH4-MIGRATION-EVIDENCE.md). MySQL-specific runtime evidence, the 519-page guide and the 167-reference visual acceptance set remain open.

Batch 5 now connects the public Page Manager contract: the locked eight-item header and footer-only contact rule are explicit, typed managed sections render through a shared escaped storefront/preview partial, the builder exposes block settings, and published pages enforce visibility/login access while supporting footer-managed links. Merged release `ba2cefb111f0b3838acd1ba331ab9dedea1d042b` passed the full main validation and production deployment workflow in [run #297](https://github.com/rozalia-dev/emeraldrozalia_project1/actions/runs/34709314668). See [Batch 5 public content evidence](docs/PROJECT1-BATCH5-PUBLIC-CONTENT-EVIDENCE.md). The 519-page guide and 167-reference visual acceptance set remain open.

Batch 6 now connects the customer front office to live returns/order data and makes the latest published managed 360 spin the authoritative product-detail viewer, with legacy/media fallback and a disabled no-frame state. Merged release `b6cd7cd19d3b8875d82f30ea91cb31d132e85622` passed the full PostgreSQL, rollback, media-browser, container and production deployment workflow in [run #304](https://github.com/rozalia-dev/emeraldrozalia_project1/actions/runs/34710843822). See [Batch 6 customer/front-office/360 evidence](docs/PROJECT1-BATCH6-CUSTOMER-FRONT-OFFICE-360-EVIDENCE.md). The 519-page guide and 167-reference visual acceptance set remain open.

Batch 7 reconciled the unified cPanel navigation hierarchy: the six order categories now sit under a dedicated Order Management group, Online Sales is separated from those category links, and Settings exposes the compact four-item reference shell. Merged release `16c4e8097a7ba647df0e1b1278574a7a9fb37038` passed the full PostgreSQL, rollback, media-browser, container and production deployment workflow in [run #308](https://github.com/rozalia-dev/emeraldrozalia_project1/actions/runs/34712502169). See [Batch 7 admin navigation evidence](docs/PROJECT1-BATCH7-ADMIN-NAVIGATION-EVIDENCE.md). The 519-page guide and 167-reference visual acceptance set remain open.

Batch 8 connects the public/private banner and review source-of-truth boundary: current published Banner records feed the homepage and catalog API, customer reviews remain pending until audited admin approval, and approved Review records alone feed public product pages. Merged release `093e451df45e4ed1665204003591f86833913d81` passed the full PostgreSQL, rollback, media-browser, container and production deployment workflow in [run #310](https://github.com/rozalia-dev/emeraldrozalia_project1/actions/runs/34713386271). See [Batch 8 public/private synchronization evidence](docs/PROJECT1-BATCH8-PUBLIC-PRIVATE-SYNC-EVIDENCE.md). The 519-page guide and 167-reference visual acceptance set remain open.

Batch 9 now publishes tenant-scoped, public-safe settings snapshots for general configuration, company branding and localization. The shared storefront shell and default tenant context consume the latest published revision, while private settings remain admin-only; versioning, tenant isolation, asset/color sanitization and reverse reads are feature-tested. Merged release `cba21d6474c3ab2d78a17aecc7175ca6dbaf2072` passed the full PostgreSQL, rollback, media-browser, container and production deployment workflow in [run #314](https://github.com/rozalia-dev/emeraldrozalia_project1/actions/runs/34714948981). See [Batch 9 settings synchronization evidence](docs/PROJECT1-BATCH9-SETTINGS-SYNC-EVIDENCE.md). The 519-page guide and 167-reference visual acceptance set remain open.

The Videos reference dashboard now uses real product-media records: private uploads, YouTube/Vimeo embeds, filters, bulk actions, scheduling, product-page playback, captions, website playback metrics and UUID audit history. See [VIDEO-DASHBOARD-EVIDENCE.md](docs/VIDEO-DASHBOARD-EVIDENCE.md) for the supported workflows and validation boundaries.

Laravel 13 full-stack storefront and single admin cPanel for Emerald Rozalia Limited. The repository is deployable with PostgreSQL 17 and contains no Production, Finance, Payroll, HR or POS module.

## Delivered scope

- Pixel-oriented responsive storefront, approved homepage reference asset and shared brand visual baseline
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
- SEO & Content cPanel workspace with metadata editing, local audits, issue fixing, target keywords, redirects, sitemap, robots.txt and Organization schema publishing
- cPanel submenu readability baseline: all submenu copy and controls are enforced at a minimum 13px, including responsive/mobile layouts
- Product image/video/360/try-on media schema and browser-side try-on preview
- Users, roles, permissions, audit log, reports, settings, integrations, automation, backups and maintenance surfaces
- Company, language and currency context
- CSV/XLSX bulk product import

Bulk Product Upload and individual video uploads accept files up to 20 MB. The production PHP-FPM upload settings are defined in `deploy/php/uploads.ini` and are installed by the Docker image.

External payment/webhook, WhatsApp, email delivery, shipping, social, hosted 360° and hosted try-on connections are disabled by default. Enable each only after core live-site verification.

## Requirements

- PHP 8.3–8.5 with `pdo_pgsql`, intl, zip, bcmath and GD
- Composer 2
- PostgreSQL 15+ (Docker uses 17)
- Nginx or Apache with document root set to `public/`

## Docker deployment

```bash
cp .env.example .env
# Set a strong DB_PASSWORD, ADMIN_EMAIL, ADMIN_PASSWORD and every live-server value.
docker compose build app
APP_KEY="$(docker compose run --rm --no-deps --entrypoint php app artisan key:generate --show --no-ansi | tail -n 1)"
sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" .env
docker compose up -d --build
docker compose exec --user www-data app php artisan migrate --force
docker compose exec --user www-data app php artisan optimize
```

For a repeatable server release after the first `.env` setup, run `bash deploy/docker-deploy.sh`. It validates the Compose file, builds the application, takes pre-migration PostgreSQL and upload backups, runs migrations as `www-data`, rebuilds Laravel caches, starts the worker and scheduler, and checks both internal and public health endpoints. Public uploads are stored in the shared `public-assets` volume so Nginx and PHP see the same files. New video files, posters and captions use `storage/app/private` in the persistent `storage` volume and are served through Laravel access checks. Releases also back up this private upload directory. Production releases do not run the demo seeder.

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

Before seeding, replace `ADMIN_EMAIL` and `ADMIN_PASSWORD`. Back up PostgreSQL, `storage/app/public` and `storage/app/private`; test recovery before launch. Use HTTPS, `APP_DEBUG=false`, secure cookies and a strong generated `APP_KEY`.

## External-service go-live rule

Every `*_LIVE_ENABLED` variable remains `false` for deployment and initial launch. After the core storefront, checkout, admin and database backups pass acceptance checks, enter one provider's credentials, test its health/webhook in staging, enable only that service, deploy and monitor. Never commit `.env`.

## Verification

```bash
composer validate --strict
php artisan migrate:fresh --seed --force
php artisan route:list
php artisan test
```

CI executes the install, PostgreSQL migration/seed and tests on every push. See `docs/PROJECT1-GUIDE-COVERAGE.md`, `docs/PROJECT1-CI-GATE-MATRIX.md` and `docs/DEPLOYMENT-RUNBOOK.md` for current specification traceability and deployment/rollback.

Official footer contact values are loaded from the live server's `BRAND_*` environment variables and are never committed.

See [the server-ready package runbook](docs/SERVER-READY-PACKAGE.md) for the first-release checklist, TLS proxy settings, deployment recovery and post-deploy checks.

For the complete local, server and GitHub connection record, use [CI/CD setup](docs/CI-CD-SETUP.md). It includes the production environment secret names, SSH fingerprint procedure, Docker/Nginx/Certbot setup, release sequence, backup/recovery limits and the latest successful production workflow evidence.
