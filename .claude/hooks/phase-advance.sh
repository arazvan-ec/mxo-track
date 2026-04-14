#!/usr/bin/env bash
# Phase Advance — CLI command for legal phase transitions
#
# Usage: .claude/hooks/phase-advance.sh <next_phase>
#
# This is the ONLY sanctioned way to advance phases and write to phase_history.
# Direct jq writes to phase_history are detected and reverted by
# phase-transition-controller.sh.
#
# Legal sequences:
#   full:  consult → brainstorming → planning → implementation → verification → capture → retrospective → finalize
#   debug: root_cause → pattern_wide → fix → verification → capture → retrospective → finalize
#
# Exit codes:
#   0 = transition successful
#   1 = invalid transition (printed to stderr)

set -euo pipefail

REPO="/home/user/mxo-track"
STATE_FILE="$REPO/.claude/session-state.json"

if [ ! -f "$STATE_FILE" ]; then
  echo "ERROR: session-state.json not found" >&2
  exit 1
fi

NEXT_PHASE="${1:-}"
if [ -z "$NEXT_PHASE" ]; then
  echo "Usage: phase-advance.sh <next_phase>" >&2
  echo "Phases (full):  consult brainstorming planning implementation verification capture retrospective finalize" >&2
  echo "Phases (debug): root_cause pattern_wide fix verification capture retrospective finalize" >&2
  exit 1
fi

# Define legal phase sequences per flow type
declare -A FLOW_PHASES
FLOW_PHASES[full]="consult brainstorming planning implementation verification capture retrospective finalize"
FLOW_PHASES[debug]="root_cause pattern_wide fix verification capture retrospective finalize"

# Read current state
CURRENT_PHASE=$(jq -r '.current_phase // "null"' "$STATE_FILE" 2>/dev/null || echo "null")
FLOW_TYPE=$(jq -r '.flow_type // "null"' "$STATE_FILE" 2>/dev/null || echo "null")

# Select phase sequence for current flow type
if [ -n "${FLOW_PHASES[$FLOW_TYPE]+x}" ]; then
  read -ra PHASES <<< "${FLOW_PHASES[$FLOW_TYPE]}"
elif [ "$FLOW_TYPE" = "full" ] || [ "$FLOW_TYPE" = "debug" ]; then
  # Shouldn't reach here, but safety net
  read -ra PHASES <<< "${FLOW_PHASES[full]}"
else
  # Unrecognized flow type — allow transition without validation
  TIMESTAMP=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
  jq --arg phase "$NEXT_PHASE" --arg ts "$TIMESTAMP" \
    '.phase_history += [{"phase": $phase, "at": $ts}] | .current_phase = $phase' \
    "$STATE_FILE" > /tmp/pa.json && mv /tmp/pa.json "$STATE_FILE"
  echo "✅ Phase advanced: $CURRENT_PHASE → $NEXT_PHASE (flow: $FLOW_TYPE, no sequence validation)"
  exit 0
fi

# Find current phase index (-1 if null/not found)
CURRENT_INDEX=-1
for i in "${!PHASES[@]}"; do
  if [ "${PHASES[$i]}" = "$CURRENT_PHASE" ]; then
    CURRENT_INDEX=$i
    break
  fi
done

# Find next phase index
NEXT_INDEX=-1
for i in "${!PHASES[@]}"; do
  if [ "${PHASES[$i]}" = "$NEXT_PHASE" ]; then
    NEXT_INDEX=$i
    break
  fi
done

# Validate next phase exists
if [ "$NEXT_INDEX" -eq -1 ]; then
  echo "ERROR: '$NEXT_PHASE' is not a valid phase." >&2
  echo "Valid phases: ${PHASES[*]}" >&2
  exit 1
fi

# Validate transition is legal
# From null → only consult is allowed
if [ "$CURRENT_PHASE" = "null" ] || [ "$CURRENT_INDEX" -eq -1 ]; then
  if [ "$NEXT_PHASE" != "consult" ]; then
    echo "ERROR: From null/undeclared phase, can only advance to 'consult'. Got: '$NEXT_PHASE'" >&2
    exit 1
  fi
