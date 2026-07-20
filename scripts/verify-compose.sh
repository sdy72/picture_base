#!/usr/bin/env bash

set -Eeuo pipefail

export LC_ALL=C.UTF-8

PROJECT="picture-browser-p4-$$"
PORT="${HOST_PORT:-8080}"
BASE_PATH='/hed'
WEB_SERVICE='picture-browser'
PHP_SERVICE='picture-browser-php'
REPOSITORY_ROOT="$(pwd)"
REPOSITORY_PICTURES_ROOT="$REPOSITORY_ROOT/subfolder/pictures"
PACKAGE_ROOT="$REPOSITORY_ROOT/subfolder"
FIXTURE_ROOT=''
OUTSIDE_ROOT=''
WORK_ROOT=''
COMPOSE_STARTED=false
CLEANUP_STATUS=0

fail() {
    printf 'P4 verification failed: %s\n' "$1" >&2
    exit 1
}

require_command() {
    command -v "$1" >/dev/null 2>&1 || fail "required command is missing: $1"
}

cleanup() {
    local status=$?

    if [[ "$COMPOSE_STARTED" == true ]]; then
        if ! docker compose -p "$PROJECT" down --volumes --remove-orphans >/dev/null 2>&1; then
            printf 'P4 cleanup failed: docker compose down\n' >&2
            CLEANUP_STATUS=1
        fi
    fi

    if [[ -n "$FIXTURE_ROOT" ]]; then
        rm -rf -- "$FIXTURE_ROOT"
    fi
    if [[ -n "$OUTSIDE_ROOT" ]]; then
        rm -rf -- "$OUTSIDE_ROOT"
    fi
    if [[ -n "$WORK_ROOT" ]]; then
        rm -rf -- "$WORK_ROOT"
    fi

    if (( status == 0 && CLEANUP_STATUS != 0 )); then
        exit "$CLEANUP_STATUS"
    fi
    exit "$status"
}

trap cleanup EXIT

require_command docker
require_command curl
require_command base64
require_command cmp
require_command mktemp
require_command sha256sum
require_command tar

docker compose version >/dev/null 2>&1 || fail 'docker compose is unavailable'
docker info >/dev/null 2>&1 || fail 'Docker daemon is unavailable'

[[ -f "$PACKAGE_ROOT/index.php" && -f "$PACKAGE_ROOT/.htaccess" ]] \
    || fail 'FTP package front controller or Apache rules are missing'
[[ -f "$PACKAGE_ROOT/bootstrap.php" ]] \
    || fail 'FTP package dependency-free bootstrap is missing'
[[ ! -e "$PACKAGE_ROOT/composer.json" && ! -e "$PACKAGE_ROOT/composer.lock" \
    && ! -e "$PACKAGE_ROOT/vendor" ]] \
    || fail 'FTP package contains local Composer artifacts'
[[ -d "$REPOSITORY_PICTURES_ROOT" ]] \
    || fail 'FTP package picture directory is missing'

PACKAGE_HTACCESS_CONTENTS="$(<"$PACKAGE_ROOT/.htaccess")"
[[ "$PACKAGE_HTACCESS_CONTENTS" == *'RewriteRule ^ index.php [END]'* \
    && "$PACKAGE_HTACCESS_CONTENTS" == *'RewriteRule ^(?:src|vendor|pictures)(?:/|$) - [F,END]'* \
    && "$PACKAGE_HTACCESS_CONTENTS" == *'RewriteRule ^bootstrap\.php$ - [F,END]'* ]] \
    || fail 'FTP package Apache rules do not route and protect the package'

FIXTURE_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/picture-browser-p4-fixture.XXXXXX")"
OUTSIDE_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/picture-browser-p4-outside.XXXXXX")"
WORK_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/picture-browser-p4-work.XXXXXX")"
export PICTURES_HOST_PATH="$FIXTURE_ROOT"

PNG_BASE64='iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
JPEG_BASE64='/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAH/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAEFAqf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/AX//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/AX//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAY/Aqf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/IV//2gAMAwEAAgADAAAAEP/EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQMBAT8Qf//EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQIBAT8Qf//EABQQAQAAAAAAAAAAAAAAAAAAABD/2gAIAQEAAT8Qf//Z'

