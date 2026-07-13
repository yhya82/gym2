#!/usr/bin/env bash
#
# Dumps the application database to a timestamped, gzipped file and prunes
# anything older than RETENTION_DAYS. Reads DB connection values straight out
# of the app's own .env so this never drifts out of sync with what the app is
# actually configured to use.
#
# Usage: ./backup-database.sh [backup-dir] [retention-days]
# Cron:  see deploy/cron/gym2-backup

set -euo pipefail

APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
ENV_FILE="${APP_ROOT}/.env"
BACKUP_DIR="${1:-/var/backups/gym2/database}"
RETENTION_DAYS="${2:-14}"

env_value() {
    # Tolerates leading whitespace before the key — PHP's dotenv parser
    # trims it, so a strict ^KEY= anchor here would silently disagree with
    # what the app itself actually reads. Strips surrounding quotes; last
    # matching line wins, matching dotenv's own "later definitions win".
    grep -E "^[[:space:]]*${1}=" "$ENV_FILE" | tail -n1 | cut -d '=' -f2- | sed -e 's/^"//' -e 's/"$//'
}

DB_HOST="$(env_value DB_HOST)"
DB_PORT="$(env_value DB_PORT)"
DB_DATABASE="$(env_value DB_DATABASE)"
DB_USERNAME="$(env_value DB_USERNAME)"
DB_PASSWORD="$(env_value DB_PASSWORD)"

mkdir -p "$BACKUP_DIR"

timestamp="$(date +%Y%m%d-%H%M%S)"
dump_file="${BACKUP_DIR}/${DB_DATABASE}-${timestamp}.sql.gz"

MYSQL_PWD="$DB_PASSWORD" mysqldump \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --user="$DB_USERNAME" \
    --single-transaction \
    --routines \
    --triggers \
    --events \
    "$DB_DATABASE" \
    | gzip > "$dump_file"

echo "Backed up ${DB_DATABASE} to ${dump_file}"

# Also archive uploaded files (Settings > logo) — these aren't in git and
# aren't in the database, so a DB-only backup would silently lose them.
storage_public="${APP_ROOT}/storage/app/public"
if [ -d "$storage_public" ]; then
    files_archive="${BACKUP_DIR}/storage-public-${timestamp}.tar.gz"
    tar -czf "$files_archive" -C "$storage_public" .
    echo "Backed up uploaded files to ${files_archive}"
fi

find "$BACKUP_DIR" -type f -mtime "+${RETENTION_DAYS}" -delete
echo "Pruned backups older than ${RETENTION_DAYS} days from ${BACKUP_DIR}"
