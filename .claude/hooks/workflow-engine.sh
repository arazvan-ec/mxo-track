#!/usr/bin/env bash
# Workflow Engine — PreToolUse hook for Edit|Write
#
# Central engine that:
# 1. Reads session-state.json
# 2. Checks flow_type is declared for ALL file edits
# 3. Warns if deviation is active
# 4. Routes to flow-specific validation (all flows, not just full)
# 5. Invokes appropriate phase validators
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

emit_warn() {
  local msg="$1"
  echo "{\"systemMessage\":\"$msg\"}"
}

warn() {
  emit_warn "$1"
  exit 0
}

# ── Exclusions: never gate these paths ──
case "$FILE_PATH" in
  */.claude/session-state.json|*/.claude/hooks/*|*/.claude/settings*)
    exit 0 ;;
  */node_modules/*|*/vendor/*|*/.git/*)
    exit 0 ;;
esac

# ── No state file → don't block (session-start may not have run) ──
if [ ! -f "$STATE_FILE" ]; then
  exit 0
fi

FLOW_TYPE=$(jq -r '.flow_type // "null"' "$STATE_FILE" 2>/dev/null || echo "null")
CURRENT_PHASE=$(jq -r '.current_phase // "null"' "$STATE_FILE" 2>/dev/null || echo "null")
DEVIATION_ACTIVE=$(jq -r '.deviation.active // false' "$STATE_FILE" 2>/dev/null || echo "false")

# ── Gate 1: Flow type must be declared for ALL file edits ──
if [ "$FLOW_TYPE" = "null" ]; then
  case "$FILE_PATH" in
    */backend/src/*|*/frontend/src/*|*/backend/tests/*|*/frontend/tests/*)
      deny "WORKFLOW ENGINE: Declara flow_type antes de modificar codigo. Escribe flow_type (micro|light|debug|full|explore) en .claude/session-state.json"
      ;;
    *)
      warn "WORKFLOW ENGINE: flow_type no declarado. Declara flow_type en .claude/session-state.json antes de hacer cambios."
      ;;
  esac
fi

# ── Gate 2: Deviation mode — warn but allow ──
if [ "$DEVIATION_ACTIVE" = "true" ]; then
  RETURN_TO=$(jq -r '.deviation.return_to_phase // "unknown"' "$STATE_FILE" 2>/dev/null || echo "unknown")
  warn "WORKFLOW ENGINE: Deviation activa. Retoma fase: $RETURN_TO despues de la accion actual."
fi

# ── Gate 3: Scope-change detection via interaction_id (all flows that touch code) ──
CURRENT_INTERACTION=$(jq -r '.interaction_id // 0' "$STATE_FILE" 2>/dev/null || echo "0")
EVIDENCE_INTERACTION=$(jq -r '.evidence.interaction_id // 0' "$STATE_FILE" 2>/dev/null || echo "0")
if [ "$CURRENT_INTERACTION" != "$EVIDENCE_INTERACTION" ]; then
  case "$FILE_PATH" in
    */backend/src/*|*/frontend/src/*|*/backend/tests/*|*/frontend/tests/*)
      deny "WORKFLOW ENGINE: Scope change detectado (interaction_id: $CURRENT_INTERACTION, evidence: $EVIDENCE_INTERACTION). Resetea evidence.interaction_id=$CURRENT_INTERACTION y completa las fases requeridas."
      ;;
  esac
fi

# ── Classify file for gating ──
# Returns: code, test, spec, plan, execution-log, decision, docs, config, other
classify_file() {
  case "$1" in
    */backend/src/*|*/frontend/src/*)                echo "code" ;;
    */backend/tests/*|*/frontend/tests/*)             echo "test" ;;
    */docs/superpowers/specs/*)                       echo "spec" ;;
    */docs/superpowers/plans/*)                       echo "plan" ;;
    */docs/superpowers/execution-logs/*)              echo "execution-log" ;;
    */docs/decisions/*)                               echo "decision" ;;
    */docs/knowledge/*|*/docs/FEATURES.md|*/docs/codebase-manifest.md) echo "docs" ;;
    */CLAUDE.md|*/AGENTS.md)                         echo "config" ;;
    *)                                                echo "other" ;;
  esac
}

FILE_CLASS=$(classify_file "$FILE_PATH")

# ══════════════════════════════════════════════════════════════
# Flow-specific validation
# Each flow defines which file classes require which validators
# ══════════════════════════════════════════════════════════════

