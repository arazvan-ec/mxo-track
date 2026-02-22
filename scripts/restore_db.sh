#!/bin/sh
# restore_db.sh - Restore PostgreSQL database from a backup file
#
# Usage: ./scripts/restore_db.sh backup_file.sql.gz
#
# Restores the mxo_track database from a gzipped pg_dump file.
# Prompts for confirmation before proceeding (use -y to skip prompt).

set -eu

# ── Configuration ──────────────────────────────────────────────────
DB_NAME="${PGDATABASE:-mxo_track}"
DB_USER="${PGUSER:-mxo}"
DB_HOST="${PGHOST:-db}"
DB_PORT="${PGPORT:-5432}"
SKIP_CONFIRM=false

# Parse DATABASE_URL if available
if [ -n "${DATABASE_URL:-}" ]; then
    _host=$(echo "$DATABASE_URL" | sed -n 's|.*@\([^:/]*\).*|\1|p')
    _port=$(echo "$DATABASE_URL" | sed -n 's|.*:\([0-9]*\)/.*|\1|p')
    _user=$(echo "$DATABASE_URL" | sed -n 's|.*://\([^:]*\):.*|\1|p')
    _dbname=$(echo "$DATABASE_URL" | sed -n 's|.*/\([^?]*\).*|\1|p')

    DB_HOST="${_host:-$DB_HOST}"
    DB_PORT="${_port:-$DB_PORT}"
    DB_USER="${_user:-$DB_USER}"
    DB_NAME="${_dbname:-$DB_NAME}"
fi

# ── Parse arguments ────────────────────────────────────────────────
BACKUP_FILE=""
for arg in "$@"; do
    case "$arg" in
        -y|--yes) SKIP_CONFIRM=true ;;
        *) BACKUP_FILE="$arg" ;;
    esac
done

if [ -z "$BACKUP_FILE" ]; then
    echo "Usage: $0 [-y] <backup_file.sql.gz>" >&2
    echo "" >&2
    echo "Options:" >&2
    echo "  -y, --yes    Skip confirmation prompt" >&2
    exit 1
fi

# ── Preflight checks ──────────────────────────────────────────────
if [ ! -f "$BACKUP_FILE" ]; then
    echo "ERROR: Backup file not found: ${BACKUP_FILE}" >&2
    exit 1
fi

if [ ! -s "$BACKUP_FILE" ]; then
    echo "ERROR: Backup file is empty: ${BACKUP_FILE}" >&2
    exit 1
fi

if ! command -v pg_restore >/dev/null 2>&1; then
    echo "ERROR: pg_restore not found in PATH." >&2
    exit 1
fi

if ! command -v gunzip >/dev/null 2>&1; then
    echo "ERROR: gunzip not found in PATH." >&2
    exit 1
fi

# ── Confirmation ───────────────────────────────────────────────────
FILE_SIZE=$(wc -c < "$BACKUP_FILE" | tr -d ' ')
echo "============================================"
echo "  DATABASE RESTORE"
echo "============================================"
echo "  File:     $(basename "$BACKUP_FILE")"
echo "  Size:     ${FILE_SIZE} bytes"
echo "  Target:   ${DB_NAME} @ ${DB_HOST}:${DB_PORT}"
echo "  User:     ${DB_USER}"
echo "============================================"
echo ""
echo "WARNING: This will DROP and recreate the database '${DB_NAME}'."
echo "All existing data will be lost."
echo ""

if [ "$SKIP_CONFIRM" = false ]; then
    printf "Are you sure you want to continue? [y/N] "
    read -r answer
    case "$answer" in
        [yY]|[yY][eE][sS]) ;;
        *)
            echo "Restore cancelled."
            exit 0
            ;;
    esac
fi

# ── Restore ────────────────────────────────────────────────────────
echo ""
echo "Restoring database..."

# Create a temporary file for the decompressed dump
TEMP_DUMP=$(mktemp /tmp/mxo_restore_XXXXXX.dump)
trap 'rm -f "$TEMP_DUMP"' EXIT

gunzip -c "$BACKUP_FILE" > "$TEMP_DUMP"

# Drop and recreate database connections, then restore
# Use --clean --if-exists to handle existing objects gracefully
if pg_restore \
    -h "$DB_HOST" \
    -p "$DB_PORT" \
    -U "$DB_USER" \
    -d "$DB_NAME" \
    --clean \
    --if-exists \
    --no-owner \
    --no-privileges \
    "$TEMP_DUMP" 2>&1; then
    echo ""
    echo "Restore completed successfully."
else
    RESTORE_EXIT=$?
    # pg_restore may return non-zero for warnings (e.g., "role does not exist")
    # which are typically harmless. Only treat as real error if exit code > 1.
    if [ "$RESTORE_EXIT" -gt 1 ]; then
        echo ""
        echo "ERROR: Restore failed with exit code ${RESTORE_EXIT}." >&2
        exit 1
    else
        echo ""
        echo "Restore completed with warnings (exit code ${RESTORE_EXIT})."
    fi
fi

echo "Done."
