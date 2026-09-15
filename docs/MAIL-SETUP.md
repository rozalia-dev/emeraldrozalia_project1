# Emerald Rozalia transactional mail setup

Customer registration and password reset use Laravel transactional mail. The application sends a signed email-verification link when a customer registers and provides a resend action at `/email/verify`.

## Production `.env`

Configure the real SMTP provider on the server only. Do not commit credentials.

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=smtp.example-provider.com
MAIL_PORT=587
MAIL_USERNAME=your-smtp-username
MAIL_PASSWORD=your-smtp-password
MAIL_EHLO_DOMAIN=emeraldrozalia.com
MAIL_FROM_ADDRESS=no-reply@emeraldrozalia.com
MAIL_FROM_NAME="Emerald Rozalia"
```

For providers that require implicit TLS on port 465, use the provider's documented SMTP scheme/settings. For normal submission on port 587, Laravel/Symfony Mailer can negotiate STARTTLS when the SMTP server supports it.

`APP_URL` must remain the public HTTPS site URL so verification and reset links are generated for the correct host:

```dotenv
APP_URL=https://emeraldrozalia.com/
APP_FORCE_HTTPS=true
```

## Apply an `.env` change to the Docker deployment

The application containers receive `.env` through Docker Compose, so recreate the application processes after changing mail values:

```bash
cd /var/www/emerald-rozalia
docker compose up -d --no-deps --force-recreate app worker scheduler
docker compose exec -T --user www-data app php artisan optimize:clear
docker compose exec -T --user www-data app php artisan optimize
```

## Verify SMTP before customer testing

Send a live diagnostic email without displaying credentials:

```bash
docker compose exec -T --user www-data app php artisan er:mail-test your-address@example.com
```

Then create a fresh customer account. Expected flow:

1. Registration creates an unverified customer and sends the signed verification email.
2. The customer is sent to the verification screen rather than the account dashboard.
3. Account pages remain unavailable until the signed link is used.
4. The verification page can resend the message if needed.
5. The same SMTP transport is used for password-reset emails.

If `MAIL_MAILER=log` or `MAIL_MAILER=array`, no external customer email is delivered. Those values are for non-live testing only.
