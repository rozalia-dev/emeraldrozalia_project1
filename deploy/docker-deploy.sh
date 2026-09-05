#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

compose() {
    COMPOSE_BAKE=false docker compose "$@"
}

on_error() {
    status=$?
    echo "Deployment failed with status ${status}. Container state and recent logs:" >&2
    compose ps >&2 || true
    compose logs --tail=120 app nginx db >&2 || true
    exit "$status"
}
trap on_error ERR

test -f .env || { echo "Missing .env in ${ROOT_DIR}." >&2; exit 78; }
command -v docker >/dev/null 2>&1 || { echo "Docker is required on the server." >&2; exit 127; }
docker compose version >/dev/null

if ! grep -Eq '^APP_KEY=(base64:)?[^[:space:]]+' .env; then
    echo "APP_KEY is empty in .env. Generate it before deployment." >&2
    exit 78
fi

echo "Validating production Compose configuration..."
compose config --quiet

echo "Building the application image..."
compose build app

echo "Starting application services..."
compose up -d

echo "Waiting for PostgreSQL..."
for attempt in $(seq 1 30); do
    if compose exec -T db pg_isready -U "${POSTGRES_USER:-emerald_rozalia}" -d "${POSTGRES_DB:-emerald_rozalia}" >/dev/null 2>&1; then
        break
    fi
    if [ "$attempt" -eq 30 ]; then
        echo "PostgreSQL did not become ready." >&2
        exit 1
    fi
    sleep 2
done

echo "Running database migrations..."
compose exec -T app php artisan migrate --force --no-ansi
compose exec -T app php artisan storage:link --no-ansi || true
compose exec -T app php artisan optimize:clear --no-ansi
compose exec -T app php artisan optimize --no-ansi

echo "Checking the public health endpoint..."
HEALTHCHECK_URL="${DEPLOY_HEALTHCHECK_URL:-https://emeraldrozalia.com/up}"
curl --fail --retry 10 --retry-all-errors --silent --show-error "$HEALTHCHECK_URL" >/dev/null

compose ps
echo "Emerald Rozalia production deployment complete."
