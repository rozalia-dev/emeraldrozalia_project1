# Product detail evidence (P3.2)

The product detail screen now uses the current commerce contract:

- active `ProductVariant` records only; inactive variants are not offered
- variant price and stock are displayed and submitted by `variant_id`
- product SKU, category, material, description and availability are surfaced
- image paths use the storage URL contract when media exists
- breadcrumbs, wishlist, review and related-product sections are present
- add-to-cart continues through the tested `CartController`/`CartService` contract

The 360° drag/viewer interaction remains a separate P3.4 task; this checkpoint keeps the existing viewer hook without claiming that interaction is complete.

Verification on 2026-09-02:

```text
artisan view:cache  -> Blade templates cached successfully.
artisan test       -> 13 passed (80 assertions)
```
