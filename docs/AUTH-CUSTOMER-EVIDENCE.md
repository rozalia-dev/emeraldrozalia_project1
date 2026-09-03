# P4.1 Customer authentication and dashboard evidence

## Scope

This checkpoint covers the customer-facing authentication boundary and dashboard:

- registration, login and logout;
- email-verification notice and resend state;
- password-reset request and reset form boundary;
- customer dashboard summary, order history links and account navigation;
- customer ownership checks for invoices and return requests.

The supplied references used for this pass were docs/new project image/customer login register page.png and docs/new project image/customer dashboard .png. The implementation uses the approved wordmark and the shared Emerald Rozalia visual tokens. The supplied reference photos are not used as production artwork because the original photo archive is not available in this workspace.

## Implementation

| Area | Route/file | Verified behaviour |
| --- | --- | --- |
| Login | GET /login, POST /login | Accessible labelled form, remember-me option, invalid-credential feedback, intended redirect, session regeneration and throttle:5,1. |
| Registration | GET /register, POST /register | Name/email/phone/password validation, unique email, hashed password, Registered event, session regeneration and throttle:5,10. |
| Email state | /email/verify, /email/verification-notification | Pending accounts remain usable in the dashboard with a visible resend action; resend endpoint remains throttled. |
| Password recovery | /forgot-password, /reset-password/{token} | Reset request has a generic response to prevent email enumeration; request and update endpoints are throttled. |
| Dashboard | GET /account | Responsive sidebar, account overview, KPI cards, recent orders, quick actions, rewards state and pending-verification alert. |
| Ownership | /account/orders/{order}/invoice, /account/orders/{order}/return | A customer can access only their own order documents and return/exchange actions. |

## Automated checks

Commands run with the local Herd PHP 8.4 runtime:

    php artisan view:cache
    php artisan test

Result: **21 tests passed, 170 assertions**.

The new feature coverage verifies:

- registration dispatches the email-verification event and stores a hashed password;
- an unverified customer sees the verification state and can request a resend;
- login rotates the session ID;
- auth/password routes carry explicit rate-limit middleware;
- password-reset requests do not reveal whether an address exists;
- invoice and return actions reject another customer's order with HTTP 403.

Browser screenshot comparison remains pending because Playwright browser binaries and the original visual asset archive are not available in the current local setup.