write_png() {
    local path=$1
    printf '%s' "$PNG_BASE64" | base64 --decode > "$path"
}

write_jpeg() {
    local path=$1
    printf '%s' "$JPEG_BASE64" | base64 --decode > "$path"
}

make_png_entry() {
    local id=$1
    local text=$2
    mkdir -p "$FIXTURE_ROOT/$id"
    write_png "$FIXTURE_ROOT/$id/picture.png"
    printf '%s' "$text" > "$FIXTURE_ROOT/$id/text.txt"
}

make_jpeg_entry() {
    local id=$1
    local text=$2
    mkdir -p "$FIXTURE_ROOT/$id"
    write_jpeg "$FIXTURE_ROOT/$id/picture.jpg"
    printf '%s' "$text" > "$FIXTURE_ROOT/$id/text.txt"
}

make_png_entry '01' 'leading zero'
ESCAPED_TEXT="$(printf '%s\n%s' '<script title="quoted">Zażółć '\''gęślą'\''</script>' 'next')"
make_png_entry '2' "$ESCAPED_TEXT"
make_png_entry '10' 'ten'
make_png_entry 'Beta' 'uppercase lexical'
make_png_entry 'alpha' 'alpha lexical'
make_png_entry 'alpha-2' 'alpha suffix lexical'
make_jpeg_entry 'jpeg_1' 'jpeg'
make_png_entry 'safe_1' 'safe'

mkdir -p "$FIXTURE_ROOT/ambiguous" "$FIXTURE_ROOT/invalid-image" "$FIXTURE_ROOT/invalid-utf8" "$FIXTURE_ROOT/wrong-case"
write_png "$FIXTURE_ROOT/ambiguous/picture.png"
write_jpeg "$FIXTURE_ROOT/ambiguous/picture.jpg"
printf '%s' 'ambiguous' > "$FIXTURE_ROOT/ambiguous/text.txt"
printf '%s' 'not an image' > "$FIXTURE_ROOT/invalid-image/picture.png"
printf '%s' 'invalid image' > "$FIXTURE_ROOT/invalid-image/text.txt"
write_png "$FIXTURE_ROOT/invalid-utf8/picture.png"
printf 'invalid \2611' > "$FIXTURE_ROOT/invalid-utf8/text.txt"
write_png "$FIXTURE_ROOT/wrong-case/Picture.png"
printf '%s' 'wrong case' > "$FIXTURE_ROOT/wrong-case/text.txt"
mkdir -p "$FIXTURE_ROOT/bad.dot"
write_png "$FIXTURE_ROOT/bad.dot/picture.png"
printf '%s' 'invalid id' > "$FIXTURE_ROOT/bad.dot/text.txt"

mkdir -p "$OUTSIDE_ROOT/escaped-directory" "$FIXTURE_ROOT/link-file"
write_png "$OUTSIDE_ROOT/escaped-directory/picture.png"
printf '%s' 'outside directory' > "$OUTSIDE_ROOT/escaped-directory/text.txt"
write_png "$OUTSIDE_ROOT/outside.png"
printf '%s' 'link file' > "$FIXTURE_ROOT/link-file/text.txt"
ln -s "$OUTSIDE_ROOT/escaped-directory" "$FIXTURE_ROOT/link-dir"
ln -s "$OUTSIDE_ROOT/outside.png" "$FIXTURE_ROOT/link-file/picture.png"

chmod -R a+rwX "$FIXTURE_ROOT"

DOCKERFILE_CONTENTS="$(<Dockerfile)"
COMPOSE_CONTENTS="$(<compose.yaml)"
APACHE_CONTENTS="$(<docker/apache-vhost.conf)"
[[ "$DOCKERFILE_CONTENTS" == *'FROM php:8.2.32-fpm-bookworm@sha256:baed99aec14419f3d4413b3735f0723a0d1d754b9149f46762b662f3e3156284'* ]] \
    || fail 'Dockerfile does not pin the approved PHP FPM runtime digest'
[[ "$DOCKERFILE_CONTENTS" == *'FROM httpd:2.4.65-bookworm@sha256:fbc12199ccad031d8047e9c789d65aceee2d14f99ba90664cd3a3996867a5582'* ]] \
    || fail 'Dockerfile does not pin the approved Apache runtime digest'
