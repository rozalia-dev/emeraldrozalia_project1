# Project 1 Communication Center contract evidence

**Scope:** Communication Center source-of-truth and delivery boundary follow-up
**Guide references:** pages 417–419 (Communication Audit Dashboard) and pages 436–438 (Email Templates Dashboard)
**Audit requirement:** one shared cPanel conversation path for web, chat, WhatsApp and email; durable correlation, idempotency, provider, queue, retry, callback, consent, redaction and audit contracts

This is a bounded implementation slice. It does not claim completion of the 519-page guide or the 167-reference visual acceptance set.

## Implemented contract

| Boundary | Implementation | Evidence |
|---|---|---|
| Public enquiry to cPanel | Public contact, corporate, bulk, careers and franchise forms continue to create one Inquiry→Conversation→Message path. Conversation records now carry company, customer/order/store links when known, correlation ID, request hash, idempotency key and consent version/timestamp. | `SiteController::inquiry`; `CommunicationContractTest::test_public_submission_stores_shared_conversation_contract_and_is_idempotent`; existing public form feature suites |
| cPanel reply | Admin replies use `CommunicationCenter::sendReply`, a conversation row lock and message idempotency hash. The new message is `queued` and dispatched after commit. | `ResourceController::storeMessage`; `CommunicationContractTest::test_admin_reply_is_queued_and_key_reuse_is_safe` |
| Provider boundary | `CommunicationProvider` and `CommunicationProviderRegistry` resolve an explicit channel adapter from configuration. A missing adapter transitions a message to `awaiting_provider`; it cannot report delivery success. | `DeliverCommunicationMessage`; `CommunicationContractTest::test_missing_provider_never_reports_delivery_success` |
| Delivery state | Messages retain provider ID, delivery attempts, delivered/failed timestamps and safe failure code/message. Provider exceptions enter retry state with three attempts and `[60, 300, 900]` second backoff. | `DeliverCommunicationMessage` |
| Signed callback | `POST /api/v1/communication/webhooks/{provider}` validates the raw-body HMAC-SHA256 signature, accepts email/WhatsApp/chat provider keys, records a UUID event ledger and applies monotonic delivery transitions. | `CommunicationWebhookController`; `CommunicationWebhookService`; signed/duplicate/bad-signature assertions in `CommunicationContractTest` |
| Duplicate/privacy boundary | `(provider, external_event_id)` is unique. Webhook payloads are redacted recursively for body/message/content/email/phone/token/secret/authorization and related contact keys before persistence. | `communication_webhook_events` migration; redaction assertions in `CommunicationContractTest` |
| Audit/correlation | Public submission, reply creation, conversation updates, delivery changes, retries, failures and webhook processing write audit entries containing identifiers/statuses without message bodies or webhook secrets. | `AuditTrail` calls in the communication service/job/webhook service |

## Required runtime configuration

The adapter and webhook secret environment variables are intentionally blank in `.env.example`:

- `COMMUNICATION_EMAIL_PROVIDER`
- `COMMUNICATION_WHATSAPP_PROVIDER`
- `COMMUNICATION_CHAT_PROVIDER`
- `COMMUNICATION_EMAIL_WEBHOOK_SECRET`
- `COMMUNICATION_WHATSAPP_WEBHOOK_SECRET`
- `COMMUNICATION_CHAT_WEBHOOK_SECRET`

Until an adapter is installed and a signed callback is tested, outbound messages remain visibly `awaiting_provider` after the queued job runs. No provider success is hard-coded.

## Explicit remaining gaps

- No production email, WhatsApp or chat adapter is enabled or verified in this slice.
- The cPanel template, approval, action/follow-up and alert surfaces still use their existing generic `AdminRecord` workflow; the pre-existing `communication_templates` and `approvals` tables are not yet the complete UI source of truth.
- Communication reports/analytics still require the later reports and integrations contract, including removal of fixture fallbacks and export reconciliation.
- The guide’s exact screenshot archive, ordered 167-row visual acceptance, responsive browser journeys and accessibility evidence remain incomplete.
- Provider-specific callback schemas, MFA/policy coverage, real queue-worker operations and a production restore drill remain open.

## Release interpretation

Local PHP, Composer and Docker are unavailable in this workspace. `git diff --check` is the local static gate; PostgreSQL migrations, PHPUnit, container rehearsal, deployment and post-deploy health are authoritative GitHub workflow gates. This document should be updated with the merged main SHA and workflow links after those gates pass.
