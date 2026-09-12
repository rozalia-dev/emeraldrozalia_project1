# Project 1 Email Templates contract evidence

**Scope:** Communication Center Email Templates Dashboard (Visual 141)
**Guide references:** narrative pages 436–438; ordered visual row 141, pages 429–431
**Status:** bounded implementation candidate; not a completion claim for the 519-page guide or 167-reference visual set

## Source of truth

Email templates now use the `communication_templates` aggregate through `CommunicationTemplate` and `CommunicationTemplateService`. The follow-up migration adds tenant ownership, creator/updater UUID relationships, versioning, publication timestamps, soft deletion, correlation ID, request hash and a unique idempotency key. The model route key is the template UUID; the explicit current-company scope is applied to dashboard/API queries and route binding, including console/test execution.

The Communication Center Email Templates page at `/admin/resource/email-templates` reads the durable aggregate. Its create, edit, duplicate, archive/restore, delete and CSV export actions use named template routes rather than the generic `AdminRecord` record endpoints. Approval Center, Alerts & Notifications and Action / Follow-ups remain separate generic surfaces in this batch.

## Web and API contract

| Boundary | Contract | Implementation evidence |
|---|---|---|
| Web create/update/delete | `admin.communication-center.templates.store`, `.update`, `.destroy` | `CommunicationTemplateController` + `CommunicationTemplateRequest` |
| Web lifecycle | `admin.communication-center.templates.action` with `activate`, `archive`, `restore`, `duplicate` and `submit_for_approval` | `CommunicationTemplateService` transition/duplicate actions |
| API collection | `GET/POST /api/v1/communication/templates` | authenticated admin route group, pagination and filters |
| API member | `GET/PATCH/DELETE /api/v1/communication/templates/{template:uuid}` | UUID route binding, policy authorization and resource response |
| API lifecycle | `POST /api/v1/communication/templates/{template:uuid}/actions/{action}` | named action allow-list and service transition |
| Resource shape | UUID, channel, name, subject, body, status, variables, version, actor UUIDs and UTC timestamps; no numeric primary key | `CommunicationTemplateResource` |

Requests use dedicated Form Requests. The API create path accepts `Idempotency-Key`; the same key and request hash replay the original resource, while a changed payload returns `409`. PATCH accepts `expected_version`; a stale version returns `409` after a row lock. The service validates email-only channel/status values and serializes mutations in transactions.

## Audit, events and privacy

Template mutations record immutable audit rows with the actor UUID, subject UUID, action, request correlation ID, IP address and a safe before/after state. Audit state contains identifiers, status, version, a SHA-256 body digest and variable-key names; it does not copy the template body. `CommunicationTemplateChanged` is dispatched after the mutation transaction and carries the template UUID and correlation ID for future consumers.

PostgreSQL migration checks cover the allowed lifecycle statuses and supporting indexes/FKs. The feature contract suite covers UUID-only resources, tenant list/member isolation, idempotency replay/conflict, pagination, version conflict, audit redaction and event dispatch. GitHub Actions remains authoritative for PHPUnit, PostgreSQL rollback/re-migrate, container rehearsal and deployment because PHP, Composer and Docker are unavailable in this workspace.

## Remaining acceptance gaps

- No real email provider adapter, signed provider-specific delivery callback schema or live worker operation is enabled in this slice.
- Approval Center, Alerts & Notifications, Action / Follow-ups, communication reports and analytics still need their durable contracts/read models.
- Exact screenshot comparison, responsive browser journeys, keyboard/accessibility checks and the ordered 167-reference evidence archive remain incomplete.
- MFA/policy breadth, production queue operations and an independent backup/restore drill remain open.
