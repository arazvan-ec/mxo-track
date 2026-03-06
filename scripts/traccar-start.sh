#!/usr/bin/env bash
set -euo pipefail

# Traccar startup script for Railway deployment.
# Configures PostgreSQL connection from environment variables,
# waits for the database to be available, then starts Traccar.

: "${TRACCAR_DB_HOST:?TRACCAR_DB_HOST is required}"
: "${TRACCAR_DB_PORT:=5432}"
: "${TRACCAR_DB_NAME:=traccar}"
: "${TRACCAR_DB_USER:?TRACCAR_DB_USER is required}"
: "${TRACCAR_DB_PASSWORD:?TRACCAR_DB_PASSWORD is required}"

CONF="/opt/traccar/conf/traccar.xml"

echo "[traccar] Configuring PostgreSQL connection to ${TRACCAR_DB_HOST}:${TRACCAR_DB_PORT}/${TRACCAR_DB_NAME}..."

# Replace placeholders in traccar.xml
sed -i "s|__DB_HOST__|${TRACCAR_DB_HOST}|g" "${CONF}"
sed -i "s|__DB_PORT__|${TRACCAR_DB_PORT}|g" "${CONF}"
sed -i "s|__DB_NAME__|${TRACCAR_DB_NAME}|g" "${CONF}"
sed -i "s|__DB_USER__|${TRACCAR_DB_USER}|g" "${CONF}"
sed -i "s|__DB_PASSWORD__|${TRACCAR_DB_PASSWORD}|g" "${CONF}"

# Wait for PostgreSQL to be reachable (up to 60 seconds)
echo "[traccar] Waiting for PostgreSQL..."
for i in $(seq 1 60); do
    if pg_isready -h "${TRACCAR_DB_HOST}" -p "${TRACCAR_DB_PORT}" -U "${TRACCAR_DB_USER}" -q 2>/dev/null; then
        echo "[traccar] PostgreSQL is reachable."
        break
    fi
    if [ "$i" -eq 60 ]; then
        echo "[traccar] ERROR: PostgreSQL not reachable after 60s."
        exit 1
    fi
    sleep 1
done

# Create database if it doesn't exist
echo "[traccar] Ensuring database '${TRACCAR_DB_NAME}' exists..."
PGPASSWORD="${TRACCAR_DB_PASSWORD}" psql -h "${TRACCAR_DB_HOST}" -p "${TRACCAR_DB_PORT}" -U "${TRACCAR_DB_USER}" -tc \
    "SELECT 1 FROM pg_database WHERE datname='${TRACCAR_DB_NAME}'" | grep -q 1 \
    || PGPASSWORD="${TRACCAR_DB_PASSWORD}" createdb -h "${TRACCAR_DB_HOST}" -p "${TRACCAR_DB_PORT}" -U "${TRACCAR_DB_USER}" "${TRACCAR_DB_NAME}" 2>/dev/null \
    || echo "[traccar] Database already exists or could not be created (Traccar will create tables on startup)."

echo "[traccar] Starting Traccar server..."
exec java -Xms256m -Xmx512m -jar /opt/traccar/tracker-server.jar /opt/traccar/conf/traccar.xml
