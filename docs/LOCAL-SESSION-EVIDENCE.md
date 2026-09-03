# Local session/CSRF evidence

The local Herd URL uses plain HTTP. `config/session.php` now defaults `SESSION_SECURE_COOKIE` to `true` only when `APP_ENV=production`; an explicit environment value still overrides it.

Verified locally:

- `config('session.secure')` resolves to `false` with `APP_ENV=local`.
- Login form receives and returns its session-backed CSRF token.
- Configured local admin login reaches `/admin` with HTTP 200.
- Existing Laravel tests remain green: 4 tests, 20 assertions.

Production must set `SESSION_SECURE_COOKIE=true` while served over HTTPS.
