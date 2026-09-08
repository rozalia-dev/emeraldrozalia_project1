# Bulk Order Reference Evidence

Approved visual source: `docs/new project image/bulk order.png`.

Production asset copy: `public/assets/brand/bulk-order-reference.png`.

Dedicated public implementation:

- `resources/views/site/bulk-order.blade.php`
- `public/css/bulk-order.css`
- `/bulk-orders`

Functional quote workflow:

- POST `/enquiry` with `type=bulk-orders`
- Validates name, email and requirements message
- Captures company, phone and country
- Persists the public Inquiry
- Persists the corresponding Communication Centre conversation and inbound message
- Stores country in inquiry and conversation metadata

Reference-aligned visual sections include the hero, service strip, five-step process, orderable product examples, quantity/lead-time/pricing, quote form, WhatsApp CTA, trust/choice blocks, factory visit and branded footer.
