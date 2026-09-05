#!/bin/sh
set -eu

cd /var/www/html

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is required before the production container can start." >&2
    echo "Generate it outside the container with: php artisan key:generate --show" >&2
    exit 78
fi

if [ "${APP_ENV:-production}" = "production" ] && [ "${APP_DEBUG:-false}" = "true" ]; then
    echo "APP_DEBUG must be false in production." >&2
    exit 78
fi

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

exec "$@"
