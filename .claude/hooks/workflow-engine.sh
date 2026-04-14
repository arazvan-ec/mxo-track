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

# Shared file classification (single source of truth)
source "$REPO/.claude/hooks/lib/classify-file.sh"

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

# ── Classify file for gating (uses shared lib/classify-file.sh) ──
FILE_CLASS=$(classify_file "$FILE_PATH")

# ── Gate 1: Flow type must be declared AND valid ──
if [ "$FLOW_TYPE" = "null" ]; then
  case "$FILE_CLASS" in
    code|test)
      deny "❌ BLOQUEADO [flow no declarado] Archivo: $FILE_CLASS | No puedes editar codigo sin declarar flow_type. | Accion: escribe flow_type (micro|light|debug|full|explore) en .claude/session-state.json"
      ;;
    *)
      warn "⚠ [flow no declarado] Archivo: $FILE_CLASS | Declara flow_type en session-state.json antes de continuar."
      ;;
  esac
elif ! is_valid_flow_type "$FLOW_TYPE"; then
  case "$FILE_CLASS" in
    code|test)
      deny "❌ BLOQUEADO [flow_type invalido: '$FLOW_TYPE'] Archivo: $FILE_CLASS | Valores validos: micro, light, debug, full, explore. | Un code change requiere flow_type='full'."
      ;;
    *)
      warn "⚠ [flow_type invalido: '$FLOW_TYPE'] Valores validos: micro, light, debug, full, explore."
      ;;
  esac
fi

# ── Gate 2: Deviation mode — warn but allow ──
if [ "$DEVIATION_ACTIVE" = "true" ]; then
  RETURN_TO=$(jq -r '.deviation.return_to_phase // "unknown"' "$STATE_FILE" 2>/dev/null || echo "unknown")
  warn "⚠ DESVIO ACTIVO [$FLOW_TYPE | $CURRENT_PHASE] Retoma fase '$RETURN_TO' despues de la accion actual."
fi

# ── Gate 3: Scope-change detection via interaction_id (all flows that touch code) ──
CURRENT_INTERACTION=$(jq -r '.interaction_id // 0' "$STATE_FILE" 2>/dev/null || echo "0")
EVIDENCE_INTERACTION=$(jq -r '.evidence.interaction_id // 0' "$STATE_FILE" 2>/dev/null || echo "0")
if [ "$CURRENT_INTERACTION" != "$EVIDENCE_INTERACTION" ]; then
  case "$FILE_CLASS" in
    code|test)
      warn "⚠ SCOPE CHANGE [$FLOW_TYPE | $CURRENT_PHASE] interaction_id=$CURRENT_INTERACTION pero evidence=$EVIDENCE_INTERACTION | Accion: resetea evidence.interaction_id y completa fases requeridas."
      ;;
  esac
fi

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
        code|test)  echo "DENY:❌ [micro-flow] No puede editar $file_class. | Accion: reclasifica como debug (bug) o full (feature)." ;;
        spec|plan)  echo "DENY:❌ [micro-flow] No puede crear specs/plans. | Accion: reclasifica como full." ;;
        *)          echo "" ;;  # docs, config, other: pass
      esac
      ;;

    # ── Light-flow: documentation changes ──
    # Can edit docs, config. Cannot edit code/tests/specs/plans.
    light|light-flow)
      case "$file_class" in
        code|test)  echo "DENY:❌ [light-flow] No puede editar $file_class. | Accion: reclasifica como debug (bug) o full (feature)." ;;
        spec|plan)  echo "DENY:❌ [light-flow] No puede crear specs/plans. | Accion: reclasifica como full." ;;
        *)          echo "" ;;  # docs, config, execution-log, decision, other: pass
      esac
      ;;

    # ── Explore-flow: codebase exploration ──
    # Can write to docs/agent-outputs. Cannot edit code.
    explore|explore-flow)
      case "$file_class" in
        code|test)  echo "DENY:❌ [explore-flow] No puede editar $file_class. | Accion: reclasifica como debug (bug) o full (feature)." ;;
        spec|plan)  echo "DENY:❌ [explore-flow] No puede crear specs/plans. | Accion: reclasifica como full." ;;
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

    # ── Agent-flow: sub-agent executing pre-planned work ──
    # No validators — main agent already completed spec/plan/brainstorm.
    # All file classes allowed. See AGENTS.md "Light Agent Mode".
    agent|agent-flow)
      echo ""
      ;;

    # ── Agent-flow: sub-agent executing pre-planned work ──
    # No validators — main agent already completed spec/plan/brainstorm.
    # All file classes allowed. See AGENTS.md "Light Agent Mode".
    agent|agent-flow)
      echo ""
      ;;

    # ── Full-flow: feature development ──
    # Full phase validation (existing behavior).
    # ── Full-flow validator routing ──
    # Design principle: file gates check PREREQUISITES (prior phases complete),
    # NOT current phase completion. A spec is the OUTPUT of brainstorming — running
    # brainstorm-validator when writing it creates a circular dependency (validator
    # requires spec to exist, but we're creating it). Phase completion is enforced
    # by phase-advance.sh when LEAVING the phase.
    #
    # spec → only consult (brainstorm checked on phase advance)
    # plan → only brainstorm (planning checked on phase advance)
    # code → full chain (brainstorm + planning must be complete before code)
    full|full-flow)
      case "$file_class" in
        code|test)      echo "brainstorm planning spec-compliance implementation" ;;
        spec)           echo "consult" ;;
        plan)           echo "brainstorm" ;;
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
      deny "❌ BLOQUEADO [debug | $CURRENT_PHASE] Archivo: $FILE_CLASS | $ESCAPED_RESULT"
    elif [ "$EXIT_CODE" -eq 1 ]; then
      ESCAPED_RESULT=$(echo "$RESULT" | tr '\n' ' ' | sed 's/"/\\"/g')
      warn "⚠ [debug | $CURRENT_PHASE] Archivo: $FILE_CLASS | $ESCAPED_RESULT"
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
    deny "❌ BLOQUEADO [$FLOW_TYPE | $CURRENT_PHASE | gate:$validator_name] Archivo: $FILE_CLASS | $ESCAPED_RESULT"
  elif [ "$EXIT_CODE" -eq 1 ]; then
    ESCAPED_RESULT=$(echo "$RESULT" | tr '\n' ' ' | sed 's/"/\\"/g')
    ACCUMULATED_WARNINGS="${ACCUMULATED_WARNINGS}⚠ [$FLOW_TYPE | $CURRENT_PHASE | gate:$validator_name] $ESCAPED_RESULT "
  fi
done

if [ -n "$ACCUMULATED_WARNINGS" ]; then
  emit_warn "$ACCUMULATED_WARNINGS"
fi

exit 0
