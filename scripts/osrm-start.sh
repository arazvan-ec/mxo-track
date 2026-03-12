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
    curl -L -o "${MAP_FILE}" "${MAP_URL}"

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
