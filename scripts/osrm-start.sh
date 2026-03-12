#!/usr/bin/env bash
set -euo pipefail

# OSRM startup script for Railway deployment.
# Prepares map data on first boot (stored in persistent volume at /data/),
# then starts osrm-routed. Subsequent boots skip preparation.
# Detects OSRM version changes and reprocesses map data automatically.

MAP_URL="https://download.geofabrik.de/europe/spain/madrid-latest.osm.pbf"
MAP_FILE="/data/madrid-latest.osm.pbf"
OSRM_FILE="/data/madrid-latest.osrm"
VERSION_FILE="/data/.osrm-version"

# Detect current OSRM binary version
CURRENT_VERSION=$(osrm-routed --version 2>&1 | head -1 || echo "unknown")
echo "[osrm] Binary version: ${CURRENT_VERSION}"

# Check if map data exists AND was processed with the same OSRM version
NEED_REPROCESS=false
if [ ! -f "${OSRM_FILE}.mldgr" ]; then
    NEED_REPROCESS=true
    echo "[osrm] No processed map data found."
elif [ -f "${VERSION_FILE}" ]; then
    STORED_VERSION=$(cat "${VERSION_FILE}")
    if [ "${STORED_VERSION}" != "${CURRENT_VERSION}" ]; then
        NEED_REPROCESS=true
        echo "[osrm] Version mismatch: data was processed with '${STORED_VERSION}', current binary is '${CURRENT_VERSION}'."
        echo "[osrm] Clearing old data and reprocessing..."
        rm -f /data/madrid-latest.*
    fi
else
    # Version file missing but data exists — likely from before version tracking.
    # Force reprocess to ensure compatibility.
    NEED_REPROCESS=true
    echo "[osrm] No version marker found. Clearing old data and reprocessing for safety..."
    rm -f /data/madrid-latest.*
fi

if [ "${NEED_REPROCESS}" = true ]; then
    echo "[osrm] Downloading and processing map data..."

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

    # Save version marker
    echo "${CURRENT_VERSION}" > "${VERSION_FILE}"

    # Remove PBF to save volume space
    rm -f "${MAP_FILE}"

    echo "[osrm] Map data ready (version: ${CURRENT_VERSION})."
else
    echo "[osrm] Map data already processed (version: ${CURRENT_VERSION}), starting server..."
fi

echo "[osrm] Starting osrm-routed on port 5000..."
exec osrm-routed --algorithm mld --port 5000 "${OSRM_FILE}"
