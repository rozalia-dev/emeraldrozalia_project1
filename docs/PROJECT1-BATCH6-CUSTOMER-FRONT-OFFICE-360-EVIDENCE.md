# Project 1 Batch 6 evidence

Date: 2026-09-12

Batch 6 is a bounded customer/front-office and product-media integration slice. It does not claim completion of the 519-page guide or the 167-reference visual acceptance set.

## Release evidence

- PR #74 passed its PostgreSQL gate in [GitHub Actions run #303](https://github.com/rozalia-dev/emeraldrozalia_project1/actions/runs/34710729157) at the corrected head `a48e3520babe0b1728ca06a98a2b01ba35d4d866`.
- The merged `main` release is `b6cd7cd19d3b8875d82f30ea91cb31d132e85622`.
- Main validation, media-browser acceptance, rollback/re-run, container rehearsal and Hetzner deployment passed in [GitHub Actions run #304](https://github.com/rozalia-dev/emeraldrozalia_project1/actions/runs/34710843822).
- Production saved `/var/backups/emerald-rozalia/20260912T182410Z-b6cd7cd19d3b` and reported healthy app, PostgreSQL, Redis and Nginx containers.

## Implemented

- Product detail now eager-loads the public managed 360 records and selects the latest valid `ProductSpin` with at least two frames.
- A public managed spin is the authoritative source for the product hero. Its frame URLs remain UUID-backed and are served through the existing public frame policy.
- Legacy `spin_images` and active public `spin_360` Product Media are retained as a fallback when no managed spin is public.
- Product detail renders one 360/photo viewer. The duplicate standalone spin widget previously appended below the product detail has been removed.
- The 360 tab is disabled when a valid two-frame source is not available; photo mode and the existing loading/fallback behavior remain available.
- Customer dashboard order KPIs now use the complete owner-scoped order count rather than the eight-order recent-history limit.
- The dashboard’s non-backed Custom Designs KPI/navigation entry is replaced with a real owner-scoped Returns & Exchanges count and route.

## Contract coverage

`tests/Feature/CustomerFrontOffice360ContractTest.php` covers:

- managed 360 authority and legacy-frame exclusion
- one primary viewer with no duplicate `data-spin-widget`
- UUID-backed frame URLs and source metadata
- no-frame disabled state
- total-order and return-request counts on the customer dashboard

Existing `ProductManagedSpinIntegrationTest` and `ProjectScopeTest` coverage remains in force for private-spin isolation, media fallback, customer ownership, returns and the existing storefront contract.

## Explicitly open

- The guide’s missing reference archive and ordered 167-image visual acceptance set.
- Browser screenshot/diff verification at the guide’s desktop, tablet and mobile widths.
- The remaining customer design workspace, store setup/customer bulk-upload route, review eligibility workflow and broader communication/provider integrations.
- MySQL-specific runtime verification; PostgreSQL, container and deployment checks remain the release gate.
