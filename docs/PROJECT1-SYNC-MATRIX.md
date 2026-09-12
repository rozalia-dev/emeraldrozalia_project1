# Project 1 public/private synchronization matrix

**Current verified release:** Batch 9 deployed `main` release `cba21d6474c3ab2d78a17aecc7175ca6dbaf2072`
**Current batch:** Settings/public-branding synchronization
**Rule:** “connected” means a traceable shared model/query path exists; it does not mean the complete production workflow is proven.

| Public surface | Public read/write path | Private consumer | Current result | Required next contract |
|---|---|---|---|---|
| Catalog, shop and product | SiteController reads Product, Category, variants, media and reviews | Product/category/variant/media admin controllers and pages | Partial shared model path | Uniform tenant/UUID scope, media approvals, route/browser proof |
| Product reviews | ReviewController writes pending Review records; Product::reviews uses the named approved scope | ResourceController reviews-ratings page and audited status route | Source-of-truth connected; public reads approved records while admin reads and moderates all live records | Complete tenant/company policy, verified-buyer/media rules, moderation history and visual/browser proof |
| Banners and sliders | SiteController and `/api/v1/banners` read Banner::publishedFor() for scheduled Home - Main Slider records | BannerController CRUD, revisions, actions and audit | Source-of-truth connected; status, schedule, position and page-target filters are regression-tested | Complete media approval, tenant/company workflow and screenshot/browser/accessibility proof |
| Managed content pages | SiteController queries published/current-locale/scheduled ContentPage; controlled site/preview renderer publishes body and typed sections | PageManagerController creates revisions and publishes; footer composer consumes published footer pages | Public read path and access policy are feature-tested; workflow/visual proof remains partial | Complete template/navigation/restore/bulk workflow, public screenshot/accessibility tests and visual archive mapping |
| Contact/corporate/bulk/careers/franchise forms | POST /enquiry writes Inquiry; creates Conversation/Message; franchise also creates FranchiseApplication | Communication Center and Franchise screens | Write path verified; records are duplicated without durable correlation ID | Add correlation/foreign-key contract, after-commit notification boundary, status/audit tests |
| Catalog cart | CartController/CartService reads Product/Variant and session cart | CheckoutController | Shared product path; session cart is not cross-device/persisted | Define cart identity and authenticated handoff semantics |
| Checkout and order | Checkout transaction creates Order, OrderItem, inventory movement, PaymentTransaction and RewardTransaction | Admin orders, customer account, payments and reports | Strongest shared path; idempotency/provider/minor-unit gaps remain | Shared order transition service, idempotency, provider boundary, reconciliation tests |
| Customer account | AccountController reads authenticated user’s orders, payments, returns and addresses | Admin order/customer/payment/return pages | Shared records with owner checks | Complete policy/transition matrix and privacy tests |
| Franchise lead/store | Public franchise form creates application; FranchiseManagementController queries applications/stores | Franchise cPanel sections | Partial; no durable origin link and generic/fallback sections remain | Application→approval→agreement→onboarding→store UUID/correlation workflow |
| Communication web channel | Public form creates web Conversation/Message | Communication Center and communication reports | Shared web path verified | Provider adapters, signed callbacks, queues, retries, delivery state and redaction |
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