[[ "$DOCKERFILE_CONTENTS" != *':latest'* && "$COMPOSE_CONTENTS" != *':latest'* ]] \
    || fail 'a floating latest image reference is present'
[[ "$DOCKERFILE_CONTENTS" != *'composer install'* && "$DOCKERFILE_CONTENTS" != *'COPY --from=composer'* ]] \
    || fail 'Docker tooling unexpectedly installs or copies Composer dependencies'
[[ "$COMPOSE_CONTENTS" == *'127.0.0.1:${HOST_PORT:-8080}:80'* ]] \
    || fail 'Compose listener is not explicitly localhost-only'
[[ "$COMPOSE_CONTENTS" == *'target: /pictures'* && "$COMPOSE_CONTENTS" == *'read_only: true'* ]] \
    || fail 'Compose picture mount is not explicitly read-only'
[[ "$COMPOSE_CONTENTS" == *'picture-browser-php'* \
    && "$DOCKERFILE_CONTENTS" == *'/var/www/html/hed/'* \
    && "$APACHE_CONTENTS" == *'/hed/index.php'* ]] \
    || fail 'Compose does not define the FPM service and deployment base path'
[[ "$APACHE_CONTENTS" == "ServerName picture-browser"$'\n\n<VirtualHost *:80>'* ]] \
    || fail 'Apache global ServerName is missing or misplaced'
[[ "$APACHE_CONTENTS" == *$'\n    ServerName picture-browser\n'* ]] \
    || fail 'Apache vhost-level ServerName is missing'

DEFAULT_CONFIG_OUTPUT="$(env -u PICTURES_HOST_PATH docker compose -p "$PROJECT" config)"
[[ "$DEFAULT_CONFIG_OUTPUT" == *"source: $REPOSITORY_PICTURES_ROOT"* ]] \
    || fail 'unset PICTURES_HOST_PATH does not resolve to the repository pictures folder'
[[ "$DEFAULT_CONFIG_OUTPUT" == *'target: /pictures'* && "$DEFAULT_CONFIG_OUTPUT" == *'read_only: true'* ]] \
    || fail 'unset PICTURES_HOST_PATH does not preserve the read-only /pictures mount'

EMPTY_CONFIG_OUTPUT="$(PICTURES_HOST_PATH= docker compose -p "$PROJECT" config)"
[[ "$EMPTY_CONFIG_OUTPUT" == *"source: $REPOSITORY_PICTURES_ROOT"* ]] \
    || fail 'empty PICTURES_HOST_PATH does not resolve to the repository pictures folder'
[[ "$EMPTY_CONFIG_OUTPUT" == *'target: /pictures'* && "$EMPTY_CONFIG_OUTPUT" == *'read_only: true'* ]] \
    || fail 'empty PICTURES_HOST_PATH does not preserve the read-only /pictures mount'

CONFIG_OUTPUT="$(docker compose -p "$PROJECT" config)"
[[ "$CONFIG_OUTPUT" == *"source: $FIXTURE_ROOT"* ]] || fail 'explicit picture fixture override was not resolved'
[[ "$CONFIG_OUTPUT" == *'host_ip: 127.0.0.1'* ]] || fail 'resolved Compose binding is not localhost-only'
[[ "$CONFIG_OUTPUT" != *'host_ip: 0.0.0.0'* ]] || fail 'resolved Compose binding exposes 0.0.0.0'

BUILD_LOG="$WORK_ROOT/docker-build.log"
printf 'Building the pinned P4 images...\n'
docker compose -p "$PROJECT" build --pull --no-cache "$WEB_SERVICE" "$PHP_SERVICE" 2>&1 | tee "$BUILD_LOG"

while IFS= read -r line; do
    if [[ "$line" == *'warning'* || "$line" == *'Warning'* || "$line" == *'WARNING'* || "$line" == *'WARN'* ]]; then
        case "$line" in
            *'No license specified'*|*'General warnings'*|*'it is recommended to do so'*) ;;
            *) fail "unapproved build warning: $line" ;;
        esac
    fi
done < "$BUILD_LOG"

