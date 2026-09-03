# P4.5 Order-master workflow evidence

## Scope

The six order-management masters now share one type-aware workflow while keeping every queue, detail record and mutation isolated by order type:

- Online Orders (`online`)
- Corporate Orders (`corporate`)
- Bulk Orders (`bulk`)
- Franchise Orders (`franchise`)
- Franchise Retail Orders (`franchise_retail`)
- Buyer Orders (`buyer`)

## Implementation

| Area | Route/file | Verified behaviour |
| --- | --- | --- |
| Master queue | GET `/admin/orders/{type}` | Validates the six allowed types, scopes every query to `order_type`, paginates and preserves filters. |
| Queue filters | `OrderMasterController@index`, `admin/orders/index.blade.php` | Search by order number/email, order status, payment status and created date range. |
| Queue metrics | `OrderMasterController@index` | Type-scoped total, open workflow count, paid order count, paid revenue and open-return count. |
| Order detail | GET `/admin/orders/{type}/{order}` | Rejects a mismatched type, then displays items, totals, customer delivery data, metadata, payment ledger and returns/exchanges. |
| Lifecycle | PATCH `/admin/orders/{type}/{order}` | Validates order/payment states, records a provider-neutral payment transaction on payment changes and writes an audit entry. |
| Admin invoice | GET `/admin/orders/{type}/{order}/invoice` | Reuses the printable invoice contract with admin back-navigation and the complete payment ledger. |

The `Order::returns()` relation and `withCount('returns')` keep return visibility available in both the queue and detail view. Type checks run before every detail, invoice or lifecycle mutation so an order cannot be reached through another master URL.

## Automated checks

Commands run with the local Herd PHP 8.4 runtime:

    php -l app/Http/Controllers/Admin/OrderMasterController.php
    php -l app/Models/Order.php
    php -l tests/Feature/ProjectScopeTest.php
    php artisan view:cache
    php artisan test

Result: **28 tests passed, 275 assertions**.

The new coverage verifies a paid online order appears in the online queue but not the corporate queue, all six masters remain routable, mismatched filters return an empty queue, cross-type detail/invoice URLs return 404, detail and invoice pages expose their type-scoped data, and order types remain unchanged.

Browser screenshot comparison remains pending because Playwright browser binaries and the original visual asset archive are not available in the current local setup.
