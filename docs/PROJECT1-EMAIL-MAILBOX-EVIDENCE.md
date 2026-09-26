# Project 1 Email Mailbox attachments and export evidence

**Scope:** Private email attachments, selected-thread print and download controls on `/admin/resource/email`.
**Release:** `b6c1dbb2670dd30978b603506a8d92b3ebdf959d`
**Production workflow:** [Run #1035](https://github.com/rozalia-dev/emeraldrozalia_project1/actions/runs/36276244682)
**Production backup:** `20260926T223231Z-b6c1dbb2670d`

## Available controls

The selected conversation header provides Print and four thread download choices. All exports use an `email-thread-<conversation id prefix>` filename.

| Control | Export contents |
|---|---|
| Print | Opens the browser print dialog for the selected conversation. Print CSS hides the dashboard controls and surrounding admin navigation. |
| TXT | Plain text transcript. The original `/download` URL remains compatible and still returns TXT. |
| PDF | Paginated PDF transcript with the conversation header, message dates, delivery status, bodies and attachment names. |
| CSV | UTF-8 CSV with one row per message (and a row for a draft), including subject, contact, conversation ID/status, direction, date, delivery status, body and attachment names. Fields that could be interpreted as spreadsheet formulas are escaped. |
| Word (.docx) | Word-compatible document containing the plain text transcript and attachment names. |

The export includes attachment names, not attachment file data. Attachments continue to be downloaded separately from their message or draft. The download routes remain behind the admin authentication and Communication Center permission middleware.

## Verification

- `EmailMailboxDashboardTest::test_email_mailbox_supports_private_attachments_thread_download_and_print_controls` checks the download content types and filenames, TXT compatibility, PDF header/cross-reference, CSV columns/content, opening the DOCX ZIP package and presence of the message text, rendered format controls, and private attachment download.
- PostgreSQL 17 feature suite: 462 tests and 5,558 assertions passed in run #1035.
- Migration rollback/re-run, media-browser acceptance and Docker release rehearsal passed in run #1035.
- Production deployment of release `b6c1dbb2670dd30978b603506a8d92b3ebdf959d` passed the app/Nginx health checks and public health endpoint in run #1035. No migrations were pending.

## Remaining limits

The print action depends on the user's browser print dialog. Thread exports are generated on demand and do not bundle private attachment bytes. Provider delivery, approval/follow-up linkage and approved reference-image/browser-accessibility comparisons remain separate Email Dashboard work.
