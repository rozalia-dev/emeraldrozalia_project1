# Deployment configuration evidence

Task P1.6 aligns the deployment configuration without adding secrets.

Implemented:

- `.env.example` now uses the same PostgreSQL username as the Docker database service: `emerald_rozalia`.
- Docker app startup waits for healthy PostgreSQL and Redis services rather than only container start order.
- Redis has an explicit `redis-cli ping` health check.
- The deployment runbook documents the shared least-privilege database username.

The local Herd runtime remains MySQL/HTTP through `.env`; production and CI remain PostgreSQL/HTTPS. Docker/PostgreSQL execution is not claimed locally because Docker is unavailable in this environment.
