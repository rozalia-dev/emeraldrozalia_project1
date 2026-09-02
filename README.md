# Emerald Rozalia Project 1

## Remaining work plan and implementation checklist

This README is the working execution plan for Project 1. It records what is verified, what is only present in code, and the small tasks that remain before the project can be called complete.

The approved Developer Guide/SPC, the 519-page visual guide, and the supplied reference visuals are authoritative. Existing code is a foundation only. A route, database table, or Blade file is not accepted until its workflow, visual comparison, and tests pass.

Status key:

- [x] Verified with repository or deployment evidence
- [~] Code or infrastructure exists, but acceptance evidence is incomplete
- [ ] Remaining work

## Scope that is locked

Project 1 contains:

- Emerald Rozalia public storefront and product display
- Online sales and customer contact
- One franchise-focused admin cPanel
- Franchise applications and franchise retail-store management
- Communication Centre
- Website and product management
- Customers, reports, users and roles, integrations, settings, audit and operations

Project 1 does not contain:

- Production, manufacturing execution, raw-material workflow or factory operations
- Unified finance, payroll, HR or workforce management
- POS
- A separate franchise portal
- The Project 2 unified production/finance/UUID engine

The six order masters remain separate:

1. Online
2. Corporate
3. Bulk
4. Franchise
5. Franchise retail
6. Buyer

Brand rules:

- Use the approved Emerald Rozalia wordmark and approved headwear badge exactly as supplied.
- Do not redraw, recolour, approximate, replace or invent a crest, shield, crown, badge or illustration.
- The website brand line is: Irish Made. Limerick Born. Worn Everywhere.
- Website contact details belong in the footer only.
- External payment, webhook, WhatsApp, email delivery, shipping, social, hosted 360-degree and hosted try-on services stay disabled until they are configured and tested after deployment.

## Verified baseline at the start of this plan

Repository: rozalia-dev/emeraldrozalia_project1

Current main commit:

- d6fbffc — Make production Docker build resilient on small servers
- Parent: d2daa37 — Prepare Laravel storage directories in deployment validation
- UUID portability change retained: b1b6955 — Made UUID migration portable between MySQL and PostgreSQL
- Dependency/runtime baseline retained: 4070c22
- Original full-stack package retained: 8dd26e1

Deployment evidence:

- [x] The single workflow is .github/workflows/deploy.yml.
- [x] ci.yml remains intentionally deleted.
- [x] GitHub Actions run 33660542936, run number 5, completed successfully.
- [x] PostgreSQL 17 migration and seed completed.
- [x] Composer validation, route registration and application tests completed.
- [x] Hetzner SSH deployment completed.
- [x] Docker Compose Bake was killed once, then the classic Docker builder completed successfully.
- [x] Laravel migration, storage link, cache rebuild and https://emeraldrozalia.com/up smoke check completed.

This proves that the current deployment path works. It does not prove that the product is pixel-accurate or that every specified workflow is complete.

Current repository inventory at d6fbffc:

| Area | Observed state | Acceptance state |
|---|---|---|
| Blade views | 23 total | [~] Several are generic or shared placeholders |
| Admin views | 6 admin screens plus admin layout | [~] Domain-specific cPanel coverage is incomplete |
| Automated test files | 2 tracked test files | [~] Required unit, browser, authorization, concurrency and portability coverage is incomplete |
| Brand assets | 2 supplied PNG assets in public/assets/brand | [~] Asset-to-reference mapping and visual hash evidence is incomplete |
| Visual baselines | No Playwright screenshot baseline or pixel-diff suite | [ ] Not started |
| Reference manifest | No version-controlled 167-row acceptance manifest | [ ] Not started |
| PostgreSQL | Green in the deploy workflow | [x] Workflow evidence exists |
| MySQL | UUID code change exists | [ ] Clean migration, seed, rollback and rerun evidence is still required |
| External services | Disabled by default | [x] Correct go-live policy; provider testing is deferred |

## Mandatory implementation order

Do not start a later gate while an earlier gate is red.

