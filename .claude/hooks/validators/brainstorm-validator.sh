#!/usr/bin/env bash
# Brainstorm phase validator (HARD gate)
# Checks: user_turns >= 3, alternatives_proposed, user_approved, spec >= 500B
# Exit 0 = pass, Exit 2 = block
set -euo pipefail

STATE_FILE="${1:-.claude/session-state.json}"
REPO="/home/user/mxo-track"

USER_TURNS=$(jq -r '.evidence.user_turns // 0' "$STATE_FILE" 2>/dev/null || echo "0")
ALTERNATIVES=$(jq -r '.evidence.alternatives_proposed // false' "$STATE_FILE" 2>/dev/null || echo "false")
USER_APPROVED=$(jq -r '.evidence.user_approved // false' "$STATE_FILE" 2>/dev/null || echo "false")
SPEC_PATH=$(jq -r '.evidence.spec_path // ""' "$STATE_FILE" 2>/dev/null || echo "")
CURRENT_PHASE=$(jq -r '.current_phase // ""' "$STATE_FILE" 2>/dev/null || echo "")

ERRORS=""

if [ "$USER_TURNS" -lt 3 ]; then
  ERRORS="${ERRORS}- Brainstorming requiere >= 3 turnos de dialogo (actual: $USER_TURNS)\n"
fi

if [ "$ALTERNATIVES" != "true" ]; then
  ERRORS="${ERRORS}- No se han propuesto alternativas de diseno\n"
fi

if [ "$USER_APPROVED" != "true" ]; then
  ERRORS="${ERRORS}- El usuario no ha aprobado el diseno\n"
fi

# Check spec file exists and has sufficient content
# Exception: if current_phase is "brainstorming", the spec is being created right now.
# In that case, only require spec_path is declared (not that the file exists).
SPEC_FULL=""
if [ -n "$SPEC_PATH" ]; then
  if [ -f "$REPO/$SPEC_PATH" ]; then
    SPEC_FULL="$REPO/$SPEC_PATH"
  elif [ -f "$SPEC_PATH" ]; then
    SPEC_FULL="$SPEC_PATH"
  fi
fi

if [ -z "$SPEC_FULL" ] || [ ! -f "$SPEC_FULL" ]; then
  if [ "$CURRENT_PHASE" = "brainstorming" ] && [ -n "$SPEC_PATH" ]; then
    # Spec is being created — allow if spec_path is declared and other evidence is valid
    :
  else
    ERRORS="${ERRORS}- No existe spec document (spec_path: $SPEC_PATH)\n"
  fi
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
fi

if [ -n "$ERRORS" ]; then
  echo "BLOCKED: Brainstorming incompleto:"
  echo -e "$ERRORS"
  echo "Completa el brainstorming (Skill 2) antes de continuar."
  exit 2
fi

exit 0
