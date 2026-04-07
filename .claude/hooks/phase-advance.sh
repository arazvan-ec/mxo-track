#!/usr/bin/env bash
# Phase Advance — CLI command for legal phase transitions
#
# Usage: .claude/hooks/phase-advance.sh <next_phase>
#
# This is the ONLY sanctioned way to advance phases and write to phase_history.
# Direct jq writes to phase_history are detected and reverted by
# phase-transition-controller.sh.
#
# Legal sequence (full-flow):
#   consult → brainstorming → planning → implementation → verification → capture → retrospective → finalize
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
  echo "Phases: consult brainstorming planning implementation verification capture retrospective finalize" >&2
  exit 1
fi

# Define legal phase sequence
PHASES=("consult" "brainstorming" "planning" "implementation" "verification" "capture" "retrospective" "finalize")

# Read current state
CURRENT_PHASE=$(jq -r '.current_phase // "null"' "$STATE_FILE" 2>/dev/null || echo "null")
FLOW_TYPE=$(jq -r '.flow_type // "null"' "$STATE_FILE" 2>/dev/null || echo "null")

# Only enforce sequence for full flow
if [ "$FLOW_TYPE" != "full" ]; then
  echo "WARNING: phase-advance is designed for full flow (current: $FLOW_TYPE)" >&2
  # Still allow the transition but without sequence validation
  TIMESTAMP=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
  jq --arg phase "$NEXT_PHASE" --arg ts "$TIMESTAMP" \
    '.phase_history += [{"phase": $phase, "at": $ts}] | .current_phase = $phase' \
    "$STATE_FILE" > /tmp/pa.json && mv /tmp/pa.json "$STATE_FILE"
  echo "✅ Phase advanced: $CURRENT_PHASE → $NEXT_PHASE (non-full flow, no sequence validation)"
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
VALIDATOR=""
case "$CURRENT_PHASE" in
  brainstorming) VALIDATOR="$REPO/.claude/hooks/validators/brainstorm-validator.sh" ;;
  planning) VALIDATOR="$REPO/.claude/hooks/validators/planning-validator.sh" ;;
  retrospective) VALIDATOR="$REPO/.claude/hooks/validators/retrospective-validator.sh" ;;
esac

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
exit 0
