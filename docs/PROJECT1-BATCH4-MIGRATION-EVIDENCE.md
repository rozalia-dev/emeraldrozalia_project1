# Project 1 Batch 4 — migration lifecycle evidence

## Scope

This batch closes the PostgreSQL portion of the P1.4 migration-lifecycle gap and the audit finding that the multi-company migration attempted to drop `companies` while base domain tables still referenced it.

- The multi-company migration now guards already-created context tables and columns so a recorded-schema replay converges safely.
- Its rollback removes `company_id` foreign keys and columns from the base domain tables before dropping company context tables.
- Orders, products and categories remove the additional localization/currency columns in the same dependency-safe rollback.
- A feature test covers the eight base-table company foreign keys and replaying the migration against an already-migrated schema.
- CI rehearses a disposable PostgreSQL full rollback, re-migration, seed and migration-status check after the normal feature suite.

Production, unified finance, POS, production operations, unified stock and the separate Project 2 UUID engine remain out of scope.

## Verification record

- Local `git diff --check` — passed.
- Local PHP, Composer and Docker — unavailable; no local runtime result is claimed.
- Pull-request validation [run #292](https://github.com/rozalia-dev/emeraldrozalia_project1/actions/runs/34707642253) — passed for the Batch 4 head, including the PostgreSQL feature suite and rollback/re-run step.
- Main PostgreSQL validation [run #293](https://github.com/rozalia-dev/emeraldrozalia_project1/actions/runs/34707758475) — passed, including clean migration/seed, route list, warning-strict PHPUnit, full rollback/re-migrate/seed/status rehearsal and main-only media-browser acceptance.
- Main container/release validation [run #293](https://github.com/rozalia-dev/emeraldrozalia_project1/actions/runs/34707758475) — passed.
- Production deployment [run #293](https://github.com/rozalia-dev/emeraldrozalia_project1/actions/runs/34707758475) — passed for exact SHA `14ba4142ed98816cec1787097944682beaf32534`.
- Release backup — saved at `/var/backups/emerald-rozalia/20260912T172108Z-14ba4142ed98` before activation.
- Production migration — reported `Nothing to migrate.`
- Production health — application, PostgreSQL, Redis and Nginx containers were healthy and the release health checks passed.

## Remaining evidence

This batch does not claim the full P1.4 cross-engine requirement: direct MySQL clean/rollback/re-run evidence remains pending. Independent backup/restore evidence also remains pending. The 519-page Developer Guide and 167-reference visual acceptance set remain incomplete; no visual-complete claim is made.
