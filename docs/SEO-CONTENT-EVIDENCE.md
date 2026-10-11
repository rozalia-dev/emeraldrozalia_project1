# SEO & Content workspace — Project 1 evidence

## Implemented

- `/admin/seo` is a reference-matched SEO & Content cPanel workspace with the Emerald Rozalia sidebar, KPI cards, health ring, health breakdown, crawl summary, issue table, content suggestions, lower status cards and right-side summary rail.
- The dashboard tabs are functional: SEO Overview, Meta Management, Keywords, Content Manager, Redirects, Sitemap, Robots & Indexing and Schema.
- Metadata can be edited for the homepage, published content pages, active products and active categories. Product/category metadata is stored in dedicated columns plus SEO JSON, while page/home metadata remains structured JSON.
- The local audit checks missing/long titles and descriptions, missing H1 headings, missing image alt text, missing internal links and duplicate titles. Audit runs persist UUID-backed audits/issues; Fix and Resolve actions write audit-log records.
- The Quick Actions broken-link check scans stored page content for unresolved internal paths, persists reviewable issues and records its last result; the safe review action does not silently rewrite a destination.
- Target keywords, robots.txt rules, Organization JSON-LD, redirects and generated XML sitemap are persisted per company.
- `/robots.txt` and `/sitemap.xml` are public endpoints. Active redirects are enforced for matched public routes and unknown legacy paths through the public fallback route.
- Saved metadata is consumed by the storefront layout for title, description, canonical URL, robots directive, Open Graph basics and escaped Organization JSON-LD.
- The supplied Emerald Rozalia wordmark remains the only brand mark used in the cPanel header/footer.
- The shared cPanel submenu typography rule keeps admin copy, forms, tables and status text at 13px or larger across desktop, tablet and mobile layouts.

## Search Console coverage follow-up (2026-10-10)

The five Google Search Console Coverage Drilldown exports contain 188 URLs. These changes address application-controlled routing, canonical, indexability and content signals; they do not guarantee that Google will index a URL.

| Search Console status | URLs | Site-side handling |
|---|---:|---|
| Not found (404) | 13 | Known legacy category/product paths are mapped to reviewed live public destinations. The legacy route map is covered by `test_search_console_legacy_404_urls_redirect_when_no_live_catalogue_record_exists`. |
| Crawled - currently not indexed | 19 | Product/category fallback copy and meta descriptions are enriched, and non-indexable pages are excluded from indexable status summaries. These quality signals cannot force Google to index a page. |
| Page with redirect | 11 | Most examples are filtered `/shop` variants. Filter query URLs receive clean canonical/noindex signals; review the report again after Google recrawls. |
| Alternative page with proper canonical tag | 25 | These are expected alternates. Virtual Try-On query state uses the clean page canonical; paginated catalogue pages retain page-specific canonicals. |
| Duplicate without user-selected canonical | 120 | Faceted catalogue URLs publish a canonical to the clean listing and `noindex,follow`; pagination remains indexable with its own canonical. |

Related feature coverage in `tests/Feature/SeoDashboardTest.php` includes `test_faceted_catalog_urls_are_noindex_but_pagination_remains_indexable`, `test_thin_product_and_category_copy_is_enriched_on_public_pages`, `test_thin_product_and_category_copy_gets_useful_meta_descriptions`, and `test_meta_status_cards_ignore_non_indexable_pages`.

Search Console reports can remain unchanged until its next crawl. Verify the same URL groups after recrawl; canonical and redirect reports do not mean every variant should be indexed.

## Verification contract

Added feature coverage in `tests/Feature/SeoDashboardTest.php` for:

- admin dashboard rendering and persisted audit/issues;
- storefront publication of saved homepage metadata and JSON-LD;
- active redirect enforcement.
- broken internal-link checks and reviewable persisted issues;
- safe automated fixes for missing image alt text and internal linking.

Static checks passed in this workspace:

- `git diff --check`
- `node --check public/js/app.js`
- CSS contract for the admin 13px readability floor and larger SEO headings/KPI values.

PHP and Composer are not installed in this workspace, so runtime validation is provided by GitHub Actions. Main commit `50ce1cf30dc1836f062ed05c8d0ab4df9badeb78` passed run #1359: 525 PostgreSQL tests, 5,997 assertions, migration rollback/re-run, media browser acceptance, Docker release rehearsal, and production health checks ([run #1359](https://github.com/rozalia-dev/emeraldrozalia_project1/actions/runs/38153893181)). Useful repeatable commands in a runtime-enabled checkout:

```bash
composer validate --strict
php artisan migrate:fresh --seed --force
php artisan route:list
php artisan view:cache
php artisan test --filter=SeoDashboardTest
docker compose config --quiet
bash deploy/docker-deploy.sh
```
