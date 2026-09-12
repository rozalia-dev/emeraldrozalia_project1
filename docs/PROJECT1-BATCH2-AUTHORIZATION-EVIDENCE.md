# Project 1 Batch 2 — authorization and tenant evidence

## Scope

This batch hardens the first Project 1 management boundary without adding any Project 2 module:

- Users & Roles routes now require action-specific permissions for view, create, edit, delete and export operations.
- Delegated permissions require an active, unlocked user, an active non-expired role assignment and an active company membership in the selected tenant context.
- Product create/update/delete actions now pass through policy authorization; cross-company update/delete access is denied when a tenant is selected.
- Non-admin users cannot silently fall back to an unrelated active company.

Production, unified finance, POS, production operations, unified stock and the separate Project 2 UUID engine remain out of scope.

## Verification record

- Local `git diff --check` — passed.
- Local PHP, Composer and Docker — unavailable; no local runtime result is claimed.
- Pull-request validation [run #285](https://github.com/rozalia-dev/emeraldrozalia_project1/actions/runs/34705133399) — passed on the corrected Batch 2 branch SHA.
- Main validation and deployment [run #286](https://github.com/rozalia-dev/emeraldrozalia_project1/actions/runs/34705242883) — passed.
- PostgreSQL feature tests and media-browser acceptance — passed.
- Container/release validation — passed.
- Production checkout — exact `main` SHA `6a8fbdbf8f12303a33be89d95eb604364eb1e335`.
- Production health — application, PostgreSQL, Redis and Nginx containers healthy; migrations reported no pending work.

This evidence closes only the Batch 2 authorization/tenant slice. It does not claim completion of the 519-page guide or the 167-reference visual acceptance set.
