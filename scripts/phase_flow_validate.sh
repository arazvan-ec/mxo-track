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

run_check "Fase 3: Doctrine filter customer_tenant configurado" "rg -n 'customer_tenant' '$BACKEND_DIR/config/packages/doctrine.yaml'"
run_check "Fase 3: Subscriber activa filter por request" "rg -n 'DoctrineCustomerFilterSubscriber|KernelEvents::REQUEST' '$BACKEND_DIR/src/EventSubscriber/DoctrineCustomerFilterSubscriber.php'"
run_check "Fase 3: customer_vehicle sin public_id" "! rg -n 'public_id' '$BACKEND_DIR/src/Entity/CustomerVehicle.php'"
run_check "Fase 3: migración elimina public_id de customer_vehicle" "rg -n 'DROP COLUMN public_id|DROP INDEX IF EXISTS uniq_customer_vehicle_public_id' '$BACKEND_DIR/migrations/Version20260218000100.php'"
run_check "Fase 3: /api/mercure-token cruza ids solicitados con autorizados" "rg -n 'array_intersect|vehiclePublicIdsFor' '$BACKEND_DIR/src/Controller/MercureTokenController.php'"
run_check "Fase 3: Topic staff /operator/fleet definido" "rg -n '/operator/fleet' '$BACKEND_DIR/src/Security/TopicResolver.php'"
run_check "Fase 3: staff sin wildcard (mínimo privilegio)" "! rg -n "/\*" '$BACKEND_DIR/src/Security/TopicResolver.php'"
run_check "Fase 3: /api/vehicles usa visibilidad por asignación" "rg -n 'vehicleIdsFor\(' '$BACKEND_DIR/src/Controller/VehicleApiController.php'"

ok "Intentando verificar rutas clave"
if (
  cd "$BACKEND_DIR"
  php bin/console debug:router | rg "fleet_map|api_mercure_token|api_vehicles"
) >/dev/null 2>&1; then
  CHECKS+=("PASS|Rutas clave registradas (fleet_map, api_mercure_token, api_vehicles)")
else
  CHECKS+=("FAIL|Rutas clave registradas (fleet_map, api_mercure_token, api_vehicles)")
  RECOMMENDATIONS+=("Ejecutar composer install en backend y revisar carga de rutas para validación runtime.")
fi

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

ok "Validación Fase 3 completada sin hallazgos críticos."
