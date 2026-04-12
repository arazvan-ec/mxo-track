#!/usr/bin/env bash
# Brainstorm phase validator (HARD gate — hardened 2026-04-07)
# Checks: user_turns >= 1, alternatives_proposed, user_approved, spec >= 500B
# Exit 0 = pass, Exit 2 = block (critical checks), Exit 1 = warn (soft checks)
set -euo pipefail

STATE_FILE="${1:-.claude/session-state.json}"
REPO="/home/user/mxo-track"

USER_TURNS=$(jq -r '.evidence.user_turns // 0' "$STATE_FILE" 2>/dev/null || echo "0")
ALTERNATIVES=$(jq -r '.evidence.alternatives_proposed // false' "$STATE_FILE" 2>/dev/null || echo "false")
USER_APPROVED=$(jq -r '.evidence.user_approved // false' "$STATE_FILE" 2>/dev/null || echo "false")
SPEC_PATH=$(jq -r '.evidence.spec_path // ""' "$STATE_FILE" 2>/dev/null || echo "")
CURRENT_PHASE=$(jq -r '.current_phase // ""' "$STATE_FILE" 2>/dev/null || echo "")

ERRORS=""
WARNINGS=""

if [ "$USER_TURNS" -lt 1 ]; then
  ERRORS="${ERRORS}- Brainstorming requiere al menos 1 turno de dialogo con el usuario (actual: $USER_TURNS)\n"
fi

# Soft warning for < 3 turns (relaxed from HARD >= 3 per harness evolution 2026-03-24)
if [ "$USER_TURNS" -ge 1 ] && [ "$USER_TURNS" -lt 3 ]; then
  WARNINGS="${WARNINGS}- SOFT: Brainstorming con $USER_TURNS turno(s). Considera mas dialogo si el scope es complejo.\n"
fi

if [ "$ALTERNATIVES" != "true" ]; then
  ERRORS="${ERRORS}- No se han propuesto alternativas de diseno\n"
fi

if [ "$USER_APPROVED" != "true" ]; then
  ERRORS="${ERRORS}- El usuario no ha aprobado el diseno\n"
fi

# Check spec file exists and has sufficient content.
# The spec must exist as a real file before advancing out of brainstorming.
SPEC_FULL=""
if [ -n "$SPEC_PATH" ]; then
  if [ -f "$REPO/$SPEC_PATH" ]; then
    SPEC_FULL="$REPO/$SPEC_PATH"
  elif [ -f "$SPEC_PATH" ]; then
    SPEC_FULL="$SPEC_PATH"
  fi
fi

if [ -z "$SPEC_FULL" ] || [ ! -f "$SPEC_FULL" ]; then
  ERRORS="${ERRORS}- No existe spec document (spec_path: $SPEC_PATH). Escribe el spec antes de avanzar.\n"
else
  SIZE=$(wc -c < "$SPEC_FULL")
  if [ "$SIZE" -lt 500 ]; then
    ERRORS="${ERRORS}- Spec demasiado pequeno ($SIZE bytes, minimo 500)\n"
  fi
  if ! grep -qiE '(Approach|Alternativa|Trade-off|Problema|Ventaja|Desventaja|Opcion)' "$SPEC_FULL" 2>/dev/null; then
    ERRORS="${ERRORS}- Spec no contiene keywords de brainstorming (Approach|Alternativa|Trade-off|Problema|Ventaja|Desventaja|Opcion)\n"
  fi

  # Anti-Omission Gate: Every spec must inventory existing functionality
  if ! grep -qE '## Existing Functionality Inventory' "$SPEC_FULL" 2>/dev/null; then
    ERRORS="${ERRORS}- ANTI-OMISION: Falta seccion '## Existing Functionality Inventory' (si no hay funcionalidad existente afectada, declarar 'No existing functionality affected')\n"
  fi
  if ! grep -qE '## Omission Decisions' "$SPEC_FULL" 2>/dev/null; then
    ERRORS="${ERRORS}- ANTI-OMISION: Falta seccion '## Omission Decisions' (si no hay omisiones, declarar 'No omissions — all inventory items addressed')\n"
  fi

  # TDD task isolation: plan must not have standalone "add tests" tasks
  PLAN_PATH_VAL=$(jq -r '.evidence.plan_path // ""' "$STATE_FILE" 2>/dev/null || echo "")
  PLAN_FULL=""
  if [ -n "$PLAN_PATH_VAL" ]; then
    if [ -f "$REPO/$PLAN_PATH_VAL" ]; then
      PLAN_FULL="$REPO/$PLAN_PATH_VAL"
    elif [ -f "$PLAN_PATH_VAL" ]; then
      PLAN_FULL="$PLAN_PATH_VAL"
    fi
  fi
  if [ -n "$PLAN_FULL" ] && [ -f "$PLAN_FULL" ]; then
    if grep -iEn '^[-*]\s.*(add|write|create|agregar|escribir)\s+(unit\s+)?tests?\b' "$PLAN_FULL" 2>/dev/null | grep -ivE '(TDD|test.*implement|implement.*test|red.green|failing test)' > /dev/null 2>&1; then
      WARNINGS="${WARNINGS}- TDD: El plan tiene tareas standalone de tests. Los tests deben ser parte integral de cada tarea (TDD: test first → implement → green).\n"
    fi
  fi
fi

if [ -n "$ERRORS" ]; then
  echo "BLOCKED: Brainstorming incompleto:"
  echo -e "$ERRORS"
  echo "Completa el brainstorming (Skill 2) antes de continuar."
  exit 2
fi

# Soft warnings (exit 1 = warn but allow)
if [ -n "$WARNINGS" ]; then
  echo -e "$WARNINGS"
  exit 1
fi

exit 0
