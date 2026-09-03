# 360° product viewer evidence (P3.4)

Repaired the product viewer contract:

- `SiteController::product()` merges configured `spin_images` with active public `spin_360` ProductMedia records; private/local media is not exposed through the storefront relation.
- The product view exposes the merged frames through `data-spin-viewer`/`data-images` and a `data-spin-image` target.
- JavaScript now supports pointer/touch drag, pointer cancellation, ArrowLeft/ArrowRight, Home and End keyboard controls, frame URL normalisation and a safe no-frame state.
- Gallery mode restores the initial product image when available.
- Public product media does not claim a 360 experience when no frames are configured.

Verification on 2026-09-02:

```text
node --check public/js/app.js -> passed
artisan view:cache           -> Blade templates cached successfully.
artisan test                 -> 15 passed (94 assertions)
```

Browser-level interaction and screenshot comparison remain pending until the Playwright browser binaries are installed; the deterministic visual test setup already records that limitation.
