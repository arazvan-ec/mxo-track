#!/usr/bin/env bash
# PostToolUse hook — generates a detailed workflow status for Claude to display.
# Reads session-state.json evidence fields, writes .claude/workflow-status-line.txt
# Enhanced 2026-03-24: shows per-phase evidence and current phase needs.
# Enhanced 2026-04-07: adds tool context suffix to avoid identical repeated lines.
# Non-blocking: always exits 0.

set -euo pipefail

REPO="/home/user/mxo-track"
STATE_FILE="$REPO/.claude/session-state.json"
OUTPUT="$REPO/.claude/workflow-status-line.txt"
# --- Read tool context from stdin (PostToolUse JSON) ---
TOOL_SUFFIX=""
if INPUT=$(cat 2>/dev/null) && [ -n "$INPUT" ]; then
  TOOL_NAME=$(echo "$INPUT" | jq -r '.tool_name // empty' 2>/dev/null || true)
  if [ -n "$TOOL_NAME" ]; then
    # Extract a short descriptor based on tool type
    case "$TOOL_NAME" in
      Read|Write|Edit)
        TOOL_TARGET=$(echo "$INPUT" | jq -r '.tool_input.file_path // empty' 2>/dev/null || true)
        if [ -n "$TOOL_TARGET" ]; then
          # Show last 2 path components (e.g. "Entity/Vehicle.php")
          TOOL_TARGET=$(echo "$TOOL_TARGET" | awk -F/ '{if(NF>=2) print $(NF-1)"/"$NF; else print $NF}')
          TOOL_SUFFIX=" · ${TOOL_NAME} ${TOOL_TARGET}"
        else
          TOOL_SUFFIX=" · ${TOOL_NAME}"
        fi
        ;;
      Bash)
        TOOL_CMD=$(echo "$INPUT" | jq -r '.tool_input.command // empty' 2>/dev/null || true)
        if [ -n "$TOOL_CMD" ]; then
          # First 30 chars of command
          TOOL_CMD="${TOOL_CMD:0:30}"
          TOOL_SUFFIX=" · Bash: ${TOOL_CMD}"
        else
          TOOL_SUFFIX=" · Bash"
        fi
        ;;
      Grep)
        TOOL_PATTERN=$(echo "$INPUT" | jq -r '.tool_input.pattern // empty' 2>/dev/null || true)
        [ -n "$TOOL_PATTERN" ] && TOOL_SUFFIX=" · Grep: ${TOOL_PATTERN:0:25}" || TOOL_SUFFIX=" · Grep"
        ;;
      Glob)
        TOOL_PATTERN=$(echo "$INPUT" | jq -r '.tool_input.pattern // empty' 2>/dev/null || true)
        [ -n "$TOOL_PATTERN" ] && TOOL_SUFFIX=" · Glob: ${TOOL_PATTERN:0:25}" || TOOL_SUFFIX=" · Glob"
        ;;
      Agent)
        TOOL_DESC=$(echo "$INPUT" | jq -r '.tool_input.description // empty' 2>/dev/null || true)
        [ -n "$TOOL_DESC" ] && TOOL_SUFFIX=" · Agent: ${TOOL_DESC:0:25}" || TOOL_SUFFIX=" · Agent"
        ;;
      *)
        TOOL_SUFFIX=" · ${TOOL_NAME}"
        ;;
    esac
  fi
fi

# Graceful fallback
if [ ! -f "$STATE_FILE" ]; then
  echo "📍 status unavailable${TOOL_SUFFIX}" > "$OUTPUT"
  cat "$OUTPUT"
  exit 0
fi

# Read all state at once
STATE=$(cat "$STATE_FILE" 2>/dev/null || echo "{}")
FLOW_TYPE=$(echo "$STATE" | jq -r '.flow_type // "null"')
CURRENT_PHASE=$(echo "$STATE" | jq -r '.current_phase // "null"')
DEV_ACTIVE=$(echo "$STATE" | jq -r '.deviation.active // false')

