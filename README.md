# picture_base

## Runtime prerequisites

- Docker Engine with the `docker compose` plugin

The image uses the official `php:8.5.4-apache-bookworm` runtime and Composer
2.9.5. Both image references are digest-pinned in `Dockerfile`; the build
installs from the committed `composer.lock` and generates the application
autoload files.

## Picture folder contract

By default, Compose mounts the repository-relative `./pictures` folder. To use
another existing folder, set `PICTURES_HOST_PATH` to its path. Each immediate
child directory is an ID matching `[A-Za-z0-9_-]+` and at most 128 characters:

```text
PICTURES_HOST_PATH/
└── <id>/
    ├── picture.jpg OR picture.png
    └── text.txt
```

The image filename is lowercase and exactly one of the two image names must be
present. `text.txt` must contain valid UTF-8. Invalid, ambiguous, and unsafe
entries are skipped. Numeric IDs sort numerically, followed by other IDs in
lexical order.

## Start and use

The host folder is mounted at `/pictures` with `PICTURES_ROOT=/pictures` and
`read_only: true`. The Apache service runs as `picture-browser` and publishes
only on localhost at port 8080.

```sh
docker compose up -d --build
```

Open `http://127.0.0.1:8080/` to view the first picture, or use
`http://127.0.0.1:8080/picture/example` for the bundled fixture. For an
explicit picture root, run Compose with an override such as
`PICTURES_HOST_PATH=/absolute/path/to/pictures docker compose up -d --build`,
then use `/picture/<id>`. The stable media route is `/media/<id>`. The
application is read-only: do not expect uploads, editing, or search functionality.

## Verification and teardown

The verification script creates temporary external fixtures (including invalid
and symlink cases), checks the default `./pictures` fixture and sample routes,
then builds the pinned image, starts Compose with an explicit fixture, checks
the localhost binding and read-only mount, exercises the HTTP routes/media/assets,
and removes its containers and temporary fixtures on exit:

```sh
./scripts/verify-compose.sh
```

The service always uses the localhost-only host binding on port 8080. For a
manually started stack, inspect it with `docker compose ps` and tear it down with:

```sh
docker compose down --remove-orphans
```

The Docker/Apache process needs read and directory-traverse permission for the
host folder. The mount is deliberately read-only, so the host folder must not
be used as an application write location. On Docker Desktop, the folder must
also be shared with Docker.
