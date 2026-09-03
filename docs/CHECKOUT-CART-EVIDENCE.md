# P4.2 Cart and checkout evidence

## Scope

This checkpoint makes the cart and checkout a single customer flow:

- cart quantity, removal and empty states;
- saved-address selection or validated manual delivery details;
- active shipping methods and free-shipping thresholds;
- active, date-valid, minimum-order and usage-limited discounts;
- provider-neutral payment choices;
- server-calculated subtotal, shipping, discount and total;
- order confirmation, payment state and customer-owned success page.

## Implementation

| Area | Route/file | Verified behaviour |
| --- | --- | --- |
| Cart | GET /cart, POST /cart/{product}, PATCH /cart/{key}, DELETE /cart/{key} | One CartService shape, quantity validation, product/variant availability checks, item count and empty-cart state. |
| Checkout form | GET /checkout | Authenticated customers can select a saved address or provide a new one; active shipping methods and payment choices are shown. Empty carts return to the cart with an explicit error. |
| Checkout submit | POST /checkout | Address ownership is checked, shipping code must be active, discount code must be valid for the date/order/usage state, and payment choice is restricted to the configured provider-neutral options. |
| Order creation | CheckoutController | Subtotal, shipping, discount and total are persisted together; order item rows, payment transaction, reward transaction and exact cart quantities are created in one transaction. |
| Confirmation | GET /order/{order}/success | Ownership-protected confirmation displays the order number, payment state and persisted totals. |

The checkout summary updates the selected delivery charge in the browser for clarity, but the server remains authoritative for the final total.

## Automated checks

Commands run with the local Herd PHP 8.4 runtime:

    php artisan view:cache
    php artisan test

Result: **23 tests passed, 207 assertions**.

The new feature coverage verifies:

- a saved address is used in the order shipping payload;
- shipping, 10% discount, payment choice and total are persisted correctly;
- the cart is cleared only after successful order creation;
- product stock is decremented by the requested quantity;
- payment transaction provider/state is recorded;
- invalid discount and shipping codes return validation errors without creating an order;
- empty checkout returns to the cart with an explicit failure state.

The direct local cart check at http://emeraldrozalia_project1.test/cart returned HTTP 200. Browser screenshot comparison remains pending because Playwright browser binaries and the original visual asset archive are not available in the current local setup.
