# picture_base

## FTP deployment package

`subfolder/` is the complete production package. Copy that folder as-is to
`./public/subfolder` on the FTP server; Apache must provide PHP 8.2+ and allow
`.htaccess` overrides with `mod_rewrite` enabled. The package includes its
front controller, assets, source, package-local autoload bootstrap, and bundled
picture data.
It does not depend on files outside the folder, and its links detect the
installed URL base at runtime.

To deploy the package over SFTP, fill in the local, git-ignored `.env` file and
install `lftp` and run:

```sh
./deploy.sh
```

`SFTP_REMOTE_PATH` must be an existing directory dedicated to this package.
The script reconciles that directory with the contents of `subfolder/`,
including `.htaccess`: files and directories that exist remotely but not
locally are deleted. The SFTP client prompts for the account password when key
authentication does not succeed. Do not point `SFTP_REMOTE_PATH` at a home or
shared directory.

Composer is local development tooling only. Install the root development
dependencies from the repository root:

```sh
composer install --no-interaction --no-progress
```

The package's `.htaccess` disables indexes, routes application paths to
`index.php`, and denies direct access to source, dependencies, picture data,
dotfiles, the package bootstrap, and Composer metadata if accidentally added.

## Runtime prerequisites

- Docker Engine with the `docker compose` plugin

The local demo uses the official PHP `8.2.32-fpm-bookworm` runtime behind
Apache `2.4.65`, matching the deployment server's PHP version and FPM/FastCGI
SAPI. Both image references are digest-pinned in `Dockerfile`. The local-only
`/phpinfo.php` route exposes the resulting PHP configuration for comparison
with `deploy_server_phpinfo.html`.

## Picture folder contract

By default, Compose mounts the repository-relative `./subfolder/pictures`
folder. To use
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
only on localhost at port 8080 by default. Set `HOST_PORT` to use another local
port.

```sh
docker compose up -d --build
```

Open `http://127.0.0.1:8080/hed/` to view the picture overview, or use
`http://127.0.0.1:8080/hed/picture/example` for the bundled fixture. Use
`http://127.0.0.1:8080/phpinfo.php` to inspect the local PHP runtime. For an
explicit picture root, run Compose with an override such as
`PICTURES_HOST_PATH=/absolute/path/to/pictures docker compose up -d --build`,
then use `/hed/picture/<id>`. The stable media route is `/hed/media/<id>`. The
application is read-only: do not expect uploads, editing, or search functionality.

## Verification and teardown

The verification script creates temporary external fixtures (including invalid
and symlink cases), checks the default `./subfolder/pictures` fixture and sample routes,
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
