# Browser cache lifetime evidence

## Cache policy

| Asset | URL versioning | Cache lifetime |
| --- | --- | --- |
| CSS and JavaScript | A non-empty `?v=` query is the version key used by the page templates. | `public, max-age=31536000, immutable` |
| CSS and JavaScript without `v` | Revalidate on each reuse. | `public, max-age=0, must-revalidate` |
| Public website video, poster, and captions | The generated `v` key is derived from the video UUID, asset type, storage disk, and unique stored path. Replacing an uploaded file changes its stored path and URL. | Exact current version: `public, max-age=31536000, immutable`; unversioned or stale version: `public, max-age=300` |
| Private preview and download responses | No public cache key. | `private, no-store` |

Nginx serves CSS/JavaScript directly and keeps the existing security headers. Static cache headers are added only to successful responses. Video file delivery remains behind Laravel's public visibility checks and uses Symfony's file response, preserving range support.

## Automated checks

- `tests/Feature/VideoDashboardTest.php` checks one-year caching for versioned video, poster, and caption assets, five-minute caching for unversioned/stale video URLs, and a new key after replacing the video file.
- `deploy/ci-container.sh` sends HEAD requests through the Docker Nginx service and checks both the versioned and unversioned CSS cache headers.
- The PR validation run [#38156515982](https://github.com/rozalia-dev/emeraldrozalia_project1/actions/runs/38156515982) passed on code head `0d22bbf053e87f01410f45ebca5f8ceabd95f5ff`: PostgreSQL feature tests and migration rollback/re-run succeeded. The workflow skips its browser and container/release jobs on pull requests; the main-branch release workflow runs the Nginx container assertions before deployment.

A fresh Lighthouse run after deployment is still needed to record the new savings measurement.
