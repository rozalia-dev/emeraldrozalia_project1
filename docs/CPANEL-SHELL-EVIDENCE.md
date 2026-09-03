# cPanel shell evidence

Task P2.3 implements the single Project 1 cPanel shell in `resources/views/layouts/admin.blade.php`.

Verified behavior:

- unauthenticated `/admin` requests redirect to `/login`
- grouped sidebar navigation preserves the approved Project 1 boundary
- the dashboard sidebar uses five reference-matched expandable groups, each with its full submenu, plus Reports, Users & Roles, and Settings utility links
- the Website & Products group keeps `Products` as the parent menu and nests its product-management submenu beneath it
- active navigation states are derived from the current route
- mobile navigation uses the responsive toggle in `public/js/app.js`
- the shell compiles through Laravel's Blade view cache

The supplied cPanel screenshot remains the visual reference for the next screenshot-comparison task. This task does not claim pixel verification of the full dashboard or individual admin modules.
