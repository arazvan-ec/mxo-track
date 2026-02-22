#!/bin/sh
# backup_db.sh - PostgreSQL backup for mxo_track database
#
# Usage: ./scripts/backup_db.sh [output_dir]
#
# Creates a gzipped pg_dump of the mxo_track database.
# Filename format: mxo_track_YYYYMMDD_HHMMSS.sql.gz
# Retention: keeps last 7 daily backups (oldest removed automatically).
#
# Can be run from inside Docker (app container) or externally if
# pg_dump is available and DATABASE_URL or PGHOST etc. are set.

set -eu

# ── Configuration ──────────────────────────────────────────────────
BACKUP_DIR="${1:-/tmp/mxo_backups}"
RETENTION_COUNT=7
DB_NAME="${PGDATABASE:-mxo_track}"
DB_USER="${PGUSER:-mxo}"
DB_HOST="${PGHOST:-db}"
DB_PORT="${PGPORT:-5432}"

# Parse DATABASE_URL if available (format: postgresql://user:pass@host:port/dbname...)
if [ -n "${DATABASE_URL:-}" ]; then
    # Extract host
    _host=$(echo "$DATABASE_URL" | sed -n 's|.*@\([^:/]*\).*|\1|p')
    _port=$(echo "$DATABASE_URL" | sed -n 's|.*:\([0-9]*\)/.*|\1|p')
    _user=$(echo "$DATABASE_URL" | sed -n 's|.*://\([^:]*\):.*|\1|p')
    _dbname=$(echo "$DATABASE_URL" | sed -n 's|.*/\([^?]*\).*|\1|p')

    DB_HOST="${_host:-$DB_HOST}"
    DB_PORT="${_port:-$DB_PORT}"
    DB_USER="${_user:-$DB_USER}"
    DB_NAME="${_dbname:-$DB_NAME}"
fi

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
FILENAME="mxo_track_${TIMESTAMP}.sql.gz"

# ── Preflight checks ──────────────────────────────────────────────
if ! command -v pg_dump >/dev/null 2>&1; then
    echo "ERROR: pg_dump not found in PATH." >&2
    exit 1
fi

if ! command -v gzip >/dev/null 2>&1; then
    echo "ERROR: gzip not found in PATH." >&2
    exit 1
fi

# ── Create output directory ────────────────────────────────────────
mkdir -p "$BACKUP_DIR"

# ── Execute backup ─────────────────────────────────────────────────
echo "Starting backup of database '${DB_NAME}' on ${DB_HOST}:${DB_PORT}..."
echo "Output: ${BACKUP_DIR}/${FILENAME}"

if pg_dump -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -Fc "$DB_NAME" | gzip > "${BACKUP_DIR}/${FILENAME}"; then
    FILE_SIZE=$(wc -c < "${BACKUP_DIR}/${FILENAME}" | tr -d ' ')
    echo "Backup completed successfully. Size: ${FILE_SIZE} bytes"
else
    echo "ERROR: pg_dump failed." >&2
    rm -f "${BACKUP_DIR}/${FILENAME}"
    exit 1
fi

# ── Verify backup is not empty ─────────────────────────────────────
if [ ! -s "${BACKUP_DIR}/${FILENAME}" ]; then
    echo "ERROR: Backup file is empty." >&2
    rm -f "${BACKUP_DIR}/${FILENAME}"
    exit 1
fi

# ── Retention: keep last N backups ─────────────────────────────────
BACKUP_COUNT=$(ls -1 "${BACKUP_DIR}"/mxo_track_*.sql.gz 2>/dev/null | wc -l | tr -d ' ')
echo "Total backups in directory: ${BACKUP_COUNT} (retention: ${RETENTION_COUNT})"

if [ "$BACKUP_COUNT" -gt "$RETENTION_COUNT" ]; then
    REMOVE_COUNT=$((BACKUP_COUNT - RETENTION_COUNT))
    echo "Removing ${REMOVE_COUNT} old backup(s)..."
    ls -1t "${BACKUP_DIR}"/mxo_track_*.sql.gz | tail -n "$REMOVE_COUNT" | while read -r old_backup; do
        echo "  Removing: $(basename "$old_backup")"
        rm -f "$old_backup"
    done
fi

echo "Done."
