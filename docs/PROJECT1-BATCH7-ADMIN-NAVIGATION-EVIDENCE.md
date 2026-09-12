# Project 1 Batch 7 evidence

Date: 2026-09-12

Batch 7 is a bounded unified cPanel navigation reconciliation. It aligns the source-defined admin shell with the locked Project 1 hierarchy without adding Production, unified Finance, Payroll, HR, POS or a separate Franchise Portal.

## Implemented

- The six order categories remain on one shared Order Master engine but now render under a dedicated `ORDER MANAGEMENT (6 CATEGORIES)` sidebar group.
- `ONLINE SALES` now owns customer management, cart and checkout, payments, discounts and sales reports; it no longer mixes the six order-category links into the same group.
- The Settings utility now exposes the compact four-item reference shell: `Settings`, `Audit & Logs`, `Integrations` and `Data Management`.
- The detailed SettingsController sections remain reachable through the Settings overview and their existing canonical routes.
- Stable `data-admin-nav-group` markers identify the source groups for route/browser assertions and future visual acceptance.

## Acceptance coverage

- `AdminNavigationContractTest` verifies group order, one shared sidebar, all six order routes, the compact Settings items, removal of the legacy 17-item Settings list and absence of excluded Project 2/operations areas.
- `OrderMasterOverviewTest` verifies the dedicated order-management group while retaining the six category links and existing order workflows.
- Local `git diff --check` and source review are clean. PHP/Composer/Docker runtime validation remains delegated to the GitHub Actions release gate for this workspace.

## Remaining gaps

- Exact sidebar/top-bar geometry and supplied-reference screenshot diffs remain open.
- Reports utility links still need one canonical route/menu contract.
- Public navigation/footer separation, the full 519-page guide and the 167-reference visual acceptance set remain incomplete.
