# Catalogue screens evidence (P3.1)

Implemented dedicated catalogue surfaces:

- `/collections` now renders a collection directory from active categories, including product-style counts and links to each category.
- `/new-arrivals` now queries active/new products with server-side search, category filtering, sort order and pagination.
- `/shop` now exposes product/SKU search alongside the existing category navigation and pagination.
- Category routes continue to use the same active-product query contract.

The catalogue image areas remain marked pending where original photography is not available; supplied screenshots are not used as production assets.

Verification on 2026-09-02:

```text
artisan view:cache  -> Blade templates cached successfully.
artisan test       -> 12 passed (73 assertions)
```
