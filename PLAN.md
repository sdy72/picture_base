# Picture Base — Project Plan and Status

**Status date:** 2026-07-20

## Goal and fixed folder contract

Build a small, framework-free, read-only picture browser over a configured
picture root. Each immediate child directory is one picture entry:

```text
PICTURES_ROOT/
└── <id>/
    ├── picture.jpg OR picture.png
    └── text.txt
```

The ID is the directory name. The contract requires exactly one valid image
with its matching lowercase filename and a UTF-8 `text.txt`; malformed or
unsafe entries are skipped rather than changing the contract.

## Approved defaults

- IDs match `[A-Za-z0-9_-]+` and are limited to 128 characters.
- Filenames are exact and lowercase: `picture.jpg`, `picture.png`, and
  `text.txt`.
- Skip invalid entries and entries containing both image files.
- Numeric IDs sort numerically; all other IDs sort lexically.
- UTF-8 plain text is rendered unchanged and safely.
- Stable routes are `/picture/{id}` for the page and `/media/{id}` for the
  image.
- Use bounded client-side zoom; initially serve original images and lazy-load
  them.
- Compose exposes the service on localhost and mounts the picture root
  read-only.
- Out of scope: authentication, uploads, editing, search, and deployment.

## Phase plan

### P1 — Catalog and domain model

**Deliverables:** ID validation, picture entry value object, safe catalog
discovery, deterministic sorting, and focused PHPUnit coverage.

**Acceptance:** The catalog accepts only the fixed folder contract, safely
rejects traversal/symlink and malformed data, preserves valid UTF-8 text,
skips invalid or ambiguous entries, and returns the approved sort order.

### P2 — HTTP routes and rendering

**Deliverables:** Framework-free request handling and HTML rendering for
`/picture/{id}`, plus safe image delivery at `/media/{id}`.

**Acceptance:** Valid entries render through the stable routes; missing or
invalid IDs return a safe not-found response; text is escaped/rendered safely
without altering its content; media cannot escape the configured root.

### P3 — Browser presentation

**Deliverables:** Initial browser UI using original images, lazy loading, and
bounded client-side zoom.

**Acceptance:** The UI displays catalog entries in approved order, zoom cannot
exceed its defined client-side bounds, and no upload/edit/search behavior is
introduced.

### P4 — Compose packaging and operational verification

**Deliverables:** Reproducible Compose setup with a localhost-only listener and
read-only picture-root mount, plus runtime documentation/configuration as
needed.

**Acceptance:** The stack builds and serves the browser, the mounted picture
root is not writable by the app, access is localhost-only, and Docker checks
confirm the P1–P3 behavior. Docker verification is still pending; no Compose
configuration exists yet.

## Current progress

| Phase | Implementation | Verification | Review | Status |
|---|---|---|---|---|
| P1 | **Implemented** — implementation is complete and files are present in the working tree | **Blocked** — PHP 8.5.4 and required extensions are available, but Composer is unavailable in the active PATH | **Not done** | Resume verification |
| P2 | Not started | Not started | Not started | Not started |
| P3 | Not started | Not started | Not started | Not started |
| P4 | Not started | Not started | Not started | Not started |

P1 implementation files present:

- `composer.json`
- `src/PictureBrowser/PictureId.php`
- `src/PictureBrowser/PictureEntry.php`
- `src/PictureBrowser/PictureCatalog.php`
- `tests/PictureBrowser/PictureCatalogTest.php`

`git diff --check` passed. PHP 8.5.4 is available, and `ext-dom`/XML plus
`ext-mbstring` are installed and working. Composer is unavailable in the
active PATH, so `composer install`, PHPUnit, and the complete P1 verification
sequence remain blocked. The P1 reviewer pass has not happened. The in-root
symlink behavior remains a reviewer-disposition item. P2, P3, and P4 are not
started.

The `docker compose` CLI works (`Docker 29.6.2` / `Compose v5.3.1`), but
Docker daemon access is blocked by permission denied on
`/var/run/docker.sock`. No Compose configuration exists yet, and P4 remains
not started.

## Blocker and exact resume steps

**Current blocker:** P1 verification and review cannot proceed because Composer
is unavailable in the active PATH. PHP 8.5.4 and the required extensions are
available, so this is an environment blocker rather than an implementation
decision. Docker daemon access is separately blocked and remains a P4
requirement.

1. After reboot, confirm PHP 8.5.4 and its working `ext-dom`/XML and
   `ext-mbstring` extensions, and make Composer available in the active PATH.
2. From the repository root, run:

   ```sh
   php --version
   composer --version
   composer validate --strict
   composer install
   php -l src/PictureBrowser/PictureId.php
   php -l src/PictureBrowser/PictureEntry.php
   php -l src/PictureBrowser/PictureCatalog.php
   php -l tests/PictureBrowser/PictureCatalogTest.php
   vendor/bin/phpunit tests/PictureBrowser/PictureCatalogTest.php
   git diff --check
   ```

3. After the focused checks pass, run the P1 reviewer pass. Resolve review
   findings, including the in-root symlink behavior disposition, before
   starting P2; do not mark P1 verified until both checks and review pass.
4. Before any commit, use the user commit gate. Enable Docker before P4 and
   create the approved Compose configuration, then run the Compose build/start,
   localhost binding, read-only mount, and smoke-verification checks.

## Workflow gates

- After every implementation phase, require a reviewer pass.
- After reviewer approval, stop for the user commit gate.
- If a phase needs revisions, stop after two revision cycles if it still does
  not pass review and escalate rather than silently proceeding.

## Resume checklist

- [x] PHP 8.5.4 and `ext-dom`/XML plus `ext-mbstring` installed and working.
- [ ] Composer available in the active PATH.
- [ ] `composer validate --strict` passes.
- [ ] `composer install` completes.
- [ ] P1 syntax checks and `vendor/bin/phpunit tests/PictureBrowser/PictureCatalogTest.php` pass.
- [ ] P1 reviewer resolves the in-root symlink behavior and approves; then obtain the user commit decision.
- [ ] Implement and review P2, then P3, with the same gates.
- [ ] Verify Docker/Compose localhost-only, read-only behavior in P4.
