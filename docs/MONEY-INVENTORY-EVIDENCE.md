# P4.3 Money and inventory evidence

## Scope

This checkpoint hardens the stock and amount boundaries used by the storefront checkout:

- one documented money precision convention;
- exact stock allocation with row locks;
- no partial decrement when a stale cart is no longer available;
- an inventory movement for every successful sale;
- persisted currency metadata on orders.

## Money convention

Project 1 currently prices the storefront in canonical EUR. Database amount columns remain decimal(12,2) (with exchange rates in decimal(18,8)), and App\Support\Money applies round-half-up to two decimal places at cart, line, discount and order-total boundaries. Orders persist both the legacy currency field and the explicit currency_code / exchange_rate pair (EUR / 1) so a future provider-neutral conversion boundary cannot silently change an existing order.

No binary floating-point value is used as the persisted source of truth; floats are converted and rounded immediately before writing decimal columns.

## Inventory convention

Checkout runs inside one database transaction. Each product or selected variant is reloaded with lockForUpdate(), checked for active status and sufficient stock, decremented by the requested quantity, and paired with an inventory_movements row:

- type: sale;
- quantity: negative units leaving stock;
- reference: the created order number;
- product_variant_id: populated for variant sales.

If another request has consumed the stock after the cart was built, the transaction raises a validation error, rolls back the order/payment/reward/movement writes and leaves the cart intact for correction.

## Implementation

| Area | File | Verified behaviour |
| --- | --- | --- |
| Money helper | app/Support/Money.php | Round-half-up and quantity multiplication helpers for persisted amounts. |
| Cart totals | app/Services/CartService.php | Prices and subtotals are rounded consistently. |
| Inventory model | app/Models/InventoryMovement.php | Typed quantity and product/variant relations. |
| Product/order models | app/Models/Product.php, ProductVariant.php, Order.php, PaymentTransaction.php | Decimal casts and inventory relations. |
| Checkout | app/Http/Controllers/CheckoutController.php | Locked stock recheck, exact decrement, movement creation and explicit EUR metadata. |

## Automated checks

Commands run with the local Herd PHP 8.4 runtime:

    php artisan view:cache
    php artisan test

Result: **24 tests passed, 219 assertions**.

The new coverage verifies:

- the persisted EUR currency code and exchange rate;
- an inventory sale movement with negative quantity and order reference;
- exact product stock decrement;
- stale-cart stock failure with no order or movement created;
- existing checkout total and payment assertions remain green.

Browser screenshot comparison remains pending because Playwright browser binaries and the original visual asset archive are not available in the current local setup.
