# Emerald Rozalia Project 1 — Agent Work Instructions

This file is the execution contract for future coding agents working on Project 1. Read it and README.md before changing the repository. The work must proceed in small, user-reviewable tasks. Do not start the next implementation task until the user has reviewed the previous result.

## Mission

Finish the existing Laravel 13 website and single admin cPanel for Emerald Rozalia Limited. The repository currently contains a foundation and several placeholders; it is not evidence that every approved screen or workflow is complete.

The required working loop is:

1. State one task, its exact files/routes/screens, and its acceptance checks.
2. Wait for the user's revision or approval when the task changes product behaviour or visual design.
3. Make the smallest coherent change.
4. Run the relevant checks and report the exact results.
5. Update the task evidence and README.md when the verified status changes.

Do not bundle unrelated domain work into one change.

## Reviewed repository baseline

The user's requested Windows directory is C:\Users\newuser\Herd\emeraldrozalia_project1. That directory was not mounted in the review environment. The available clean snapshot reviewed for this instruction file was the repository's origin/main at commit 0727c8d (Add GitHub Actions workflow for production deployment to Hetzner). Confirm the user's actual branch and commit before applying these instructions there.

The current README.md correctly describes the intended Project 1 boundary and deployment shape, but its Delivered scope list is broader than the verified implementation. Treat a README claim as a target or foundation claim until a test, migration result, route check, or approved screenshot proves it.

Observed baseline inventory in the reviewed snapshot:

| Area | Current evidence |
|---|---:|
| Blade templates | 23 total; 6 admin templates |
| Controllers | 15, including the base controller |
| Eloquent models | 31 |
| Migration-declared tables | 53 |
| Dedicated Form Requests | 0 |
| Policies | 0 |
| Jobs / domain events | 0 |
| API route files | 0 |
| Test classes | 1, with basic feature coverage |
| Product/brand assets tracked in public/assets | 2 PNG brand assets |
| Approved visual references pixel-verified | 0 / 167 |

The separate codex/phase-0-1 history contains an earlier gap matrix, visual traceability CSV, and a migration-hardening attempt that are not present on the reviewed origin/main tree. Reconcile those artifacts deliberately; do not silently discard intentional duplicate references or merge a branch without checking its migration and deployment changes.

PHP, Composer, and vendor/ were unavailable in the review environment, so no runtime, migration, route, or PHPUnit result is evidence yet. Record real command output once the target repository has its runtime.

## Locked Project 1 boundary

Project 1 owns one franchise-focused admin cPanel and the public website/customer experience:

- Website & Products, catalogue, categories, variants, product media and Page Manager.
- Online sales, cart, checkout, customers, returns/refunds, discounts and payment hooks.
- Six separate order masters: online, corporate, bulk, franchise, franchise_retail, and buyer.
- Franchise Management and Franchise Retail Stores inside the same cPanel. There is no separate Franchise Portal.
- Communication Centre: inbox, 24/7 chat, WhatsApp, email, templates, approvals, follow-ups/actions, alerts, reports and complete history/audit.
- Reports, Users & Roles, Integrations, Settings, Audit/Logs, Automation, Backup/Recovery, System Maintenance and the Page Manager.
- Company, language and currency context where the approved specification requires it.

The following are outside Project 1 and must not be added to its cPanel: production operations, unified finance, payroll, HR, POS, or a separate franchise portal. Production, shared UUID/planning/traceability, stock, finance, POS and Project 2 capabilities may be integrated later through an explicitly designed API/event boundary; do not recreate them as hidden Project 1 modules.

## Locked brand, navigation and content rules

- Use the supplied Emerald Rozalia logo/wordmark exactly as provided. Do not redraw, approximate, recolour, restyle, add a crest/crown/shield, or substitute another logo.
- Approved company position: Emerald Rozalia Limited, Limerick, Ireland. Approved slogan: “Irish Made. Limerick Born. Worn Everywhere.”
- Approved contact values: 0899788187, urmos@rozalia.ie, and emeraldrozalia.ie. Show contact details in the footer only; do not repeat them in the header or page body.
- Public header navigation is: HOME | SHOP | COLLECTIONS | NEW ARRIVALS | CORPORATE ORDER | BULK ORDER | FRANCHISE APPLY | HIRING APPLY.
- Header utilities are Language, Currency, Search, Login and Cart. Virtual Try-On belongs in the hero or product experience, not as a header navigation item.
- Keep the Limerick marker in Ireland when implementing the global-network visual. Do not invent partner counts, countries, production facts, or contact values.
- Responsive behaviour and the approved desktop/mobile references are acceptance requirements, not optional polish.

