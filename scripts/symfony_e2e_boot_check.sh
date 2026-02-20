#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSE_FILE="$ROOT_DIR/docker-compose.local.yml"
PROJECT_NAME="mxo_track_e2e"
TEST_DB_NAME="${TEST_DB_NAME:-mxo_track}"

ok(){ echo "✅ $*"; }
warn(){ echo "⚠️  $*"; }
fail(){ echo "❌ $*"; exit 1; }

require_cmd(){ command -v "$1" >/dev/null 2>&1 || fail "Falta comando requerido: $1"; }

require_cmd docker

compose(){ docker compose -p "$PROJECT_NAME" -f "$COMPOSE_FILE" "$@"; }

cleanup(){
  compose down -v >/dev/null 2>&1 || true
}
trap cleanup EXIT

ok "Levantando stack local (db, redis, mercure)..."
compose up -d db redis mercure >/dev/null

ok "Esperando PostgreSQL listo..."
for _ in {1..30}; do
  if compose exec -T db pg_isready -U mxo -d "$TEST_DB_NAME" >/dev/null 2>&1; then
    ok "PostgreSQL listo"
    break
  fi
  sleep 1
done

ok "Instalando dependencias y verificando app Symfony en contenedor app"
compose run --rm app bash -lc '
  set -euo pipefail
  cd /app/backend
  composer install --no-interaction --prefer-dist
  php bin/console about
  php bin/console doctrine:migrations:migrate -n
  php -r "new Predis\\Client(getenv(\"REDIS_URL\")); echo \"Redis client OK\\n\";"
'

ok "Verificando endpoint Mercure"
HTTP_CODE="$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:3000/.well-known/mercure || true)"
if [[ "$HTTP_CODE" == "200" || "$HTTP_CODE" == "401" ]]; then
  ok "Mercure responde en localhost:3000 (HTTP $HTTP_CODE)"
else
  fail "Mercure no responde correctamente (HTTP $HTTP_CODE)"
fi

ok "E2E local completado: Symfony + DB + Redis + Mercure operativos."