printf 'Starting the default local picture fixture...\n'
COMPOSE_STARTED=true
env -u PICTURES_HOST_PATH docker compose -p "$PROJECT" up -d "$WEB_SERVICE"

DEFAULT_CONTAINER_ID="$(docker compose -p "$PROJECT" ps -q "$WEB_SERVICE")"
[[ -n "$DEFAULT_CONTAINER_ID" ]] || fail 'Compose did not create the default picture-browser container'
DEFAULT_PHP_CONTAINER_ID="$(docker compose -p "$PROJECT" ps -q "$PHP_SERVICE")"
[[ -n "$DEFAULT_PHP_CONTAINER_ID" ]] || fail 'Compose did not create the default PHP container'

for attempt in {1..30}; do
    if response="$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' "http://127.0.0.1:$PORT$BASE_PATH/picture/example" 2>/dev/null)" && [[ "$response" == '200' ]]; then
        break
    fi
    if (( attempt == 30 )); then
        fail 'the default picture fixture did not become ready on the localhost listener'
    fi
    sleep 1
done

DEFAULT_PAGE="$WORK_ROOT/default-picture-example.html"
DEFAULT_PAGE_METADATA="$(curl --silent --show-error --path-as-is --output "$DEFAULT_PAGE" \
    --write-out '%{http_code}\t%{content_type}' "http://127.0.0.1:$PORT$BASE_PATH/picture/example")"
IFS=$'\t' read -r DEFAULT_PAGE_STATUS DEFAULT_PAGE_TYPE <<< "$DEFAULT_PAGE_METADATA"
[[ "$DEFAULT_PAGE_STATUS" == '200' && "$DEFAULT_PAGE_TYPE" == 'text/html; charset=UTF-8' ]] \
    || fail 'default /hed/picture/example did not return HTTP 200 HTML'
DEFAULT_PAGE_BODY="$(<"$DEFAULT_PAGE")"
[[ "$DEFAULT_PAGE_BODY" == *'Picture example'* \
    && "$DEFAULT_PAGE_BODY" == *'src="/hed/media/example"'* \
    && "$DEFAULT_PAGE_BODY" == *'Bundled example picture.'* ]] \
    || fail 'default /picture/example did not render the example media route'

DEFAULT_MEDIA="$WORK_ROOT/default-picture-example.jpg"
DEFAULT_MEDIA_METADATA="$(curl --silent --show-error --path-as-is --output "$DEFAULT_MEDIA" \
    --write-out '%{http_code}\t%{content_type}' "http://127.0.0.1:$PORT$BASE_PATH/media/example")"
IFS=$'\t' read -r DEFAULT_MEDIA_STATUS DEFAULT_MEDIA_TYPE <<< "$DEFAULT_MEDIA_METADATA"
[[ "$DEFAULT_MEDIA_STATUS" == '200' && "$DEFAULT_MEDIA_TYPE" == 'image/jpeg' ]] \
    || fail 'default /media/example did not return HTTP 200 JPEG'
cmp -s "$DEFAULT_MEDIA" "$REPOSITORY_PICTURES_ROOT/example/picture.jpg" \
    || fail 'default /media/example did not preserve the fixture bytes'

DEFAULT_MOUNT_FORMAT='{{range .Mounts}}{{if eq .Destination "/pictures"}}{{.RW}}{{end}}{{end}}'
DEFAULT_MOUNT_RW="$(docker inspect --format "$DEFAULT_MOUNT_FORMAT" "$DEFAULT_PHP_CONTAINER_ID")"
[[ "$DEFAULT_MOUNT_RW" == 'false' ]] || fail "the default /pictures mount is not read-only: $DEFAULT_MOUNT_RW"

DEFAULT_MOUNT_FORMAT='{{range .Mounts}}{{if eq .Destination "/pictures"}}{{.Source}}{{end}}{{end}}'
DEFAULT_MOUNT_SOURCE="$(docker inspect --format "$DEFAULT_MOUNT_FORMAT" "$DEFAULT_PHP_CONTAINER_ID")"
[[ "$DEFAULT_MOUNT_SOURCE" == "$REPOSITORY_PICTURES_ROOT" ]] \
    || fail "unexpected default /pictures source: $DEFAULT_MOUNT_SOURCE"

