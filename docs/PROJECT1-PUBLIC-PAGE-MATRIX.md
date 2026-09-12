# Project 1 public and customer page matrix

**Baseline:** c54e933e36c68f2951e5e05afb32119ea02ea467
**Purpose:** isolate the public/customer page family from admin-only reference entries and record the actual data/write paths.

A static Blade view or a named route is not visual completion. Every row needs a matching source reference, real data contract, responsive browser result, accessibility result, and public-to-private synchronization evidence.

| Guide row | Guide visual | Route | Controller/view owner | Read data | Write/consumer path | Existing test/evidence | Status |
|---:|---|---|---|---|---|---|---|
| 002 | Approved Home Page | GET / | SiteController::home → site.home | Product, Category; static sections | — | HomeCollectionsReferenceTest; foundation.spec.cjs | Partial · screenshot diff pending |
| 066 | home page 1 | GET / | SiteController::home → site.home | Product, Category; configured brand assets | — | HomeCollectionsReferenceTest; foundation.spec.cjs | Partial · source visual registered |
| 067 | home page 2 | GET / | SiteController::home → site.home | Product, Category; configured brand assets | — | HomeCollectionsReferenceTest; foundation.spec.cjs | Partial · source visual registered |
| 068 | shop page | GET /shop | SiteController::shop → site.shop | Product, Category, variants, media | GET /cart/{product} | ShopReferencePageTest; foundation.spec.cjs | Partial |
| 069 | shop by catagory | GET /category/{category:slug} or /shop | SiteController::category/shopCatalog → site.shop | Category, Product filters | GET /cart/{product} | ShopReferencePageTest | Partial · category source mapping pending |
| 070 | hats collection | GET /collections | SiteController::collections → site.collections | Category, Product | — | CollectionsReferencePageTest | Partial |
| 071 | new arrival page | GET /new-arrivals | SiteController::newArrivals → site.new-arrivals | Product, Category, reviews aggregates | GET /cart/{product} | NewArrivalsReferencePageTest | Partial |
| 072 | our collection page | GET /collections | SiteController::collections → site.collections | Category, Product | — | CollectionsReferencePageTest | Partial |
| 073 | irish traditional page | GET /irish-traditional | SiteController::irishTraditional → site.category-landing | Category slug irish-traditional-flat-caps, Product | GET /cart/{product} | Informational/public page coverage; screenshot pending | Partial |
| 074 | irish heritage page | GET /irish-heritage | SiteController::irishHeritage → site.category-landing | Category slug irish-heritage-hats, Product | GET /cart/{product} | Informational/public page coverage; ProductManagedSpinIntegrationTest | Partial |
| 075 | gaa baseball page | GET /category/{category:slug} | SiteController::category → site.shop | Category, Product | GET /cart/{product} | No dedicated GAA browser suite | Partial |
| 076 | gaa bucket hats | GET /category/{category:slug} | SiteController::category → site.shop | Category, Product | GET /cart/{product} | No dedicated GAA browser suite | Partial |
| 077 | gaa beanie hats page | GET /category/{category:slug} | SiteController::category → site.shop | Category, Product | GET /cart/{product} | No dedicated GAA browser suite | Partial |
| 078 | gaa footbal club page | GET /category/{category:slug} | SiteController::category → site.shop | Category, Product | GET /cart/{product} | No dedicated GAA browser suite | Partial |
| 079 | quality | GET /quality via content.page | SiteController::page → site.page | Published ContentPage if present; fallback route allowed | POST /enquiry only if page supplies form | No dedicated quality suite | Partial · managed page/source gap |
| 080 | Manufacturing and Franchise | GET /factory | SiteController::factory → site.factory | Static factory view and approved assets | POST /enquiry only if page supplies form | No dedicated factory suite | Partial |
| 081 | how we work 1 | GET /how-we-work via content.page | SiteController::page → site.page | Published ContentPage if present | — | No dedicated how-we-work suite | Partial · managed page/source gap |
| 082 | how we work | GET /how-we-work via content.page | SiteController::page → site.page | Published ContentPage if present | — | No dedicated how-we-work suite | Partial · managed page/source gap |
| 083 | corporate order page | GET /corporate-orders | SiteController::corporateOrders → site.corporate-order | Static page; inquiry context | POST /enquiry type=corporate-orders → Inquiry + Conversation | CorporateOrderPageTest | Partial |
| 084 | bulk order | GET /bulk-orders | SiteController::bulkOrders → site.bulk-order | Static page; inquiry context | POST /enquiry type=bulk-orders → Inquiry + Conversation | BulkOrderPageTest | Partial |
| 085 | franchise page | GET /franchise | SiteController::franchise → site.franchise | Static page; franchise context | POST /enquiry type=franchise → Inquiry + FranchiseApplication + Conversation | FranchisePageTest | Partial |
| 086 | be a store owner | GET /be-a-store-owner via content.page | SiteController::page → site.page | Published ContentPage if present | POST /enquiry only if page supplies form | No dedicated store-owner page suite | Partial · route/content gap |
| 087 | build career with us | GET /careers | SiteController::careers → site.careers | Static page; careers form context | POST /enquiry type=careers → Inquiry + Conversation | CareersReferencePageTest | Partial |
| 088 | contact us | GET /contact | SiteController::contact → site.contact | Static page; meeting fields | POST /enquiry type=contact → Inquiry + Conversation | Contact/informational coverage; screenshot pending | Partial |
| 089 | global network | GET /global-network | SiteController::globalNetwork → site.global-network | Static page; Limerick marker must be Ireland | — | No dedicated global-network suite | Partial · map/data proof pending |
| 090 | degre view | GET /product/{product:slug} + /360/{spin:uuid} | SiteController::product + SpinViewerController | Product, ProductSpin, public UUID frames | POST /360/{uuid}/visit | ProductManagedSpinIntegrationTest; SpinDashboardTest | Partial |
| 091 | bucket hats page | GET /category/{category:slug} | SiteController::category → site.shop | Category, Product | GET /cart/{product} | No dedicated bucket-hat suite | Partial |
| 092 | customer login register page | GET /login, /register | AuthController + auth views | User/session/verification | POST /login, POST /register | ProjectScopeTest; auth feature coverage | Partial |
| 093 | customer dashboard | GET /account and /account/{section} | AccountController + account views | Authenticated User, Order, Address, ReturnRequest | PATCH/POST account resources | AUTH-CUSTOMER evidence; account tests | Partial |
| 094 | store setup page | No dedicated public route | Admin/store setup is represented through cPanel resources | FranchiseStore and Franchise management records | Admin-only franchise actions | FranchiseManagementDashboardsTest | Partial · route gap |
| 095 | virtual studio try on | GET /virtual-tryon | SiteController::virtualTryOn → site.virtual-tryon | Product, ProductMedia, TryOnAsset; public assets | POST /try-on/{uuid}/visit | TryOnDashboardTest; try-on browser suite | Partial |
| 096 | bulk upload page | No dedicated customer route; admin GET /admin/bulk-product-upload | BulkProductController and admin bulk-upload view | Product import file and audit records | POST /admin/bulk-product-upload | No customer-facing upload suite | Partial · boundary/route gap |