1. Evidence and traceability manifest
2. Clean MySQL/PostgreSQL technical baseline
3. Design tokens and approved workflow reference
4. Approved homepage, in exact visual order
5. Public screens and customer workflows
6. Commerce correctness
7. Franchise and Communication Centre workflows
8. Domain-specific cPanel
9. Page Manager rendering and lifecycle
10. Security and application architecture
11. Automated visual, functional, accessibility and recovery acceptance
12. Production hardening and one-service-at-a-time integration enablement

The approved page order starts with the workflow reference, then the homepage. The homepage is the first public screen. Each subsequent screen receives the next stable reference ID from the manifest.

## Remaining task breakdown

### Gate 0 — Evidence and traceability

Goal: make every approved visual and every implementation decision addressable.

- [ ] P0.01 Inventory the Developer Guide/SPC, the 519-page PDF, the workflow image, the approved homepage, both approved logos, and the supplied image archive.
- [ ] P0.02 Create docs/TRACEABILITY-MANIFEST.csv with one row for every ordered visual reference.
- [ ] P0.03 Assign stable IDs such as REF-001 through REF-167, preserving the exact approved order.
- [ ] P0.04 Preserve all 23 intentional duplicate references as separate rows. Never deduplicate them out of acceptance.
- [ ] P0.05 Add these manifest columns: sequence, reference ID, source file, source page, route, authentication role, approved viewport, implementation component, backend dependency, test ID, status, screenshot path, diff result and notes.
- [ ] P0.06 Create docs/GAP-MATRIX.md from the current repository, not from assumptions.
- [ ] P0.07 Record every missing asset, ambiguous crop, missing viewport or unresolved product decision in an ambiguity log.
- [ ] P0.08 Add a small manifest validator that fails when the count, order, duplicate rows or required columns are wrong.

Gate acceptance:

- 167 ordered reference rows exist.
- 165 supplied image references are represented.
- 23 intentional duplicate rows are retained.
- Every row has a route, component, test ID and explicit status, or a documented ambiguity.

### Gate 1 — Technical baseline

Goal: no visual implementation work is accepted on a broken or non-portable application.

- [ ] P1.01 Run Composer validation against the checked-in lock file with composer validate --strict.
- [ ] P1.02 Create a disposable local MySQL test database and run clean migrations.
- [ ] P1.03 Seed the disposable MySQL database and run the route and application test suite.
- [ ] P1.04 Re-run the same migration and seed sequence on a clean PostgreSQL 17 database.
- [ ] P1.05 Test rollback only for migrations that are explicitly reversible and data-safe.
- [ ] P1.06 Drop and recreate both databases, then prove migrations can run again without partial-schema errors.
- [ ] P1.07 Verify that the UUID migration uses application generation/backfilling or portable SQL, not default UUID() on MySQL.
- [ ] P1.08 Reconcile PostgreSQL database name, user and password between .env.example and docker-compose.yml.
- [ ] P1.09 Confirm the production Dockerfile contains every required PHP extension and that local MySQL support remains available.
- [ ] P1.10 Keep .env, generated keys, credentials, logs, vendor files and runtime storage uncommitted.

Gate acceptance:

- MySQL and PostgreSQL clean migration/seed/rerun evidence is attached to the commit.
- php artisan route:list and php artisan test pass on both supported database engines.
- The single deploy.yml validation job remains green.

### Gate 2 — Design system, workflow reference and homepage

Goal: implement the visual source of truth before multiplying generic layouts.

- [ ] P2.01 Extract exact fonts, weights, colours, spacing, grids, borders, shadows, radii, controls, icons and breakpoints into documented design tokens.
- [ ] P2.02 Register the supplied wordmark, headwear badge and approved photography with file hashes and intended usage.
- [ ] P2.03 Build the workflow/reference-start screen and map it to its manifest row.
- [ ] P2.04 Build the homepage shell, including exact header, navigation, language, currency, search, login, cart and footer treatment.
- [ ] P2.05 Add the manufacturing top strip without putting contact details in the header.
- [ ] P2.06 Implement the photographic hero and the virtual try-on upload panel in the approved hero position.
- [ ] P2.07 Implement the benefits/global-reach strip and exact icon treatment.
- [ ] P2.08 Implement collection photography, Irish heritage collage and product cards.
- [ ] P2.09 Implement the bestseller carousel, manufacturing imagery, franchise-store panel and newsletter.
- [ ] P2.10 Implement the approved payment/footer treatment without enabling live payment providers.
- [ ] P2.11 Reproduce desktop, tablet and mobile states at the exact approved viewport sizes.
- [ ] P2.12 Add Playwright screenshots, a documented pixel-diff threshold and a failure artifact for every homepage reference state.
- [ ] P2.13 Mark the homepage accepted only after screenshot comparison, accessibility checks and functional interaction checks pass.

