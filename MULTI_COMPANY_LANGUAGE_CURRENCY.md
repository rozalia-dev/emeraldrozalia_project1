# Multi-company, Multi-language, Multi-currency architecture

Implemented foundation:
- Company master and user/company memberships.
- Per-company enabled languages and currencies, base currency and default locale.
- Session context switchers in storefront/admin; company switching restricted to authenticated admin or memberships.
- Currency master and dated exchange-rate table. Orders retain transaction currency and exchange rate.
- `company_id` added to principal operational tables for tenant isolation.
- Product/category translation JSON columns plus Laravel locale context.
- Seeded Emerald Rozalia Limited (Ireland), EUR/GBP/USD, English/Irish/French/German/Spanish.

Production note: exchange rates are manual until an FX provider is configured. No live provider credentials are embedded. Existing operational queries should be progressively scoped by `company_id` as modules are expanded; this migration establishes the tenant key and context layer.
