# Informational screen slice evidence (P3.6a)

Dedicated screens added:

- `/irish-traditional` — category-specific Irish Traditional Flat Caps landing page with search, sorting, pagination and product/cart links.
- `/irish-heritage` — category-specific Irish Heritage Hats landing page with the same real catalogue contract.
- `/factory` — How We Work hero, verified manufacturing values, nine process steps and factory-visit CTA.

The routes no longer use the generic informational-page fallback. Missing original photography remains explicitly marked as pending approved source assets.

Verification on 2026-09-02:

```text
artisan view:cache  -> Blade templates cached successfully.
artisan test       -> 17 passed (111 assertions)
```