Gate acceptance:

- Workflow reference is first.
- Homepage is second.
- Every homepage section is present in the approved order.
- Supplied artwork is used exactly; no invented gradient, emoji, Unicode hat or placeholder replaces an approved visual.
- All required homepage viewports pass the documented pixel-diff threshold.

### Gate 3 — Public screens

Each item below is a separate implementation unit. Each unit must add or update its route, Blade component, backend dependency, test and manifest row.

- [ ] P3.01 Catalogue, shop grid, filters, sorting, pagination and empty states.
- [ ] P3.02 Search results, query validation, no-results state and keyboard navigation.
- [ ] P3.03 Categories, collections, new arrivals and Irish traditional/heritage pages.
- [ ] P3.04 GAA product groups and club-specific catalogue states.
- [ ] P3.05 Product detail layout, image gallery, variant selection and availability.
- [ ] P3.06 Product video, 360-degree view and virtual studio try-on interfaces.
- [ ] P3.07 Cart, line-item updates, removal, quantity limits and empty cart.
- [ ] P3.08 Discounts and coupons, including invalid, expired, limited and eligible cases.
- [ ] P3.09 Checkout address, shipping selection, tax and order review.
- [ ] P3.10 Customer login, registration, verification, password reset and failure states.
- [ ] P3.11 Customer profile, addresses, wishlist, reviews, rewards, returns and invoices.
- [ ] P3.12 Order history and order-detail states for all six order categories.
- [ ] P3.13 Corporate order, bulk order, franchise apply, hiring apply and contact-us forms.
- [ ] P3.14 Contact scheduling/request-a-meeting flow and Communication Centre persistence.
- [ ] P3.15 Franchise retail-store and owner enquiry flow.
- [ ] P3.16 Global network and factory/process/information pages, with Limerick correctly located in Ireland.
- [ ] P3.17 Managed-content pages, legal/return/refund pages and newsletter subscription.
- [ ] P3.18 Language and currency selection with deterministic fallback behaviour.
- [ ] P3.19 Loading, validation, error, success, empty and permission-denied states for each public workflow.
- [ ] P3.20 Run a visual and functional acceptance pass for every completed reference before starting the next group.

### Gate 4 — Commerce correctness

Goal: make online sales safe under real requests and concurrent activity.

- [ ] P4.01 Store money as integer minor units and document the safe migration/backfill strategy.
- [ ] P4.02 Centralise product, variant, price, tax and shipping calculations in tested services.
- [ ] P4.03 Reserve and decrement stock inside transactions with lockForUpdate().
- [ ] P4.04 Add concurrent checkout tests proving overselling cannot occur.
- [ ] P4.05 Enforce coupon dates, limits, eligibility, per-customer usage and rollback.
- [ ] P4.06 Keep Online, Corporate, Bulk, Franchise, Franchise Retail and Buyer orders isolated by type.
- [ ] P4.07 Add order idempotency keys for repeated checkout/form submissions.
- [ ] P4.08 Add payment-provider interfaces and signed webhook verification, with live providers disabled.
- [ ] P4.09 Add refund, return, cancellation and payment-ledger tests.
- [ ] P4.10 Add invoice generation and download tests without exposing internal numeric identifiers publicly.

### Gate 5 — Customers, franchise and Communication Centre

- [ ] P5.01 Add dedicated Form Requests and validation messages for every public form.
- [ ] P5.02 Ensure each enquiry creates a tracked Communication Centre record.
- [ ] P5.03 Implement conversations, messages, assignments, actions, alerts, follow-ups and history.
- [ ] P5.04 Implement email templates and approval states inside Communication Centre.
- [ ] P5.05 Implement franchise applications, stores, milestones, owner communication and status transitions.
- [ ] P5.06 Implement customer groups and the customer-facing order/account history.
- [ ] P5.07 Add rewards, reviews, returns and GDPR export/deletion/retention workflows.
- [ ] P5.08 Keep WhatsApp, email delivery and social credentials disabled until after live-site testing.

