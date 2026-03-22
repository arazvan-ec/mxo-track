#!/usr/bin/env bash
# Full-flow gate hook: blocks Edit/Write to source code unless
# the full workflow has been followed.
#
# Checks (sequential — first failure stops):
# 1. session-state.json exists and flow_declared is true
# 2. For full-flow: learning_loop_done is true
# 3. For full-flow or debug-flow: brainstorm_done is true (full) or learning_loop_done (debug)
# 4. .claude/active-spec file exists (set during brainstorming)
# 5. The spec file it references actually exists
# 6. A plan file for today exists in docs/superpowers/plans/
#
# Self-gating (full-flow only):
# 7. Nivel 1: Spec ≥500 bytes + keywords reales; Plan ≥300 bytes + estructura
# 8. Nivel 2: brainstorm_user_turns ≥ 2 (ida y vuelta real con usuario)
# 9. Nivel 3: Archivo editado debe estar mencionado en el plan
#
# Bypasses:
# - Files outside frontend/src and backend/src (docs, tests, config, migrations, etc.)
# - flow_type "micro" or "light" skip checks 4-6 (no spec/plan needed)
#
# To pass this gate:
#   1. Initialize session state: write flow_type + flow_declared to .claude/session-state.json
#   2. For full-flow: mark learning_loop_done and brainstorm_done
#   3. Brainstorm (Skill 2) -> write spec
#   4. echo "docs/superpowers/specs/YYYY-MM-DD-slug-design.md" > .claude/active-spec
#   5. Plan (Skill 3) -> write plan in docs/superpowers/plans/
#   6. Now you can edit source code

set -euo pipefail

INPUT=$(cat)
FILE_PATH=$(echo "$INPUT" | jq -r '.tool_input.file_path // empty')

