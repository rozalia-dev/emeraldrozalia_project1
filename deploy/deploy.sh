#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
php artisan migrate --force
php artisan storage:link || true
php artisan optimize
php artisan queue:restart || true
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
echo "Emerald Rozalia Laravel 13 deployment complete."
