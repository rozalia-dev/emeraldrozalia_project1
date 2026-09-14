# Tenant context evidence

Task P1.5 aligns tenant-aware models, child ownership, seed data and public fallback rules.

Implemented:

- `BelongsToTenant` provides a shared global session-company scope, explicit `forCompany()` selection, parent-company inheritance and conflict rejection for new records.
- Migration `2026_09_14_000400_complete_child_tenant_ownership` adds indexed/FK-backed `company_id` ownership to addresses, variants, media, spins, try-on, playback, order items, inventory, wishlists, reviews, returns, rewards, customer segmentation/profile records, content/revisions, media versions, communication records, integrations, audits, automation and backup records.
- Parent relationships (product, variant, order, page, banner, asset, media, application and conversation) are checked before child records can be created under a different company.
- Direct analytics/tracking inserts for spin visits, video plays and try-on visits carry the company identifier; seed data and the shared reserved homepage are repaired consistently.
- `ContentPage` preserves the explicitly reserved global homepage fallback while ordinary pages remain tenant-scoped.

Verified by GitHub Actions run **#408** (`34847169918`) on commit `da7ccd394b84c6857c3b7ba086211f903d5dd1fd`: **272 tests passed, 3,190 assertions**, PostgreSQL validation passed, and migration rollback/re-run passed. The tenant contract suite covers child columns/FKs, parent inheritance, global session scope, explicit company scopes, order-item/media ownership, public cross-tenant media blocking and global homepage behavior.

The scope remains unfiltered when no session company is selected, which allows migrations and explicit console/queued maintenance to choose `forCompany()` safely. A session company is required for request-scoped isolation; job/report code must pass an explicit company context when it does not have a request session. API/FormRequest authorization, provider idempotency, restore/rollback and browser/accessibility evidence remain separate open gates.
