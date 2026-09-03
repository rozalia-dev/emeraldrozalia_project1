# Homepage evidence (P2.5)

The homepage has been rebuilt as a dedicated, reference-ordered composition rather than a generic page template. The approved homepage reference supplied for this task is available as the public static asset `public/assets/brand/home-page-reference.png` and is used as the visual crop source for the live composition while Laravel keeps the links, product data and Try-On destination functional:

1. hero message and Virtual Try-On placement
2. five-value benefits band
3. six collection cards
4. Irish Heritage feature
5. Bestseller product row
6. manufacturing quality feature
7. Ireland franchise CTA
8. shared footer from the public shell

The homepage visual pass now includes the hero campaign, collection imagery, heritage collage, bestseller imagery, manufacturing strip and franchise visual treatment from the supplied approved reference. The reference is served directly from `public/` so the hero, exact wordmark crop, and all photographic panels do not depend on a Laravel route or a deploy-time `docs/` path. The homepage remains data-aware: seeded product media takes precedence when available, with the approved reference crops providing the branded visual baseline when catalogue media is empty.

## Verification

The expected verification commands are:

```text
& 'C:\Users\newuser\.config\herd\bin\php84\php.exe' artisan view:cache
& 'C:\Users\newuser\.config\herd\bin\php84\php.exe' artisan test
```

The current work environment does not include a PHP executable or Composer, so view compilation and the Laravel feature suite could not be re-run here. The latest GitHub `main` workflow was green before this homepage change; the homepage change must be validated by the next GitHub workflow run.

Pixel comparison remains tied to the supplied 864×1821 reference and should be confirmed with the Playwright visual suite at the same viewport.