### Gate 6 — Single cPanel, replacing generic admin screens

Goal: make the admin experience domain-specific and visually faithful to the approved cPanel references.

- [ ] P6.01 Build the exact cPanel shell, sidebar, top bar, breadcrumbs, responsive navigation, focus states and page numbering/reference mapping.
- [ ] P6.02 Build the dashboard metrics, alerts, activity, shortcuts and empty/loading/error states.
- [ ] P6.03 Build six separate order-master screens with filters, detail, status changes, actions and authorization.
- [ ] P6.04 Build Product Manager create/edit/detail screens.
- [ ] P6.05 Build category, collection, variant, pricing, stock and discount management.
- [ ] P6.06 Build Media Manager for product images, video, 360-degree media and try-on assets.
- [ ] P6.07 Build customers, customer groups, accounts, returns, reviews and rewards administration.
- [ ] P6.08 Build franchise applications, retail stores, milestones and owner follow-ups.
- [ ] P6.09 Build Communication Centre, email templates, approvals, action queue, alerts and reports.
- [ ] P6.10 Build reports, scheduler/history, integrations and settings screens.
- [ ] P6.11 Build users, roles, groups and the permission matrix.
- [ ] P6.12 Build backups, restoration, automation, audit history and maintenance operations.
- [ ] P6.13 Add domain-specific policies, Form Requests, tests and audit entries for every cPanel action.
- [ ] P6.14 Remove or replace generic AdminRecord CRUD wherever the approved design defines a real module.

### Gate 7 — Page Manager

- [ ] P7.01 Build dedicated page create/edit screens with draft, review, publish and archive status.
- [ ] P7.02 Add live preview using the same public renderer as the published page.
- [ ] P7.03 Add templates, sections, widgets, media, forms and permitted custom-code handling.
- [ ] P7.04 Add drag-and-drop ordering with server-side ordering validation.
- [ ] P7.05 Add revision snapshots, revision history and safe revision restoration.
- [ ] P7.06 Add scheduling, locale content, SEO/meta fields and workflow transitions.
- [ ] P7.07 Add duplication, archive, trash, restore, permanent-delete and bulk actions.
- [ ] P7.08 Add import/export, navigation management, analytics/statistics and activity history.
- [ ] P7.09 Connect SiteController and public Blade views to managed page content; no managed record may be ignored by the public renderer.
- [ ] P7.10 Add per-action authorization and audit coverage.

### Gate 8 — Security and application architecture

- [ ] P8.01 Install/configure Spatie Laravel Permission and seed the owner/admin role safely.
- [ ] P8.02 Add policies and gates for every public, customer, admin and integration action.
- [ ] P8.03 Add MFA for privileged users and recovery/audit flows.
- [ ] P8.04 Use public UUID/ULID route identifiers and prevent accidental internal-ID exposure.
- [ ] P8.05 Complete immutable audit coverage for operational CRUD, authentication and privileged actions.
- [ ] P8.06 Add API v1 routes, API Resources, authentication and OpenAPI documentation.
- [ ] P8.07 Add domain events and after-commit jobs for orders, communication, media and imports.
- [ ] P8.08 Configure queue workers for imports, communication and media processing.
- [ ] P8.09 Add safe upload validation, derivative generation and a malware-scanning boundary.
- [ ] P8.10 Add rate limiting, signed webhook verification and replay protection.
- [ ] P8.11 Verify secure cookies, HTTPS assumptions, APP_DEBUG=false and secret handling in deployment.

### Gate 9 — Objective validation