## Shared public rules

- Header order: HOME, SHOP, COLLECTIONS, NEW ARRIVALS, CORPORATE ORDER, BULK ORDER, FRANCHISE APPLY, HIRING APPLY.
- Utilities: Language, Currency, Search, Login, Cart.
- Contact values are footer-only.
- Use the exact supplied Emerald Rozalia logo/wordmark. Do not add an unauthorized logo or shield.
- Virtual Try-On is a hero/product experience; 360° is connected to product detail through public UUID routes.
- Limerick must be located in Ireland in the global-network composition.
- Product, category, media, content, review, cart, order, customer and enquiry records must have one documented source of truth.
- Reference screenshots are evidence, not production assets. Missing archive images remain an explicit blocker.

## Public-to-private form map

| Public form | Current write path | Private consumer | Known gap |
|---|---|---|---|
| Contact | POST /enquiry with type=contact | Inquiry, Conversation, Message, Communication Center | No durable cross-record correlation ID |
| Corporate order | POST /enquiry with type=corporate-orders | Inquiry, Conversation, Message | Quote/conversion contract not proven |
| Bulk order | POST /enquiry with type=bulk-orders | Inquiry, Conversation, Message | Quote/conversion contract not proven |
| Franchise apply | POST /enquiry with type=franchise | Inquiry, FranchiseApplication, Conversation, Message | Application/inquiry/conversation linkage is duplicated, not FK-correlated |
| Hiring apply | POST /enquiry with type=careers | Inquiry, Conversation, Message | Recruitment remains outside Project 1; intake status needs explicit boundary |
| Try-On visit | POST /try-on/{uuid}/visit | TryOnVisit | Uploaded-face privacy/retention contract remains open |
| 360 visit | POST /360/{uuid}/visit | SpinVisit | Public UUID/privacy/analytics proof remains partial |

## Page-matrix exit gate

No public row is released as pixel-complete until the exact reference is available, the correct route renders real data, responsive screenshots and accessibility checks pass, and any form reaches the declared Communication Center or domain workflow.