# Evidence fields
DECISIONS_READ=$(echo "$STATE" | jq -r '.evidence.decisions_read // false')
LOGS_SCANNED=$(echo "$STATE" | jq -r '.evidence.logs_scanned // false')
USER_TURNS=$(echo "$STATE" | jq -r '.evidence.user_turns // 0')
ALTERNATIVES=$(echo "$STATE" | jq -r '.evidence.alternatives_proposed // false')
USER_APPROVED=$(echo "$STATE" | jq -r '.evidence.user_approved // false')
SPEC_PATH=$(echo "$STATE" | jq -r '.evidence.spec_path // ""')
PLAN_PATH=$(echo "$STATE" | jq -r '.evidence.plan_path // ""')
TESTS_WRITTEN=$(echo "$STATE" | jq -r '.evidence.tests_written // 0')
TESTS_PASSED=$(echo "$STATE" | jq -r '.evidence.tests_passed // "null"')
LINT_CLEAN=$(echo "$STATE" | jq -r '.evidence.lint_clean // "null"')
EXEC_LOG=$(echo "$STATE" | jq -r '.evidence.execution_log_path // ""')
BRANCH_STRATEGY=$(echo "$STATE" | jq -r '.evidence.branch_strategy // ""')
ROOT_CAUSE=$(echo "$STATE" | jq -r '.evidence.root_cause_identified // false')
PATTERN_WIDE=$(echo "$STATE" | jq -r '.evidence.pattern_wide_search_done // false')

# Task progress fields
TASK_CURRENT=$(echo "$STATE" | jq -r '.evidence.task_progress.current // 0')
TASK_TOTAL=$(echo "$STATE" | jq -r '.evidence.task_progress.total // 0')
TASK_LABEL=$(echo "$STATE" | jq -r '.evidence.task_progress.label // ""')
TASK_COMPLETED=$(echo "$STATE" | jq -r '.evidence.task_progress.completed_labels // [] | length')

DEVIATION_SUFFIX=""
if [ "$DEV_ACTIVE" = "true" ]; then
  DEVIATION_SUFFIX=" | ⚠ DESVÍO"
fi

# Helper: basename or empty
base_name() {
  if [ -n "$1" ]; then
    basename "$1" 2>/dev/null || echo "$1"
  fi
}

# Helper: build evidence tag for a completed phase
# Returns abbreviated evidence string
phase_evidence() {
  local phase="$1"
  case "$phase" in
    consult)
      local ev=""
      [ "$DECISIONS_READ" = "true" ] && ev="dec"
      [ "$LOGS_SCANNED" = "true" ] && { [ -n "$ev" ] && ev="$ev+log" || ev="log"; }
      [ -n "$ev" ] && echo "(${ev})" || echo "(—)"
      ;;
    brainstorming)
      local ev="t${USER_TURNS}"
      [ "$ALTERNATIVES" = "true" ] && ev="$ev,alt"
      [ "$USER_APPROVED" = "true" ] && ev="$ev,ok"
      local sp=$(base_name "$SPEC_PATH")
      [ -n "$sp" ] && ev="$ev,${sp:0:20}"
      echo "(${ev})"
      ;;
    planning)
      local sp=$(base_name "$PLAN_PATH")
      [ -n "$sp" ] && echo "(${sp:0:25})" || echo "(—)"
      ;;
    implementation)
      if [ "$TASK_TOTAL" -gt 0 ] 2>/dev/null; then
        echo "(t${TASK_CURRENT}/${TASK_TOTAL},tests:${TESTS_WRITTEN})"
      else
        echo "(tests:${TESTS_WRITTEN})"
      fi
      ;;
    verification)
      local ev=""
      [ "$TESTS_PASSED" = "true" ] && ev="tests✓" || ev="tests?"
      [ "$LINT_CLEAN" = "true" ] && ev="$ev,lint✓" || ev="$ev,lint?"
      echo "(${ev})"
      ;;
    capture)
      local sp=$(base_name "$EXEC_LOG")
      [ -n "$sp" ] && echo "(${sp:0:25})" || echo "(—)"
      ;;
    retrospective)
      echo ""
      ;;
    finalize)
      [ -n "$BRANCH_STRATEGY" ] && echo "(${BRANCH_STRATEGY})" || echo "(—)"
      ;;
    *)
      echo ""
      ;;
  esac
}