else
  # Must advance exactly one step forward
  EXPECTED_INDEX=$((CURRENT_INDEX + 1))
  if [ "$NEXT_INDEX" -ne "$EXPECTED_INDEX" ]; then
    if [ "$NEXT_INDEX" -le "$CURRENT_INDEX" ]; then
      echo "ERROR: Cannot go backwards. Current: '${PHASES[$CURRENT_INDEX]}' → requested: '$NEXT_PHASE'" >&2
    else
      EXPECTED_PHASE="${PHASES[$EXPECTED_INDEX]}"
      echo "ERROR: Cannot skip phases. Current: '$CURRENT_PHASE' → expected: '$EXPECTED_PHASE', got: '$NEXT_PHASE'" >&2
    fi
    exit 1
  fi
fi

# Validate evidence for the phase being LEFT (not the target phase)
# This prevents advancing with incomplete evidence (e.g., missing spec in brainstorming)
# Autodiscovery: looks for validators/${phase}-validator.sh by convention.
# Handles "brainstorming" → "brainstorm-validator.sh" via suffix stripping fallback.
VALIDATORS_DIR="$REPO/.claude/hooks/validators"
VALIDATOR=""
if [ -f "$VALIDATORS_DIR/${CURRENT_PHASE}-validator.sh" ]; then
  VALIDATOR="$VALIDATORS_DIR/${CURRENT_PHASE}-validator.sh"
elif [ -f "$VALIDATORS_DIR/${CURRENT_PHASE%ing}-validator.sh" ]; then
  VALIDATOR="$VALIDATORS_DIR/${CURRENT_PHASE%ing}-validator.sh"
fi

if [ -n "$VALIDATOR" ] && [ -f "$VALIDATOR" ]; then
  VALIDATION_OUTPUT=$("$VALIDATOR" "$STATE_FILE" 2>&1) || {
    EXIT_CODE=$?
    if [ "$EXIT_CODE" -eq 2 ]; then
      echo "ERROR: Cannot advance from '$CURRENT_PHASE' — evidence incomplete:" >&2
      echo "$VALIDATION_OUTPUT" >&2
      exit 1
    fi
    # Exit 1 = soft warning, allow advancement
  }
fi

# Perform the transition
TIMESTAMP=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
jq --arg phase "$NEXT_PHASE" --arg ts "$TIMESTAMP" --arg prev "$CURRENT_PHASE" \
  '.phase_history += [{"phase": $prev, "at": $ts}] | .current_phase = $phase' \
  "$STATE_FILE" > /tmp/pa.json && mv /tmp/pa.json "$STATE_FILE"

echo "✅ Phase advanced: $CURRENT_PHASE → $NEXT_PHASE (at $TIMESTAMP)"

# Retrospective reminder when entering that phase
if [ "$NEXT_PHASE" = "retrospective" ]; then
  cat <<'RETRO'
📋 RETROSPECTIVE — Presentar al usuario ANTES de escribir al execution log:
  1. Estimate accuracy: estimado vs. real (líneas, archivos, tiempo)
  2. Process gap: ¿qué permitió que algo saliera mal o se desviara?
  3. Emergent patterns: ¿algún patrón nuevo? (si 3+ ocurrencias → graduar a knowledge module)
RETRO
fi

# Auto-init plan progress when entering implementation (non-blocking)
if [ "$NEXT_PHASE" = "implementation" ]; then
  CURRENT_TOTAL=$(jq -r '.evidence.task_progress.total // 0' "$STATE_FILE" 2>/dev/null || echo "0")
  PLAN_PATH=$(jq -r '.evidence.plan_path // ""' "$STATE_FILE" 2>/dev/null || echo "")
  if [ "$CURRENT_TOTAL" = "0" ] && [ -n "$PLAN_PATH" ] && [ -f "$REPO/$PLAN_PATH" ]; then
    bash "$REPO/.claude/hooks/plan-progress.sh" init 2>&1 | sed 's/^/  [auto-init] /' || true
  fi
fi

exit 0
