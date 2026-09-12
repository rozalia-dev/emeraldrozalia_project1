# Project 1 Email Dashboard contract evidence

**Scope:** Communication Center Email Dashboard (Visual 142 / Build Step 142)
**Guide references:** narrative pages 439–441; ordered visual row 142, pages 432–434
**Status:** deployed bounded implementation slice; not a completion claim for the 519-page guide or 167-reference visual set
**Deployed release:** `96da0731a516c4ee652534df44bc2b3c1590ee74` via [GitHub Actions run #328](https://github.com/rozalia-dev/emeraldrozalia_project1/actions/runs/34720557324); backup `20260912T214342Z-96da0731a516`

## Source of truth

Email threads use the shared `Conversation` aggregate with `channel=email`. The aggregate is tenant-scoped through the current company context, uses the conversation UUID as its route key, and now supports soft deletion plus stable channel/status/date indexes. Customer and order relations remain links from the conversation rather than duplicate dashboard records.

The browser surface at `/admin/resource/email` reads the same aggregate and preserves filters through the URL. Search covers contact, subject, conversation UUID, message body/UUID, customer name/email/UUID, order number/UUID and allowlisted metadata. Dedicated filters cover customer, order, UID and created-date bounds, with status and priority filters shared by the dashboard contract.

## Web and API contract

| Boundary | Contract | Implementation evidence |
|---|---|---|
| Dashboard list | `GET /admin/resource/email` | `CommunicationCenterController` and the shared Email Dashboard view; eager-loaded customer/order/assignee/message relations and stable updated-date ordering |
| Dashboard filters | `q`, `customer`, `order`, `uid`, `date_from`, `date_to`, `status`, `priority` | URL-persisted browser filters and date validation coverage in `CommunicationEmailDashboardContractTest` |
| Conversation selection | `/admin/resource/email?conversation={conversation_uuid}` | UUID-only browser selection and UUID route binding; numeric conversation IDs are not used by the Email surface |
| Actions | `POST /admin/communication-center/email/{conversation:uuid}/actions/{resolve|reopen|escalate}` | Policy-authorized service mutation with row locking, correlation/audit state and after-commit change event |
| Audit export | `GET /admin/communication-center/email/{conversation:uuid}/audit/export` | CSV export contains audit/actor/subject/request identifiers and redacted before/after snapshots |
| API collection | `GET /api/v1/communication/email` | Authenticated/admin/throttled UUID-keyed collection with pagination and tenant scope |
| API member/update | `GET/PATCH /api/v1/communication/email/{conversation:uuid}` | Form Requests, policy authorization and `CommunicationConversationResource`; numeric IDs are omitted |
| API reply | `POST /api/v1/communication/email/{conversation:uuid}/messages` | `CommunicationReplyRequest`, `Idempotency-Key`, queued delivery job and message resource |
| API audit | `GET /api/v1/communication/email/{conversation:uuid}/audit` | Paginated redacted audit resources; no message body or secret fields are copied into audit snapshots |

The primary fields exposed by the resource are conversation UUID, channel, contact, subject, status, priority, consent, customer/order/assigned-agent UUIDs, follow-up timestamp, allowlisted metadata, message count and message history when loaded. The browser exposes resolve, reopen, escalate and Export Audit operations for an authorized administrator.

## States and privacy boundary

The Email configuration maps `new`, `open`, `pending` and `closed` to New, Open, Approval Required and Resolved. The dashboard retains the loading, empty, validation and forbidden/error paths supplied by the shared admin shell and request/policy boundary. Provider delivery remains a separate stateful queue boundary; this slice does not claim successful external delivery.

Conversation and message resources expose UUIDs instead of numeric primary keys. Queries apply the active company scope, route binding rejects another company’s conversation, and the resource only returns allowlisted metadata. Audit exports recursively redact body, message, content, email, phone, token, secret and related private fields.

## Verification

`CommunicationEmailDashboardContractTest` covers related-record search, message/conversation UUID matching, date bounds, browser actions, CSV audit export, tenant isolation, API resources, update events, reply idempotency and soft deletion. GitHub Actions run #327 passed the PR gate with 201 tests and 2,211 assertions; the exact merged `main` SHA passed run #328 with the same PostgreSQL test count, migration rollback/re-run, media browser acceptance, container release rehearsal and the exact-SHA Hetzner health check.

Local PHP, Composer and Docker are unavailable in this workspace. `git diff --check` is the local static gate; GitHub Actions is authoritative for runtime, PostgreSQL, PHPUnit, container, migration and deployment evidence.

## Remaining acceptance gaps

- No production email adapter, provider-specific signed delivery callback schema or live worker operation is enabled.
- Approval Center, Alerts & Notifications, Action / Follow-ups, communication reports and analytics still need their durable contracts/read models and policy/MFA coverage.
- Exact Archive 063 screenshot comparison, responsive browser journeys, keyboard/accessibility checks and the ordered 167-reference evidence archive remain incomplete.
- MySQL lifecycle evidence, an independent backup/restore drill and the broader guide completion gate remain open.
