# Homepage evidence (P2.5)

The homepage has been rebuilt as a dedicated, reference-ordered composition rather than a generic page template:

1. hero message and Virtual Try-On placement
2. five-value benefits band
3. six collection cards
4. Irish Heritage feature
5. Bestseller product row
6. manufacturing quality feature
7. Ireland franchise CTA
8. shared footer from the public shell

The available source archive contains screenshot references only. It does not contain the original hero, collection, manufacturing or franchise photography, so those visual slots are marked as pending approved source assets. The supplied screenshots are not reused as production artwork.

## Verification

The homepage view compiles successfully and the feature suite verifies the section sequence:

```text
& 'C:\Users\newuser\.config\herd\bin\php84\php.exe' artisan view:cache
& 'C:\Users\newuser\.config\herd\bin\php84\php.exe' artisan test
```

Result on 2026-09-02: **10 passed (64 assertions)** after adding the homepage acceptance check.

Pixel comparison remains open until the original image archive is supplied and browser screenshot binaries are available for the deterministic visual suite.
