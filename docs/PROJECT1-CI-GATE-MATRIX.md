# Project 1 CI and release gate matrix

**Baseline audited release:** c54e933e36c68f2951e5e05afb32119ea02ea467
**Current verified release:** 96da0731a516c4ee652534df44bc2b3c1590ee74
**Repository:** rozalia-dev/emeraldrozalia_project1
**Production target:** Hetzner, /var/www/emerald-rozalia, https://emeraldrozalia.com

## Current verified workflow

GitHub Actions run #328 (workflow “Validate and deploy production”) completed successfully for merged release `96da0731a516c4ee652534df44bc2b3c1590ee74` on 12 September 2026.

| Job | Current evidence | Guide completion meaning |
|---|---|---|
| PostgreSQL validation | Passed in run #328, including 201 tests, 2,211 assertions, full rollback/re-run and media browser acceptance | Green release baseline; does not cover all guide contracts |
| Container and release validation | Passed in run #328 | Container/release rehearsal is green |
| Deploy to Hetzner | Passed in run #328; backup `20260912T214342Z-96da0731a516` recorded | Exact merged SHA reached the server and all service health checks passed |
| Commit status API | No individual statuses were reported; workflow run is the authoritative record | Do not infer broader coverage from an empty status list |

## Required gates and current state

| Gate | Required evidence | Current state |
|---|---|---|
| Scope/boundary | Project 1 exclusions and single-cPanel rule | Documented; retain no Production/Finance/Payroll/HR/POS/separate portal |
| Guide matrix | 167 ordered rows, 165 archive image references, duplicates retained | Captured in PROJECT1-GUIDE-COVERAGE.md; archive files remain incomplete |
| Navigation | Target hierarchy and source-to-route map | Captured; source mismatch remains open |
| Asset register | Hash, dimensions, source/archive mapping, exact-logo review | 33 local references registered; 132 archive references missing; two brand PNG verification failures |
| Public page matrix | Route/data/form/consumer/evidence per public row | Captured; visual/live content proof remains partial |
| Synchronization | Public write/read to private/admin/report consumers | Captured; Batch 8 closes the banner/review boundary, Batch 9 adds versioned tenant-scoped public settings, and Communication Center slices add durable correlation/idempotency, queued delivery, signed callback state, Email Templates and Email Dashboard aggregates; lifecycle/report gaps remain |
| Order engine | One engine with six category projections | Captured; conversion/transition/reconciliation evidence remains partial |
| Franchise lifecycle | Application→approval→agreement→onboarding→store→retail→renewal | Captured; complete transition/policy evidence remains partial |
| UI tokens | Geometry, typography, spacing, responsive and published theme version | Contract captured; measurements/runtime publisher unverified |
| Visual diffs | Exact supplied-reference screenshots at approved viewports | Not run for all 167 entries; current foundation spec covers four public routes only |
| Browser/accessibility | Desktop/tablet/mobile journeys, keyboard, focus, labels, contrast, overflow | Missing broad suite |
| API/contracts | /api/v1, Form Requests, API Resources, Policies, OpenAPI | Versioned catalog, Email Templates and Email Dashboard contracts are feature-tested with dedicated requests/resources/policies and UUID routes; broader guide/API coverage remains partial |
| Async/provider | Jobs, events/listeners, queue/retry/idempotency, email/WhatsApp/payment/webhook contracts | Communication reply queue/retry/idempotency, signed email/WhatsApp/chat callback ledger, the template event/idempotency boundary and the Email Dashboard event/audit boundary are feature-tested; real provider adapters, callback schemas and live delivery remain unconfigured |
| PostgreSQL | migrate/seed/route/feature suite | Passed in run #328; 201 tests/2,211 assertions plus rollback, re-migrate, seed, status and media browser acceptance are now rehearsed |
| Container/release | Docker image, storage, Nginx, worker/scheduler rehearsal | Passed in run #328 |
| Backup/restore/rollback | Actual restore drill and rollback rehearsal | Release backup and rollback/re-run passed in the release workflow; independent restore remains not evidenced |
| Deploy | Push only the tested SHA; server fast-forward to same SHA | Enforced by deploy workflow; run #328 deployed the exact `96da0731a516c4ee652534df44bc2b3c1590ee74` |
| Post-deploy smoke | /up, homepage, product, account/cart/admin and relevant feature paths | Deployment health passed in run #328; broad post-deploy data smoke remains limited |

## Release rule

A main push triggers validation and, only when the main workflow is green, the Deploy to Hetzner job. The server checks that origin/main equals the exact GitHub SHA, fast-forwards main, runs deploy/docker-deploy.sh, and checks health. Never deploy a dirty worktree or a SHA that was not the completed workflow head.

Local PHP, Composer and Docker are unavailable in this workspace. Local evidence for the Communication Center contract is limited to source/static checks and documentation validation; CI must remain the runtime authority for migrations, PHPUnit, containers and deployment.

## Next gates

1. Reconcile the canonical navigation tree and screenshot dimensions.
2. Attach the missing ordered guide archive/manifest or record an approved exception.
3. Add strict visual, responsive and accessibility suites.
4. Configure and verify real email/WhatsApp/chat adapters, signed callback schemas and worker operations.
5. Advance the next Communication Center contract (Approval Center), connect the durable Email Templates and Email Dashboard aggregates to approvals/provider rendering, then replace generic approval/follow-up/alert records and connect reports/analytics to reconciled read models.
6. Complete explicit settings approval/activation/rollback policies and broaden public consumers beyond the shared shell.
7. Add strict visual/browser/accessibility evidence, direct MySQL lifecycle evidence and an independent backup/restore drill before a guide-complete claim.
