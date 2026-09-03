# Tenant context evidence

Task P1.5 aligns tenant-aware models and seed data.

Implemented:

- `Category`, `Product`, `Order`, `Inquiry`, `Store` and `AdminRecord` use a shared tenant scope.
- New records inherit the active session `company_id` when one is present.
- `Category` and `Inquiry` explicitly allow `company_id`; `Store` includes it in its fillable contract.
- Re-running the seeder now preserves the ERL company association on seeded categories/products/admin records.
- An explicit `forCompany()` scope is available for jobs, reports and console work where session context is absent.

Verified: 6 Laravel tests passed with 31 assertions, including isolation of two companies’ category records; the local shop route returns HTTP 200.

The tenant scope intentionally does not run in console commands, so migrations/seeders and queued maintenance can choose an explicit company scope.
