# Sales quote and shared order conversion evidence

**Status:** Verified implementation slice; this is not a project-complete claim.

**Branch and CI:** `codex/completion-b1-contract-hardening` at `5479bc3c6efe2d7fe480fd0d45910ea4172f06a1`. GitHub Actions run [#447](https://github.com/rozalia-dev/emeraldrozalia_project1/actions/runs/34869443717) passed the PostgreSQL validation job with 292 tests and 3,364 assertions. The job also completed fresh migration/seed, full rollback, re-migration, seed and migration-status checks. Container/release and deployment jobs were skipped because this is a pull-request branch. `.github/workflows/deploy.yml` was not changed.

## Contract implemented

Corporate, bulk and franchise enquiry submissions now have an explicit quote boundary:

1. The public POST /enquiry path creates the Inquiry, Conversation and Message records in the existing communication transaction.
2. The corporate-orders, bulk-orders and franchise types also create one tenant-aware SalesQuote linked to the source inquiry and, for franchise, the FranchiseApplication.
3. Admin users can inspect the live quote queue, update exact pricing, approve/reject/cancel a quote, and convert an approved quote through the Sales Quote routes.
4. Conversion creates the shared Order and OrderItem records, a payment transaction, the inventory sale movement, source/order links, the audit entry and the after-commit SalesQuoteConverted event.
5. The quote remains separate from an order until the explicit approved conversion succeeds.

## Functional safeguards

- Tenant/company visibility is enforced in the model binding, policy, controller and service paths.
- Product and variant ownership is checked against the quote company before pricing or conversion.
- Pricing uses the project Money value object and minor-unit arithmetic; the tested example is 2 × 18.00 + 6.95 shipping − 1.50 discount = 41.45.
- Quote updates and state changes use an expected version, so stale admin edits are rejected.
- Conversion uses row locks, a normalized conversion key and Idempotency-Key replay. The same request returns the existing order; a conflicting request is rejected.
- Inventory is decremented and linked to the created order inside the same database transaction; an insufficient-stock failure rolls the entire conversion back.
- Conversion creates the initial payment state and records the source correlation/audit trail.
- Non-admin access to the queue and conversion path is denied by policy.

## Evidence

The dedicated feature contract is `tests/Feature/SalesQuoteConversionContractTest.php`. It covers:

- corporate, bulk and franchise enquiry creation without premature orders;
- admin pricing, approval and conversion;
- exact quote/order/payment totals;
- shared order/item/payment/inventory/audit/event records;
- idempotent replay and conflicting idempotency keys;
- rollback after insufficient inventory; and
- authorization failure for non-admin users.

The implementation is distributed across `SalesQuote`, `SalesQuoteService`, `SalesQuoteController`, the quote requests/policy/event, the quote migration, public enquiry integration, and the linked Inquiry/Conversation/Order models.

## Remaining boundary

This slice does not yet close the whole guide or the full order exit gate. The FranchiseManagement application `convert` action still needs to delegate to its approved quote as the canonical conversion entry point. The remaining work also includes the complete order lifecycle, provider/reconciliation contracts, durable action/follow-up/alert read models, tenant/public-asset hardening, supplied-reference visual mapping, browser/accessibility coverage, independent restore/release evidence and final unified-server verification.