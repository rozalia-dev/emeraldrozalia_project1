# Project 1 CI and release gate matrix

**Baseline audited release:** c54e933e36c68f2951e5e05afb32119ea02ea467
**Repository:** rozalia-dev/emeraldrozalia_project1
**Production target:** Hetzner, /var/www/emerald-rozalia, https://emeraldrozalia.com

## Current verified workflow

GitHub Actions run #277 (workflow “Validate and deploy production”) completed successfully for c54e933 on 12 September 2026.

| Job | Current evidence | Guide completion meaning |
|---|---|---|
| PostgreSQL validation | Passed in run #277 | Green release baseline; does not cover all guide contracts |
| Container and release validation | Passed in run #277 | Container/release rehearsal is green |
| Deploy to Hetzner | Passed in run #277 | Exact audited SHA reached the server and health checks passed |
| Commit status API | No individual statuses were reported; workflow run is the authoritative record | Do not infer broader coverage from an empty status list |

## Required gates and current state

| Gate | Required evidence | Current state |
|---|---|---|
| Scope/boundary | Project 1 exclusions and single-cPanel rule | Documented; retain no Production/Finance/Payroll/HR/POS/separate portal |
| Guide matrix | 167 ordered rows, 165 archive image references, duplicates retained | Captured in PROJECT1-GUIDE-COVERAGE.md; archive files remain incomplete |
| Navigation | Target hierarchy and source-to-route map | Captured; source mismatch remains open |
| Asset register | Hash, dimensions, source/archive mapping, exact-logo review | 33 local references registered; 132 archive references missing; two brand PNG verification failures |
| Public page matrix | Route/data/form/consumer/evidence per public row | Captured; visual/live content proof remains partial |
| Synchronization | Public write/read to private/admin/report consumers | Captured; banner/review/correlation/settings/report gaps remain |
| Order engine | One engine with six category projections | Captured; conversion/transition/reconciliation evidence remains partial |
| Franchise lifecycle | Application→approval→agreement→onboarding→store→retail→renewal | Captured; complete transition/policy evidence remains partial |
| UI tokens | Geometry, typography, spacing, responsive and published theme version | Contract captured; measurements/runtime publisher unverified |
| Visual diffs | Exact supplied-reference screenshots at approved viewports | Not run for all 167 entries; current foundation spec covers four public routes only |
| Browser/accessibility | Desktop/tablet/mobile journeys, keyboard, focus, labels, contrast, overflow | Missing broad suite |
| API/contracts | /api/v1, Form Requests, API Resources, Policies, OpenAPI | Not found in audited tree |
| Async/provider | Jobs, events/listeners, queue/retry/idempotency, email/WhatsApp/payment/webhook contracts | Not found or incomplete |
| PostgreSQL | migrate/seed/route/feature suite | Passed in run #277 |
| Container/release | Docker image, storage, Nginx, worker/scheduler rehearsal | Passed in run #277 |
| Backup/restore/rollback | Actual restore drill and rollback rehearsal | Not independently evidenced |
| Deploy | Push only the tested SHA; server fast-forward to same SHA | Enforced by deploy workflow; verify again for every release |
| Post-deploy smoke | /up, homepage, product, account/cart/admin and relevant feature paths | Passed for audited release workflow; current data correctness still requires smoke evidence |

## Release rule

A main push triggers validation and, only when the main workflow is green, the Deploy to Hetzner job. The server checks that origin/main equals the exact GitHub SHA, fast-forwards main, runs deploy/docker-deploy.sh, and checks health. Never deploy a dirty worktree or a SHA that was not the completed workflow head.

Local PHP, Composer and Docker are unavailable in this workspace. The local evidence for this Batch 0 commit is therefore limited to source/static checks and documentation validation; CI must remain the runtime authority.

## Next gates

1. Reconcile the canonical navigation tree and screenshot dimensions.
2. Attach the missing ordered guide archive/manifest or record an approved exception.
3. Add strict visual, responsive and accessibility suites.
4. Correct public Banner and admin Review synchronization.
5. Define domain contracts, policies, UUID/tenant/money/idempotency and provider boundaries.
6. Run backup/restore/rollback evidence before a guide-complete claim.