DEFAULT_APACHE_LOGS="$(docker compose -p "$PROJECT" logs --no-color "$WEB_SERVICE")"
[[ "$DEFAULT_APACHE_LOGS" != *'AH00558'* ]] || fail 'Apache startup logs contain AH00558'

docker compose -p "$PROJECT" down --volumes --remove-orphans
COMPOSE_STARTED=false

printf 'Starting the Compose service...\n'
COMPOSE_STARTED=true
docker compose -p "$PROJECT" up -d "$WEB_SERVICE"

CONTAINER_ID="$(docker compose -p "$PROJECT" ps -q "$WEB_SERVICE")"
[[ -n "$CONTAINER_ID" ]] || fail 'Compose did not create the picture-browser container'
PHP_CONTAINER_ID="$(docker compose -p "$PROJECT" ps -q "$PHP_SERVICE")"
[[ -n "$PHP_CONTAINER_ID" ]] || fail 'Compose did not create the PHP container'

for attempt in {1..30}; do
    if response="$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' "http://127.0.0.1:$PORT$BASE_PATH/picture/2" 2>/dev/null)" && [[ "$response" == '200' ]]; then
        break
    fi
    if (( attempt == 30 )); then
        fail 'picture-browser did not become ready on the localhost listener'
    fi
    sleep 1
done

MOUNT_FORMAT='{{range .Mounts}}{{if eq .Destination "/pictures"}}{{.RW}}{{end}}{{end}}'
MOUNT_RW="$(docker inspect --format "$MOUNT_FORMAT" "$PHP_CONTAINER_ID")"
[[ "$MOUNT_RW" == 'false' ]] || fail "the /pictures mount is not read-only: $MOUNT_RW"

MOUNT_FORMAT='{{range .Mounts}}{{if eq .Destination "/pictures"}}{{.Source}}{{end}}{{end}}'
MOUNT_SOURCE="$(docker inspect --format "$MOUNT_FORMAT" "$PHP_CONTAINER_ID")"
[[ "$MOUNT_SOURCE" == "$FIXTURE_ROOT" ]] || fail "unexpected /pictures source: $MOUNT_SOURCE"

PORT_MAPPING_TEMPLATE='{{(index (index .NetworkSettings.Ports "80/tcp") 0).HostIp}}'
HOST_IP="$(docker inspect --format "$PORT_MAPPING_TEMPLATE" "$CONTAINER_ID")"
PORT_MAPPING_TEMPLATE='{{(index (index .NetworkSettings.Ports "80/tcp") 0).HostPort}}'
HOST_PORT="$(docker inspect --format "$PORT_MAPPING_TEMPLATE" "$CONTAINER_ID")"
[[ "$HOST_IP" == '127.0.0.1' ]] || fail "published host IP is not 127.0.0.1: $HOST_IP"
[[ "$HOST_PORT" == "$PORT" ]] || fail "published host port is not $PORT: $HOST_PORT"

docker compose -p "$PROJECT" exec -T "$PHP_SERVICE" php -r \
    'require "/var/www/html/hed/bootstrap.php"; exit(class_exists("PictureBrowser\\Application") ? 0 : 1);' \
    || fail 'The package bootstrap did not load the application classes'
docker compose -p "$PROJECT" exec -T "$PHP_SERVICE" php -r \
    'exit(PHP_VERSION_ID === 80232 ? 0 : 1);' \
    || fail 'The local PHP runtime does not match PHP 8.2.32'
[[ -f "$FIXTURE_ROOT/2/picture.png" ]] || fail 'fixture disappeared before the write test'

FIXTURE_HASH_BEFORE="$(tar -C "$FIXTURE_ROOT" --sort=name --format=ustar --mtime='UTC 1970-01-01' --owner=0 --group=0 --numeric-owner -cf - . | sha256sum)"
if docker compose -p "$PROJECT" exec -T --user www-data "$PHP_SERVICE" \
    sh -c 'touch /pictures/.p4-write-test' >/dev/null 2>&1; then
    fail 'the application user can write through the /pictures mount'
fi
[[ ! -e "$FIXTURE_ROOT/.p4-write-test" ]] || fail 'write attempt changed the host fixture'

