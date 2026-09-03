# Business and company screen evidence (P3.6b)

Dedicated routes/views added:

- `/corporate-orders` — corporate value proposition, five-step process, offer cards and quote form.
- `/bulk-orders` — bulk value proposition, five-step process, offer cards and bulk quote form.
- `/franchise` — partnership benefits, advantage list and franchise enquiry form.
- `/careers` — career proposition, open-position list and application form.
- `/global-network` — Limerick HQ, verified principles, network placeholder and partner CTA without invented counts/countries.
- `/contact` — contact options, message form, scheduling/location placeholders and FAQ panel.

All enquiry forms continue to post to the existing transactional `/enquiry` endpoint. Official contact values remain footer-only, per the locked project rule. Missing original photography is explicitly marked pending approved source assets.

Verification on 2026-09-02:

```text
artisan view:cache  -> Blade templates cached successfully.
artisan test       -> 18 passed (134 assertions)
```
