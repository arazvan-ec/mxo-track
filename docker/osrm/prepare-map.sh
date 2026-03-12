#!/usr/bin/env bash
set -euo pipefail

# Downloads and prepares OSRM map data for the Comunidad de Madrid region.
# Run this script from the project root before starting the OSRM Docker service.
#
# Usage:
#   ./docker/osrm/prepare-map.sh
#
# The processed data will be stored in docker/osrm/data/ which is mounted
# as a volume by the osrm service in docker-compose.local.yml.

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DATA_DIR="${SCRIPT_DIR}/data"
MAP_URL="https://download.geofabrik.de/europe/spain/madrid-latest.osm.pbf"
MAP_FILE="madrid-latest.osm.pbf"
OSRM_FILE="madrid-latest.osrm"

mkdir -p "${DATA_DIR}"

# Download map if not already present
if [ ! -f "${DATA_DIR}/${MAP_FILE}" ]; then
    echo "Downloading Comunidad de Madrid map (~75 MB)..."
    curl -L -o "${DATA_DIR}/${MAP_FILE}" "${MAP_URL}"
else
    echo "Map file already exists, skipping download."
fi

# Check if already processed
if [ -f "${DATA_DIR}/${OSRM_FILE}.mldgr" ]; then
    echo "OSRM data already processed. Delete ${DATA_DIR}/${OSRM_FILE}* to reprocess."
    exit 0
fi

echo "Step 1/3: Extracting road network (osrm-extract)..."
docker run --rm -v "${DATA_DIR}:/data" osrm/osrm-backend \
    osrm-extract -p /opt/car.lua "/data/${MAP_FILE}"

echo "Step 2/3: Partitioning graph (osrm-partition)..."
docker run --rm -v "${DATA_DIR}:/data" osrm/osrm-backend \
    osrm-partition "/data/${OSRM_FILE}"

echo "Step 3/3: Customizing weights (osrm-customize)..."
docker run --rm -v "${DATA_DIR}:/data" osrm/osrm-backend \
    osrm-customize "/data/${OSRM_FILE}"

echo ""
echo "OSRM map data ready in ${DATA_DIR}/"
echo "You can now start the services with: docker compose -f docker-compose.local.yml up -d"