# Only gate frontend/src and backend/src edits
case "$FILE_PATH" in
  */frontend/src/*|*/backend/src/*) ;;
  *) exit 0 ;;
esac

REPO="/home/user/mxo-track"
STATE_FILE="$REPO/.claude/session-state.json"
ACTIVE_SPEC_FILE="$REPO/.claude/active-spec"
TODAY=$(date +%Y-%m-%d)
PLANS_DIR="$REPO/docs/superpowers/plans"

deny() {
  local reason="$1"
  echo "{\"hookSpecificOutput\":{\"hookEventName\":\"PreToolUse\",\"permissionDecision\":\"deny\",\"permissionDecisionReason\":\"$reason\"}}"
  exit 0
}

# ── Check 1: session-state.json exists and flow is declared ──

if [ ! -f "$STATE_FILE" ]; then
  deny "FULL-FLOW GATE: No session state found. Classify the interaction first: write flow_type and set flow_declared=true in .claude/session-state.json. See CLAUDE.md 'Flujo Obligatorio para Toda Interaccion'."
fi

FLOW_DECLARED=$(jq -r '.flow_declared // false' "$STATE_FILE" 2>/dev/null || echo "false")
if [ "$FLOW_DECLARED" != "true" ]; then
  deny "FULL-FLOW GATE: Flow not declared. Before editing source code, classify the interaction type (micro/light/debug/full) and set flow_declared=true in .claude/session-state.json."
fi

FLOW_TYPE=$(jq -r '.flow_type // "unknown"' "$STATE_FILE" 2>/dev/null || echo "unknown")

# ── Check 2: For full-flow — learning loop must be done ──

if [ "$FLOW_TYPE" = "full" ]; then
  LEARNING_LOOP=$(jq -r '.learning_loop_done // false' "$STATE_FILE" 2>/dev/null || echo "false")
  if [ "$LEARNING_LOOP" != "true" ]; then
    deny "FULL-FLOW GATE: Learning Loop not done. Before brainstorming, read docs/decisions/log.md and scan recent execution-logs and retrospectives. Then set learning_loop_done=true in .claude/session-state.json."
  fi
fi

# ── Check 3: For full-flow — brainstorming must be done ──

if [ "$FLOW_TYPE" = "full" ]; then
  BRAINSTORM=$(jq -r '.brainstorm_done // false' "$STATE_FILE" 2>/dev/null || echo "false")
  if [ "$BRAINSTORM" != "true" ]; then
    deny "FULL-FLOW GATE: Brainstorming not done. Invoke Skill 2 (brainstorming), propose 2-3 approaches, get user approval, write spec. Then set brainstorm_done=true in .claude/session-state.json."
  fi
fi

# ── For micro/light flows — no spec/plan needed ──

if [ "$FLOW_TYPE" = "micro" ] || [ "$FLOW_TYPE" = "light" ]; then
  exit 0
fi

# ── Check 4: active-spec file exists ──

if [ ! -f "$ACTIVE_SPEC_FILE" ]; then
  deny "FULL-FLOW GATE: No active spec registered. Write spec (Skill 2) then: echo 'docs/superpowers/specs/YYYY-MM-DD-slug-design.md' > .claude/active-spec"
fi

# ── Check 5: the referenced spec file exists ──

SPEC_PATH=$(tr -d '[:space:]' < "$ACTIVE_SPEC_FILE")
if [ -z "$SPEC_PATH" ]; then
  deny "FULL-FLOW GATE: .claude/active-spec is empty. Write the spec path into it."
fi

# Support both relative (from repo root) and absolute paths
if [ -f "$REPO/$SPEC_PATH" ]; then
  : # ok
elif [ -f "$SPEC_PATH" ]; then
  : # ok
else
  deny "FULL-FLOW GATE: active-spec points to '$SPEC_PATH' which does not exist. Create the spec first (Skill 2)."
fi

# ── Check 6: a plan file for today exists ──

# Prefer active_plan from session state; fall back to any plan for today
ACTIVE_PLAN=$(jq -r '.active_plan // empty' "$STATE_FILE" 2>/dev/null || true)
PLAN_FILE=""

if [ -n "$ACTIVE_PLAN" ]; then
  if [ -f "$REPO/$ACTIVE_PLAN" ]; then
    PLAN_FILE="$REPO/$ACTIVE_PLAN"
  elif [ -f "$ACTIVE_PLAN" ]; then
    PLAN_FILE="$ACTIVE_PLAN"
  fi
fi

# Fallback: find any plan for today
if [ -z "$PLAN_FILE" ]; then
  for f in "$PLANS_DIR"/${TODAY}-*.md; do
    if [ -f "$f" ]; then
      PLAN_FILE="$f"
      break
    fi
  done
fi

if [ -z "$PLAN_FILE" ]; then
  deny "FULL-FLOW GATE: Spec registered but no plan found for today ($TODAY) in docs/superpowers/plans/. Write a plan (Skill 3) before implementing."
fi

# ── Self-gating Nivel 1: Evidencia verificable en spec y plan ──

if [ "$FLOW_TYPE" = "full" ]; then
  # Spec must have real content (≥500 bytes) and brainstorming keywords
  SPEC_FULL_PATH="$REPO/$SPEC_PATH"
  [ ! -f "$SPEC_FULL_PATH" ] && SPEC_FULL_PATH="$SPEC_PATH"

  SPEC_SIZE=$(wc -c < "$SPEC_FULL_PATH" 2>/dev/null || echo 0)
  if [ "$SPEC_SIZE" -lt 500 ]; then
    deny "SELF-GATE Nivel 1: Spec '$SPEC_PATH' tiene solo ${SPEC_SIZE} bytes (minimo 500). Un brainstorming real produce un spec mas sustancial."
  fi

  if ! grep -qiE '(Approach|Alternativa|Trade-off|Problema|Ventaja|Desventaja|Opcion)' "$SPEC_FULL_PATH" 2>/dev/null; then
    deny "SELF-GATE Nivel 1: Spec '$SPEC_PATH' no contiene keywords de brainstorming real (Approach|Alternativa|Trade-off|Problema|Ventaja|Desventaja|Opcion). Asegurate de documentar alternativas evaluadas."
  fi

  # Plan must have real content (≥300 bytes) and structural keywords
  PLAN_SIZE=$(wc -c < "$PLAN_FILE" 2>/dev/null || echo 0)
  if [ "$PLAN_SIZE" -lt 300 ]; then
    deny "SELF-GATE Nivel 1: Plan '$(basename "$PLAN_FILE")' tiene solo ${PLAN_SIZE} bytes (minimo 300). Un plan real tiene tareas con archivos y pasos."
  fi

  if ! grep -qiE '(Task|Step|File|Archivo|Crear|Modificar|Actualizar)' "$PLAN_FILE" 2>/dev/null; then
    deny "SELF-GATE Nivel 1: Plan '$(basename "$PLAN_FILE")' no contiene estructura minima (Task|Step|File|Archivo). Escribe un plan con tareas concretas."
  fi
fi

# ── Self-gating Nivel 2: Turnos de conversacion durante brainstorming ──

if [ "$FLOW_TYPE" = "full" ]; then
  BRAINSTORM_TURNS=$(jq -r '.brainstorm_user_turns // 0' "$STATE_FILE" 2>/dev/null || echo 0)
  if [ "$BRAINSTORM_TURNS" -lt 2 ]; then
    deny "SELF-GATE Nivel 2: Brainstorming tuvo solo ${BRAINSTORM_TURNS} turnos de conversacion. Minimo 2 turnos de ida y vuelta con el usuario (proponer approaches -> usuario elige -> refinar)."
  fi
fi

# ── Self-gating Nivel 3: Coherencia plan<->edit ──

if [ "$FLOW_TYPE" = "full" ] && [ -n "$PLAN_FILE" ] && [ -n "$FILE_PATH" ]; then
  EDIT_BASENAME=$(basename "$FILE_PATH")
  if ! grep -qiF "$EDIT_BASENAME" "$PLAN_FILE" 2>/dev/null; then
    deny "SELF-GATE Nivel 3: Archivo '${EDIT_BASENAME}' no esta mencionado en el plan '$(basename "$PLAN_FILE")'. Actualiza el plan antes de editar archivos no planificados."
  fi
fi

# All checks passed
exit 0
