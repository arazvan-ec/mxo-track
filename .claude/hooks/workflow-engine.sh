#!/usr/bin/env bash
# Workflow Engine — PreToolUse hook for Edit|Write|Bash
#
# Central engine that:
# 1. Reads session-state.json
# 2. Checks flow_type is declared for src/tests edits
# 3. Warns if deviation is active
# 4. Determines required phase from file path
# 5. Invokes appropriate phase validator
# 6. Hard gate failure → deny; Soft gate failure → systemMessage warning
#
# Exit codes from validators:
#   0 = pass
#   1 = warn (soft gate) → systemMessage
#   2 = block (hard gate) → deny

set -euo pipefail

REPO="/home/user/mxo-track"
STATE_FILE="$REPO/.claude/session-state.json"
VALIDATORS_DIR="$REPO/.claude/hooks/validators"

# Parse tool input from stdin
INPUT=$(cat)
TOOL_NAME=$(echo "$INPUT" | jq -r '.tool_name // ""')
FILE_PATH=$(echo "$INPUT" | jq -r '.tool_input.file_path // .tool_input.command // ""')

deny() {
  local reason="$1"
  echo "{\"hookSpecificOutput\":{\"hookEventName\":\"PreToolUse\",\"permissionDecision\":\"deny\",\"permissionDecisionReason\":\"$reason\"}}"
  exit 0
}

warn() {
  local msg="$1"
  echo "{\"systemMessage\":\"$msg\"}"
  exit 0
}

# ── No state file → don't block (session-start may not have run) ──
if [ ! -f "$STATE_FILE" ]; then
  exit 0
fi

FLOW_TYPE=$(jq -r '.flow_type // "null"' "$STATE_FILE" 2>/dev/null || echo "null")
CURRENT_PHASE=$(jq -r '.current_phase // "null"' "$STATE_FILE" 2>/dev/null || echo "null")
DEVIATION_ACTIVE=$(jq -r '.deviation.active // false' "$STATE_FILE" 2>/dev/null || echo "false")

# ── Gate 1: Flow type must be declared for code edits ──
if [ "$FLOW_TYPE" = "null" ]; then
  case "$FILE_PATH" in
    */backend/src/*|*/frontend/src/*|*/backend/tests/*|*/frontend/tests/*)
      deny "WORKFLOW ENGINE: Declara el tipo de flujo antes de modificar codigo. Escribe flow_type (micro-flow|light-flow|debug-flow|full-flow|explore-flow) en .claude/session-state.json"
      ;;
  esac
  # Non-code files pass without flow declaration
  exit 0
fi

# ── Gate 2: Deviation return enforcement ──
if [ "$DEVIATION_ACTIVE" = "true" ]; then
  RETURN_TO=$(jq -r '.deviation.return_to_phase // "unknown"' "$STATE_FILE" 2>/dev/null || echo "unknown")
  warn "WORKFLOW ENGINE: Deviation activa. Retoma fase: $RETURN_TO despues de la accion actual."
fi

# ── Flows that skip phase validation ──
case "$FLOW_TYPE" in
  micro-flow|light-flow|explore-flow|debug-flow) exit 0 ;;
esac

# ── Gate 3: Determine required phase from file path ──
determine_required_phase() {
  case "$FILE_PATH" in
    */backend/src/*|*/frontend/src/*)    echo "implementation" ;;
    */backend/tests/*|*/frontend/tests/*) echo "implementation" ;;
    */docs/superpowers/specs/*)           echo "brainstorming" ;;
    */docs/superpowers/plans/*)           echo "planning" ;;
    */docs/superpowers/execution-logs/*)  echo "capture" ;;
    */docs/decisions/*)                   echo "retrospective" ;;
    *)                                    echo "none" ;;
  esac
}

REQUIRED_PHASE=$(determine_required_phase)

if [ "$REQUIRED_PHASE" = "none" ]; then
  exit 0
fi

# ── Phase ordering for prerequisite checks ──
# For implementation: must have passed brainstorming + planning first
# For brainstorming: must have passed consult first
get_prerequisite_validators() {
  case "$1" in
    implementation)
      echo "brainstorm planning spec-compliance implementation"
      ;;
    brainstorming)
      echo "consult brainstorm"
      ;;
    planning)
      echo "brainstorm planning"
      ;;
    verification)
      echo "verification"
      ;;
    capture)
      echo "capture"
      ;;
    retrospective)
      echo "retrospective"
      ;;
    *)
      echo ""
      ;;
  esac
}

VALIDATORS=$(get_prerequisite_validators "$REQUIRED_PHASE")

# ── Gate 4: Run validators in order ──
for validator_name in $VALIDATORS; do
  VALIDATOR_SCRIPT="$VALIDATORS_DIR/${validator_name}-validator.sh"

  if [ ! -x "$VALIDATOR_SCRIPT" ]; then
    continue
  fi

  # Capture output and exit code separately (set +e to prevent script exit)
  set +e
  RESULT=$("$VALIDATOR_SCRIPT" "$STATE_FILE" 2>&1)
  EXIT_CODE=$?
  set -e

  if [ "$EXIT_CODE" -eq 2 ]; then
    # Hard gate — block
    # Escape newlines and quotes for JSON
    ESCAPED_RESULT=$(echo "$RESULT" | tr '\n' ' ' | sed 's/"/\\"/g')
    deny "WORKFLOW ENGINE ($validator_name): $ESCAPED_RESULT"
  elif [ "$EXIT_CODE" -eq 1 ]; then
    # Soft gate — warn but continue
    ESCAPED_RESULT=$(echo "$RESULT" | tr '\n' ' ' | sed 's/"/\\"/g')
    warn "WORKFLOW ENGINE ($validator_name): $ESCAPED_RESULT"
  fi
  # Exit code 0 = pass, continue to next validator
done

exit 0
