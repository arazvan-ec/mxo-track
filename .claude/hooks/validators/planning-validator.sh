#!/usr/bin/env bash
# Planning phase validator (HARD gate — hardened 2026-04-07)
# Checks: plan_path exists and contains task checkboxes
# Exit 0 = pass, Exit 2 = block
set -euo pipefail

STATE_FILE="${1:-.claude/session-state.json}"
REPO="/home/user/mxo-track"

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
  echo "BLOCKED: No hay plan de implementacion (plan_path: $PLAN_PATH)."
  echo "Crea el plan (Skill 3) antes de implementar."
  exit 2
fi

PLAN_SIZE=$(wc -c < "$PLAN_FULL")
if [ "$PLAN_SIZE" -lt 300 ]; then
  echo "BLOCKED: Plan demasiado pequeno ($PLAN_SIZE bytes, minimo 300)."
  exit 2
fi

if ! grep -qiE '(Task|Step|File|Archivo|Crear|Modificar|Actualizar)' "$PLAN_FULL" 2>/dev/null; then
  echo "BLOCKED: Plan no contiene estructura minima (Task|Step|File|Archivo)."
  exit 2
fi

exit 0
