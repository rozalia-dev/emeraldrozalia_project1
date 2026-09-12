# Project 1 Batch 5 — Public content contract evidence

**Scope:** public page rendering, canonical public navigation and Page Manager access behavior  
**Source basis:** Emerald Rozalia Project 1 Developer Guide, version 3, 519 pages; audit dated 12 September 2026  
**Status:** deployed on `main`; guide-wide completion is not claimed

## Release evidence

- PR #72 passed its PostgreSQL gate in [GitHub Actions run #296](https://github.com/rozalia-dev/emeraldrozalia_project1/actions/runs/34709149267) on the exact head `d932e07afdbb78ee44a1e801ce42042fd1d0bf91`.
- The merged `main` release is `ba2cefb111f0b3838acd1ba331ab9dedea1d042b`.
- The main validation, release rehearsal and Hetzner deployment all passed in [GitHub Actions run #297](https://github.com/rozalia-dev/emeraldrozalia_project1/actions/runs/34709314668).
- Production reported `Nothing to migrate`, saved `/var/backups/emerald-rozalia/20260912T175254Z-ba2cefb111f0`, and passed app, PostgreSQL, Redis and Nginx health checks.

## Implemented slice

- The public header now follows the locked eight-item order: Home, Shop, Collections, New Arrivals, Corporate Order, Bulk Order, Franchise Apply and Hiring Apply.
- Contact Us remains available in the footer and is not repeated in the primary header navigation.
- Language, Currency, Search, Login and Cart are exposed as named accessible utilities. Guest users are sent to the login route; signed-in users are sent to their account dashboard.
- Published Page Manager content is rendered through one shared controlled section partial used by both the storefront page and the admin preview.
- Hero, content, gallery, call-to-action and enquiry-form sections are supported. Text is escaped, links are restricted to relative/hash or HTTP(S) URLs, and invalid image schemes are ignored.
- The Page Manager builder exposes the settings needed by the supported block types, including gallery image paths, CTA links and enquiry type.
- Public page delivery now honours published/current-locale/schedule state plus `visibility` and `login_required` settings. Private pages return 404; authenticated-only pages redirect guests to login.
- Published pages marked `show_in_footer` are queried from `ContentPage` and appended to the footer without changing the locked primary navigation.
- Managed page SEO resolution excludes private pages and respects login-required access.

## Verification added

`tests/Feature/PublicContentContractTest.php` covers:

1. exact public header order and footer-only contact placement;
2. accessible utility labels and guest Login behavior;
3. footer-managed published pages;
4. typed section rendering, safe text escaping and rejected `javascript:`/`data:` media values;
5. private 404 behavior and authenticated-only page access.

`node --check public/js/app.js` passed locally. PHP, Laravel, database and browser execution remain GitHub Actions authority because PHP, Composer and Docker are not installed in this workspace.

## Deliberately open after Batch 5

- The 519-page guide still has 167 roadmap/build entries and is not fully implemented or visually accepted.
- The 167-reference visual acceptance set still lacks a complete ordered archive-to-screenshot diff, responsive browser suite, accessibility scan and pixel-approved result for every reference.
- Public Banner↔admin synchronization, admin Review↔public synchronization, settings propagation, full Page Manager lifecycle/bulk workflows, API/provider/queue contracts, direct MySQL lifecycle evidence and independent backup/restore evidence remain open.

This document records a real code/test slice; it is not a completion certificate for the Developer Guide or visual acceptance set.