The referenced file Emerald_Rozalia_Project_1_Visual_Developer_Guide_v3(1).pdf was unavailable during this review. When it is supplied again, use it as specification evidence and record its pages/assets in the traceability manifest; never claim that it was reviewed before it is available.

## Open findings from the repository review

Treat each item below as open until a later task closes it with evidence.

### Evidence and visual completeness

- The reviewed main tree contains only the two brand PNGs, not the supplied 165-image reference archive.
- There is no 167-reference traceability manifest or current gap matrix on origin/main.
- The public CSS and Blade views still contain placeholder glyphs, emoji, gradients, generic cards and invented artwork. No approved screen has passed pixel comparison.
- The homepage, catalogue, product, informational, customer and admin views are foundations; distinct approved screens are not interchangeable with a generic template.

### Immediate technical contract breaks

- routes/web.php maps /cart to CartController@index and cart updates to CartController@update, but the reviewed CartController does not define those methods.
- That controller references missing App\Models\Customer and App\Services\OrderService classes, uses storefront.* view names that are not present, and redirects to route names cart.show and cart.confirmation that are not defined.
- CartController uses fields such as active, enum status values, stock_quantity, price_minor, and attributes, while the reviewed models, migration and CartService use is_active, string status, stock, price, and colour/size/options fields. Choose one coherent cart contract and test it end to end.
- The cart Blade view expects the CartService shape (items, subtotal, key, price, options), but the route currently points at the incompatible controller implementation.
- The 360° JavaScript looks for data-spin, while the product view exposes data-spin-viewer/data-images; the feature is not verified functional.
- Product media has a migration table but no ProductMedia model or dedicated media-manager workflow. The virtual try-on currently uses a placeholder badge asset instead of a product-specific transparent hat asset.
- The Page Manager has basic lifecycle persistence, but the public page view does not render the managed page/sections, and there is no complete edit, preview, builder, template, navigation, restore-revision or bulk workflow.
- Many cPanel links resolve to generic AdminRecord pages. The dashboard quick links for /admin/module/products and /admin/module/collections do not match the reviewed AdminController allow-list.
- Communication, franchise stores/milestones, reports, integrations, automation, backup/recovery and maintenance have schema/foundation pieces but lack their dedicated workflows, permissions, adapters and acceptance tests.

### Data, security and deployment gaps

- Operational queries are not consistently scoped by company_id; Category does not allow company_id through its fillable fields, and public/admin product, order, inquiry and store queries need explicit tenant rules.
- The only admin gate is the boolean is_admin; seeded roles and permissions are not yet enforced by policies or per-action authorization. Add least-privilege checks before exposing real management operations.
- The UUID/domain migration on the reviewed main tree is not a proven interrupted-run recovery path. It must be deterministic, safe for existing rows, idempotent where required, portable across the supported databases, and tested on clean and partially applied schemas.
- The multi-company migration's rollback must be tested for foreign-key ordering and removal of tenant columns/constraints.
- Checkout currently needs row-lock/stock correctness, decimal-money consistency, coupon date/usage enforcement, payment/refund contracts and audit/event boundaries before production use.
- Docker's fixed PostgreSQL user and .env.example database user are not aligned by default. The secure-cookie default also needs a deliberate local/staging/production configuration.
- The deployment workflow health URL must match the approved live domain and must be gated by the required CI checks. Never commit credentials or enable live external services by default.

## Required work order

Do these phases in order. Each numbered item is intended to be a separate reviewable task or small commit. A phase gate must pass before the next phase starts.

### Phase 0 — Evidence and source reconciliation

| ID | Small task | Required output / gate |
|---|---|---|
| P0.1 | Confirm the actual repository path, branch, commit, clean/dirty state, README and available source files. | Baseline report with exact paths and git status; no application change. |
| P0.2 | Re-attach or mount the approved Developer Guide/SPC and supplied image archive. | Source files are available and named; unavailable evidence is explicitly listed. |
| P0.3 | Build the ordered visual traceability manifest. | Exactly 167 reference rows, 165 supplied image references, 142 unique hashes, and 23 intentional duplicate references retained. |
| P0.4 | Build the implementation gap matrix from the real code. | Every row has source page, route, role, component, backend dependency, test ID and honest status. |
| P0.5 | Verify the two approved logo assets by hash/visual inspection and document footer-only contact placement. | No unauthorized brand asset or invented contact detail. |

Phase 0 gate: the user can review the evidence inventory and agree that it is the source of truth for later screen work.

### Phase 1 — Green technical baseline

