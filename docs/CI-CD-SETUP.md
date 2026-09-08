# Production CI/CD setup

This workflow targets `/var/www/emerald-rozalia` on the existing server, using the
`deploy` account and Docker Compose plugin. Host nginx and Certbot remain managed
on the host. The public health URL is `https://emeraldrozalia.com/up`.

## GitHub configuration

Create the `production` environment in Settings → Environments. Restrict its
deployment branches to `main`. Enable a required reviewer if your GitHub plan
supports it. Keep deployment branches restricted to `main`. Deployment is controlled by the protected `production` environment and its required secrets/reviewers; no extra repository variable is required.

Set these **environment secrets** in `production`:

| Secret | Value |
| --- | --- |
| `SERVER_HOST` | `188.245.86.68` |
| `SERVER_USER` | `deploy` |
| `SERVER_SSH_KEY` | Complete private key from the Windows `emerald_actions_20260908` file, including BEGIN/END lines |
| `SERVER_SSH_PASSPHRASE` | Passphrase for that private key |
| `SERVER_FINGERPRINT` | Full current server ED25519 fingerprint, starting with `SHA256:` |

Obtain the host fingerprint from the trusted eradmin session:

```bash
sudo ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub -E sha256
```

Use only the fingerprint field, not the key length, username or `(ED25519)` suffix.
Do not use the former rescue system fingerprint. Paste secrets directly into
GitHub; do not put them in an issue, terminal transcript or chat. The deployment
account must already be able to fetch this repository noninteractively. Its
incoming Actions SSH key does not itself grant access to a private GitHub repo.

## Server preflight

Log in as deploy and run:

```bash
cd /var/www/emerald-rozalia
git status --short --branch
GIT_TERMINAL_PROMPT=0 git fetch origin main
docker compose version
docker compose config --quiet
stat -c '%a %U:%G %n' .env
curl --fail --silent --show-error https://emeraldrozalia.com/up
```

Preserve the existing `.env`, APP_KEY, database and upload volumes. Keep `.env`
mode 600, owned by deploy. Resolve tracked changes before deployment. The workflow
will not force-reset them. Review any local Compose override before enabling CI;
worker and scheduler are now defined in the repository and need no override.

## Release behavior

Pull requests run PostgreSQL tests and a container integration check. The latter
builds from restrictively permissioned source, runs Artisan as www-data, migrates
a disposable database, starts nginx, and exercises the release script and workers.
Only this disposable CI database is seeded/reset. Production releases never seed,
reset passwords, run migrate:fresh or delete volumes.

After both checks pass on main, the deployment authenticates the host,
fetches main, rejects a superseded workflow, and fast-forwards to the exact tested
commit. It builds on the server, checks Artisan permissions, stops background
services, puts the existing app into maintenance, saves a PostgreSQL custom-format
dump and public uploads, and validates that the dump index can be read. It then
recreates the app, fixes runtime directory ownership, migrates, caches, restores
traffic, recreates nginx, starts the worker and scheduler, and checks internal and
public health URLs. A file lock and Actions concurrency serialize releases.

This is a single-server deployment with a maintenance window, not zero downtime.
nginx serves source assets from the checkout, so source files can change before
the new app starts. Dependencies and base image tags are rebuilt on the server;
the commit is exact but the image is not a promoted immutable CI artifact.

The scheduler process starts, but scheduled business tasks still require schedule
definitions in application code. A running worker is not proof that a real queued
business job has completed. Health checks do not replace storefront/admin testing.

## First run

1. Merge the reviewed change only after its CI checks pass.
2. Confirm the environment secrets and server preflight above.
3. Open Actions → Validate and deploy production → Run workflow, selecting main.
4. Approve the production environment if configured. Inspect both validation jobs
   and the deployment job; require all three to succeed.
5. Check HTTPS `/up`, the homepage and admin login in the browser. Inspect
   `docker compose --profile background ps` and worker/scheduler logs on the server.

For branch protection, require `PostgreSQL validation` and `Container and release
validation` before merging main. The deploy job must not be a PR-required check.

## Failure and recovery

A failure stops the script. Maintenance may remain enabled after a backup or
migration failure. Inspect `docker compose --profile background ps` and server
logs; do not blindly run `artisan up` after a failed migration. The release backup
directory is printed in the deployment output under `/var/backups/emerald-rozalia`.
It contains database.dump, uploads.tar.gz, and previous/target commit identifiers.
The previous commit is `unknown` for a manual invocation without the workflow.

Rollback is an operator decision: determine whether the previous code is compatible
with any applied migrations. If data restoration is necessary, stop writes, restore
the selected database and matching uploads, and redeploy compatible code. Never run
automatic migration rollback on production. This script does not certify restoration
merely because `pg_restore --list` succeeds. Perform a full restore drill into an
isolated database before accepting recovery readiness.

These are local release backups, not an off-server backup policy. Configure encrypted
off-server copies, retention and recovery testing separately; monitor disk space.

## Evidence

The operator reported successful production PostgreSQL migrations, all four original
containers healthy, HTTPS `/up` HTTP 200, nginx configuration validation, and a
successful Certbot renewal dry run on 2026-09-08. Those results predate this workflow.
CI execution and the first automated production release must be recorded separately;
they are not implied by the earlier manual deployment.
