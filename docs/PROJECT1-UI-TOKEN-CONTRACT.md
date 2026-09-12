# Project 1 UI token and dynamic styling contract

**Baseline:** c54e933e36c68f2951e5e05afb32119ea02ea467
**Purpose:** define one visual contract for consistent sizing/spacing and cPanel-controlled dynamic styling. This Batch 0 document records the contract; it does not claim that the measurements or runtime theme publisher are complete.

## Source-of-truth rule

Approved reference images define the visual source. A versioned published theme/content state in the cPanel is the runtime source for permitted dynamic values. Public and admin consumers must read the same published version for a given tenant/company and locale. A local page override may not silently replace the shared token contract.

## Token groups

| Group | Required tokens/controls | Consumers | Current state |
|---|---|---|---|
| Brand | exact logo/wordmark asset, approved green/neutral palette, slogan, footer contact visibility | Public header/footer, admin shell/footer, media/content previews | Partial; two brand PNGs fail image verification and layout uses multiple assets |
| Typography | font family/source, size scale, weight scale, line-height, letter spacing, minimum submenu/control copy | Public pages, cPanel, tables, forms, status labels | Partial; Inter/Arial/UI-sans mix and late global !important rules remain |
| Layout | content max width, sidebar width, top-bar height, canvas padding, grid columns, right rail rules | Public and all admin reference screens | Unverified; current CSS includes 245px base and 208px override |
| Spacing | named spacing scale for section, card, field, table and footer gaps | Shared components and page-specific compositions | Unverified; feature CSS uses different paddings/gaps |
| Surfaces | card/background/border/radius/shadow tokens | Cards, tables, dialogs, empty/error states | Unverified; page-specific values are mixed |
| Controls | input height, button height, icon size, focus ring, disabled/loading states | Forms, filters, actions, navigation | Partial; behavior and keyboard evidence incomplete |
| Data display | table density, numeric alignment, currency/date/empty-state formats | Orders, reports, customers, franchise and communication | Partial; report money/metric contracts remain open |
| Responsive | desktop/tablet/mobile breakpoints, overflow, navigation collapse, touch target | Public and admin layouts | Partial; only limited foundation visual/browser coverage exists |
| Theme publishing | draft/review/publish/rollback version, tenant/company/locale scope | cPanel settings → public/admin consumers | Not found as one verified end-to-end contract |

## Required geometry evidence

The current source and references include these distinct canvases:

- Public foundation visual tests: desktop 1536×1024 and mobile 390×844.
- cPanel, Reports, Orders and Settings references: commonly 1536×1024.
- Customer/Banners/Returns references include 1402×1122.
- Several public portrait references are 1024×1536; their browser viewport and page-height behavior must be recorded rather than assumed.

The reference images are not all the same canvas. Do not force one fixed CSS rule across every visual.

## Dynamic styling policy

1. Settings changes are stored as a draft under the active company/tenant and locale.
2. The change is validated, authorized and audited.
3. A publish action creates an immutable version with a release identifier.
4. Public/admin requests resolve the latest published version for the current context.
5. Cache invalidation references that version; rollback selects a previous published version.
6. Screenshot/browser evidence records the theme/content version used.
7. Unauthorized changes return forbidden and create no mutation.

## Non-negotiable visual rules

- Preserve the exact Emerald Rozalia logo; never add a shield or substitute artwork.
- Keep public contact information in the footer only.
- Keep the public navigation order and header utilities locked.
- Keep Try-On in hero/product experience, not the header.
- Keep Limerick’s global-network marker in Ireland.
- Remove conflicting late global overrides before visual acceptance.
- Do not bless a Playwright snapshot merely because the page renders.
- Every visual claim needs a reference hash, viewport, browser/font/assets, screenshot result and reviewer/evidence ID.
