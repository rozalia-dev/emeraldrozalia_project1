# Project 1 Batch 1 — foundation evidence

## Scope

This task closes one bounded technical slice from the audit and the 519-page guide:

| Area | Implementation | Acceptance target |
| --- | --- | --- |
| Reviews & Ratings | Replaced the eight-row preview collection with paginated `Review` queries, live metrics, filters, rating breakdown and UUID display. | Admin page renders persisted review data and no longer depends on the preview fixtures. |
| Published banners | Added a shared published/schedule/page scope, public homepage reader and `/api/v1/banners`. | Only currently published records are delivered; draft/expired records remain hidden. |
| Public API | Added `/api/v1/products`, `/api/v1/products/{slug}` and `/api/v1/banners`, dedicated Form Requests, JSON Resources, policies and `docs/api/openapi.yaml`. | Responses expose public UUIDs and stable fields without internal numeric IDs. |
| Public enquiries | Added request correlation IDs, `Idempotency-Key` validation, request hashing and durable Inquiry/Application/Conversation links. | Retried submissions do not duplicate records; conflicting reuse is rejected. |

## Changed files

The source, migration, tests and contract are in the candidate commit. The focused feature suite is `tests/Feature/Project1Batch1FoundationTest.php`.

## Verification record

Local static checks:

- `git diff --check` — passed.
- Existing JavaScript `node --check` smoke files — passed.
- PHP, Composer, vendor and Docker — unavailable in this workspace; no local PHP or PHPUnit result is claimed.

The authoritative PostgreSQL migration, seeded feature suite, container rehearsal and production deployment are gated by `.github/workflows/deploy.yml` on the candidate `main` commit. Record the exact workflow run and deployed SHA in the release handoff; a green candidate is still only this bounded batch and does not close the remaining visual, accessibility, cart, order-master, communication-adapter, backup/restore or broader guide gaps.
