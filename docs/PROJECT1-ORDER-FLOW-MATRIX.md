# Project 1 order-flow matrix

**Baseline:** c54e933e36c68f2951e5e05afb32119ea02ea467
**Current verified branch:** codex/completion-b1-contract-hardening at 5479bc3c6efe2d7fe480fd0d45910ea4172f06a1; GitHub Actions run #447 passed (292 tests, 3,364 assertions)
**Boundary:** six separate order categories use one shared order engine. Production, unified Finance, POS and the broader Project 2 stock/traceability system remain external integrations.

## Six order masters

| Order category | Creation source | Current route/domain | Required records | Current status | Next acceptance |
|---|---|---|---|---|---|
| Online | Authenticated customer cart and checkout | /cart, /checkout, /admin/orders/online; CheckoutController and OrderMasterController | Order, OrderItem, PaymentTransaction, InventoryMovement, RewardTransaction, Conversation/notifications | Partial; core transaction path exists | Idempotent checkout, minor-unit money, payment provider boundary, explicit transitions and reconciliation |
| Corporate | Corporate enquiry/quote request | /corporate-orders → POST /enquiry type=corporate-orders; /admin/quotes and shared admin order masters | Inquiry, Conversation, Message, SalesQuote, then shared Order conversion | Contract slice verified on the completion branch; enquiry remains a quote until explicit conversion | Complete full order lifecycle, provider/reconciliation, browser/accessibility and supplied-reference evidence |
| Bulk | Bulk enquiry/quote request | /bulk-orders → POST /enquiry type=bulk-orders; /admin/quotes and shared admin order masters | Inquiry, Conversation, Message, SalesQuote, then shared Order conversion | Contract slice verified on the completion branch; quantity and pricing are admin-priced before approval | Complete full order lifecycle, provider/reconciliation, browser/accessibility and supplied-reference evidence |
| Franchise | Franchise enquiry/application quote request | /franchise → application/enquiry; /admin/quotes and /admin/orders/franchise | FranchiseApplication, Inquiry, Conversation, Message, SalesQuote, then shared Order conversion | Public enquiry-to-quote and quote conversion contract verified; franchise application action is not yet the canonical conversion entry point | Wire application conversion to the approved quote, then complete store eligibility, pricing, fulfillment, returns and browser/accessibility evidence |
| Franchise Retail | Activated retail store operator order | /admin/orders/franchise_retail | FranchiseStore, Order, OrderItem, payment/fulfillment/returns records | Partial; store-scoped authorization and pricing are not fully evidenced | Store/operator policies, price assignment, reconciliation and retail reports |
| Buyer | Buyer-assisted/manual order | /admin/orders/buyer | Order, OrderItem, customer/buyer context, payment/fulfillment records | Partial; buyer-specific creation source is not proven | Buyer role matrix, required fields, conversion and audit/idempotency tests |

## Shared lifecycle target

The implementation should use one transition vocabulary and record a transition/audit event for:

Draft → Quote → Submitted → Approved → Confirmed → Paid → Fulfilling → Shipped → Completed

Exceptional states are Cancelled, Return Requested, Partially Returned, Refunded, Failed, and Rejected. The current code uses ordinary strings across domains; this vocabulary is a target contract, not a claim that the current database enforces it.

## Shared source of truth

- Product and ProductVariant provide sellable item and variant data.
- SalesQuote is the explicit commercial proposal for corporate, bulk and franchise enquiry sources; it remains separate until an approved, authorized conversion creates the shared Order and OrderItem records.
- Order and OrderItem provide the commercial record.
- PaymentTransaction provides payment state; external gateways remain provider-neutral until explicitly enabled.
- InventoryMovement records stock effects within Project 1’s current boundary; unified stock/production belongs to Project 2 integration.
- ReturnRequest records customer return intent; refund ledger semantics remain open.
- Conversation and Message carry customer/order communication through Communication Center.
- AuditLog records operational changes, but UUID/correlation/redaction requirements remain open.

## Prohibited duplication

Do not create six copied order schemas or controllers. Category-specific behavior belongs in configuration, policies, transitions and projections over the shared order domain. Do not treat an enquiry or quote form submission as an order until explicit conversion is recorded.

## Order exit gate

Each category needs a creation source, required fields, role matrix, transition matrix, tenant scope, UUID/public identifier policy, money/currency rule, idempotency rule, audit event, customer/admin/report sync result, return/refund semantics, and browser/API/contract tests before it is considered complete. The quote slice satisfies the source, pricing, approval, conversion, tenant, UUID, money, idempotency, inventory, payment, audit and contract-test portions for corporate, bulk and franchise enquiries; it does not satisfy the remaining full-category exit gate.