# Helper: what does the current phase still need?
phase_needs() {
  local phase="$1"
  case "$phase" in
    consult)
      local needs=""
      [ "$DECISIONS_READ" != "true" ] && [ "$LOGS_SCANNED" != "true" ] && needs="read decisions or logs"
      [ -n "$needs" ] && echo " | Need: $needs"
      ;;
    brainstorming)
      local needs=""
      [ "$USER_TURNS" -lt 1 ] 2>/dev/null && needs="user dialog"
      [ "$ALTERNATIVES" != "true" ] && { [ -n "$needs" ] && needs="$needs, alternatives" || needs="alternatives"; }
      [ "$USER_APPROVED" != "true" ] && { [ -n "$needs" ] && needs="$needs, approval" || needs="approval"; }
      [ -z "$SPEC_PATH" ] && { [ -n "$needs" ] && needs="$needs, spec" || needs="spec"; }
      [ -n "$needs" ] && echo " | Need: $needs"
      ;;
    planning)
      [ -z "$PLAN_PATH" ] && echo " | Need: plan document"
      ;;
    implementation)
      local parts=""
      if [ "$TASK_TOTAL" -gt 0 ] 2>/dev/null && [ "$TASK_CURRENT" -gt 0 ] 2>/dev/null; then
        parts="Tarea ${TASK_CURRENT}/${TASK_TOTAL}"
        [ -n "$TASK_LABEL" ] && parts="${parts}: ${TASK_LABEL}"
      fi
      [ "$TESTS_WRITTEN" -eq 0 ] 2>/dev/null && { [ -n "$parts" ] && parts="$parts — tests first (TDD)" || parts="tests first (TDD)"; }
      [ -n "$parts" ] && echo " | ${parts}"
      ;;
    verification)
      local needs=""
      [ "$TESTS_PASSED" != "true" ] && needs="run tests"
      [ "$LINT_CLEAN" != "true" ] && { [ -n "$needs" ] && needs="$needs, run lint" || needs="run lint"; }
      [ -n "$needs" ] && echo " | Need: $needs"
      ;;
    capture)
      [ -z "$EXEC_LOG" ] && echo " | Need: execution log"
      ;;
    finalize)
      [ -z "$BRANCH_STRATEGY" ] && echo " | Need: branch strategy"
      ;;
    *)
      echo ""
      ;;
  esac
}

# No flow declared
if [ "$FLOW_TYPE" = "null" ] || [ -z "$FLOW_TYPE" ]; then
  echo "📍 no flow declared${DEVIATION_SUFFIX}${TOOL_SUFFIX}" > "$OUTPUT"
  cat "$OUTPUT"
  exit 0
fi

# Simple flows
case "$FLOW_TYPE" in
  micro)
    echo "📍 micro | Responder${DEVIATION_SUFFIX}${TOOL_SUFFIX}" > "$OUTPUT"
    cat "$OUTPUT"
    exit 0
    ;;
  light)
    echo "📍 light | Documentar${DEVIATION_SUFFIX}${TOOL_SUFFIX}" > "$OUTPUT"
    cat "$OUTPUT"
    exit 0
    ;;
  explore)
    echo "📍 explore | Investigar${DEVIATION_SUFFIX}${TOOL_SUFFIX}" > "$OUTPUT"
    cat "$OUTPUT"
    exit 0
    ;;
esac

