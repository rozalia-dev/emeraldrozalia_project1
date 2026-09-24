#!/usr/bin/env bash
# Disposable GitHub runner only. This script creates and deletes test volumes.
set -Eeuo pipefail
test "${GITHUB_ACTIONS:-}" = true || { echo 'This script is for GitHub Actions only.' >&2; exit 78; }
cd "$(dirname "${BASH_SOURCE[0]}")/.."
cleanup() {
    status=$?
    if [ "$status" -ne 0 ]; then docker compose logs --tail=80 || true; fi
    docker compose --profile background down --volumes || true
    exit "$status"
}
trap cleanup EXIT

bash -n deploy/docker-deploy.sh
bash -n deploy/configure-host-nginx-upload.sh
sh -n deploy/docker-entrypoint.sh
cp .env.example .env
printf '\nAPP_KEY=base64:%s\nDB_PASSWORD=ci-only-password\n' "$(openssl rand -base64 32)" >> .env
mkdir -p public/storage

# Reproduce the source permissions that previously made Artisan unreadable.
git ls-files -z | xargs -0 chmod go-rwx
docker compose config --quiet
docker compose build app
docker compose run --rm --no-deps --user www-data app php artisan --version
docker compose run --rm --no-deps --user www-data app sh -ec 'test -L public/storage; test -d public/storage; test -r vendor/autoload.php; test -w storage/framework/views; test -w bootstrap/cache'
git ls-files -z | xargs -0 chmod go+rX

# Initialize a disposable installation, then rehearse an ordinary release.
docker compose up -d --no-build --wait --wait-timeout 180 db redis app
docker compose exec -T --user www-data app php artisan migrate --force
docker compose up -d --no-build --wait --wait-timeout 180 nginx
docker compose exec -T nginx nginx -T 2>&1 | grep -q 'client_max_body_size 1100M;'
docker compose exec -T nginx nginx -T 2>&1 | grep -q 'fastcgi_read_timeout 900s;'
docker compose exec -T app php -r 'exit(ini_get("upload_max_filesize") === "1024M" && ini_get("post_max_size") === "1100M" ? 0 : 1);'
DEPLOY_BACKUP_DIR="$RUNNER_TEMP/emerald-backups" DEPLOY_HEALTHCHECK_URL=http://127.0.0.1:8080/up bash deploy/docker-deploy.sh
sleep 10
for service in worker scheduler; do
    test -n "$(docker compose ps --status running -q "$service")"
done
