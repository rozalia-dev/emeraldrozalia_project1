# Foundation smoke-suite evidence

Task P1.7 expands `tests/Feature/ProjectScopeTest.php` to cover the foundation route surface.

The suite now verifies:

- public storefront, informational, auth, cart and `/up` health routes
- guest redirects for account, checkout and cPanel boundaries
- all six order-master routes
- invalid order-master rejection
- excluded Production, Finance, Payroll, POS and HR module rejection
- cart add/update/remove behavior
- two-company category isolation
- external services remain disabled by default

The suite is the foundation smoke gate; it does not replace browser screenshot, accessibility, PostgreSQL, or deployment tests.

## Verification

Command:

```text
& 'C:\Users\newuser\.config\herd\bin\php84\php.exe' artisan test
```

Result on 2026-09-02: **9 passed (56 assertions)**.
