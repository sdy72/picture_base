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

require_command sftp

if [[ "$SFTP_KEY_PATH" == "~/"* ]]; then
    SFTP_KEY_PATH="$HOME/${SFTP_KEY_PATH#~/}"
elif [[ "$SFTP_KEY_PATH" != /* ]]; then
    SFTP_KEY_PATH="$SCRIPT_DIR/$SFTP_KEY_PATH"
fi

[[ -f "$SFTP_KEY_PATH" ]] || fail "SFTP key file is missing: $SFTP_KEY_PATH"
[[ -d "$PACKAGE_ROOT" ]] || fail "deployment package is missing: $PACKAGE_ROOT"

quote_sftp_path() {
    local path=$1

    path=${path//\\/\\\\}
    path=${path//\"/\\\"}
    printf '"%s"' "$path"
}

LOCAL_ROOT="$(quote_sftp_path "$PACKAGE_ROOT")"
REMOTE_PATH="$(quote_sftp_path "$SFTP_REMOTE_PATH")"

printf 'Uploading %s to %s...\n' "$PACKAGE_ROOT" "$SFTP_REMOTE_PATH"
{
    printf 'cd %s\n' "$REMOTE_PATH"
    printf 'put -r %s/* %s/.[!.]* .\n' "$LOCAL_ROOT" "$LOCAL_ROOT"
} | sftp -i "$SFTP_KEY_PATH" -o BatchMode=no -o StrictHostKeyChecking=accept-new "$SFTP_USER@$SFTP_HOST"

printf 'Deployment completed.\n'
