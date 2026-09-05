# SEO & Content workspace — Project 1 evidence

## Implemented

- `/admin/seo` is a reference-matched SEO & Content cPanel workspace with the Emerald Rozalia sidebar, KPI cards, health ring, health breakdown, crawl summary, issue table, content suggestions, lower status cards and right-side summary rail.
- The dashboard tabs are functional: SEO Overview, Meta Management, Keywords, Content Manager, Redirects, Sitemap, Robots & Indexing and Schema.
- Metadata can be edited for the homepage, published content pages, active products and active categories. Product/category metadata is stored in dedicated columns plus SEO JSON, while page/home metadata remains structured JSON.
- The local audit checks missing/long titles and descriptions, missing H1 headings and duplicate titles. Audit runs persist UUID-backed audits/issues; Fix and Resolve actions write audit-log records.
- Target keywords, robots.txt rules, Organization JSON-LD, redirects and generated XML sitemap are persisted per company.
- `/robots.txt` and `/sitemap.xml` are public endpoints. Active redirects are enforced for matched public routes and unknown legacy paths through the public fallback route.
- Saved metadata is consumed by the storefront layout for title, description, canonical URL, robots directive, Open Graph basics and escaped Organization JSON-LD.
- The supplied Emerald Rozalia wordmark remains the only brand mark used in the cPanel header/footer.
- The shared cPanel submenu typography rule keeps admin copy, forms, tables and status text at 13px or larger across desktop, tablet and mobile layouts.

## Verification contract

Added feature coverage in `tests/Feature/SeoDashboardTest.php` for:

- admin dashboard rendering and persisted audit/issues;
- storefront publication of saved homepage metadata and JSON-LD;
- active redirect enforcement.

Static checks passed in this workspace:

- `git diff --check`
- `node --check public/js/app.js`

Runtime checks remain blocked here because PHP and Composer are not installed. Run the following in Laravel Herd, Docker or CI before release:

```bash
composer validate --strict
php artisan migrate:fresh --seed --force
php artisan route:list
php artisan view:cache
php artisan test --filter=SeoDashboardTest
```