get_validators_for_flow() {
  local flow="$1"
  local file_class="$2"

  case "$flow" in
    # ── Micro-flow: informational questions ──
    # Should rarely edit files. Warn on any edit except docs/config.
    micro|micro-flow)
      case "$file_class" in
        code|test)  echo "DENY:micro-flow no debe editar codigo. Reclasifica como debug o full." ;;
        spec|plan)  echo "DENY:micro-flow no debe crear specs ni plans. Reclasifica como full." ;;
        *)          echo "" ;;  # docs, config, other: pass
      esac
      ;;

    # ── Light-flow: documentation changes ──
    # Can edit docs, config. Cannot edit code/tests/specs/plans.
    light|light-flow)
      case "$file_class" in
        code|test)  echo "DENY:light-flow no debe editar codigo. Reclasifica como debug o full." ;;
        spec|plan)  echo "DENY:light-flow no debe crear specs ni plans. Reclasifica como full." ;;
        *)          echo "" ;;  # docs, config, execution-log, decision, other: pass
      esac
      ;;

    # ── Explore-flow: codebase exploration ──
    # Can write to docs/agent-outputs. Cannot edit code.
    explore|explore-flow)
      case "$file_class" in
        code|test)  echo "DENY:explore-flow no debe editar codigo. Reclasifica como debug o full." ;;
        spec|plan)  echo "DENY:explore-flow no debe crear specs ni plans. Reclasifica como full." ;;
        *)          echo "" ;;  # docs, config, other: pass
      esac
      ;;

    # ── Debug-flow: bug fixes ──
    # Must do root cause + pattern-wide investigation before touching code.
    debug|debug-flow)
      case "$file_class" in
        code|test)  echo "debug-code" ;;
        *)          echo "" ;;  # docs, config, other: pass during debug
      esac
      ;;

    # ── Full-flow: feature development ──
    # Full phase validation (existing behavior).
    full|full-flow)
      case "$file_class" in
        code|test)      echo "brainstorm planning spec-compliance implementation" ;;
        spec)           echo "consult brainstorm" ;;
        plan)           echo "brainstorm planning" ;;
        execution-log)  echo "capture" ;;
        decision)       echo "retrospective" ;;
        *)              echo "" ;;
      esac
      ;;

    *)
      echo ""
      ;;
  esac
}

VALIDATOR_SPEC=$(get_validators_for_flow "$FLOW_TYPE" "$FILE_CLASS")

# ── Handle DENY directives (flow doesn't allow this file class) ──
case "$VALIDATOR_SPEC" in
  DENY:*)
    REASON="${VALIDATOR_SPEC#DENY:}"
    deny "WORKFLOW ENGINE: $REASON"
    ;;
esac

# ── No validators needed → pass ──
if [ -z "$VALIDATOR_SPEC" ]; then
  exit 0
fi

# ── Special case: debug-flow code validation ──
if [ "$VALIDATOR_SPEC" = "debug-code" ]; then
  VALIDATOR_SCRIPT="$VALIDATORS_DIR/debug-validator.sh"
  if [ -x "$VALIDATOR_SCRIPT" ]; then
    set +e
    RESULT=$("$VALIDATOR_SCRIPT" "$STATE_FILE" "$FILE_PATH" 2>&1)
    EXIT_CODE=$?
    set -e

    if [ "$EXIT_CODE" -eq 2 ]; then
      ESCAPED_RESULT=$(echo "$RESULT" | tr '\n' ' ' | sed 's/"/\\"/g')
      deny "WORKFLOW ENGINE (debug): $ESCAPED_RESULT"
    elif [ "$EXIT_CODE" -eq 1 ]; then
      ESCAPED_RESULT=$(echo "$RESULT" | tr '\n' ' ' | sed 's/"/\\"/g')
      warn "WORKFLOW ENGINE (debug): $ESCAPED_RESULT"
    fi
  fi
  exit 0
fi

# ── Gate 4: Run validators in order (full-flow) ──
ACCUMULATED_WARNINGS=""
for validator_name in $VALIDATOR_SPEC; do
  VALIDATOR_SCRIPT="$VALIDATORS_DIR/${validator_name}-validator.sh"

  if [ ! -x "$VALIDATOR_SCRIPT" ]; then
    continue
  fi

  set +e
  RESULT=$("$VALIDATOR_SCRIPT" "$STATE_FILE" "$FILE_PATH" 2>&1)
  EXIT_CODE=$?
  set -e

  if [ "$EXIT_CODE" -eq 2 ]; then
    ESCAPED_RESULT=$(echo "$RESULT" | tr '\n' ' ' | sed 's/"/\\"/g')
    deny "WORKFLOW ENGINE ($validator_name): $ESCAPED_RESULT"
  elif [ "$EXIT_CODE" -eq 1 ]; then
    ESCAPED_RESULT=$(echo "$RESULT" | tr '\n' ' ' | sed 's/"/\\"/g')
    ACCUMULATED_WARNINGS="${ACCUMULATED_WARNINGS}WORKFLOW ENGINE ($validator_name): $ESCAPED_RESULT "
  fi
done

if [ -n "$ACCUMULATED_WARNINGS" ]; then
  emit_warn "$ACCUMULATED_WARNINGS"
fi

exit 0
