#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND_DIR="$ROOT_DIR/backend"

ok() { echo "✅ $*"; }
warn() { echo "⚠️  $*"; }
fail() { echo "❌ $*"; exit 1; }

require_cmd() {
  command -v "$1" >/dev/null 2>&1 || fail "Falta comando requerido: $1"
}

require_cmd php
require_cmd composer
require_cmd curl

[ -d "$BACKEND_DIR" ] || fail "No existe directorio backend en $BACKEND_DIR"

ok "Root detectado: $ROOT_DIR"

cd "$BACKEND_DIR"

ok "1) Validando composer.json"
composer validate --no-check-publish >/dev/null
ok "composer validate OK"

if [ ! -f "vendor/autoload.php" ]; then
  warn "No existe vendor/. Ejecutando composer install..."
  composer install --no-interaction --prefer-dist
  ok "composer install OK"
else
  ok "vendor/ ya presente"
fi

ok "2) Verificando consola Symfony"
php bin/console --version >/dev/null
ok "symfony console funciona"

ok "3) Ejecutando migraciones"
php bin/console doctrine:migrations:migrate -n >/dev/null
ok "migraciones OK"

ok "4) Cargando fixtures ADMIN"
php bin/console doctrine:fixtures:load -n >/dev/null
ok "fixtures OK"

ok "5) Ejecutando tests unitarios/funcionales"
php bin/phpunit >/dev/null
ok "tests OK"

ok "6) Verificación Redis local"
if ! command -v redis-cli >/dev/null 2>&1; then
  warn "redis-cli no está instalado. Saltando verificación de keys de sesión."
else
  if ! redis-cli -h 127.0.0.1 -p 6379 PING >/dev/null 2>&1; then
    warn "Redis no responde en 127.0.0.1:6379. Levántalo con: docker run --name transporte-redis -p 6379:6379 -d redis:7-alpine"
  else
    ok "Redis responde"

    CSRF_TOKEN="$(curl -s -c /tmp/transporte.cookies http://127.0.0.1:8000/login | sed -n "s/.*name=\"_csrf_token\" value=\"\([^\"]*\)\".*/\1/p" | head -n1)"

    if [ -z "$CSRF_TOKEN" ]; then
      warn "No se pudo extraer _csrf_token de /login. Asegura que symfony server esté corriendo en http://127.0.0.1:8000"
    else
      curl -s -b /tmp/transporte.cookies -c /tmp/transporte.cookies -X POST http://127.0.0.1:8000/login \
        --data-urlencode "_username=admin@transporte.local" \
        --data-urlencode "_password=ChangeMe_123!" \
        --data-urlencode "_csrf_token=$CSRF_TOKEN" >/dev/null || true

      KEY_COUNT="$(redis-cli -h 127.0.0.1 -p 6379 KEYS 'sess:transporte:*' | wc -l | tr -d ' ')"
      if [ "$KEY_COUNT" -gt 0 ]; then
        ok "Redis guarda sesiones con prefijo sess:transporte:* (keys=$KEY_COUNT)"
      else
        warn "No se encontraron keys sess:transporte:*. Verifica login/cookie y servidor local."
      fi
    fi
  fi
fi

ok "Checklist fase 1 completado (sin E2E)."