# Full-flow: 8 phases with evidence
if [ "$FLOW_TYPE" = "full" ]; then
  PHASES=("consult" "brainstorming" "planning" "implementation" "verification" "capture" "retrospective" "finalize")
  TOTAL=8

  # Find current phase index (1-based)
  CURRENT_INDEX=0
  for i in "${!PHASES[@]}"; do
    if [ "${PHASES[$i]}" = "$CURRENT_PHASE" ]; then
      CURRENT_INDEX=$((i + 1))
      break
    fi
  done

  if [ "$CURRENT_INDEX" -eq 0 ]; then
    echo "📍 full | ${CURRENT_PHASE} | ⚠ fase no reconocida${DEVIATION_SUFFIX}${TOOL_SUFFIX}" > "$OUTPUT"
    cat "$OUTPUT"
    exit 0
  fi

  # Build completed phases with evidence
  COMPLETED=""
  for i in "${!PHASES[@]}"; do
    idx=$((i + 1))
    if [ "$idx" -lt "$CURRENT_INDEX" ]; then
      EV=$(phase_evidence "${PHASES[$i]}")
      if [ -n "$COMPLETED" ]; then
        COMPLETED="${COMPLETED} → ✅ ${PHASES[$i]}${EV}"
      else
        COMPLETED="✅ ${PHASES[$i]}${EV}"
      fi
    fi
  done

  # Build pending phases
  PENDING=""
  for i in "${!PHASES[@]}"; do
    idx=$((i + 1))
    if [ "$idx" -gt "$CURRENT_INDEX" ]; then
      if [ -n "$PENDING" ]; then
        PENDING="${PENDING}, ${PHASES[$i]}"
      else
        PENDING="${PHASES[$i]}"
      fi
    fi
  done

  # Capitalize
  DISPLAY_PHASE="$(echo "${CURRENT_PHASE:0:1}" | tr '[:lower:]' '[:upper:]')${CURRENT_PHASE:1}"

  # Current phase needs
  NEEDS=$(phase_needs "$CURRENT_PHASE")

  LINE="📍 full | ${DISPLAY_PHASE} (${CURRENT_INDEX}/${TOTAL})"

  # Add task progress for implementation/verification phases
  if [ "$TASK_TOTAL" -gt 0 ] 2>/dev/null && [ "$TASK_CURRENT" -gt 0 ] 2>/dev/null; then
    if [ "$CURRENT_PHASE" = "implementation" ] || [ "$CURRENT_PHASE" = "verification" ]; then
      TASK_BAR=""
      for ti in $(seq 1 "$TASK_TOTAL"); do
        if [ "$ti" -lt "$TASK_CURRENT" ]; then
          TASK_BAR="${TASK_BAR}✅"
        elif [ "$ti" -eq "$TASK_CURRENT" ]; then
          TASK_BAR="${TASK_BAR}🔄"
        else
          TASK_BAR="${TASK_BAR}⬚"
        fi
      done
      LINE="${LINE} | ${TASK_BAR} t${TASK_CURRENT}/${TASK_TOTAL}"
      [ -n "$TASK_LABEL" ] && LINE="${LINE}: ${TASK_LABEL}"
    fi
  fi

  if [ -n "$COMPLETED" ]; then
    LINE="${LINE} | ${COMPLETED} → 🔄 ${CURRENT_PHASE}"
  else
    LINE="${LINE} | 🔄 ${CURRENT_PHASE}"
  fi
  LINE="${LINE}${NEEDS}"
  if [ -n "$PENDING" ]; then
    LINE="${LINE} | Pendiente: ${PENDING}"
  fi
  LINE="${LINE}${DEVIATION_SUFFIX}${TOOL_SUFFIX}"

  echo "$LINE" > "$OUTPUT"
  cat "$OUTPUT"
  exit 0
fi

