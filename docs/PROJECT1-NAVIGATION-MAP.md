# Project 1 navigation map

**Baseline:** c54e933e36c68f2951e5e05afb32119ea02ea467
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
  - Orders (6 Categories)
  - Customers
    - Customer Management
    - Customer Groups
    - Customer Segments
  - Cart & Checkout
  - Payments
  - Discounts & Coupons
  - Sales Reports

- ORDER MANAGEMENT (6 CATEGORIES)
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
| Online Sales | 12 top-level, 3 Customers children | Six order links are direct children of Online Sales |
| Franchise Management | 10 | Dedicated and generic franchise resource links |
| Communication Center | 10 | Dedicated CommunicationCenter sections |
| Reports utility | 7 | Utility links are separate from report routes |
| Users & Roles utility | 7 | UserSystemController routes |
| Settings utility | 17 | SettingsController section links |
| All expanded source entries | 68 | Audit count; 81 clickable destinations including nested leaves |

## Definite mismatches to resolve in the next shell task

1. The source has no separate ORDER MANAGEMENT (6 CATEGORIES) group. The six order links sit directly in ONLINE SALES.
2. The supplied reference shows the compact four-item SETTINGS shell; the current source exposes 17 Settings entries. Detailed Settings pages still need to remain reachable through the compact shell design.
3. The reference uses a compact black shell. Current CSS contains a 245px sidebar, a later 208px override, a generic approximately 70px top area, and page-specific overrides. Exact geometry is not yet measured by screenshot diff.
4. Reports utility links and report pages are not yet a single canonical route/menu contract.
5. The public navigation and footer-only contact rule must remain separate from the admin shell.

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