| ID | Small task | Required output / gate |
|---|---|---|
| P1.1 | Validate PHP/Composer versions, extensions, lockfile and autoloading. | composer validate --strict, install and class discovery succeed. |
| P1.2 | Repair the cart contract in one bounded change. | /cart, add, update, remove, login redirect and checkout handoff use one service/model/view shape; feature tests pass. |
| P1.3 | Harden the UUID/domain migration. | Existing rows are backfilled before unique/non-null enforcement; interrupted runs and re-runs are safe; no database-expression UUID default. |
| P1.4 | Verify migration and seed lifecycle on supported engines. | Clean migrate/seed, route list, rollback where data-safe, re-run and partial-recovery checks pass on PostgreSQL and MySQL; local SQLite is only a convenience check. |
| P1.5 | Align models, factories, seed data and tenant context. | Foreign keys, fillable/casts/relations, company IDs, active language/currency membership and query scoping agree. |
| P1.6 | Align Docker, .env.example, Nginx, Supervisor and deployment workflow. | DB credentials, health URL, session behaviour, storage permissions and CI/deploy ordering are tested without secrets. |
| P1.7 | Expand the smoke suite for all foundation routes and excluded-module 404s. | CI is green and exact results are recorded before visual expansion. |

Phase 1 gate: the application boots, migrations/seed are reproducible, cart/core routes work, the six order-master routes are isolated, excluded modules remain excluded, and CI is green.

### Phase 2 — Approved visual foundation

| ID | Small task | Required output / gate |
|---|---|---|
| P2.1 | Extract design tokens, typography, spacing, colours, borders and responsive breakpoints from approved references. | Shared token file and evidence mapping; no invented replacement artwork. |
| P2.2 | Implement the public header, utility controls and footer. | Exact nav order, Language/Currency/Search/Login/Cart, responsive states and footer-only contacts verified. |
| P2.3 | Implement the single cPanel shell and sidebar. | Approved hierarchy, active state, responsive navigation and excluded-module absence verified. |
| P2.4 | Add deterministic desktop/mobile screenshot capture and comparison tooling. | Reproducible viewport, font, asset and screenshot baseline; visual test IDs link to the manifest. |
| P2.5 | Rebuild the approved homepage from its reference. | Hero, try-on placement, collection/product imagery, Irish heritage, manufacturing, franchise and footer sections match the approved evidence. |

### Phase 3 — Public website and product experience

| ID | Small task | Required output / gate |
|---|---|---|
| P3.1 | Implement shop, category, collections and new-arrivals screens. | Real filters, search, pagination, empty/loading/error states and responsive comparisons. |
| P3.2 | Implement the product detail screen and variants. | Correct pricing, stock, colour/size/variant selection, related products, wishlist and reviews. |
| P3.3 | Implement Product Media Manager. | Images, videos, 360° frames, try-on assets, ordering, alt text, active state, storage validation and permissions. |
| P3.4 | Repair and verify 360° interaction. | Gallery/360 controls, pointer/touch/keyboard behaviour, missing-frame handling and tests. |
| P3.5 | Implement Virtual Studio Try-On safely. | Product-specific overlay selection, in-browser default, privacy/consent boundary, responsive controls and no unauthorized asset use. |
| P3.6 | Implement approved Irish traditional, Irish heritage, GAA, factory, global-network, corporate, bulk, franchise, careers and contact screens. | Each approved reference gets its own traceability status; no generic page substituted without approval. |

### Phase 4 — Customer and commerce workflows

| ID | Small task | Required output / gate |
|---|---|---|
| P4.1 | Complete registration, login, verification, password reset and customer dashboard. | Validation, rate limits, session security, email states and authorization tests. |
| P4.2 | Complete cart and checkout after P1.2. | Address, shipping, discount, payment choice, totals, order confirmation and failure states are consistent. |
| P4.3 | Correct inventory and money handling. | Minor/decimal decision documented, row locks/availability checks, no oversell, inventory movement records and concurrency tests. |
| P4.4 | Complete order, invoice, return/exchange and payment ledgers. | Customer ownership, admin lifecycle, refund hooks, documents, audit history and provider-neutral interfaces. |
| P4.5 | Implement the six order-master workflows. | Separate filters, detail states, approvals, documents, returns and metrics for Online, Corporate, Bulk, Franchise, Franchise Retail and Buyer. |

### Phase 5 — Dedicated cPanel domains

