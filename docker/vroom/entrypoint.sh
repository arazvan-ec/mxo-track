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

# Find where vroom-express lives and copy config there
VROOM_APP_DIR=""
for dir in /usr/src/app /usr/local/src/vroom-express /opt/vroom-express /vroom-express; do
  if [ -d "$dir" ]; then
    VROOM_APP_DIR="$dir"
    break
  fi
done

echo "[vroom-entrypoint] Detected app dir: ${VROOM_APP_DIR:-NOT FOUND}"
echo "[vroom-entrypoint] Filesystem layout:"
ls -la / 2>/dev/null | head -20

if [ -n "$VROOM_APP_DIR" ]; then
  cp /conf/config.yml "${VROOM_APP_DIR}/config.yml"
  touch /conf/access.log
  echo "[vroom-entrypoint] Starting VROOM (npm start) from ${VROOM_APP_DIR}..."
  cd "$VROOM_APP_DIR"
  exec npm start
else
  # Fallback: try the original entrypoint with chmod
  echo "[vroom-entrypoint] No app dir found. Trying chmod + original entrypoint..."
  chmod +x /docker-entrypoint.sh 2>/dev/null || true
  exec /docker-entrypoint.sh "$@"
fi
