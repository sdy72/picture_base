#!/usr/bin/env bash

set -Eeuo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$SCRIPT_DIR/.env"
PACKAGE_ROOT="$SCRIPT_DIR/subfolder"

fail() {
    printf 'Deployment failed: %s\n' "$1" >&2
    exit 1
}

require_command() {
    command -v "$1" >/dev/null 2>&1 || fail "required command is missing: $1"
}

[[ -f "$ENV_FILE" ]] || fail "configuration file is missing: $ENV_FILE"

# Load the local deployment settings without requiring an external dotenv tool.
set -a
# shellcheck disable=SC1090
. "$ENV_FILE"
set +a

for variable in SFTP_HOST SFTP_USER SFTP_KEY_PATH SFTP_REMOTE_PATH; do
    [[ -n "${!variable:-}" ]] || fail "$variable is not set in $ENV_FILE"
done

require_command lftp
require_command ssh

if [[ "$SFTP_KEY_PATH" == "~/"* ]]; then
    SFTP_KEY_PATH="$HOME/${SFTP_KEY_PATH#~/}"
elif [[ "$SFTP_KEY_PATH" != /* ]]; then
    SFTP_KEY_PATH="$SCRIPT_DIR/$SFTP_KEY_PATH"
fi

[[ -f "$SFTP_KEY_PATH" ]] || fail "SFTP key file is missing: $SFTP_KEY_PATH"
[[ -d "$PACKAGE_ROOT" ]] || fail "deployment package is missing: $PACKAGE_ROOT"

case "$SFTP_REMOTE_PATH" in
    /|.|./|..|../|~|~/)
        fail "SFTP_REMOTE_PATH must be a dedicated package directory, not: $SFTP_REMOTE_PATH"
        ;;
esac
case "/$SFTP_REMOTE_PATH/" in
    */../*)
        fail "SFTP_REMOTE_PATH must not contain a parent-directory component: $SFTP_REMOTE_PATH"
        ;;
esac

quote_lftp_arg() {
    local path=$1

    path=${path//\'/\'\\\'\'}
    printf "'%s'" "$path"
}

printf -v SSH_KEY_ARGUMENT '%q' "$SFTP_KEY_PATH"
CONNECT_PROGRAM="ssh -a -x -i $SSH_KEY_ARGUMENT -o BatchMode=no -o StrictHostKeyChecking=accept-new"

LFTP_SCRIPT="$(mktemp "${TMPDIR:-/tmp}/picture-base-deploy.XXXXXX")"
cleanup() {
    local status=$?

    if [[ -n "$LFTP_SCRIPT" ]]; then
        rm -f -- "$LFTP_SCRIPT" || true
    fi
    exit "$status"
}
trap cleanup EXIT

LOCAL_ROOT="$(quote_lftp_arg "$PACKAGE_ROOT")"
REMOTE_PATH="$(quote_lftp_arg "$SFTP_REMOTE_PATH")"
SFTP_USER_ARGUMENT="$(quote_lftp_arg "$SFTP_USER")"
SFTP_URL="$(quote_lftp_arg "sftp://$SFTP_HOST")"
CONNECT_PROGRAM_ARGUMENT="$(quote_lftp_arg "$CONNECT_PROGRAM")"

printf 'Reconciling %s with %s...\n' "$PACKAGE_ROOT" "$SFTP_REMOTE_PATH"
{
    printf 'set cmd:fail-exit yes\n'
    printf 'set mirror:set-permissions off\n'
    printf 'set sftp:connect-program %s\n' "$CONNECT_PROGRAM_ARGUMENT"
    printf 'open -u %s %s\n' "$SFTP_USER_ARGUMENT" "$SFTP_URL"
    printf 'cd %s\n' "$REMOTE_PATH"
    printf 'mirror --reverse --delete --delete-excluded --verbose %s .\n' "$LOCAL_ROOT"
    printf 'bye\n'
} > "$LFTP_SCRIPT"

if ! lftp -f "$LFTP_SCRIPT"; then
    fail 'lftp SFTP reconciliation failed'
fi

printf 'Deployment completed.\n'
