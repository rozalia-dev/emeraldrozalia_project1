#!/usr/bin/env bash
set -euo pipefail
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

test -f .env || { echo "Missing .env in ${ROOT_DIR}." >&2; exit 78; }
command -v php >/dev/null 2>&1 || { echo "PHP is required." >&2; exit 127; }
command -v composer >/dev/null 2>&1 || { echo "Composer is required." >&2; exit 127; }
grep -Eq '^APP_KEY=(base64:)?[^[:space:]]+' .env || { echo "APP_KEY is empty in .env." >&2; exit 78; }

composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
php artisan optimize:clear
php artisan migrate --force
php artisan storage:link || true
php artisan optimize
php artisan queue:restart || true
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
echo "Emerald Rozalia Laravel 13 deployment complete."
