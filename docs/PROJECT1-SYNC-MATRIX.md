# Project 1 public/private synchronization matrix

**Current verified release:** Communication Center contract follow-up deployed `main` release `b8b9db1d1f9313bb3a3f49a3051c38cd321cbbf3`
**Current batch:** Communication Center delivery/webhook contract plus Email Templates durable contract candidate
**Rule:** “connected” means a traceable shared model/query path exists; it does not mean the complete production workflow is proven.

| Public surface | Public read/write path | Private consumer | Current result | Required next contract |
|---|---|---|---|---|
| Catalog, shop and product | SiteController reads Product, Category, variants, media and reviews | Product/category/variant/media admin controllers and pages | Partial shared model path | Uniform tenant/UUID scope, media approvals, route/browser proof |
| Product reviews | ReviewController writes pending Review records; Product::reviews uses the named approved scope | ResourceController reviews-ratings page and audited status route | Source-of-truth connected; public reads approved records while admin reads and moderates all live records | Complete tenant/company policy, verified-buyer/media rules, moderation history and visual/browser proof |
| Banners and sliders | SiteController and `/api/v1/banners` read Banner::publishedFor() for scheduled Home - Main Slider records | BannerController CRUD, revisions, actions and audit | Source-of-truth connected; status, schedule, position and page-target filters are regression-tested | Complete media approval, tenant/company workflow and screenshot/browser/accessibility proof |
| Managed content pages | SiteController queries published/current-locale/scheduled ContentPage; controlled site/preview renderer publishes body and typed sections | PageManagerController creates revisions and publishes; footer composer consumes published footer pages | Public read path and access policy are feature-tested; workflow/visual proof remains partial | Complete template/navigation/restore/bulk workflow, public screenshot/accessibility tests and visual archive mapping |
| Contact/corporate/bulk/careers/franchise forms | POST /enquiry writes Inquiry; creates Conversation/Message; franchise also creates FranchiseApplication | Communication Center and Franchise screens | Shared write path now persists tenant/entity links when known, correlation/request hash, consent and public idempotency state; duplicate submissions remain one record set | Add provider delivery verification, template/approval/follow-up source-of-truth and visual/browser/accessibility proof |
| Catalog cart | CartController/CartService reads Product/Variant and session cart | CheckoutController | Shared product path; session cart is not cross-device/persisted | Define cart identity and authenticated handoff semantics |
| Checkout and order | Checkout transaction creates Order, OrderItem, inventory movement, PaymentTransaction and RewardTransaction | Admin orders, customer account, payments and reports | Strongest shared path; idempotency/provider/minor-unit gaps remain | Shared order transition service, idempotency, provider boundary, reconciliation tests |
| Customer account | AccountController reads authenticated user’s orders, payments, returns and addresses | Admin order/customer/payment/return pages | Shared records with owner checks | Complete policy/transition matrix and privacy tests |
| Franchise lead/store | Public franchise form creates application; FranchiseManagementController queries applications/stores | Franchise cPanel sections | Partial; no durable origin link and generic/fallback sections remain | Application→approval→agreement→onboarding→store UUID/correlation workflow |
| Communication web/channel path | Public forms create shared web Conversation/Message; cPanel replies use the CommunicationCenter service and after-commit delivery job; signed provider callbacks use the event ledger | Communication Center and communication reports | Shared conversation path, reply idempotency, queue/retry state, provider boundary, signed callback duplicate handling and redaction are feature-tested; providers remain intentionally unconfigured | Wire and verify real email/WhatsApp/chat adapters, approval/follow-up/alert contracts, reports/analytics and visual/browser/accessibility proof |
| Communication email templates | Admin Email Templates reads/writes the tenant-scoped `CommunicationTemplate` aggregate through named web/API routes; resource responses expose UUIDs and lifecycle changes are versioned/idempotent/audited | Email Templates dashboard, future provider/template consumers | Durable template source-of-truth and API contract are feature-tested in the candidate batch; approval linkage, provider rendering/delivery and visual proof remain open | Connect approval records and provider rendering, add live delivery/report reconciliation and browser/a11y evidence |
| Settings and branding | SettingsController saves tenant-scoped AdminRecord and appends a public-safe `PublishedSiteSetting` revision for general, company-branding and localization sections | Shared public shell and TenantContext consume the latest published revision; private sections remain admin-only | Source-of-truth connected for the shared shell; version, tenant isolation, allow-list, asset/color sanitization and reverse reads are feature-tested | Add explicit approval/activation/rollback UI, per-record policies, provider consumers and supplied-reference screenshot/accessibility proof |
| Reports and analytics | ReportController/analytics service reads Order, Conversation and User | Report pages and exports | Live queries exist, but fallbacks/simplified metrics/N+1 are present | Versioned read models, no fixture fallback, pagination, currency/timezone and export reconciliation |

## Synchronization acceptance

For each row, a release must show:

- the source record and tenant/company scope;
- the exact write/read route and authorization decision;
- the event/transaction boundary for side effects;
- the admin/customer/public result after publish or conversion;
- audit/correlation identifiers and privacy treatment;
- happy, empty, validation, failure, retry and forbidden tests.

A menu label or matching view name is not synchronization evidence.
