# P4.3 Money and inventory evidence

## Scope

This checkpoint hardens the stock and amount boundaries used by the storefront checkout:

- one documented money precision convention;
- exact stock allocation with row locks;
- no partial decrement when a stale cart is no longer available;
- an inventory movement for every successful sale;
- persisted currency metadata on orders.

## Money convention

Project 1 currently prices the storefront in canonical EUR. Database amount columns remain decimal(12,2) (with exchange rates in decimal(18,8)), and `App\Support\Money` converts decimal input to integer minor units, applies round-half-up at the two-place boundary, and returns canonical decimal strings for persistence. Cart, line, discount, shipping, order-total, payment, and reward calculations therefore do not use binary floating-point arithmetic as their source of truth. Orders persist both the legacy currency field and the explicit `currency_code` / `exchange_rate` pair (EUR / 1) so a future provider-neutral conversion boundary cannot silently change an existing order.

The minor-unit implementation is intentionally internal: existing decimal(12,2) schemas and public display contracts remain compatible, while values written to those columns are normalized strings such as `60.95`.

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
| Money helper | app/Support/Money.php | Integer-minor-unit conversion, half-up rounding, addition/subtraction, percentage, comparison and quantity multiplication. |
| Cart totals | app/Services/CartService.php | Session prices are normalized to canonical strings and subtotals are summed in minor units. |
| Inventory model | app/Models/InventoryMovement.php | Typed quantity and product/variant relations. |
| Product/order models | app/Models/Product.php, ProductVariant.php, Order.php, PaymentTransaction.php | Decimal casts and inventory relations. |
| Discount rules | app/Services/DiscountCalculator.php | Minimum-order, percent, fixed, free-shipping and BOGO rules calculate and cap benefits in minor units. |
| Checkout | app/Http/Controllers/CheckoutController.php | Locked stock recheck, exact decrement, movement creation, canonical order/payment/reward amounts and explicit EUR metadata. |

## Automated checks

GitHub Actions run **#409** (`34848492825`) on commit `15cf2d4499bae9c58887f1a97156c2064d4ffc0f` ran the repository’s PHP 8.4 PostgreSQL validation job.

Result: **274 tests passed, 3,200 assertions**. Migrations and the complete feature suite passed, followed by migration rollback and re-run. The workflow’s media browser, container/release, and deploy jobs were skipped by their existing conditions and are not counted as release evidence.

The new coverage verifies:

- the persisted EUR currency code and exchange rate;
- an inventory sale movement with negative quantity and order reference;
- exact product stock decrement;
- stale-cart stock failure with no order or movement created;
- half-up decimal text rounding, float-drift resistance, exact percentage rounding and minor-unit comparisons;
- existing checkout total and payment assertions remain green;
- the workflow file remained unchanged.

Browser screenshot comparison remains pending because Playwright browser binaries and the original visual asset archive are not available in the current local setup.