# Debug-flow: 4 phases with evidence
if [ "$FLOW_TYPE" = "debug" ]; then
  DEBUG_PHASES=("consult" "root_cause" "pattern_search" "fix")
  TOTAL=4

  # Determine current debug phase
  if [ "$CURRENT_PHASE" = "implementation" ] || ([ "$ROOT_CAUSE" = "true" ] && [ "$PATTERN_WIDE" = "true" ]); then
    DEBUG_CURRENT="fix"
    DEBUG_INDEX=4
  elif [ "$PATTERN_WIDE" = "true" ]; then
    DEBUG_CURRENT="fix"
    DEBUG_INDEX=4
  elif [ "$ROOT_CAUSE" = "true" ]; then
    DEBUG_CURRENT="pattern_search"
    DEBUG_INDEX=3
  elif [ "$CURRENT_PHASE" = "consult" ]; then
    DEBUG_CURRENT="consult"
    DEBUG_INDEX=1
  else
    DEBUG_CURRENT="root_cause"
    DEBUG_INDEX=2
  fi

  # Build completed with evidence
  COMPLETED=""
  for i in "${!DEBUG_PHASES[@]}"; do
    idx=$((i + 1))
    if [ "$idx" -lt "$DEBUG_INDEX" ]; then
      PH="${DEBUG_PHASES[$i]}"
      case "$PH" in
        consult)
          EV=""
          [ "$DECISIONS_READ" = "true" ] && EV="(dec)"
          [ "$LOGS_SCANNED" = "true" ] && EV="(log)"
          ;;
        root_cause) EV="(identified)" ;;
        pattern_search) EV="(done)" ;;
        *) EV="" ;;
      esac
      if [ -n "$COMPLETED" ]; then
        COMPLETED="${COMPLETED} → ✅ ${PH}${EV}"
      else
        COMPLETED="✅ ${PH}${EV}"
      fi
    fi
  done

  # Build pending
  PENDING=""
  for i in "${!DEBUG_PHASES[@]}"; do
    idx=$((i + 1))
    if [ "$idx" -gt "$DEBUG_INDEX" ]; then
      if [ -n "$PENDING" ]; then
        PENDING="${PENDING}, ${DEBUG_PHASES[$i]}"
      else
        PENDING="${DEBUG_PHASES[$i]}"
      fi
    fi
  done

  # Current phase needs for debug
  NEEDS=""
  case "$DEBUG_CURRENT" in
    consult)
      [ "$DECISIONS_READ" != "true" ] && [ "$LOGS_SCANNED" != "true" ] && NEEDS=" | Need: read decisions or logs"
      ;;
    root_cause)
      [ "$ROOT_CAUSE" != "true" ] && NEEDS=" | Need: identify root cause (Skill 8)"
      ;;
    pattern_search)
      [ "$PATTERN_WIDE" != "true" ] && NEEDS=" | Need: pattern-wide search (Skill 8 Phase 2.5)"
      ;;
    fix)
      NEEDS=" | Need: TDD fix + verify"
      ;;
  esac

  DISPLAY_PHASE="$(echo "${DEBUG_CURRENT:0:1}" | tr '[:lower:]' '[:upper:]')${DEBUG_CURRENT:1}"

  LINE="📍 debug | ${DISPLAY_PHASE} (${DEBUG_INDEX}/${TOTAL})"
  if [ -n "$COMPLETED" ]; then
    LINE="${LINE} | ${COMPLETED} → 🔄 ${DEBUG_CURRENT}"
  else
    LINE="${LINE} | 🔄 ${DEBUG_CURRENT}"
  fi
  LINE="${LINE}${NEEDS}"
  if [ -n "$PENDING" ]; then
    LINE="${LINE} | Pendiente: ${PENDING}"
  fi
  LINE="${LINE}${DEVIATION_SUFFIX}${TOOL_SUFFIX}"

  echo "$LINE" > "$OUTPUT"
  cat "$OUTPUT"
  exit 0
fi

# Unknown flow type — show raw
echo "📍 ${FLOW_TYPE} | ${CURRENT_PHASE}${DEVIATION_SUFFIX}${TOOL_SUFFIX}" > "$OUTPUT"
cat "$OUTPUT"
exit 0
