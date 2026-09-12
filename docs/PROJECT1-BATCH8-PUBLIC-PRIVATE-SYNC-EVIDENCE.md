# Project 1 Batch 8 evidence

Date: 2026-09-12

Batch 8 hardens the public/private synchronization boundary for the two audit-priority domains: homepage banners and product reviews.

## Implemented

- The homepage and versioned catalog banner API continue to read the shared `Banner` model through `publishedFor()`, which enforces published status, schedule, position and page targeting.
- The public homepage identifies that source with `data-public-source="published-banner-records"`; the API and homepage now have one regression contract covering visible, draft, expired and page-targeted records.
- `Review::approved()` is the named public-read scope, and `Product::reviews()` uses that scope for counts, averages and rendered review cards.
- Customer review submissions are stored as `pending`, honoring the Reviews & Ratings setting that requires approval.
- Admins can approve, reject, return to pending or flag a live review through `admin.reviews.status`; every transition is recorded in the audit log.
- The admin Reviews & Ratings page identifies its live `Review` source and exposes moderation actions on each record.

## Acceptance coverage

- `PublicPrivateSynchronizationContractTest` proves that only current published homepage Banner records reach the homepage/API.
- The same test proves approved reviews reach the public product page, pending reviews remain private, the admin page sees both states, non-admin moderation is forbidden, and an admin approval publishes that same Review record.
- Existing `Project1Batch1FoundationTest` and `BannerDashboardReferenceTest` continue to cover live banner delivery, dashboard lifecycle, admin review data and dynamic metrics.
- Local `git diff --check` and source/static review are required before publication. PHP, Composer, PostgreSQL and Docker execution remain authoritative in GitHub Actions for this workspace.

## Remaining gaps

- Banner media approval, complete tenant/company workflow and full review moderation history remain partial.
- Exact supplied-reference screenshot diffs, responsive/browser/accessibility journeys, the complete 519-page guide and the 167-reference visual acceptance set remain open.
