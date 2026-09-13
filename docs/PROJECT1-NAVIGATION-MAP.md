# Project 1 navigation map

**Baseline:** Batch 6 deployed `main` release `dfeb04dac54982bc420b537384cdc5eedf28abf5`
**Canonical owner:** one unified Project 1 cPanel and the public/customer website
**Reference sources:** guide v3, cPanel/Settings/Orders/Reports references, resources/views/layouts/admin.blade.php

## Locked boundary

Project 1 contains the website/content, product display, online sales, customer communication, Franchise Management, Franchise Retail Stores, Reports, Users & Roles, Integrations, Settings, and Page Manager. It does not contain Production, unified Finance, Payroll, HR, POS, or a separate Franchise Portal.

## Public header and footer

The public header navigation is locked in this order:

HOME · SHOP · COLLECTIONS · NEW ARRIVALS · CORPORATE ORDER · BULK ORDER · FRANCHISE APPLY · HIRING APPLY

Header utilities are Language, Currency, Search, Login, and Cart. Virtual Try-On belongs in the hero/product experience, not in the header navigation. Contact details appear in the footer only. The supplied Emerald Rozalia logo/wordmark must be used exactly; no shield, redraw, recolour, crop, or substitute is permitted.

## Target cPanel hierarchy

The following is the canonical structure to reconcile against the supplied cPanel reference:

- Dashboard

- WEBSITE & PRODUCTS
  - Products
    - Product Manager
    - Add Product
    - Bulk Product Upload
    - Product Media Manager
    - Images
    - Videos
    - 360° Product View
    - Virtual Try-On
    - Categories
    - Collections
    - Variants
  - Banners / Sliders
  - Pages
  - SEO & Content
  - Reviews & Ratings

- ONLINE SALES
  - Customers
    - Customer Management
    - Customer Groups
    - Customer Segments
  - Cart & Checkout
  - Payments
  - Discounts & Coupons
  - Sales Reports

- ORDER MANAGEMENT (6 CATEGORIES)
  - Order Master Overview
  - Online Orders
  - Corporate Orders
  - Bulk Orders
  - Franchise Orders
  - Franchise Retail Orders
  - Buyer Orders

- FRANCHISE MANAGEMENT
  - Franchise Dashboard
  - Applications & Leads
  - Territories
  - Agreements
  - Franchisees
  - Franchise Retail Stores
  - Training & Documents
  - Marketing Assets
  - Performance & Targets
  - Renewals

- COMMUNICATION CENTER
  - Communication Center
  - Inbox
  - Chat 24/7
  - WhatsApp
  - Email
  - Email Templates
  - Approval Center
  - Action / Follow-ups
  - Alerts & Notifications
  - Communication History (Log)

- REPORTS utility
  - Franchise Reports
  - Franchise Retail Store Reports
  - Order Reports
  - Product & Sales Reports
  - Customer Reports
  - Communication Reports
  - Website Analytics

- USERS & ROLES utility
  - Users Management
  - Roles Management
  - User Roles & Permissions
  - Role Assignments
  - Permission Groups
  - Permission Matrix
  - Activity & Security Log

- SETTINGS reference shell
  - Settings
  - Audit & Logs
  - Integrations
  - Data Management

Detailed Settings screens from the guide remain owned by SettingsController and must be reachable without creating a second cPanel.

## Current source-defined hierarchy

The current shared shell in resources/views/layouts/admin.blade.php defines:

| Area | Current source entries | Current observation |
|---|---:|---|
| Dashboard | 1 | Named admin.dashboard route |
| Website & Products | 5 top-level, 11 Products children | Real product/media/page/SEO/resource links |
| Online Sales | 5 top-level, 3 Customers children | Customer, cart, payment, discount and sales-report links; order categories are isolated below |
| Order Management | 1 overview, 6 category links | Dedicated six-category group backed by the shared Order Master routes |
| Franchise Management | 11 | Dedicated and generic franchise resource links, including Store Setup |
| Communication Center | 10 | Dedicated CommunicationCenter sections |
| Reports utility | 7 | Utility links are separate from report routes |
| Users & Roles utility | 7 | UserSystemController routes |
| Settings utility | 4 | Compact reference shell; detailed SettingsController sections remain reachable from the Settings overview |
| All expanded source entries | 55 | Audit count; 68 clickable destinations including nested leaves |

## Resolved in Batch 7

1. The six order links now sit under a dedicated ORDER MANAGEMENT (6 CATEGORIES) group while the shared Order Master engine remains one domain.
2. The sidebar now exposes the compact four-item Settings shell: Settings, Audit & Logs, Integrations and Data Management. Detailed SettingsController sections remain reachable through the Settings overview and direct canonical routes.

## Remaining shell gaps

1. The reference uses a compact black shell. Current CSS contains a 245px sidebar, a later 208px override, a generic approximately 70px top area, and page-specific overrides. Exact geometry is not yet measured by screenshot diff.
2. Reports utility links and report pages are not yet a single canonical route/menu contract.
3. The public navigation and footer-only contact rule must remain separate from the admin shell.

## Route ownership index

- Public entry and content: routes/web.php, SiteController, resources/views/site/
- Product and catalog administration: routes/categories.php, routes/variants.php, AddProductController, MediaManagerController, ImageManagerController, VideoController, SpinController, TryOnController
- Page/SEO administration: PageManagerController and SeoController in routes/web.php
- Online/order administration: routes/order-master.php, OrderMasterController, OrderMasterOverviewController, CartCheckoutController, PaymentController, DiscountController
- Customers: routes/customers.php and CustomerManagementController
- Franchise: routes/franchise-management.php, FranchiseManagementController, FranchiseTerritoryController
- Communication: routes/communication-center.php, CommunicationCenterController
- Reports: routes/reports.php, routes/sales-reports.php, ReportController, SalesReportController
- Users and roles: routes/user-system.php and UserSystemController
- Settings: routes/settings.php and SettingsController
- Generic fallback/resource surface: ResourceController and the wildcard admin/resource/{module} route

## Acceptance checks

- Compare the rendered sidebar at every supplied admin viewport.
- Confirm the six order categories are separate and isolated while the shared engine remains one domain.
- Confirm excluded Production, Finance, Payroll, HR, POS, and separate Franchise Portal links are absent.
- Confirm public header order, utilities, exact logo use, and footer-only contact placement.
- Add route-level and browser assertions for the canonical target tree before declaring the navigation task complete.
