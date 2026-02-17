#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND_DIR="$ROOT_DIR/backend"
REPORT_PATH="$ROOT_DIR/docs/PHASE_FLOW_VALIDATION.md"

ok(){ echo "✅ $*"; }
warn(){ echo "⚠️  $*"; }
fail(){ echo "❌ $*"; exit 1; }

require_cmd(){ command -v "$1" >/dev/null 2>&1 || fail "Falta comando requerido: $1"; }

require_cmd bash
require_cmd rg
require_cmd php
require_cmd composer

[ -d "$BACKEND_DIR" ] || fail "No existe backend/"

CHECKS=()
RECOMMENDATIONS=()

run_check(){
  local description="$1"
  local cmd="$2"
  if eval "$cmd" >/dev/null 2>&1; then
    CHECKS+=("PASS|$description")
    ok "$description"
  else
    CHECKS+=("FAIL|$description")
    warn "$description"
  fi
}

ok "Validando lock Symfony 7.4"
if bash "$ROOT_DIR/scripts/check_symfony_74_lock.sh" >/dev/null 2>&1; then
  CHECKS+=("PASS|Política Symfony 7.4 (sin symfony/* 8.x)")
else
  CHECKS+=("FAIL|Política Symfony 7.4 (sin symfony/* 8.x)")
  RECOMMENDATIONS+=("Revisar dependencias symfony/* en composer.lock y bloquear cualquier 8.x.")
fi

ok "Asegurando dependencias de backend"
(
  cd "$BACKEND_DIR"
  composer install --no-interaction --prefer-dist >/dev/null
)

run_check "Fase 2: docs/REALTIME_MAP.md existe" "test -f '$ROOT_DIR/docs/REALTIME_MAP.md'"
run_check "Fase 2: /fleet/map desactiva Turbo solo en esa vista" "rg -n \"data-turbo=\\\"false\\\"\" '$BACKEND_DIR/templates/tracking/map.html.twig'"
run_check "Fase 2: endpoint admin fake push-position existe" "rg -n \"Route\('/push-position'\" '$BACKEND_DIR/src/Controller/AdminDevPushPositionController.php'"
run_check "Fase 2: validación ULID en /api/mercure-token" "rg -n \"Ulid::fromString\" '$BACKEND_DIR/src/Controller/MercureTokenController.php'"
run_check "Fase 2: topics Mercure por public_id" "rg -n \"/vehicles/%s/position\" '$BACKEND_DIR/src/Security/TopicResolver.php' '$BACKEND_DIR/src/Service/TraccarIngestionService.php'"
run_check "Fase 2: Vehicle incluye timestamps createdAt/updatedAt" "rg -n \"createdAt|updatedAt\" '$BACKEND_DIR/src/Entity/Vehicle.php'"
run_check "Fase 2: CUSTOMER/DRIVER reciben lista vacía en /api/vehicles" "rg -n \"ROLE_CUSTOMER|ROLE_DRIVER\" '$BACKEND_DIR/src/Controller/VehicleApiController.php' && rg -n -F \"vehicles = []\" '$BACKEND_DIR/src/Controller/VehicleApiController.php'"
run_check "Arquitectura: regla rígida BIGINT + ULID documentada" "rg -n \"Regla rígida de identidad|BIGINT|public_id\" '$ROOT_DIR/docs/DECISIONS.md'"

ok "Verificando rutas clave"
if (
  cd "$BACKEND_DIR"
  php bin/console debug:router | rg "fleet_map|admin_dev_push_position|api_mercure_token"
) >/dev/null 2>&1; then
  CHECKS+=("PASS|Rutas clave registradas (fleet_map, admin_dev_push_position, api_mercure_token)")
else
  CHECKS+=("FAIL|Rutas clave registradas (fleet_map, admin_dev_push_position, api_mercure_token)")
  RECOMMENDATIONS+=("Revisar anotaciones de rutas y carga de controladores para endpoints de mapa/Mercure.")
fi

# recomendaciones automáticas por checks fallidos
for item in "${CHECKS[@]}"; do
  status="${item%%|*}"
  desc="${item#*|}"
  if [[ "$status" == "FAIL" ]]; then
    case "$desc" in
      *"docs/REALTIME_MAP.md"*) RECOMMENDATIONS+=("Añadir guía de operación realtime con topics, payload y pruebas manuales.") ;;
      *"desactiva Turbo"*) RECOMMENDATIONS+=("Asegurar data-turbo=false sólo en /fleet/map para evitar conflictos SSE.") ;;
      *"Vehicle incluye timestamps"*) RECOMMENDATIONS+=("Agregar created_at/updated_at en Vehicle (entidad + migración).") ;;
      *"CUSTOMER/DRIVER"*) RECOMMENDATIONS+=("Forzar vaciado temporal para CUSTOMER/DRIVER en /api/vehicles hasta Fase 3.") ;;
    esac
  fi
done

# deduplicar recomendaciones
UNIQ_RECS=()
for rec in "${RECOMMENDATIONS[@]:-}"; do
  skip=0
  for seen in "${UNIQ_RECS[@]:-}"; do
    if [[ "$rec" == "$seen" ]]; then
      skip=1
      break
    fi
  done
  if [[ "$skip" -eq 0 && -n "$rec" ]]; then
    UNIQ_RECS+=("$rec")
  fi
done

PASSED=0
FAILED=0
for item in "${CHECKS[@]}"; do
  [[ "${item%%|*}" == "PASS" ]] && PASSED=$((PASSED+1)) || FAILED=$((FAILED+1))
done

{
  echo "# PHASE_FLOW_VALIDATION"
  echo
  echo "Fecha: $(date -u +%Y-%m-%dT%H:%M:%SZ)"
  echo
  echo "## Resumen"
  echo "- Checks OK: $PASSED"
  echo "- Checks FAIL: $FAILED"
  echo
  echo "## Resultado por check"
  for item in "${CHECKS[@]}"; do
    status="${item%%|*}"
    desc="${item#*|}"
    if [[ "$status" == "PASS" ]]; then
      echo "- ✅ $desc"
    else
      echo "- ❌ $desc"
    fi
  done
  echo
  echo "## Recomendaciones y decisiones"
  if [[ ${#UNIQ_RECS[@]} -eq 0 ]]; then
    echo "- ✅ No se detectaron mejoras urgentes adicionales en este flujo de validación."
  else
    for rec in "${UNIQ_RECS[@]}"; do
      echo "- $rec"
    done
  fi
} > "$REPORT_PATH"

ok "Reporte generado en $REPORT_PATH"
if [[ "$FAILED" -gt 0 ]]; then
  warn "Validación completada con hallazgos (FAIL=$FAILED)."
  exit 2
fi

ok "Validación de encaje entre fases completada sin hallazgos críticos."
