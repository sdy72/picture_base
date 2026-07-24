#!/usr/bin/env bash

set -Eeuo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
REPOSITORY_ROOT="$(cd -- "$SCRIPT_DIR/.." && pwd)"
WORK_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/picture-base-deploy-test.XXXXXX")"
FAKE_LFTP_SCRIPT="$WORK_ROOT/lftp-commands"
OUTPUT=''
LAST_STATUS=0

fail() {
    printf 'Deployment script test failed: %s\n' "$1" >&2
    exit 1
}

cleanup() {
    local status=$?

    rm -rf -- "$WORK_ROOT"
    exit "$status"
}
trap cleanup EXIT

assert_contains() {
    local haystack=$1
    local needle=$2

    [[ "$haystack" == *"$needle"* ]] || fail "expected output to contain: $needle"
}

mkdir -p "$WORK_ROOT/bin" "$WORK_ROOT/subfolder"
cp -- "$REPOSITORY_ROOT/deploy.sh" "$WORK_ROOT/deploy.sh"
chmod +x "$WORK_ROOT/deploy.sh"
touch "$WORK_ROOT/deploy.key"

printf '%s\n' \
    '#!/usr/bin/env bash' \
    'set -Eeuo pipefail' \
    'while (($# > 0)); do' \
    '    if [[ "$1" == "-f" ]]; then' \
    '        cp -- "$2" "$FAKE_LFTP_SCRIPT"' \
    '        break' \
    '    fi' \
    '    shift' \
    'done' \
    'exit "${FAKE_LFTP_STATUS:-0}"' > "$WORK_ROOT/bin/lftp"
chmod +x "$WORK_ROOT/bin/lftp"

write_env() {
    local remote_path=$1

    {
        printf 'SFTP_HOST=%q\n' 'example.test'
        printf 'SFTP_USER=%q\n' "deploy'user"
        printf 'SFTP_KEY_PATH=%q\n' "$WORK_ROOT/deploy.key"
        printf 'SFTP_REMOTE_PATH=%q\n' "$remote_path"
    } > "$WORK_ROOT/.env"
}

run_deploy() {
    local fake_status=$1

    if OUTPUT="$(
        PATH="$WORK_ROOT/bin:$PATH" \
        FAKE_LFTP_SCRIPT="$FAKE_LFTP_SCRIPT" \
        FAKE_LFTP_STATUS="$fake_status" \
        "$WORK_ROOT/deploy.sh" 2>&1
    )"; then
        LAST_STATUS=0
    else
        LAST_STATUS=$?
    fi
}

write_env '/srv/picture-base'
run_deploy 0
[[ "$LAST_STATUS" -eq 0 ]] || fail 'successful mirror returned a failure status'
assert_contains "$OUTPUT" 'Reconciling '
assert_contains "$OUTPUT" 'Deployment completed.'

COMMANDS="$(<"$FAKE_LFTP_SCRIPT")"
assert_contains "$COMMANDS" 'set cmd:fail-exit yes'
assert_contains "$COMMANDS" 'set mirror:set-permissions off'
assert_contains "$COMMANDS" 'set sftp:connect-program '
assert_contains "$COMMANDS" "open -u 'deploy'\\''user' 'sftp://example.test'"
assert_contains "$COMMANDS" "cd '/srv/picture-base'"
assert_contains "$COMMANDS" 'mirror --reverse --delete --delete-excluded --verbose '
assert_contains "$COMMANDS" "'$WORK_ROOT/subfolder' ."

run_deploy 17
[[ "$LAST_STATUS" -eq 1 ]] || fail 'lftp failure was not converted to a deployment failure'
assert_contains "$OUTPUT" 'lftp SFTP reconciliation failed'
[[ "$OUTPUT" != *'Deployment completed.'* ]] || fail 'failed mirror reported completion'

write_env '/'
run_deploy 0
[[ "$LAST_STATUS" -eq 1 ]] || fail 'root target was not rejected'
assert_contains "$OUTPUT" 'must be a dedicated package directory'

printf 'Deployment script tests passed.\n'
