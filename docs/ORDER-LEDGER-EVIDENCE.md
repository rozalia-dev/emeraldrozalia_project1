# P4.4 Order, invoice, return and payment-ledger evidence

## Scope

This checkpoint completes the order lifecycle boundary shared by customers and the cPanel:

- customer-owned order invoices with delivery and payment ledger data;
- payment history for the signed-in customer only;
- return/exchange eligibility and duplicate-request protection;
- provider-neutral payment ledger entries when an admin changes payment state;
- audit records for order creation, customer return requests and admin lifecycle changes.

The six order-master screens remain the separate P4.5 checkpoint.

## Implementation

| Area | Route/file | Verified behaviour |
| --- | --- | --- |
| Customer orders | GET /account/orders | Orders, payment status and latest ledger entry are customer-owned; return actions appear only after shipment. |
| Payment history | GET /account/payments | Payment transactions are queried through the customer's orders and never expose another customer's ledger. |
| Invoice | GET /account/orders/{order}/invoice | Owner-protected printable document includes delivery address, line items, decimal totals, payment status and every payment ledger entry. |
| Returns/exchanges | POST /account/orders/{order}/return | Owner check, shipped/completed eligibility, type/reason validation and one-open-request rule. |
| Admin lifecycle | PATCH /admin/orders/{type}/{order} | Status/payment validation, payment transition ledger entry, before/after audit record and type/order isolation. |
| Audit | AuditTrail service | Order creation, customer return request and admin order update actions are recorded with subject, user, request ID and IP metadata. |

## Status rules

Customer return/exchange requests can start only when the order is shipped or completed. An existing requested, approved, received or inspecting request blocks a second open request. The request remains pending until an admin workflow advances it.

Payment transitions do not call an external provider. They append a provider-neutral payment_transactions entry, leaving real gateway activation behind the existing disabled-by-default external-service gate.

## Automated checks

Commands run with the local Herd PHP 8.4 runtime:

    php -l app/Http/Controllers/Account/AccountController.php
    php -l app/Http/Controllers/Admin/OrderMasterController.php
    php artisan view:cache
    php artisan test

Result: **27 tests passed, 255 assertions**.

The new coverage verifies:

- eligible return/exchange creation, audit entry and duplicate prevention;
- ineligible order return rejection;
- customer payment-ledger and invoice ownership;
- admin payment transition, ledger append and audit record;
- existing checkout, stock and customer-ownership contracts remain green.

Browser screenshot comparison remains pending because Playwright browser binaries and the original visual asset archive are not available in the current local setup.
