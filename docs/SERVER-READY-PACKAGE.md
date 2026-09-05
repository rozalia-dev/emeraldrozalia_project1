# Server-ready package

This repository is prepared for a Linux Docker host with PostgreSQL 17, Redis 7, an external TLS reverse proxy and the public domain `https://emeraldrozalia.com`.

## First server setup

1. Install Docker Engine, Docker Compose v2, Git and `curl`.
2. Clone the repository into `/var/www/emerald-rozalia`.
3. Copy `.env.example` to `.env` and set `DB_PASSWORD`, `ADMIN_EMAIL`, `ADMIN_PASSWORD`, `APP_URL`, `BRAND_*` values and a strong database password. Keep every external `*_LIVE_ENABLED` value false until core acceptance is complete.
4. Generate the application key before the first container start:

   ```bash
   docker compose build app
   APP_KEY="$(docker compose run --rm --no-deps --entrypoint php app artisan key:generate --show --no-ansi | tail -n 1)"
   sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" .env
   ```

5. Start and seed the stack:

   ```bash
   docker compose up -d
   docker compose exec -T app php artisan migrate --seed --force
   docker compose exec -T app php artisan storage:link || true
   docker compose exec -T app php artisan optimize
   curl --fail http://127.0.0.1:8080/up
   ```

The Nginx container listens on `127.0.0.1:8080`. Put the host TLS proxy in front of it and forward `Host`, `X-Forwarded-For` and `X-Forwarded-Proto: https`. The application uses `TRUSTED_PROXIES=*` in the production example so Laravel generates secure HTTPS URLs behind that proxy.

## Repeatable deploy

From the checked-out release directory:

```bash
git pull --ff-only origin main
bash ./deploy/docker-deploy.sh
```

The script refuses an absent `.env` or `APP_KEY`, validates Compose, builds the app, starts healthy PostgreSQL/Redis services, runs migrations, refreshes Laravel caches and checks the public health URL. On any failure it prints the service state and the latest app, Nginx and database logs. GitHub Actions calls this script with `bash`, so a missing executable bit cannot cause the SSH deploy step to exit with status 126.

## Persistent data

- `pgsql`: PostgreSQL database data.
- `storage`: Laravel framework/session/log storage.
- `public-assets`: product and public upload files, mounted into both PHP and Nginx.

Back up PostgreSQL and the `public-assets` volume before a release. A restore drill is required before enabling live external integrations.

## Post-deploy acceptance

```bash
curl --fail https://emeraldrozalia.com/up
curl --fail https://emeraldrozalia.com/robots.txt
curl --fail https://emeraldrozalia.com/sitemap.xml
docker compose exec -T app php artisan route:list
docker compose exec -T app php artisan migrate:status
docker compose ps
```

Then verify login, the cPanel SEO & Content page, metadata save, SEO audit, broken-link check, sitemap generation, a product media upload, public storage delivery, queue worker and the six order-master routes. Do not enable payment, WhatsApp, email, shipping, social, hosted 360° or hosted try-on credentials until those checks pass.
