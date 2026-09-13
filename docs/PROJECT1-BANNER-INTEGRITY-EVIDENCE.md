# Project 1 Banner Management Integrity Evidence

**Scope:** Banner / Slider Management and its public homepage read path.  
**Guide mapping:** Phase 02, build visuals 047–048; public/private synchronization contract.  
**Implementation state:** Candidate change from the current verified `main` release; release SHA and CI evidence are recorded after promotion.

## Problem closed

`BannerDashboardService` previously rendered invented reference campaigns, counts, clicks, impressions, UUIDs, activity and trend percentages whenever the `banners` table was empty. That made an empty installation appear populated and allowed preview content to be mistaken for live business data.

## Implemented contract

- Banner rows are mapped only from the filtered `Banner` paginator.
- Summary statistics are calculated from non-trashed `Banner` records in the active company context.
- Empty installations return zero statistics, no selected banner, no UUID, and one explicit empty activity message.
- The dashboard exposes `data-empty` and a visible data-source note.
- KPI comparisons say `No comparison loaded` until a real comparison dataset is available; no percentage is invented.
- Existing saved-record CRUD, filtering, scheduling, revisions, audit, export and public `Banner::publishedFor()` delivery remain database-backed.

## Acceptance evidence

| Check | Expected result |
|---|---|
| Empty admin dashboard | `data-empty="true"`, `No banner records yet`, zero live metrics, no reference campaign names |
| Saved banner dashboard | `data-empty="false"`, saved banner appears, source note names `Banner` records, no reference campaign names |
| KPI comparisons | `No comparison loaded`; no hard-coded reference trend percentages |
| Public homepage/API | Existing published-banner synchronization tests continue to read only eligible `Banner` records |
| Runtime gate | PostgreSQL feature suite, container validation and same-SHA deployment must pass before this evidence is promoted to verified |

## Remaining boundary

This closes the fixture-data defect for Banner Management. It does not claim the 519-page guide or 167-reference visual/accessibility acceptance set is complete; media approval, tenant/role depth, provider contracts and objective screenshot/browser evidence remain open.
