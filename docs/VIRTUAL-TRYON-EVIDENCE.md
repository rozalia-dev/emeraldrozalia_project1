# Virtual Try-On evidence (P3.5)

Added a dedicated `/virtual-tryon` studio:

- product selector backed by active catalogue products
- only active public `try_on` media or an existing public `try_on_asset` is eligible for overlays
- customer photo is previewed with an object URL in the browser; no upload endpoint is called
- size, X/Y position and rotation controls update the overlay client-side
- reset and photo/background view controls are available
- clear no-asset state prevents use of the brand badge as an invented hat overlay
- responsive three-panel layout collapses to a mobile stack

Verification on 2026-09-02:

```text
node --check public/js/app.js -> passed
artisan view:cache           -> Blade templates cached successfully.
artisan test                 -> 16 passed (99 assertions)
```

Camera capture and richer multi-angle style comparison remain outside this bounded task because they require explicit browser permission and approved product assets.
