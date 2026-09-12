# Project 1 Batch 3 — UUID migration recovery evidence

## Scope

This batch closes the P1.3 migration-safety slice identified by the audit:

- The Project 1 UUID/domain migration now converges safely when a prior run added some columns or tables.
- Existing public UUIDs are preserved; only null values are backfilled.
- Duplicate values are repaired in stable primary-key order before unique and non-null enforcement.
- A forward repair migration applies the same safety contract to databases that already recorded the original domain migration.
- Domain table creation and content-page column additions are guarded so a partial DDL run can resume.

The forward migration has a data-safe rollback: it intentionally does not delete public identifiers that may already be used by external links.

Production, unified finance, POS, production operations, unified stock and the separate Project 2 UUID engine remain out of scope.

## Verification record

- Local git diff --check — passed.
- Local PHP, Composer and Docker — unavailable; no local runtime result is claimed.
- Pull-request validation [run #289](https://github.com/rozalia-dev/emeraldrozalia_project1/actions/runs/34706494564) — passed on the corrected Batch 3 head.
- Main PostgreSQL validation — passed in [run #290](https://github.com/rozalia-dev/emeraldrozalia_project1/actions/runs/34706590537).
- Main container/release validation — passed in [run #290](https://github.com/rozalia-dev/emeraldrozalia_project1/actions/runs/34706590537).
- Production deployment — passed in [run #290](https://github.com/rozalia-dev/emeraldrozalia_project1/actions/runs/34706590537).
- PostgreSQL clean migration/seed, route listing and feature suite — passed.
- UUID recovery coverage — passed for clean schema, partial column recovery, duplicate repair and repeated application.
- Production release — exact main SHA a2464ddf7258eb436ecd7c5361dc7ba40351e74f.
- Production migration — 2026_09_12_000300_repair_project1_uuid_contract completed successfully.
- Production health — application, PostgreSQL, Redis and Nginx containers healthy; release backup saved before activation.

This evidence closes only the Batch 3 UUID/migration-safety slice. It does not claim completion of the 519-page guide or the 167-reference visual acceptance set.
