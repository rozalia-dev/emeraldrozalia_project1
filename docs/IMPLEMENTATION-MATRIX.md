# Project 1 implementation matrix

| Requirement | Implementation | Acceptance evidence |
|---|---|---|
| Brand | Exact supplied PNG assets in `public/assets/brand`; footer-only official contact | Asset hashes; layout review |
| Storefront | Home, shop, collection/category, product, search, account and informational Blade views | Public route tests and visual review |
| Commerce | Session cart, discounts, variants, shipping, checkout, orders, payments ledger, invoices, returns | Migration/seed plus checkout tests |
| Six order masters | `orders.order_type` enum and filtered admin controller | Each type route rejects invalid type and isolates rows |
| Customers | Auth, verification, account, addresses, wishlist, rewards, reviews | Authenticated route and policy checks |
| Franchise | Application, store and milestone entities; enquiries create tracked applications | PostgreSQL constraints and form test |
| Communication Centre | Conversation/message/template/approval entities; every public enquiry is persisted | Transactional enquiry test |
| Page Manager | Content pages, sections, revisions, SEO, locale, schedule, lifecycle and soft delete | Admin lifecycle routes and revision count |
| Media/try-on | Product media schema; image/video/360/try-on types; local browser preview | Product and studio view review |
| Access/audit | Admin middleware, roles, permissions and immutable audit entries for operational CRUD | Seeded owner role and audit records |
| Operations | Reports/settings/integrations/automation/backups/maintenance cPanel surfaces | Admin resource routes and database schema |
| Integrations | Credential fields encrypted at rest; every live toggle defaults false | `.env.example` and integration status page |
| Server readiness | PostgreSQL 17 Docker stack, Nginx, Supervisor, health route and CI | CI migration/seed/test job |

## Visual acceptance rule

The approved workflow determines documentation/page order; the approved home visual is first public screen. All 165 supplied page images, including duplicates, remain authoritative visual references. Duplicates are intentional and must not be silently merged. Automated functional tests complement—not replace—viewport screenshot comparison. Validate desktop and mobile breakpoints before accepting any pixel-to-pixel page.