FIXTURE_HASH_AFTER="$(tar -C "$FIXTURE_ROOT" --sort=name --format=ustar --mtime='UTC 1970-01-01' --owner=0 --group=0 --numeric-owner -cf - . | sha256sum)"
[[ "$FIXTURE_HASH_BEFORE" == "$FIXTURE_HASH_AFTER" ]] || fail 'host fixture changed during the read-only check'

fetch() {
    local path=$1
    local output=$2
    curl --silent --show-error --path-as-is --output "$output" \
        --write-out '%{http_code}\t%{content_type}' \
        "http://127.0.0.1:$PORT$path"
}

assert_http() {
    local expected_status=$1
    local expected_type=$2
    local path=$3
    local output=$4
    local metadata status content_type

    metadata="$(fetch "$path" "$output")"
    IFS=$'\t' read -r status content_type <<< "$metadata"
    [[ "$status" == "$expected_status" ]] || fail "$path returned HTTP $status, expected $expected_status"
    [[ "$content_type" == "$expected_type" ]] || fail "$path returned content type $content_type, expected $expected_type"
}

assert_contains() {
    local haystack=$1
    local needle=$2
    [[ "$haystack" == *"$needle"* ]] || fail "expected response to contain: $needle"
}

assert_not_contains() {
    local haystack=$1
    local needle=$2
    [[ "$haystack" != *"$needle"* ]] || fail "response unexpectedly contained: $needle"
}

assert_occurrences() {
    local haystack=$1
    local needle=$2
    local expected=$3
    local remaining=$haystack
    local count=0
    local prefix

    while [[ "$remaining" == *"$needle"* ]]; do
        prefix="${remaining%%"$needle"*}"
        remaining="${remaining#*"$needle"}"
        count=$((count + 1))
        [[ "$prefix" != "$remaining" ]] || break
    done
    [[ "$count" == "$expected" ]] || fail "expected $expected occurrences of $needle, got $count"
}

assert_not_found() {
    local path=$1
    local output="$WORK_ROOT/not-found-${RANDOM}.body"
    local metadata status body

    metadata="$(fetch "$path" "$output")"
    IFS=$'\t' read -r status _ <<< "$metadata"
    [[ "$status" == '404' ]] || fail "$path returned HTTP $status, expected 404"
    body="$(<"$output")"
    [[ "$body" == 'Not Found' ]] || fail "$path did not return the generic not-found body"
    assert_not_contains "$body" "$FIXTURE_ROOT"
}

assert_not_found_status() {
    local path=$1
    local output="$WORK_ROOT/not-found-status-${RANDOM}.body"
    local metadata status

    metadata="$(fetch "$path" "$output")"
    IFS=$'\t' read -r status _ <<< "$metadata"
    [[ "$status" == '404' ]] || fail "$path returned HTTP $status, expected 404"
}

PHPINFO="$WORK_ROOT/phpinfo.html"
assert_http '200' 'text/html; charset=UTF-8' '/phpinfo.php' "$PHPINFO"
PHPINFO_BODY="$(<"$PHPINFO")"
assert_contains "$PHPINFO_BODY" 'PHP Version 8.2.32'
assert_contains "$PHPINFO_BODY" 'FPM/FastCGI'
assert_contains "$PHPINFO_BODY" 'memory_limit'
assert_contains "$PHPINFO_BODY" '128M'

OVERVIEW="$WORK_ROOT/overview.html"
assert_http '200' 'text/html; charset=UTF-8' "$BASE_PATH/" "$OVERVIEW"
OVERVIEW_BODY="$(<"$OVERVIEW")"
assert_contains "$OVERVIEW_BODY" '<title>Overview</title>'
assert_contains "$OVERVIEW_BODY" 'class="picture-grid"'
assert_contains "$OVERVIEW_BODY" "href=\"$BASE_PATH/picture/2\""
assert_contains "$OVERVIEW_BODY" "src=\"$BASE_PATH/media/2\""
assert_contains "$OVERVIEW_BODY" 'aria-label="Picture navigation"'

