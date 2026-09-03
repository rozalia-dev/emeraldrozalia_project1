# Page Manager ParseError fix

Fixed `resources/views/admin/pages/index.blade.php` after Laravel reported:

```text
syntax error, unexpected token "endforeach"
```

The cause was compressed, same-line `@if/@else/@foreach` nesting in the actions cell. The view is now formatted with explicit Blade blocks, preserving the existing Page Manager actions and form fields.

Verification:

- `artisan view:cache` succeeds.
- Admin render regression test for `/admin/pages` passes.
- Full suite: **11 passed (66 assertions)** on 2026-09-02.
