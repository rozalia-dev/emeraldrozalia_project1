# Homepage evidence (P2.5)

The homepage is a dedicated, reference-ordered composition rather than a generic page template. The supplied full-page artwork and standalone hero artwork are registered as approved baseline media during deployment. They are delivered through the controlled UUID media route and used only when a Page Manager section has no selected media UUID. A selected approved UUID always takes precedence while Laravel keeps links, product data and the Try-On destination functional:

1. hero message and Virtual Try-On placement
2. five-value benefits band
3. six collection cards
4. Irish Heritage feature
5. Bestseller product row
6. manufacturing quality feature
7. Ireland franchise CTA
8. shared footer from the public shell

The homepage visual pass now includes the hero campaign, collection imagery, heritage collage, bestseller imagery, manufacturing strip and franchise visual treatment from the supplied approved reference. The homepage remains data-aware: approved product media takes precedence when available, with the approved reference crops providing the branded visual baseline when catalogue media is empty.

## Shared shell alignment

The homepage, Contact page, catalog pages, and standard public pages render the same `layouts.site` shell. The attached slim header geometry is now the shared desktop header: 104px tall, approved horizontal wordmark, locked eight-item navigation, and search/account/cart utilities. The active navigation item is route-aware. Contact Us remains footer-only. Header and footer regions are marked as shared shell regions so regression tests can compare them directly. The `factory-reference` presentation remains an intentional approved-artwork exception because its page is a single interactive factory canvas with its own artwork hotspots.

The cPanel uses the same centralized `<x-icon>` SVG component for navigation and utility actions. The public templates contain no Unicode/emoji icon glyphs; arrows, account, cart, social, upload, media and status affordances are SVG paths from `resources/views/components/icon.blade.php`.

For the operator workflow, see [`docs/PUBLIC-MEDIA-EDITING.md`](PUBLIC-MEDIA-EDITING.md). It covers upload, alt text and focal points, approval, Page Manager UUID selection, replacement/version restore, and shared-layout activation.

## Verification

The expected verification commands are:

```text
& 'C:\Users\newuser\.config\herd\bin\php84\php.exe' artisan view:cache
& 'C:\Users\newuser\.config\herd\bin\php84\php.exe' artisan test
```

Static source verification for this shell pass is clean: standard public views resolve through the shared layout, the media manager link resolves to the approved workflow, icon names resolve through the centralized SVG component, and the approved reference assets decode. The current work environment does not include a PHP executable or Composer, so view compilation and the Laravel feature suite must be verified by branch CI.

Pixel comparison remains tied to the supplied 864×1821 reference and should be confirmed with the Playwright visual suite at the same viewport.
