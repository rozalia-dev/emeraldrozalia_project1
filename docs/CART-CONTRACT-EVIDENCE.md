# Cart contract evidence

Task P1.2 aligns the cart routes, controller, service and Blade view around the existing Project 1 contract:

- `/cart` renders `site.cart` from `CartService` items and subtotal.
- Product add uses `is_active`, integer variant IDs, `stock`, `price`, `colour`, `size` and `options`.
- Update and remove use the same session key and redirect back to the named `cart` route.
- Stock is checked before adding or increasing quantity.
- The feature test covers add, update and remove.

Verified: 5 Laravel tests passed with 29 assertions; local `/cart` returns HTTP 200 without an exception.

Checkout remains a separate follow-up task because its order write contract needs to be reconciled against the current migration fields.