PAGE="$WORK_ROOT/picture-2.html"
assert_http '200' 'text/html; charset=UTF-8' "$BASE_PATH/picture/2" "$PAGE"
PAGE_BODY="$(<"$PAGE")"
assert_contains "$PAGE_BODY" 'Picture 2'
assert_contains "$PAGE_BODY" "<a href=\"$BASE_PATH/\">Overview</a>"
assert_contains "$PAGE_BODY" 'src="/hed/media/2"'
assert_contains "$PAGE_BODY" 'data-picture-open'
assert_contains "$PAGE_BODY" 'data-picture-lightbox'
assert_contains "$PAGE_BODY" 'data-picture-close'
assert_contains "$PAGE_BODY" '>X</button>'
assert_contains "$PAGE_BODY" 'loading="lazy"'
assert_contains "$PAGE_BODY" 'decoding="async"'
assert_contains "$PAGE_BODY" 'href="/hed/assets/picture-browser.css"'
assert_contains "$PAGE_BODY" 'src="/hed/assets/picture-browser.js" defer'
assert_contains "$PAGE_BODY" '&lt;script title=&quot;quoted&quot;&gt;Zażółć &#039;gęślą&#039;&lt;/script&gt;<br>'
assert_not_contains "${PAGE_BODY,,}" '<form'
assert_not_contains "${PAGE_BODY,,}" 'upload'
assert_not_contains "${PAGE_BODY,,}" 'edit'
assert_not_contains "${PAGE_BODY,,}" 'search'

EXPECTED_IDS=('01' '2' '10' 'Beta' 'alpha' 'alpha-2' 'jpeg_1' 'safe_1')
previous_position=-1
for id in "${EXPECTED_IDS[@]}"; do
    needle="data-picture-id=\"$id\""
    assert_occurrences "$PAGE_BODY" "$needle" 1
    prefix="${PAGE_BODY%%"$needle"*}"
    current_position=${#prefix}
    (( current_position > previous_position )) || fail "catalog order is incorrect around $id"
    previous_position=$current_position
done

for invalid_id in 'ambiguous' 'invalid-image' 'invalid-utf8' 'wrong-case' 'bad.dot' 'link-dir' 'link-file'; do
    assert_not_contains "$PAGE_BODY" "data-picture-id=\"$invalid_id\""
done

PNG_RESPONSE="$WORK_ROOT/media-2.png"
assert_http '200' 'image/png' "$BASE_PATH/media/2" "$PNG_RESPONSE"
cmp -s "$PNG_RESPONSE" "$FIXTURE_ROOT/2/picture.png" || fail 'PNG media response changed the original bytes'

JPEG_RESPONSE="$WORK_ROOT/media-jpeg-1.jpg"
assert_http '200' 'image/jpeg' "$BASE_PATH/media/jpeg_1" "$JPEG_RESPONSE"
cmp -s "$JPEG_RESPONSE" "$FIXTURE_ROOT/jpeg_1/picture.jpg" || fail 'JPEG media response changed the original bytes'

CSS_RESPONSE="$WORK_ROOT/picture-browser.css"
assert_http '200' 'text/css' "$BASE_PATH/assets/picture-browser.css" "$CSS_RESPONSE"
JS_RESPONSE="$WORK_ROOT/picture-browser.js"
assert_http '200' 'text/javascript' "$BASE_PATH/assets/picture-browser.js" "$JS_RESPONSE"
JS_BODY="$(<"$JS_RESPONSE")"
assert_contains "$JS_BODY" '[data-picture-open]'
assert_contains "$JS_BODY" '[data-picture-lightbox]'
assert_contains "$JS_BODY" '[data-picture-close]'
assert_contains "$JS_BODY" "event.key === 'Escape'"
assert_not_contains "$JS_BODY" 'MIN_ZOOM'
assert_not_contains "$JS_BODY" 'data-zoom-action'

for not_found_path in \
    "$BASE_PATH/picture/missing" \
    "$BASE_PATH/picture/bad.dot" \
    "$BASE_PATH/picture/../2" \
    "$BASE_PATH/media/link-dir" \
    "$BASE_PATH/media/link-file" \
    "$BASE_PATH/media/../index.php" \
    "$BASE_PATH/unknown/2"; do
    assert_not_found "$not_found_path"
done

assert_not_found_status "$BASE_PATH/picture/a%2Fb"

printf 'P4 Docker/Compose verification passed; cleanup will remove containers and temporary fixtures.\n'
