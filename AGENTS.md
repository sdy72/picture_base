# Agent Guide

## Repository Shape

- This is a framework-free PHP application. `public/index.php` is the front controller; Apache serves existing files under `public/` and rewrites application routes to it via `docker/apache-vhost.conf`.
- Runtime code is PSR-4 mapped from `PictureBrowser\` to `src/PictureBrowser/`; tests are under `tests/PictureBrowser/` and use the same Composer autoloader.
- `PictureCatalog` reads `PICTURES_ROOT`. Compose maps `${PICTURES_HOST_PATH:-./pictures}` to `/pictures` and makes that mount read-only; do not hard-code a host path or treat the picture tree as writable.
- The stable application routes are `/`, `/picture/<id>`, and `/media/<id>`. The application is intentionally read-only; uploads, editing, and search are out of scope.
- A picture entry must be an immediate child directory whose ID matches `[A-Za-z0-9_-]+` and is at most 128 characters, contains exactly one valid lowercase `picture.jpg` or `picture.png`, and contains UTF-8 `text.txt`. Invalid, ambiguous, traversal, and symlink entries are skipped. Numeric IDs sort numerically before other IDs, which sort lexically.
- `PLAN.md` contains stale phase/status claims (including that Compose is absent); use the current source, `README.md`, `composer.json`, `Dockerfile`, `compose.yaml`, and `scripts/verify-compose.sh` as the operational sources of truth.

## Commands

- From the repository root, validate metadata and install the locked dependencies with `composer validate --strict` and `composer install`.
- Run the full PHPUnit suite with `composer test` or `composer test:unit`. Run a focused suite with `vendor/bin/phpunit tests/PictureBrowser/ApplicationTest.php` or `vendor/bin/phpunit tests/PictureBrowser/PictureCatalogTest.php`.
- For a PHP syntax-only check, run `php -l` on each changed PHP file; there is no configured formatter, linter, typechecker, CI workflow, or pre-commit hook.
- Run the Docker/HTTP integration check with `./scripts/verify-compose.sh` from the repository root. It requires Docker Compose, a running Docker daemon, and `curl`; it builds with no cache, uses host port `8080`, creates temporary fixtures, and cleans up its project and fixtures.
- For manual local runtime checks, use `docker compose up -d --build`, optionally setting `PICTURES_HOST_PATH=/absolute/path/to/pictures`; tear it down with `docker compose down --remove-orphans`.

## Change Constraints

- Keep `composer.lock` in sync with dependency changes and preserve digest-pinned base images in `Dockerfile`; the Compose verification script checks both properties.
- When changing routes, rendering, or media delivery, preserve the front-controller rewrite and re-run the focused PHPUnit suite plus `./scripts/verify-compose.sh` when Docker is available.
