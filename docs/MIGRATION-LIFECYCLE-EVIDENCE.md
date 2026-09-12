# Migration lifecycle evidence

The earlier Windows/Herd/MySQL record below is retained as historical evidence. The current workspace has no PHP, Composer or Docker runtime, so the authoritative release evidence is the PostgreSQL GitHub Actions workflow.

Historical local record:

- `composer validate --strict`: valid
- `php artisan migrate:status`: all migrations ran
- `php artisan migrate`: nothing to migrate
- `php artisan db:seed --force`: completed successfully
- `php artisan route:list`: routes loaded
- `php artisan test`: 5 passed, 29 assertions

Current PostgreSQL release record:

- Pull-request validation [run #292](https://github.com/rozalia-dev/emeraldrozalia_project1/actions/runs/34707642253): passed, including the PostgreSQL feature suite and the new full rollback/re-migrate/seed/status rehearsal.
- Main validation [run #293](https://github.com/rozalia-dev/emeraldrozalia_project1/actions/runs/34707758475): PostgreSQL validation passed, including `migrate:fresh --seed --force`, route listing, warning-strict PHPUnit, full `migrate:rollback --step=999 --force`, `migrate --force`, `db:seed --force` and `migrate:status`.
- The final status output listed all 23 repository migrations as `[1] Ran` after the rollback/re-run cycle.
- Main media-browser acceptance, container/release validation and production deployment also passed in run #293.

Batch 4 closes the PostgreSQL rollback/re-run portion of P1.4 and the multi-company foreign-key ordering finding. Direct MySQL clean/rollback/re-run evidence and independent backup/restore evidence remain pending. This file does not claim completion of the 519-page guide or the 167-reference visual acceptance set.
