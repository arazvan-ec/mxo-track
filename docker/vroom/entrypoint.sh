#!/bin/sh
set -e

OSRM_HOST="${OSRM_HOST:-osrm-mxo.railway.internal}"
OSRM_PORT="${OSRM_PORT:-5000}"

echo "[vroom-entrypoint] OSRM_HOST=${OSRM_HOST}, OSRM_PORT=${OSRM_PORT}"
echo "[vroom-entrypoint] Generating /conf/config.yml..."

cat > /conf/config.yml <<EOF
cliArgs:
  baseurl: "/"
  port: 3000
  logdir: "/tmp"
  logsize: "10M"
  geometry: true
  planmode: true
  router: "osrm"
  threads: 4
  timeout: 300000
  maxlocations: 1000
  maxvehicles: 200

routingServers:
  osrm:
    car:
      host: "${OSRM_HOST}"
      port: "${OSRM_PORT}"
EOF

echo "[vroom-entrypoint] Config written. Testing DNS resolution for ${OSRM_HOST}..."
if nslookup "${OSRM_HOST}" > /dev/null 2>&1; then
  echo "[vroom-entrypoint] DNS OK: ${OSRM_HOST} resolves."
elif getent hosts "${OSRM_HOST}" > /dev/null 2>&1; then
  echo "[vroom-entrypoint] DNS OK (getent): ${OSRM_HOST} resolves."
else
  echo "[vroom-entrypoint] WARNING: Cannot resolve ${OSRM_HOST} — VROOM routing will fail!"
fi

echo "[vroom-entrypoint] Config contents:"
cat /conf/config.yml

# Replicate what the original docker-entrypoint.sh does:
# 1. Copy config to app dir
cp /conf/config.yml /usr/src/app/config.yml
# 2. Ensure access log exists
touch /conf/access.log

echo "[vroom-entrypoint] Starting VROOM (npm start)..."
cd /usr/src/app
exec npm start
