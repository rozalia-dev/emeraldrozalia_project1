# Project 1 CI and release gate matrix

**Baseline audited release:** c54e933e36c68f2951e5e05afb32119ea02ea467
**Current verified release:** completion branch `codex/completion-b1-contract-hardening` at `5479bc3c6efe2d7fe480fd0d45910ea4172f06a1`; GitHub Actions run #447 passed the PostgreSQL validation job with 292 tests and 3,364 assertions
**Repository:** rozalia-dev/emeraldrozalia_project1
**Production target:** Hetzner, /var/www/emerald-rozalia, https://emeraldrozalia.com

## Current verified workflow

GitHub Actions run #339 (workflow “Validate and deploy production”) completed successfully for release `841e74972e325f7d88f972cc4283125fc11d38b2` on 13 September 2026: [run #339](https://github.com/rozalia-dev/emeraldrozalia_project1/actions/runs/34742279764).

| Job | Current evidence | Guide completion meaning |
|---|---|---|
| PostgreSQL validation | Passed in run #339, including the configured feature suite, rollback/re-run, seed/status and media browser acceptance | Green release baseline; does not cover all guide contracts |
| Container and release validation | Passed in run #339 | Container/release rehearsal is green |
| Deploy to Hetzner | Passed in run #339; backup `20260913T061943Z-841e74972e32` recorded | Exact GitHub SHA reached the server and all service health checks passed |
| Commit status API | No individual statuses were reported; workflow run is the authoritative record | Do not infer broader coverage from an empty status list |

## Required gates and current state

| Gate | Required evidence | Current state |
|---|---|---|
| Scope/boundary | Project 1 exclusions and single-cPanel rule | Documented; retain no Production/Finance/Payroll/HR/POS/separate portal |
| Guide matrix | 167 ordered rows, 165 archive image references, duplicates retained | Captured in PROJECT1-GUIDE-COVERAGE.md; archive files remain incomplete |
| Navigation | Target hierarchy and source-to-route map | Captured; source mismatch remains open |
| Asset register | Hash, dimensions, source/archive mapping, exact-logo review | 33 local references registered; 132 archive references missing; two brand PNG verification failures |
| Public page matrix | Route/data/form/consumer/evidence per public row | Captured; visual/live content proof remains partial |
| Synchronization | Public write/read to private/admin/report consumers | Captured; the prior banner/settings/communication/report contracts remain verified, and the quote slice now adds a transactional corporate/bulk/franchise enquiry → SalesQuote → approved shared Order path with exact money, tenant, idempotency, inventory, payment and audit assertions; lifecycle/read-model/visual gaps remain |
| Order engine | One engine with six category projections | Corporate, bulk and franchise quote conversion now creates the shared Order/OrderItem/payment/inventory records and is covered by the PostgreSQL feature suite; the six-category lifecycle, reconciliation, return/refund and browser evidence remains partial |
| Franchise lifecycle | Application→approval→agreement→onboarding→store→retail→renewal | Public application/quote origin and quote conversion are covered, but the FranchiseManagement application action is not yet the canonical approved-quote conversion path; agreement/store/retail/renewal policy evidence remains partial |
| UI tokens | Geometry, typography, spacing, responsive and published theme version | Contract captured; measurements/runtime publisher unverified |
| Visual diffs | Exact supplied-reference screenshots at approved viewports | Not run for all 167 entries; current foundation spec covers four public routes only |
| Browser/accessibility | Desktop/tablet/mobile journeys, keyboard, focus, labels, contrast, overflow | Missing broad suite |
| API/contracts | /api/v1, Form Requests, API Resources, Policies, OpenAPI | Versioned catalog, Email Templates and Email Dashboard contracts are feature-tested with dedicated requests/resources/policies and UUID routes; broader guide/API coverage remains partial |
| Async/provider | Jobs, events/listeners, queue/retry/idempotency, email/WhatsApp/payment/webhook contracts | Communication reply queue/retry/idempotency, signed email/WhatsApp/chat callback ledger, the template event/idempotency boundary and the Email Dashboard event/audit boundary are feature-tested; real provider adapters, callback schemas and live delivery remain unconfigured |
| PostgreSQL | migrate/seed/route/feature suite | Passed in run #339, including rollback, re-migrate, seed, status and media browser acceptance |
| Container/release | Docker image, storage, Nginx, worker/scheduler rehearsal | Passed in run #339 |
| Backup/restore/rollback | Actual restore drill and rollback rehearsal | Release backup and rollback/re-run passed in the release workflow; independent restore remains not evidenced |
| Deploy | Push only the tested SHA; server fast-forward to same SHA | Enforced by deploy workflow; run #339 deployed the exact `841e74972e325f7d88f972cc4283125fc11d38b2` |
| Post-deploy smoke | /up, homepage, product, account/cart/admin and relevant feature paths | Deployment health passed in run #339; broad post-deploy data smoke remains limited |

## Release rule

A main push triggers validation and, only when the main workflow is green, the Deploy to Hetzner job. The server checks that origin/main equals the exact GitHub SHA, fast-forwards main, runs deploy/docker-deploy.sh, and checks health. Never deploy a dirty worktree or a SHA that was not the completed workflow head.

Local PHP, Composer and Docker are unavailable in this workspace. Local evidence for the Communication Center contract is limited to source/static checks and documentation validation; CI must remain the runtime authority for migrations, PHPUnit, containers and deployment.

## Next gates

1. Wire FranchiseManagement application conversion to the approved SalesQuote and shared order conversion service, with its own request, idempotency and regression tests.
2. Reconcile the canonical navigation tree and screenshot dimensions.
3. Attach the missing ordered guide archive/manifest or record an approved exception.
4. Add strict visual, responsive and accessibility suites.
5. Configure and verify real email/WhatsApp/chat adapters, signed callback schemas and worker operations.
6. Connect the durable Email Templates, Email Dashboard and Approval Center aggregates to approvals/provider rendering, then replace generic follow-up/alert records and connect reports/analytics to versioned reconciled read models.
7. Complete explicit settings approval/activation/rollback policies and broaden public consumers beyond the shared shell.
8. Add strict visual/browser/accessibility evidence, direct MySQL lifecycle evidence and an independent backup/restore drill before a guide-complete claim.

The quote slice was validated on the PostgreSQL job only. Container/release and deployment jobs were skipped for this pull-request branch, and the frozen deployment workflow was not changed.
