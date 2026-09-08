# Emerald Rozalia Project 1 — local, server and GitHub CI/CD runbook

This is the saved operational record for Project 1. It deliberately documents
connection names, paths and commands, but never stores private keys, passwords,
APP_KEY values or the contents of `.env`.

## Current verified release

The latest verified production release is commit `f9a3d5bbcb82e065e6869fb52478674db9182cf8`.
GitHub Actions run [34240664241](https://github.com/rozalia-dev/emeraldrozalia_project1/actions/runs/34240664241)
completed successfully on 2026-09-08:

| Job | Result |
| --- | --- |
| PostgreSQL validation | Passed: 48 tests, 471 assertions |
| Container and release validation | Passed: image permissions, storage link, migrations, nginx, backup rehearsal, worker and scheduler |
| Deploy to Hetzner | Passed: SSH fingerprint verification, exact-commit checkout, Docker release and internal/public health checks |

The workflow is triggered by a push to `main` or manually from Actions. Pull
requests run validation only. Production deployment is protected by the GitHub
`production` environment and its required secrets/reviewers.

## Project and branch connection

| Item | Value |
| --- | --- |
| GitHub repository | `rozalia-dev/emeraldrozalia_project1` |
| Production branch | `main` |
| Production checkout | `/var/www/emerald-rozalia` |
| Deployment account | `deploy` |
| Administrative account | `eradmin` with sudo; root SSH is disabled |
| Public domain | `https://emeraldrozalia.com` |
| Server address | `188.245.86.68` |
| Server OS | Ubuntu 26.04.1 LTS (Resolute) |
| Backup directory | `/var/backups/emerald-rozalia` |

The server repository must remain on `main`, have no tracked local changes, and
be able to fetch `origin/main` without an interactive Git credential prompt. The
deployment script uses fast-forward-only updates and refuses to deploy a commit
other than the exact GitHub Actions SHA being tested.

## Local development (Windows + Laravel Herd)

The local development baseline is Windows with Laravel Herd, PHP 8.4 and MySQL:

```text
APP_ENV=local
APP_URL=http://emeraldrozalia_project1.test
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=emerald_rozalia
```

Use a local `.env` derived from `.env.example`; keep it untracked and never copy
production PostgreSQL credentials into it. From the repository root:

```powershell
git switch main
git pull --ff-only origin main
composer install
php artisan key:generate
php artisan migrate:fresh --seed
php artisan storage:link
php artisan optimize:clear
php artisan serve
```

Herd may provide the host automatically at
`http://emeraldrozalia_project1.test`. If using the Docker stack locally instead,
set `DB_HOST=db`, `REDIS_HOST=redis`, and a development-only `DB_PASSWORD` in
`.env`, then run:

```powershell
docker compose config --quiet
docker compose up -d --build --wait --wait-timeout 180
docker compose exec --user www-data app php artisan migrate --force
docker compose exec --user www-data app php artisan optimize
```

The demo seeder is for a disposable local/CI database only. It is not part of a
production release and must not be used to reset a live administrator password.

Before pushing a change, run the checks available in the local environment:

```powershell
composer validate --strict
php artisan route:list
php artisan test
git diff --check
git status --short --branch
```

## Server foundation

The rebuilt host has a 76 GB disk (about 71 GB free at setup), 3.7 GiB RAM and
no swap. UFW allows only TCP 22, 80 and 443 inbound; outgoing traffic is allowed.
Docker Engine 29.8.0 and Docker Compose v5.5.1 are installed and the Docker
health check passed with `hello-world`.

The `deploy` account has no sudo access and belongs to the `docker` group. Its
Actions key is passphrase-protected and authorized with restricted SSH options.
The `eradmin` account is the only administrative login. Effective SSH settings
are public-key-only: root login, password authentication and keyboard-interactive
authentication are disabled. Do not reintroduce the former rescue-system
`ForceCommand`/session-gate configuration.

Useful read-only checks:

```bash
whoami
sudo -v
sudo ufw status verbose
docker version
docker compose version
sudo sshd -T | grep -E '^(permitrootlogin|passwordauthentication|kbdinteractiveauthentication|pubkeyauthentication|forcecommand) '
```

## Docker services and persistent data

`docker-compose.yml` defines:

| Service | Purpose | Exposure |
| --- | --- | --- |
| `app` | Laravel PHP-FPM 8.4 application | Docker network only, port 9000 |
| `nginx` | Static/PHP front controller | `127.0.0.1:8080` only |
| `db` | PostgreSQL 17 | Docker network only |
| `redis` | Redis 7 | Docker network only |
| `worker` | Database queue worker | Background profile |
| `scheduler` | Laravel scheduler | Background profile |

Named volumes are `pgsql`, `storage` and `public-assets`. Never use `docker
compose down --volumes` on production. Nginx mounts the repository read-only and
the public-assets volume at `public/storage`; the host directory
`/var/www/emerald-rozalia/public/storage` must exist before nginx starts.

The image normalizes source files to be readable by `www-data`, creates the
`public/storage` link, and keeps runtime write access limited to `storage` and
`bootstrap/cache`. This prevents the earlier unreadable `artisan` and missing
storage-mount failures.

## Host Nginx and HTTPS

Host Nginx listens on ports 80 and 443 for `emeraldrozalia.com` and proxies to
`http://127.0.0.1:8080`. The container itself is never exposed directly to the
Internet. Certbot manages the certificate under
`/etc/letsencrypt/live/emeraldrozalia.com/`; the renewal dry run passed. DNS A
record `emeraldrozalia.com` points to `188.245.86.68`; no AAAA record is currently
configured.

Checks:

```bash
sudo nginx -t
curl --fail --silent --show-error https://emeraldrozalia.com/up
sudo /snap/bin/certbot renew --dry-run
```

## GitHub Actions connection

Create the `production` environment and restrict deployments to `main`. The
workflow uses these environment secret names:

| Secret | Stored value |
| --- | --- |
| `SERVER_HOST` | `188.245.86.68` |
| `SERVER_USER` | `deploy` |
| `SERVER_SSH_KEY` | Complete private key for the local `emerald_actions_20260908` key |
| `SERVER_SSH_PASSPHRASE` | Passphrase for that key |
| `SERVER_FINGERPRINT` | Current server host-key fingerprint |

The fingerprint must be copied from the rebuilt server, with no key length,
username, spaces or trailing text. The SSH action may negotiate the ECDSA host
key, so obtain the value used by the action with:

```bash
sudo ssh-keygen -lf /etc/ssh/ssh_host_ecdsa_key.pub -E sha256 | awk '{print $2}'
```

If the server host keys or IP are changed, recalculate the fingerprint and update
the environment secret before rerunning Actions. Never remove fingerprint
verification to make a deployment pass.

The workflow pins `actions/checkout` and `appleboy/ssh-action` to reviewed commit
SHAs. The SSH step first verifies all five secrets are non-empty, then checks that
the server has fetched the exact workflow SHA. It refuses a superseded commit.

## Production release sequence

After the two validation jobs pass, `Deploy to Hetzner`:

1. Connects as `deploy` with the passphrase-protected key and host fingerprint.
2. Fetches `origin/main` and fast-forwards to the exact tested SHA.
3. Builds the application image before interrupting the current app.
4. Ensures `public/storage` exists and takes a PostgreSQL custom-format dump and
   public-upload archive under `/var/backups/emerald-rozalia/<timestamp>-<sha>`.
5. Stops worker/scheduler and enters Laravel maintenance mode.
6. Recreates `app`, fixes runtime ownership, runs migrations as `www-data`,
   clears/rebuilds caches, and exits maintenance mode.
7. Recreates nginx, starts worker/scheduler, and checks both
   `http://127.0.0.1:8080/up` and the public HTTPS `/up` endpoint.

This is a single-server deployment with a maintenance window. It does not seed
demo data, reset passwords, prune volumes, or claim zero downtime. A passing
health endpoint does not replace homepage, admin-login, checkout and queue
acceptance checks.

## Failure, rollback and rotation

If a release fails, inspect the printed backup directory and container state:

```bash
cd /var/www/emerald-rozalia
docker compose --profile background ps
docker compose logs --tail=120 app nginx db worker scheduler
```

Maintenance may remain enabled after a migration failure. Do not blindly run
`php artisan up`; first determine whether the database migration completed and
whether the previous code is compatible. Restore a selected database dump and
matching uploads only after stopping writes and reviewing migration compatibility.
Never run an automatic production migration rollback. The local release backup
is not an off-server backup policy; configure encrypted off-server copies,
retention and an isolated full restore drill.

Rotate the following independently when needed: server host keys, the Actions SSH
key/passphrase, GitHub environment secrets, the production database password,
administrator password, APP_KEY (only with a planned session/token impact), and
TLS certificates. Never paste any of these values into commits, issues or chat.

## Operating evidence and ownership

The server setup, Docker stack, nginx proxy, Certbot renewal and GitHub deployment
connection were manually verified on 2026-09-08. The latest automated evidence is
the successful [main workflow run 34240664241](https://github.com/rozalia-dev/emeraldrozalia_project1/actions/runs/34240664241).
Update this document whenever the server IP, OS, Docker versions, repository path,
SSH key, deployment action, ports, volumes or release procedure changes.

---

# Operator command card and disaster recovery

## Permanent workflow rule

Never edit, replace, rename, disable, or simplify files under `.github/workflows/`.
The current workflow files are verified production controls. This rule remains in
force during normal work, secret rotation, incidents, server recovery and Git
recovery. Add documentation only; do not change workflow logic.

## Windows local commands

~~~powershell
$Project = "$env:USERPROFILE\Herd\emeraldrozalia_project1"
Set-Location $Project
git status --short --branch
git fetch origin main
git switch main
git pull --ff-only origin main
composer install
php artisan key:generate
php artisan migrate:fresh --seed
php artisan storage:link
php artisan optimize:clear
php artisan serve
~~~

Local baseline: APP_ENV=local, APP_URL=http://emeraldrozalia_project1.test,
PHP 8.4 through Herd, MySQL at 127.0.0.1:3306 and database emerald_rozalia.
Never copy production .env, APP_KEY, passwords or tokens to local development.

Local validation:

~~~powershell
composer validate --strict
php artisan route:list
php artisan test
git diff --check
git status --short --branch
curl.exe --fail --silent --show-error http://emeraldrozalia_project1.test/up
~~~

Local dependency repair is safe:

~~~powershell
composer clear-cache
Remove-Item -Recurse -Force vendor
composer install
php artisan optimize:clear
~~~

## Windows SSH access

~~~powershell
$AdminKey = "$env:USERPROFILE\.ssh\emerald_admin_20260908"
$ActionsKey = "$env:USERPROFILE\.ssh\emerald_actions_20260908"
ssh -o IdentitiesOnly=yes -o PasswordAuthentication=no -i $AdminKey eradmin@188.245.86.68
ssh -T -o IdentitiesOnly=yes -o PasswordAuthentication=no -i $ActionsKey deploy@188.245.86.68 "whoami && docker version --format '{{.Server.Version}}' && docker compose version"
~~~

Expected users: eradmin is sudo administration; deploy is non-sudo deployment and
docker-group access; root SSH is disabled. Expected server tools are Docker Engine
29.8.0 and Docker Compose v5.5.1.

## Server health command card

~~~bash
whoami
sudo -v
sudo ufw status verbose
sudo systemctl is-active docker nginx
docker version
docker compose version
df -h /
cd /var/www/emerald-rozalia
git status --short --branch
docker compose config --quiet
docker compose ps
docker compose exec -T db pg_isready -U emerald_rozalia -d emerald_rozalia
docker compose exec -T redis redis-cli ping
curl --fail --silent --show-error http://127.0.0.1:8080/up
curl --fail --silent --show-error https://emeraldrozalia.com/up
~~~

Never run `docker system prune`, `docker volume prune` or `docker compose down --volumes`
on production. Never expose ports 5432, 6379, 9000 or 8080 publicly.

## GitHub connection

Repository: https://github.com/rozalia-dev/emeraldrozalia_project1
branch: main, project path: /var/www/emerald-rozalia.
Production environment secrets are SERVER_HOST, SERVER_USER, SERVER_SSH_KEY,
SERVER_SSH_PASSPHRASE and SERVER_FINGERPRINT. The fingerprint is the ECDSA host
fingerprint, obtained on the server with:

~~~bash
sudo ssh-keygen -lf /etc/ssh/ssh_host_ecdsa_key.pub -E sha256 | awk '{print $2}'
~~~

Keep only the SHA256:... value; never store key length, username or trailing text.
Do not remove fingerprint verification when troubleshooting.

Action monitoring from PowerShell:

~~~powershell
gh auth status
gh run list --repo rozalia-dev/emeraldrozalia_project1 --branch main --limit 10
gh run view --repo rozalia-dev/emeraldrozalia_project1 <RUN_ID> --log-failed
~~~

## Backup inventory

Each release backup is under /var/backups/emerald-rozalia/<UTC>-<commit>/ and
contains database.dump, uploads.tar.gz, previous-commit.txt and target-commit.txt.

~~~bash
sudo -iu deploy
cd /var/backups/emerald-rozalia
find . -maxdepth 2 -type f -printf '%p %s bytes\n' | sort
cd /var/www/emerald-rozalia
docker compose exec -T db pg_restore --list < /var/backups/emerald-rozalia/<RELEASE>/database.dump
~~~

Same-disk backups are not disaster-proof. Copy encrypted backups off-server and
test a full restore into an isolated database before relying on them.

## Recovery without rebuilding the server

### Container, daemon or reboot recovery

~~~bash
sudo systemctl enable --now docker
sudo systemctl enable --now nginx
cd /var/www/emerald-rozalia
docker compose up -d --remove-orphans --wait --wait-timeout 180
docker compose up -d --no-deps --no-build worker scheduler
docker compose ps
curl --fail --silent --show-error https://emeraldrozalia.com/up
~~~

### Dirty or stale Git checkout

~~~bash
cd /var/www/emerald-rozalia
git status --short --branch
git diff --binary > /var/backups/emerald-rozalia/manual-$(date -u +%Y%m%dT%H%M%SZ).patch
git fetch origin main
git log --oneline --decorate --max-count=10 --all
~~~

Never force-reset production. Preserve the patch and review tracked changes; the
deployment workflow intentionally refuses a dirty checkout.

### Failed deployment

~~~bash
cd /var/www/emerald-rozalia
docker compose --profile background ps
docker compose logs --tail=120 app nginx db worker scheduler
df -h /
git status --short --branch
~~~

Do not blindly run artisan up after a migration error. Inspect migration state and
backup first. Only after confirming the application is compatible:

~~~bash
docker compose exec -T --user www-data app php artisan up
~~~

Fix the secret, fingerprint, disk, Git or migration cause and rerun the same
validated workflow. Never edit `.github/workflows/` during an incident.

### Existing-server database restore (destructive)

Confirm the exact compatible release backup and obtain operator approval. This
restores data in place; it does not recreate the server:

~~~bash
cd /var/www/emerald-rozalia
docker compose stop worker scheduler
docker compose exec -T --user www-data app php artisan down --retry=60
docker compose exec -T db psql -U emerald_rozalia -d postgres -c 'DROP DATABASE emerald_rozalia WITH (FORCE);'
docker compose exec -T db psql -U emerald_rozalia -d postgres -c 'CREATE DATABASE emerald_rozalia OWNER emerald_rozalia;'
docker compose exec -T db pg_restore --clean --if-exists --no-owner -U emerald_rozalia -d emerald_rozalia < /var/backups/emerald-rozalia/<RELEASE>/database.dump
docker compose up -d --no-build --wait --wait-timeout 180 app nginx
docker compose exec -T --user www-data app php artisan optimize:clear
docker compose exec -T --user www-data app php artisan optimize
docker compose exec -T --user www-data app php artisan up
docker compose up -d --no-deps --no-build worker scheduler
~~~

### Public uploads restore

~~~bash
cd /var/www/emerald-rozalia
docker compose stop nginx worker scheduler
docker run --rm -i -v emerald-rozalia_public-assets:/restore alpine:3.20 sh -c 'rm -rf /restore/* && tar -xzf - -C /restore' < /var/backups/emerald-rozalia/<RELEASE>/uploads.tar.gz
docker compose up -d --no-build nginx worker scheduler
~~~

### Host crash or power loss

Reconnect as eradmin. Do not provision a new server. Check findmnt, df -h,
docker/nginx services, UFW, the existing project directory and Compose volumes.
Then use the container/daemon recovery commands above and validate database, Redis,
/up, homepage, admin login, worker and scheduler.

If the boot disk and all local data are destroyed, no-rebuild recovery is only
possible from a provider disk snapshot or encrypted off-server database/uploads
backup. That is why a restore drill is mandatory.

### Local computer failure

~~~powershell
git clone https://github.com/rozalia-dev/emeraldrozalia_project1.git
Set-Location .\emeraldrozalia_project1
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
~~~

Production .env, SSH keys and GitHub secrets are not recovered from Git. Restore
them from secure stores and rotate anything that may have been exposed.

## Permanent incident rules

1. Never change existing workflow files under .github/workflows/.
2. Never commit credentials, production .env, private keys or APP_KEY.
3. Never force-reset production or delete production Docker volumes.
4. Never run production migrate:fresh, db:wipe, demo seeders or automatic rollback.
5. Never disable SSH host-fingerprint verification.
6. Preserve logs and backups before destructive recovery.
7. Confirm compatible code/migrations before restoring data.
8. Record the incident, backup, commit SHA, commands and final checks.

