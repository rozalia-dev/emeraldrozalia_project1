# Videos dashboard

Reference: user-supplied Video Dashboard.png, 8 September 2026.
Entry point: /admin/resource/videos.
Baseline: main at 2b2a1d108d37b193fd7d0d0cf3f961c6671bfe24.

## Implemented workflow

- The existing cPanel shell and approved wordmark are retained. The dedicated Videos page replaces the generic records screen with KPI cards, category tabs, product/type/status/platform filters, search, paginated video rows, lower editing panels, a preview and a summary/audit rail.
- ProductMedia remains the source of truth. ProductVideo scopes that table to video records and the accessible product relationship. Videos also appear in Media Manager.
- Native MP4, WebM and MOV uploads use private persistent storage. Maximum 20 MB per video, matching production PHP/Nginx limits. Batches contain up to ten files and are uploaded sequentially with per-file progress and partial-failure reporting.
- Title, product, category, publication state, future publish time, privacy, gallery inclusion, download permission, description, SEO title, tags, caption language, thumbnail and captions persist to the media record.
- Browser metadata extraction captures duration and resolution. The browser can generate a JPEG thumbnail; manual JPG/PNG/WebP thumbnails and WebVTT captions are also supported.
- Published public videos belonging to active products can be watched through gated file delivery, including byte ranges. Gallery inclusion is independently controllable. Scheduled publishing is evaluated at request time and requires no job to make content visible.
- Private/draft/future scheduled videos, posters, captions and watch pages are not accessible to visitors. Admin preview is permitted. Existing public media is copied to private storage on editing; shared legacy files require a replacement before privacy can change.
- Existing public YouTube and Vimeo URLs are normalized to allow-listed embed URLs. No external URL is fetched by the server.
- Bulk publication, draft and deletion actions validate the whole selection, restrict targets to videos, preserve privacy and record audit entries. Deletion removes the media and its recorded playback rows; owned unreferenced files are cleaned after commit.
- Website playback records are deduplicated per browser session/video/day. Watch increments are bounded by elapsed server time. Admin previews are excluded. The dashboard and CSV export use these records; no screenshot counts are seeded.
- Filtered CSV export escapes spreadsheet formula prefixes. The public /video-sitemap.xml lists watch URLs for publicly playable videos.
- UUID copy and the per-video audit dialog expose traceable changes.
- The persistent private media volume is included in release backups.

## Validation

Feature coverage: tests/Feature/VideoDashboardTest.php.
Browser coverage: tests/Browser/video-dashboard.cjs, executed against a disposable PostgreSQL database in CI.
Existing PostgreSQL and Docker release gates remain required before production deployment.

The local command runner disconnected during this task. Runtime and browser validation are performed in GitHub Actions; this document does not claim a locally reviewed pixel comparison.

## Boundaries

- External platform analytics, platform publishing, transcoding, GIF generation and automatic caption transcription are not implemented. The interface identifies the available measurements and upload guidance.
- The six categories are the supplied reference categories. They are assignable/filterable, not a separate user-defined taxonomy editor.
- 360° / Interactive is a video category; it does not convert flat videos into a hosted 360° player.
- MOV playback depends on browser/codec support. H.264 MP4 is the recommended upload format.
- A public external provider video remains subject to that provider's access controls even if its dashboard record is private.
- Old public URLs may remain in browser/CDN caches for their previous cache lifetime after a legacy file is migrated; all newly uploaded native files start in private storage.
- Tenant access follows the existing product company context and admin authentication gate. This task does not claim a new global role/permission system or completion of unrelated cPanel modules.
