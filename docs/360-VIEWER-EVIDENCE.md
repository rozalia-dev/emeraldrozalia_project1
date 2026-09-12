# 360° product viewer evidence (P3.4 / Batch 6)

The product-detail viewer now uses one public media contract:

- `SiteController::product()` selects the latest published/public managed `ProductSpin` with at least two frames and exposes its UUID-backed `/360/{uuid}/frames/{index}` URLs to the primary product hero.
- Private managed spins are excluded by the query and cannot leak their frame URLs into the product page.
- When no managed spin is public, the product falls back to its legacy `spin_images` and active public `spin_360` Product Media records.
- The primary product view exposes the frame set through `data-product-viewer`, `data-spin-frames`, `data-spin-source` and, for managed spins, `data-spin-uuid`.
- The former duplicate `data-spin-widget` section is no longer rendered on product detail; the standalone `/360/{uuid}` route remains available for the dedicated public viewer/admin preview flow.
- JavaScript now supports pointer/touch drag, pointer cancellation, ArrowLeft/ArrowRight, Home and End keyboard controls, frame URL normalisation and a safe no-frame state.
- Gallery mode restores the initial product image when available.
- The 360 tab is disabled when fewer than two approved frames exist, so the storefront does not claim an interactive experience without a valid frame set.

Verification scope on 2026-09-12:

```text
CustomerFrontOffice360ContractTest -> managed authority, one hero viewer, no-frame state
ProductManagedSpinIntegrationTest  -> public managed and private/legacy paths
ProjectScopeTest                   -> existing public product/media assertions
```

Browser-level interaction and screenshot comparison remain pending until the Playwright browser binaries are installed; this batch does not claim completion of the 167-reference visual acceptance set.
