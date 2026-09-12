# Project 1 Batch 9 evidence

Date: 2026-09-12

Batch 9 closes the next documented public/private synchronization gap: the
settings workspace can now publish a versioned, tenant-specific public-safe
branding/general/localization snapshot consumed by the shared storefront shell.

## Implemented

- Added the append-only `published_site_settings` aggregate with UUID, company
  scope, section, version, publisher and publication timestamp.
- Existing active-company settings are backfilled at migration time; fresh
  seeded environments receive the same initial public snapshots.
- `SettingsController` saves public-safe settings and the corresponding
  published revision in one transaction, with separate audit entries for the
  private update and public publication.
- The public shell reads the latest published revision for the active company;
  no session override is replaced and legacy company data remains a bounded
  fallback when no revision exists.
- Only allow-listed public fields are copied. Private settings are not
  published, logo paths are limited to the two supplied logo assets, and color
  values are constrained to six-digit hex values before reaching the shell.
- The shell now consumes the published trading/legal name, description, footer
  text, location, logo slots, color tokens and publication version. Published
  localization also controls the default public locale/currency context.

## Acceptance coverage

- `PublicPrivateSettingsContractTest` proves a branding save creates version 1,
  the public shell consumes it, private fields and private logo paths do not
  leak, later saves create version 2 without mutating version 1, and two
  companies cannot read each other’s published branding.
- The same suite proves private Security & Access settings remain in
  `AdminRecord` without a public snapshot and that published localization
  controls the public HTML locale when no user session override exists.
- `git diff --check` and source/static review are required locally. PHP,
  Composer, PostgreSQL and Docker execution remain authoritative in GitHub
  Actions for this workspace.

## Remaining gaps

- Full settings lifecycle UI for pending approval, explicit activation,
  rollback and per-record policies remains partial.
- Exact supplied-reference screenshot diffs, responsive/browser/accessibility
  journeys, the complete 519-page guide and the 167-reference visual
  acceptance set remain open.
