# Project 1 Batch 10 — reports integrity evidence

## Scope

This bounded slice covers the Reports analytics dashboards and the Sales Reports dashboard/export path. It keeps the Project 1 boundary intact: website, franchise and communication reporting only; no POS, production, finance, payroll, HR or separate portal module is introduced.

## Implemented contract

- Order, communication and customer dashboards read filtered PostgreSQL records and render an explicit empty state when the result set is empty.
- Reference totals, preview rows, fabricated trend points and fixture alerts were removed from the two dashboard data providers.
- Order returns/refunds are scoped to the selected orders and report window.
- Sales Reports exports always use the actual order-row schema; an empty result produces headers without fabricated rows.
- Sales channel options normalize missing attribution to `Unattributed`; POS is not exposed as a Project 1 reporting channel.
- Report filter options and recent report history are derived from records in the current report domain.
- Communication SLA, response-time, resolution-time and CSAT values are displayed only when their conversation metadata is recorded. Missing demographic, historical comparison, time-of-day and tax fields are labeled as unavailable rather than estimated.
- Customer segment counts are mutually exclusive for the displayed segment mix.

## Regression coverage

`tests/Feature/ReportsReferenceSuiteTest.php` now covers:

- empty filtered windows for order, communication, customer and sales reports;
- absence of the previous fixture totals in those empty responses;
- recorded conversation metadata for response time, resolution time, SLA and CSAT;
- existing live-record dashboard and export coverage remains in the suite.

## Verification boundary

Local static checks passed for whitespace and both affected JavaScript assets. GitHub Actions run [#337](https://github.com/rozalia-dev/emeraldrozalia_project1/actions/runs/34724229368) passed 206 tests/2,288 assertions, migration rollback/re-run, media browser acceptance and the container release rehearsal. The deploy job fast-forwarded Hetzner to `f0dd61f32d3541067787c5b282985ea036ea77a7`, recorded backup `20260912T230453Z-f0dd61f32d35`, and reported healthy app, database, Redis and Nginx containers.

PHP, Composer and Docker are not installed in this workspace; GitHub Actions remains the runtime authority for those gates. The deployment evidence confirms the tested SHA reached the server, but does not replace visual, accessibility, independent restore or broad post-deploy data evidence.

The following remain intentionally open and are not implied by this batch: full 167-reference screenshot proof, broad browser/accessibility coverage, paginated/versioned analytical read models, independent backup/restore evidence, provider enablement and complete 519-page guide acceptance.
