#!/usr/bin/env bash
set -Eeuo pipefail
umask 022

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"
compose() { COMPOSE_BAKE=false docker compose "$@"; }
on_error() {
    status=$?
    echo "Deployment failed (status $status). Inspect the server before retrying; maintenance may remain enabled." >&2
    compose ps >&2 || true
    exit "$status"
}
trap on_error ERR

test -f .env || { echo 'Missing production .env.' >&2; exit 78; }
test -z "$(git status --porcelain --untracked-files=no)" || { echo 'Tracked server changes must be resolved first.' >&2; exit 78; }
docker compose version >/dev/null
exec 9> .git/emerald-deploy.lock
flock -n 9 || { echo 'Another release is running.' >&2; exit 75; }
chmod 600 .env
compose config --quiet

# Nginx mounts this directory below a read-only parent bind mount.
mkdir -p public/storage
chmod 755 public/storage

# Build before interrupting the running application. Never delete data volumes.
compose build --pull app
compose run --rm --no-deps --user www-data app php artisan --version
compose up -d --no-build --wait --wait-timeout 180 db redis

BACKUP_DIR="${DEPLOY_BACKUP_DIR:-/var/backups/emerald-rozalia}"
install -d -m 700 "$BACKUP_DIR"
RELEASE_ID="$(date -u +%Y%m%dT%H%M%SZ)-$(git rev-parse --short=12 HEAD)"
BACKUP="$BACKUP_DIR/$RELEASE_ID"
mkdir -m 700 "$BACKUP"

# Stop background writes and drain active jobs before taking the release backup.
compose stop worker scheduler
if [ -n "$(compose ps --status running -q app)" ]; then
    compose exec -T --user www-data app php artisan down --retry=60
fi
(umask 077; compose exec -T db pg_dump -U emerald_rozalia -d emerald_rozalia -Fc > "$BACKUP/database.dump")
test -s "$BACKUP/database.dump"
compose exec -T db pg_restore --list < "$BACKUP/database.dump" >/dev/null
git rev-parse HEAD > "$BACKUP/target-commit.txt"
printf '%s\n' "${DEPLOY_PREVIOUS_COMMIT:-unknown}" > "$BACKUP/previous-commit.txt"
echo "Database release backup saved in $BACKUP"

# Keep release backups bounded so repeated deployments cannot exhaust the host disk.
# The current release backup is retained, along with the four most recent older releases.
BACKUP_KEEP_COUNT="${DEPLOY_BACKUP_KEEP_COUNT:-5}"
case "$BACKUP_KEEP_COUNT" in
    ''|*[!0-9]*) echo "DEPLOY_BACKUP_KEEP_COUNT must be a non-negative integer." >&2; exit 78 ;;
esac
if [ "$BACKUP_KEEP_COUNT" -gt 0 ]; then
    find "$BACKUP_DIR" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' \
        | sort -nr \
        | tail -n +"$((BACKUP_KEEP_COUNT + 1))" \
        | cut -d' ' -f2- \
        | while IFS= read -r old_backup; do
            [ -n "$old_backup" ] || continue
            case "$old_backup" in
                "$BACKUP_DIR"/*) rm -rf -- "$old_backup" ;;
                *) echo "Refusing to prune unexpected backup path: $old_backup" >&2; exit 78 ;;
            esac
          done
fi

compose up -d --no-deps --no-build --force-recreate --wait --wait-timeout 180 app
compose exec -T --user root app sh -c 'mkdir -p storage/app/public storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache && chown -R www-data:www-data storage bootstrap/cache && chmod -R ug+rwX storage bootstrap/cache'
compose exec -T --user www-data app test -d public/storage
compose exec -T --user www-data app php artisan optimize:clear --no-ansi
compose exec -T --user www-data app php artisan migrate --force --no-ansi
compose exec -T --user www-data app php artisan optimize --no-ansi
compose exec -T --user www-data app php artisan up --no-ansi

# Recreate nginx so it resolves the recreated app container's address.
compose up -d --no-deps --no-build --force-recreate --wait --wait-timeout 180 nginx
compose up -d --no-deps --no-build --force-recreate worker scheduler
for service in worker scheduler; do
    test -n "$(compose ps --status running -q "$service")"
done
for url in http://127.0.0.1:8080/up "${DEPLOY_HEALTHCHECK_URL:-https://emeraldrozalia.com/up}"; do
    curl --fail --silent --show-error --connect-timeout 10 --max-time 30 --retry 6 --retry-all-errors --retry-delay 5 "$url" >/dev/null
done
# Verify database-backed public media routes after migrations and cache warming.
curl --fail --silent --show-error --connect-timeout 10 --max-time 30 http://127.0.0.1:8080/360-sitemap.xml >/dev/null
curl --fail --silent --show-error --connect-timeout 10 --max-time 30 http://127.0.0.1:8080/virtual-tryon >/dev/null
compose ps
echo "Production release $(git rev-parse HEAD) passed health checks."
