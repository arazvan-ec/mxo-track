#!/usr/bin/env bash
set -euo pipefail

# OSRM startup script for Railway deployment.
# Prepares map data on first boot (stored in persistent volume at /data/),
# then starts osrm-routed. Subsequent boots skip preparation.

MAP_URL="https://download.geofabrik.de/europe/spain/comunidad-de-madrid-latest.osm.pbf"
MAP_FILE="/data/comunidad-de-madrid-latest.osm.pbf"
OSRM_FILE="/data/comunidad-de-madrid-latest.osrm"

if [ -f "${OSRM_FILE}.mldgr" ]; then
    echo "[osrm] Map data already processed, starting server..."
else
    echo "[osrm] First boot — downloading and processing map data..."

    echo "[osrm] Step 1/4: Downloading Comunidad de Madrid map (~75 MB)..."
    curl -fSL --retry 3 --retry-delay 5 --connect-timeout 30 -o "${MAP_FILE}" "${MAP_URL}"

    # Validate download: actual PBF file should be >1 MB
    FILE_SIZE=$(stat -c%s "${MAP_FILE}" 2>/dev/null || echo 0)
    if [ "${FILE_SIZE}" -lt 1048576 ]; then
        echo "[osrm] ERROR: Downloaded file is only ${FILE_SIZE} bytes (expected ~75 MB)."
        echo "[osrm] This usually means a TLS/certificate issue or the URL returned an error page."
        rm -f "${MAP_FILE}"
        exit 1
    fi
    echo "[osrm] Downloaded ${FILE_SIZE} bytes."

    echo "[osrm] Step 2/4: Extracting road network..."
    osrm-extract -p /opt/car.lua "${MAP_FILE}"

    echo "[osrm] Step 3/4: Partitioning graph..."
    osrm-partition "${OSRM_FILE}"

    echo "[osrm] Step 4/4: Customizing weights..."
    osrm-customize "${OSRM_FILE}"

    # Remove PBF to save volume space
    rm -f "${MAP_FILE}"

    echo "[osrm] Map data ready."
fi

echo "[osrm] Starting osrm-routed on port 5000..."
exec osrm-routed --algorithm mld --port 5000 "${OSRM_FILE}"
