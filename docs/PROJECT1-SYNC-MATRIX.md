# Project 1 public/private synchronization matrix

**Baseline:** Batch 6 deployed `main` release `b6cd7cd19d3b8875d82f30ea91cb31d132e85622`
**Rule:** “connected” means a traceable shared model/query path exists; it does not mean the complete production workflow is proven.

| Public surface | Public read/write path | Private consumer | Current result | Required next contract |
|---|---|---|---|---|
| Catalog, shop and product | SiteController reads Product, Category, variants, media and reviews; product detail selects the public managed 360 source | Product/category/variant/media admin controllers and pages | Shared product/media path verified; broader tenant/visual proof remains partial | Uniform tenant/UUID scope, media approvals, route/browser proof |
| Product reviews | ReviewController writes Review; product page reads approved reviews | ResourceController reviews-ratings page | Not synchronized; admin page uses fixture rows | Admin must query Review with tenant/status/pagination and test public→admin visibility |
| Banners and sliders | No verified public Banner query/placement found in SiteController/public views | BannerController CRUD, revisions, actions and audit | Not synchronized | Published Banner placement model/query and public screenshot/sync test |
| Managed content pages | SiteController queries published/current-locale/scheduled ContentPage; controlled site/preview renderer publishes body and typed sections | PageManagerController creates revisions and publishes; footer composer consumes published footer pages | Public read path and access policy are feature-tested; workflow/visual proof remains partial | Complete template/navigation/restore/bulk workflow, public screenshot/accessibility tests and visual archive mapping |
| Contact/corporate/bulk/careers/franchise forms | POST /enquiry writes Inquiry; creates Conversation/Message; franchise also creates FranchiseApplication | Communication Center and Franchise screens | Write path verified; records are duplicated without durable correlation ID | Add correlation/foreign-key contract, after-commit notification boundary, status/audit tests |
| Catalog cart | CartController/CartService reads Product/Variant and session cart | CheckoutController | Shared product path; session cart is not cross-device/persisted | Define cart identity and authenticated handoff semantics |
| Checkout and order | Checkout transaction creates Order, OrderItem, inventory movement, PaymentTransaction and RewardTransaction | Admin orders, customer account, payments and reports | Strongest shared path; idempotency/provider/minor-unit gaps remain | Shared order transition service, idempotency, provider boundary, reconciliation tests |
| Customer account | AccountController reads authenticated user’s orders, payments, returns and addresses; dashboard counts are owner-scoped | Admin order/customer/payment/return pages | Shared records with owner checks; dashboard total-order/return counts verified in Batch 6 | Complete policy/transition matrix and privacy tests |
| Franchise lead/store | Public franchise form creates application; FranchiseManagementController queries applications/stores | Franchise cPanel sections | Partial; no durable origin link and generic/fallback sections remain | Application→approval→agreement→onboarding→store UUID/correlation workflow |
| Communication web channel | Public form creates web Conversation/Message | Communication Center and communication reports | Shared web path verified | Provider adapters, signed callbacks, queues, retries, delivery state and redaction |
| Settings and branding | SettingsController saves AdminRecord and selected Company/IntegrationConnection state | Shared admin/public context and layout | Partial; public layout still relies on config/static assets in places | Versioned published settings/theme contract and reverse-direction tests |
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
