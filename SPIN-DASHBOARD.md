# 360° Product View dashboard

Admin: `/admin/resource/360-product-view` (existing Project 1 cPanel).
Public viewer: `/360/{uuid}`. Sitemap: `/360-sitemap.xml`.

- Create a view, choose a product, and upload a ZIP of 2–72 JPG/PNG frames with identical dimensions. Natural filename sorting determines rotation order. 24–72 frames recommended.
- 20 MB compressed / 100 MB unpacked total; 8 MB per frame; maximum 12 MP and 6000 px per dimension. Archive paths, entries, counts, formats and dimensions are validated. Frames are decoded and re-encoded to JPEG, max 2000 px, quality 85.
- Frames are stored privately and served through access-checked routes. Draft, in-progress, needs-attention, archived, private and inactive-product views are not public. Administrators can preview scoped views.
- Settings, alt/SEO/ARIA text and frame-specific hotspots are editable. Hotspots use JSON with frame (zero-based), x/y (percentage) and label. No HTML is rendered from hotspot labels.
- Viewer supports drag, touch, keyboard, slider, auto rotation, zoom and fullscreen. Auto rotation respects reduced-motion settings and pauses out of view. Mobile interaction and lazy loading are configurable per view.
- Search/filter/pagination, bulk publish/draft/archive/delete, CSV export and audit history use persisted data. Publishing never changes visibility.
- Public visit and engagement counts are session-deduplicated per view/day. Admin previews are excluded. Unique browsers are session-based estimates, not unique people. Load time is browser-reported. Metrics are not fraud-proof analytics.
- New public published views appear in the storefront alongside existing legacy spin frames. Legacy frames remain intact and are not automatically imported into this manager.
- Private upload backups already include the `spins` directory. Database migration adds `product_spins` and `spin_visits`. Release script checks the database-backed 360 sitemap after deployment.

Verification: `SpinDashboardTest` covers authorization, ZIP safety, upload validation, private delivery, publishing, gallery/sitemap, editing, bulk actions, export, metrics and tenant isolation. Existing PostgreSQL and container CI gates apply. Local PHP is unavailable in this workspace; CI is the execution environment for Laravel tests.

Not included: synthetic mockup products or analytics, 3D model reconstruction, automated turntable photography, external platform integrations. Categories are the four reference categories. Optimization is automatic on upload; to reorder/replace frames upload a new ZIP.
