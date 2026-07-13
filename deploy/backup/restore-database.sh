#!/usr/bin/env bash
#
# Restores a database dump produced by backup-database.sh. Destructive —
# drops and recreates every table in the target database — so it always
# asks for confirmation unless --force is passed.
#
# Usage: ./restore-database.sh <path-to-dump.sql.gz> [--force]

set -euo pipefail

APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
ENV_FILE="${APP_ROOT}/.env"
DUMP_FILE="${1:-}"
FORCE="${2:-}"

if [ -z "$DUMP_FILE" ] || [ ! -f "$DUMP_FILE" ]; then
    echo "Usage: $0 <path-to-dump.sql.gz> [--force]" >&2
    exit 1
fi

env_value() {
    # See backup-database.sh's env_value for why leading whitespace is tolerated.
    grep -E "^[[:space:]]*${1}=" "$ENV_FILE" | tail -n1 | cut -d '=' -f2- | sed -e 's/^"//' -e 's/"$//'
}

DB_HOST="$(env_value DB_HOST)"
DB_PORT="$(env_value DB_PORT)"
DB_DATABASE="$(env_value DB_DATABASE)"
DB_USERNAME="$(env_value DB_USERNAME)"
DB_PASSWORD="$(env_value DB_PASSWORD)"

if [ "$FORCE" != "--force" ]; then
    read -r -p "This will REPLACE every table in '${DB_DATABASE}' on ${DB_HOST}. Continue? [y/N] " reply
    if [[ ! "$reply" =~ ^[Yy]$ ]]; then
        echo "Aborted."
        exit 1
    fi
fi

gunzip -c "$DUMP_FILE" | MYSQL_PWD="$DB_PASSWORD" mysql \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --user="$DB_USERNAME" \
    "$DB_DATABASE"

echo "Restored ${DB_DATABASE} from ${DUMP_FILE}"