- [ ] P9.01 Add Playwright screenshot capture at every approved viewport.
- [ ] P9.02 Store approved baselines without overwriting supplied source images.
- [ ] P9.03 Compare all 167 manifest rows, including duplicate rows, with a strict documented threshold.
- [ ] P9.04 Add desktop, tablet and mobile coverage wherever the reference specifies it.
- [ ] P9.05 Add accessibility scans, keyboard navigation and visible focus-state checks.
- [ ] P9.06 Test loading, empty, validation-error, server-error, success and permission-denied states.
- [ ] P9.07 Add unit tests for pricing, discounts, shipping, coupons, refunds and inventory.
- [ ] P9.08 Add feature tests for auth, public forms, orders, Page Manager, Communication Centre and cPanel.
- [ ] P9.09 Add authorization, database portability and concurrent checkout tests.
- [ ] P9.10 Add browser end-to-end tests for the approved customer and admin workflows.
- [ ] P9.11 Add backup-and-restore tests for PostgreSQL and public media.
- [ ] P9.12 Add production-like deployment smoke tests and keep the single deploy.yml workflow green.
- [ ] P9.13 Update the manifest status only from recorded evidence; visual inspection alone is not acceptance.

### Gate 10 — Deployment hardening and deferred integrations

- [ ] P10.01 Verify PostgreSQL 17, Nginx/PHP-FPM, document root public/, TLS and environment permissions.
- [ ] P10.02 Run the queue worker under Supervisor and schedule schedule:run every minute.
- [ ] P10.03 Add a documented swap/build strategy for the 4 GB Hetzner host, or move image builds to a dedicated builder.
- [ ] P10.04 Verify health, logs, error rate, database connections, queue failure handling and rollback.
- [ ] P10.05 Perform a verified PostgreSQL and storage/app/public restore drill.
- [ ] P10.06 Keep every external live toggle false during core acceptance.
- [ ] P10.07 After core acceptance, configure exactly one provider, test it in staging, verify webhooks/health, enable it, deploy and monitor.
- [ ] P10.08 Repeat the provider process separately for payment/webhooks, WhatsApp, email, shipping, social, hosted 360-degree and hosted try-on.

## How each work unit must be completed

For every task ID:

1. Record the affected REF-* rows before coding.
2. State the exact files and routes being handled.
3. Implement one cohesive task; do not hide unrelated changes in the same commit.
4. Add or update backend, authorization and validation logic.
5. Add unit/feature/browser tests relevant to the task.
6. Capture the required viewport screenshots and run the pixel diff.
7. Update the manifest, gap matrix and ambiguity log.
8. Commit with the task ID in the message.
9. Record exact test commands and results in the daily handoff.

Suggested commit format:

    P3.05 Implement product detail variants and availability

Suggested daily handoff:

    Date:
    Task IDs:
    Reference IDs:
    Files/routes:
    Commit:
    Tests and exact results:
    Screenshot diff results:
    Blockers or decisions:
    Next task:

## Required validation commands

Run these against a disposable test database, never against production data:

    composer validate --strict
    php artisan migrate:fresh --seed --force
    php artisan route:list
    php artisan test

For each database engine, record the connection and result separately:

- Local Herd: PHP 8.4 and MySQL
- CI/staging/production: PostgreSQL 15 or newer, targeting PostgreSQL 17

For deployment smoke validation:

    curl --fail --retry 10 --retry-all-errors https://emeraldrozalia.com/up

The production workflow remains .github/workflows/deploy.yml; do not create or restore a second CI workflow unless the project owner explicitly changes this decision.

## Definition of done

Project 1 may be called complete only when:

1. All 167 ordered visual references map to implemented and tested screens or documented approved states.
2. All 165 supplied image references are represented, including all intentional duplicates.
3. Every approved viewport comparison passes the documented pixel-difference threshold.
4. MySQL and PostgreSQL migrations, seeds, reruns and supported rollbacks pass.
5. Public customer, commerce, franchise, Communication Centre, Page Manager and cPanel workflows pass end to end.
6. Policies, roles, MFA, auditing, idempotency, queues, webhooks, uploads and concurrency protections pass.
7. Accessibility, keyboard, responsive and failure-state checks pass.
8. Backup and restoration are proven.
9. The single deploy.yml validation and deployment path is green.
10. Production-like deployment smoke tests pass.
11. The traceability manifest contains no incomplete, unverified or placeholder rows.
12. No live external service is enabled without credentials, staging verification and a recorded go-live decision.

Until these conditions are evidenced, describe the repository as an actively implemented baseline, not as complete, server-ready, or pixel-perfect.
