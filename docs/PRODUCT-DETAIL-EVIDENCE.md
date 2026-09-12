# Product detail evidence (P3.2 / Batch 6)

The product detail screen now uses the current commerce contract:

- active `ProductVariant` records only; inactive variants are not offered
- variant price and stock are displayed and submitted by `variant_id`
- product SKU, category, material, description and availability are surfaced
- image paths use the storage URL contract when media exists
- breadcrumbs, wishlist, review and related-product sections are present
- add-to-cart continues through the tested `CartController`/`CartService` contract

Batch 6 closes the product-detail 360 integration gap:

- `SiteController::product()` eager-loads only published/public managed spins and selects the latest spin with at least two frames
- the selected managed spin is the authoritative frame source for the primary product hero, including its UUID-backed frame routes
- legacy `spin_images` and approved `spin_360` Product Media remain the fallback when no managed spin is public
- the hero contains the single product 360/photo switcher; the former duplicate standalone spin widget is not rendered on product detail
- when fewer than two approved frames exist, the 360 tab is disabled and the approved photo gallery remains available

Verification scope on 2026-09-12:

```text
CustomerFrontOffice360ContractTest -> managed source, single viewer, private/legacy fallback, customer counts
ProductManagedSpinIntegrationTest  -> managed/public and private/legacy integration
ProjectScopeTest                   -> existing product, media and customer account contracts
```

PHP/Composer are not installed in this workspace; the GitHub pull-request gate is the authoritative execution environment for the full Laravel suite.
