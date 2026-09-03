# cPanel dashboard evidence

Task P2.4 implements the dashboard KPI and operational layout in `resources/views/admin/dashboard.blade.php`.

Verified:

- KPI values are read from current Project 1 records (orders, paid sales, customers, products, franchise applications, stores and conversations).
- Empty franchise/map/activity states are explicit and do not invent partner or location data.
- All six order categories link to their separate order-master routes.
- Blade view cache compiles successfully.
- Existing test suite passes: 4 tests, 20 assertions.
- Unauthenticated `/admin` requests remain redirected to `/login`.

The reference dashboard still requires an authenticated visual screenshot comparison before pixel acceptance.
