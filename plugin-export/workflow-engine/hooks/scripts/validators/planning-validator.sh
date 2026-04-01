#!/usr/bin/env bash
# Planning phase validator (SOFT gate)
# Checks: plan_path exists and contains task checkboxes
# Exit 0 = pass, Exit 1 = warn
set -euo pipefail
source "$(dirname "$0")/../config-helper.sh"

PLAN_PATH=$(jq -r '.evidence.plan_path // ""' "$STATE_FILE" 2>/dev/null || echo "")
CURRENT_PHASE=$(jq -r '.current_phase // ""' "$STATE_FILE" 2>/dev/null || echo "")

# Resolve plan file
PLAN_FULL=""
if [ -n "$PLAN_PATH" ]; then
  if [ -f "$REPO/$PLAN_PATH" ]; then
    PLAN_FULL="$REPO/$PLAN_PATH"
  elif [ -f "$PLAN_PATH" ]; then
    PLAN_FULL="$PLAN_PATH"
  fi
fi

if [ -z "$PLAN_FULL" ] || [ ! -f "$PLAN_FULL" ]; then
  if [ "$CURRENT_PHASE" = "planning" ] && [ -n "$PLAN_PATH" ]; then
    # Plan is being created — allow if plan_path is declared
    exit 0
  fi
  echo "WARNING (SOFT): No hay plan de implementacion (plan_path: $PLAN_PATH)."
  echo "Crea el plan antes de implementar."
  exit 1
fi

PLAN_SIZE=$(wc -c < "$PLAN_FULL")
if [ "$PLAN_SIZE" -lt 300 ]; then
  echo "WARNING (SOFT): Plan demasiado pequeno ($PLAN_SIZE bytes, minimo 300)."
  exit 1
fi

if ! grep -qiE '(Task|Step|File|Archivo|Crear|Modificar|Actualizar)' "$PLAN_FULL" 2>/dev/null; then
  echo "WARNING (SOFT): Plan no contiene estructura minima (Task|Step|File|Archivo)."
  exit 1
fi

exit 0