| ID | Small task | Required output / gate |
|---|---|---|
| P5.1 | Replace generic product/category/variant admin records with domain screens. | Create/edit/duplicate/archive/import/export, validation, media links and audit entries. |
| P5.2 | Complete Page Manager. | All Pages, Add/Edit, sections/widgets/media/forms/custom-code boundaries, preview, publish/unpublish, draft, schedule, archive, trash/restore/permanent delete, templates, navigation, multilingual SEO and revision restore. |
| P5.3 | Complete Franchise Management and Franchise Retail Stores. | Territory, application, assignment, agreement, training, store setup, milestones, marketing, performance, renewal and store lifecycle. |
| P5.4 | Complete Communication Centre. | Inbox, chat, email/WhatsApp adapters, templates, approvals, actions/follow-ups, alerts, reports, delivery status and immutable history. |
| P5.5 | Complete reports and exports. | Tested read models/queries, date/status filters, order-type metrics, export history and permission checks. |
| P5.6 | Complete Users & Roles. | Users, roles, permissions, groups/assignments, matrix UI, MFA decision, session/security activity and least-privilege enforcement. |
| P5.7 | Complete Integrations, Settings, Automation, Backup/Recovery and Maintenance. | Typed settings, encrypted credentials, disabled-by-default health checks, queued automation, backup records, restore drill and maintenance controls. |

### Phase 6 — Security and architecture hardening

| ID | Small task | Required output / gate |
|---|---|---|
| P6.1 | Add Form Requests and policies for each public/admin write boundary. | Authorization is action-specific; validation and ownership tests cover every mutation. |
| P6.2 | Make audit coverage complete. | UUID/request/user/IP, before/after, approval, export, login and destructive-operation trails are queryable and protected. |
| P6.3 | Add domain events, after-commit jobs and retry/idempotency rules. | Communication, payment, stock, reporting and automation side effects are observable and safe to retry. |
| P6.4 | Define API v1/integration contracts only where Project 1 needs external boundaries. | Resources, authentication, signatures, webhook replay protection, rate limits and OpenAPI/equivalent documentation. |
| P6.5 | Complete privacy/accessibility safeguards. | GDPR retention/consent/deletion/export decisions, face-photo handling, keyboard/focus/contrast/labels and secure headers. |

### Phase 7 — Objective acceptance and deployment

| ID | Small task | Required output / gate |
|---|---|---|
| P7.1 | Expand unit/feature tests. | Commerce, order masters, franchise, communication, page lifecycle, permissions and tenant isolation are covered. |
| P7.2 | Add browser end-to-end and accessibility checks. | Desktop/mobile critical journeys, keyboard use, validation errors, auth boundaries and 404/500 states pass. |
| P7.3 | Compare every reference. | All 167 rows have a screenshot/test result; intentional duplicates remain linked; no pixel-complete claim without comparison evidence. |
| P7.4 | Run backup/restore and deployment smoke drills. | PostgreSQL backup restore, storage restore, queue/scheduler, health route, rollback and post-deploy checks pass. |
| P7.5 | Update README and release evidence. | README separates implemented, verified and remaining work; commands, environment requirements, migration support and deployment domain are accurate. |

## Verification commands

Run only commands supported by the target environment, and record exact output or the reason a check is blocked:

~~~bash
git status --short --branch
git log -1 --oneline --decorate
composer validate --strict
composer install --no-interaction --prefer-dist
php artisan migrate:fresh --seed --force
php artisan route:list
php artisan test
~~~

For database/deployment tasks, also record the engine/version, clean migration, seed, rollback/re-run or recovery result, Docker configuration result, health endpoint result and backup/restore result. Do not report “passed” when the command was not run.

## Safety and change rules

- Preserve unrelated user changes, uploaded assets, source names and the 23 intentional duplicate visual references.
- Use apply_patch for source edits. Never overwrite .env, commit secrets, or place real credentials in fixtures, screenshots or logs.
- Do not connect live payment, WhatsApp, email, shipping, social, hosted 360° or hosted try-on services during normal development. Keep all live toggles false unless the user explicitly authorizes a staged integration task.
- Do not invent missing visual details, partner data, product photography, production facts, or contact details. Mark the gap and ask for the source.
- Do not add excluded Production, Finance, Payroll, HR, POS or a separate franchise portal to Project 1.
- Do not claim that a route, screen, migration, workflow, security control, visual match or deployment is complete without the corresponding evidence.
- If the target repository, approved guide, image archive, logo, or user decision is unavailable and the choice would change implementation, stop that task and ask the user for the missing input.

## Definition of Project 1 completion

Project 1 is complete only when the approved evidence set is traceable and accepted, the technical baseline is green on the supported deployment engines, all required public/admin workflows and permissions work, all 167 references have objective visual/functional evidence, responsive/accessibility/security checks pass, backup/restore and deployment drills pass, and README.md accurately states what was verified. Until then, describe the repository as a foundation with explicitly named remaining work.
