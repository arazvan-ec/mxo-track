#!/usr/bin/env bash
# PostToolUse hook — generates a detailed workflow status for Claude to display.
# Reads session-state.json evidence fields, writes .claude/workflow-status-line.txt
# Enhanced 2026-03-24: shows per-phase evidence and current phase needs.
# Enhanced 2026-04-07: adds tool context suffix to avoid identical repeated lines.
# Enhanced 2026-04-08: 5-line adaptive format with evidence, next, branch.
# Non-blocking: always exits 0.

set -euo pipefail

REPO="/home/user/mxo-track"
STATE_FILE="$REPO/.claude/session-state.json"
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
  echo "📍 status unavailable"
  echo "  session-state.json not found${TOOL_SUFFIX}"
  exit 0
fi

# Read all state at once
STATE=$(cat "$STATE_FILE" 2>/dev/null || echo "{}")
FLOW_TYPE=$(echo "$STATE" | jq -r '.flow_type // "null"')
CURRENT_PHASE=$(echo "$STATE" | jq -r '.current_phase // "null"')

# Suppress repeated output: compute state signature, skip cat if unchanged
SIG_FILE="$REPO/.claude/.workflow-status-sig"
STATE_SIG=$(echo "$STATE" | jq -Sc '[.flow_type, .current_phase, .interaction_id, .deviation.active, .evidence]' | md5sum | cut -d' ' -f1)
PREV_SIG=""
[ -f "$SIG_FILE" ] && PREV_SIG=$(cat "$SIG_FILE" 2>/dev/null)
echo "$STATE_SIG" > "$SIG_FILE"
STATE_CHANGED=true
[ "$STATE_SIG" = "$PREV_SIG" ] && STATE_CHANGED=false

# Output helper: only emit to stdout if state changed
emit() { if [ "$STATE_CHANGED" = true ]; then cat; else cat > /dev/null; fi; }
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

# Wave progress fields (design structure: Phase 1-A, Wave 2/5)
WAVE_CURRENT=$(echo "$STATE" | jq -r '.evidence.task_progress.wave_current // 0')
WAVE_TOTAL=$(echo "$STATE" | jq -r '.evidence.task_progress.wave_total // 0')
WAVE_LABEL=$(echo "$STATE" | jq -r '.evidence.task_progress.wave_label // ""')

# Multi-problem tracking
PROB_TOTAL=$(echo "$STATE" | jq -r '.evidence.work_context.problems.total // 0')
PROB_CURRENT=$(echo "$STATE" | jq -r '.evidence.work_context.problems.current // 0')
PROB_LABEL=""
if [ "$PROB_TOTAL" -ge 2 ] 2>/dev/null; then
  PROB_LABEL=$(echo "$STATE" | jq -r "if .evidence.work_context.problems.current > 0 then .evidence.work_context.problems.labels[.evidence.work_context.problems.current - 1] // \"\" else \"\" end")
fi

DEVIATION_SUFFIX=""
if [ "$DEV_ACTIVE" = "true" ]; then
  DEVIATION_SUFFIX=" | ⚠ DESVÍO"
fi

# Interaction ID
INTERACTION_ID=$(echo "$STATE" | jq -r '.interaction_id // 0')

# Helper: Y/N from bool
yn() { [ "$1" = "true" ] && echo "Y" || echo "N"; }

# Helper: build evidence string for current phase
current_evidence() {
  local phase="$1"
  case "$phase" in
    consult)
      echo "decisions_read=$(yn $DECISIONS_READ) logs_scanned=$(yn $LOGS_SCANNED)"
      ;;
    brainstorming)
      local spec_yn="N"; [ -n "$SPEC_PATH" ] && spec_yn="Y"
      echo "user_turns=$USER_TURNS alternatives=$(yn $ALTERNATIVES) approved=$(yn $USER_APPROVED) spec=$spec_yn"
      ;;
    planning)
      local plan_yn="N"; [ -n "$PLAN_PATH" ] && plan_yn="Y"
      echo "spec=$([ -n "$SPEC_PATH" ] && echo "Y" || echo "N") plan=$plan_yn"
      ;;
    implementation)
      local ev="plan=$([ -n "$PLAN_PATH" ] && echo "Y" || echo "N") tests_written=$TESTS_WRITTEN"
      if [ "$TASK_TOTAL" -gt 0 ] 2>/dev/null; then
        ev="$ev task=${TASK_CURRENT}/${TASK_TOTAL}"
        [ -n "$TASK_LABEL" ] && ev="$ev ($TASK_LABEL)"
      fi
      echo "$ev"
      ;;
    verification)
      echo "tests_passed=$(yn ${TESTS_PASSED:-false}) lint_clean=$(yn ${LINT_CLEAN:-false})"
      ;;
    capture)
      echo "exec_log=$([ -n "$EXEC_LOG" ] && echo "Y" || echo "N")"
      ;;
    retrospective)
      echo "exec_log=$([ -n "$EXEC_LOG" ] && echo "Y" || echo "N")"
      ;;
    finalize)
      echo "branch_strategy=$([ -n "$BRANCH_STRATEGY" ] && echo "$BRANCH_STRATEGY" || echo "N")"
      ;;
    *) echo "" ;;
  esac
}

# Helper: build next action string for current phase
next_action() {
  local phase="$1"
  case "$phase" in
    consult)
      local n=""
      [ "$DECISIONS_READ" != "true" ] && [ "$LOGS_SCANNED" != "true" ] && n="read decisions/logs"
      [ -z "$n" ] && n="→ brainstorming"
      echo "$n"
      ;;
    brainstorming)
      local parts=""
      [ "$USER_TURNS" -lt 1 ] 2>/dev/null && parts="user dialog"
      [ "$ALTERNATIVES" != "true" ] && parts="${parts:+$parts, }propose alternatives"
      [ "$USER_APPROVED" != "true" ] && parts="${parts:+$parts, }get approval"
      [ -z "$SPEC_PATH" ] && parts="${parts:+$parts, }write spec"
      [ -z "$parts" ] && parts="→ planning"
      echo "$parts"
      ;;
    planning)
      [ -z "$PLAN_PATH" ] && echo "write plan" || echo "→ implementation"
      ;;
    implementation)
      if [ "$TASK_TOTAL" -gt 0 ] 2>/dev/null && [ "$TASK_CURRENT" -gt 0 ] 2>/dev/null; then
        local n="Tarea ${TASK_CURRENT}/${TASK_TOTAL}"
        [ -n "$TASK_LABEL" ] && n="$n: $TASK_LABEL"
        n="$n (TDD)"
        echo "$n"
      elif [ "$WAVE_TOTAL" -gt 0 ] 2>/dev/null; then
        echo "Wave ${WAVE_CURRENT}/${WAVE_TOTAL}: ${WAVE_LABEL:-implementar} (TDD)"
      else
        echo "TDD cycle (test first), commit frequently"
      fi
      ;;
    verification)
      local parts=""
      [ "$TESTS_PASSED" != "true" ] && parts="run tests"
      [ "$LINT_CLEAN" != "true" ] && parts="${parts:+$parts, }run lint"
      [ -z "$parts" ] && parts="→ capture"
      echo "$parts"
      ;;
    capture)
      [ -z "$EXEC_LOG" ] && echo "write execution log" || echo "→ retrospective"
      ;;
    retrospective)
      echo "update decision log, → finalize"
      ;;
    finalize)
      [ -z "$BRANCH_STRATEGY" ] && echo "declare branch strategy (merge/pr/keep/discard)" || echo "execute $BRANCH_STRATEGY"
      ;;
    *) echo "" ;;
  esac
}

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
      local ev=""
      if [ "$WAVE_TOTAL" -gt 0 ] 2>/dev/null; then
        ev="w${WAVE_CURRENT}/${WAVE_TOTAL}"
      fi
      if [ "$TASK_TOTAL" -gt 0 ] 2>/dev/null; then
        ev="${ev:+$ev,}t${TASK_CURRENT}/${TASK_TOTAL}"
      fi
      ev="${ev:+$ev,}${TESTS_WRITTEN}tests"
      echo "(${ev})"
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
      if [ "$WAVE_TOTAL" -gt 0 ] 2>/dev/null && [ "$WAVE_CURRENT" -gt 0 ] 2>/dev/null; then
        parts="Wave ${WAVE_CURRENT}/${WAVE_TOTAL}"
        [ -n "$WAVE_LABEL" ] && parts="${parts}: ${WAVE_LABEL}"
      fi
      if [ "$TASK_TOTAL" -gt 0 ] 2>/dev/null && [ "$TASK_CURRENT" -gt 0 ] 2>/dev/null; then
        [ -n "$parts" ] && parts="${parts} · Tarea ${TASK_CURRENT}/${TASK_TOTAL}" || parts="Tarea ${TASK_CURRENT}/${TASK_TOTAL}"
        [ -n "$TASK_LABEL" ] && parts="${parts}: ${TASK_LABEL}"
      fi
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
  { echo "📍 no flow declared | i#${INTERACTION_ID}${DEVIATION_SUFFIX}"
    echo "  Clasificar antes de continuar${TOOL_SUFFIX}"
  } | emit
  exit 0
fi

# Simple flows
case "$FLOW_TYPE" in
  micro)
    { echo "📍 micro | Responder | i#${INTERACTION_ID}${DEVIATION_SUFFIX}"
      echo "  ${TOOL_SUFFIX:+${TOOL_SUFFIX} · }i#$(echo "$STATE" | jq -r '.interaction_id // 0')"
    } | emit
    exit 0
    ;;
  light)
    { echo "📍 light | Documentar | i#${INTERACTION_ID}${DEVIATION_SUFFIX}"
      echo "  ${TOOL_SUFFIX:+${TOOL_SUFFIX} · }i#$(echo "$STATE" | jq -r '.interaction_id // 0')"
    } | emit
    exit 0
    ;;
  explore)
    { echo "📍 explore | Investigar | i#${INTERACTION_ID}${DEVIATION_SUFFIX}"
      echo "  ${TOOL_SUFFIX:+${TOOL_SUFFIX} · }i#$(echo "$STATE" | jq -r '.interaction_id // 0')"
    } | emit
    exit 0
    ;;
esac

# Full-flow: 8 phases with hierarchical timeline
if [ "$FLOW_TYPE" = "full" ]; then
  PHASES=("consult" "brainstorming" "planning" "implementation" "verification" "capture" "retrospective" "finalize")
  PHASE_SHORT=("consult" "brainstorm" "planning" "impl" "verify" "capture" "retro" "finalize")
  TOTAL=8

  # Handle null/undeclared phase
  if [ "$CURRENT_PHASE" = "null" ] || [ -z "$CURRENT_PHASE" ]; then
    { echo "📍 Pendiente — avanzar a consult"
      echo "  ⬚ consult → brainstorm → planning → impl → verify → capture → retro → finalize"
    } | emit
    exit 0
  fi

  # Normalize common phase variants
  case "$CURRENT_PHASE" in
    implement)      CURRENT_PHASE="implementation" ;;
    brainstorm)     CURRENT_PHASE="brainstorming" ;;
    plan)           CURRENT_PHASE="planning" ;;
    verify|verif*)  CURRENT_PHASE="verification" ;;
    retro)          CURRENT_PHASE="retrospective" ;;
    final*)         CURRENT_PHASE="finalize" ;;
  esac

  # Find current phase index (1-based)
  CURRENT_INDEX=0
  for i in "${!PHASES[@]}"; do
    if [ "${PHASES[$i]}" = "$CURRENT_PHASE" ]; then
      CURRENT_INDEX=$((i + 1))
      break
    fi
  done

  if [ "$CURRENT_INDEX" -eq 0 ]; then
    { echo "📍 full | ${CURRENT_PHASE} | ⚠ fase no reconocida${DEVIATION_SUFFIX}"
      echo "  Usar: phase-advance.sh <fase>${TOOL_SUFFIX}"
    } | emit
    exit 0
  fi

  # Capitalize
  DISPLAY_PHASE="$(echo "${CURRENT_PHASE:0:1}" | tr '[:lower:]' '[:upper:]')${CURRENT_PHASE:1}"

  # ── Line 1: Current phase with design structure context ──
  # Prefix with problem label when multiple problems are tracked
  PROB_PREFIX=""
  if [ "$PROB_TOTAL" -ge 2 ] 2>/dev/null; then
    if [ "$PROB_CURRENT" -gt 0 ] 2>/dev/null && [ -n "$PROB_LABEL" ]; then
      PROB_PREFIX="[${PROB_LABEL}] "
    else
      PROB_PREFIX="⚠ MULTI-PROBLEMA (${PROB_TOTAL}) sin current — setear work_context.problems.current | "
    fi
  fi
  LINE1="📍 ${PROB_PREFIX}${DISPLAY_PHASE} (${CURRENT_INDEX}/${TOTAL})"
  # Add wave + phase context from design plan
  if [ "$WAVE_TOTAL" -gt 0 ] 2>/dev/null && [ "$WAVE_CURRENT" -gt 0 ] 2>/dev/null; then
    LINE1="${LINE1} · Wave ${WAVE_CURRENT}/${WAVE_TOTAL}"
    [ -n "$WAVE_LABEL" ] && LINE1="${LINE1} · ${WAVE_LABEL}"
  fi
  [ "$DEV_ACTIVE" = "true" ] && LINE1="${LINE1} [DESVÍO]"

  # ── Line 2: Timeline — completed → current → pending ──
  TIMELINE=""
  for i in "${!PHASES[@]}"; do
    IDX=$((i + 1))
    SHORT="${PHASE_SHORT[$i]}"
    if [ "$IDX" -lt "$CURRENT_INDEX" ]; then
      TIMELINE="${TIMELINE:+$TIMELINE → }✅ ${SHORT}"
    elif [ "$IDX" -eq "$CURRENT_INDEX" ]; then
      TIMELINE="${TIMELINE:+$TIMELINE → }🔄 ${SHORT}"
    else
      TIMELINE="${TIMELINE:+$TIMELINE → }⬚ ${SHORT}"
    fi
  done
  LINE2="  $TIMELINE"

  # ── Line 3: Current phase detail ──
  DETAIL=""
  case "$CURRENT_PHASE" in
    consult)
      [ "$DECISIONS_READ" = "true" ] && DETAIL="${DETAIL:+$DETAIL, }decisions"
      [ "$LOGS_SCANNED" = "true" ] && DETAIL="${DETAIL:+$DETAIL, }logs"
      [ -z "$DETAIL" ] && DETAIL="leyendo decisions y logs"
      ;;
    brainstorming)
      [ "$USER_TURNS" -gt 0 ] 2>/dev/null && DETAIL="${DETAIL:+$DETAIL, }dialogo($USER_TURNS)"
      [ "$ALTERNATIVES" = "true" ] && DETAIL="${DETAIL:+$DETAIL, }alternativas"
      [ "$USER_APPROVED" = "true" ] && DETAIL="${DETAIL:+$DETAIL, }aprobado"
      [ -n "$SPEC_PATH" ] && DETAIL="${DETAIL:+$DETAIL, }spec"
      ;;
    planning)
      [ -n "$SPEC_PATH" ] && DETAIL="${DETAIL:+$DETAIL, }spec"
      [ -n "$PLAN_PATH" ] && DETAIL="${DETAIL:+$DETAIL, }plan"
      ;;
    implementation)
      if [ "$TASK_TOTAL" -gt 0 ] 2>/dev/null && [ "$TASK_CURRENT" -gt 0 ] 2>/dev/null; then
        DETAIL="Tarea ${TASK_CURRENT}/${TASK_TOTAL}"
        [ -n "$TASK_LABEL" ] && DETAIL="${DETAIL}: ${TASK_LABEL}"
        [ "$TESTS_WRITTEN" -gt 0 ] 2>/dev/null && DETAIL="${DETAIL} (${TESTS_WRITTEN} tests)"
      else
        [ -n "$PLAN_PATH" ] && DETAIL="${DETAIL:+$DETAIL, }plan"
        [ "$TESTS_WRITTEN" -gt 0 ] 2>/dev/null && DETAIL="${DETAIL:+$DETAIL, }${TESTS_WRITTEN} tests"
      fi
      ;;
    verification)
      [ "$TESTS_PASSED" = "true" ] && DETAIL="${DETAIL:+$DETAIL, }tests ✓" || DETAIL="${DETAIL:+$DETAIL, }tests pendiente"
      [ "$LINT_CLEAN" = "true" ] && DETAIL="${DETAIL:+$DETAIL, }lint ✓" || DETAIL="${DETAIL:+$DETAIL, }lint pendiente"
      ;;
    capture)
      [ -n "$EXEC_LOG" ] && DETAIL="execution log escrito" || DETAIL="escribir execution log"
      ;;
    retrospective)
      [ -n "$EXEC_LOG" ] && DETAIL="${DETAIL:+$DETAIL, }log escrito"
      DETAIL="${DETAIL:+$DETAIL, }reflexionar"
      ;;
    finalize)
      [ -n "$BRANCH_STRATEGY" ] && DETAIL="strategy: $BRANCH_STRATEGY" || DETAIL="declarar strategy"
      ;;
  esac
  LINE3=""
  [ -n "$DETAIL" ] && LINE3="  Estado: $DETAIL"

  # ── Line 4: Next action ──
  NEXT=$(next_action "$CURRENT_PHASE")
  LINE4="  Siguiente: ${NEXT}"

  # ── Line 5: Tool context ──
  LINE5=""
  [ -n "$TOOL_SUFFIX" ] && LINE5=" ${TOOL_SUFFIX}"

  # ── Narration guard: reminder during active work phases ──
  NARRATION_GUARD=""
  case "$CURRENT_PHASE" in
    implementation|verification|capture|retrospective|finalize)
      NARRATION_GUARD="  ⛔ No narrar proceso entre tools. Solo texto si: resultado concreto, cambio de fase, o decisión del usuario."
      ;;
  esac

  # Assemble output
  {
    echo "$LINE1"
    echo "$LINE2"
    [ -n "$LINE3" ] && echo "$LINE3"
    echo "$LINE4"
    [ -n "$LINE5" ] && echo "$LINE5"
    [ -n "$NARRATION_GUARD" ] && echo "$NARRATION_GUARD"
  } | emit
  exit 0
fi

# Debug-flow: 4 phases with hierarchical timeline
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

  DISPLAY_PHASE="$(echo "${DEBUG_CURRENT:0:1}" | tr '[:lower:]' '[:upper:]')${DEBUG_CURRENT:1}"

  # ── Line 1: Current phase prominently ──
  LINE1="📍 Debug: ${DISPLAY_PHASE} (${DEBUG_INDEX}/${TOTAL})"
  [ "$DEV_ACTIVE" = "true" ] && LINE1="${LINE1} [DESVÍO]"

  # ── Line 2: Timeline ──
  TIMELINE=""
  for i in "${!DEBUG_PHASES[@]}"; do
    IDX=$((i + 1))
    PH="${DEBUG_PHASES[$i]}"
    if [ "$IDX" -lt "$DEBUG_INDEX" ]; then
      TIMELINE="${TIMELINE:+$TIMELINE → }✅ ${PH}"
    elif [ "$IDX" -eq "$DEBUG_INDEX" ]; then
      TIMELINE="${TIMELINE:+$TIMELINE → }🔄 ${PH}"
    else
      TIMELINE="${TIMELINE:+$TIMELINE → }⬚ ${PH}"
    fi
  done
  LINE2="  $TIMELINE"

  # ── Line 3: Current phase detail ──
  DETAIL=""
  [ "$DECISIONS_READ" = "true" ] && DETAIL="${DETAIL:+$DETAIL, }decisions"
  [ "$ROOT_CAUSE" = "true" ] && DETAIL="${DETAIL:+$DETAIL, }causa raiz"
  [ "$PATTERN_WIDE" = "true" ] && DETAIL="${DETAIL:+$DETAIL, }patron"
  [ "${TESTS_PASSED:-false}" = "true" ] && DETAIL="${DETAIL:+$DETAIL, }tests ✓"
  [ "${LINT_CLEAN:-false}" = "true" ] && DETAIL="${DETAIL:+$DETAIL, }lint ✓"
  LINE3=""
  [ -n "$DETAIL" ] && LINE3="  Estado: $DETAIL"

  # ── Line 4: Next action ──
  NEXT=""
  case "$DEBUG_CURRENT" in
    consult) NEXT="leer decisions/logs" ;;
    root_cause) NEXT="identificar causa raiz" ;;
    pattern_search) NEXT="busqueda patron-wide" ;;
    fix) NEXT="TDD fix + verificar" ;;
  esac
  LINE4="  Siguiente: ${NEXT}"

  # ── Line 5: Tool context ──
  LINE5=""
  [ -n "$TOOL_SUFFIX" ] && LINE5=" ${TOOL_SUFFIX}"

  # Assemble output
  {
    echo "$LINE1"
    echo "$LINE2"
    [ -n "$LINE3" ] && echo "$LINE3"
    echo "$LINE4"
    [ -n "$LINE5" ] && echo "$LINE5"
  } | emit
  exit 0
fi

# Unknown flow type — show raw
{ echo "📍 ${FLOW_TYPE} | ${CURRENT_PHASE}${DEVIATION_SUFFIX}"
  echo "  ${TOOL_SUFFIX:---}"
} | emit
exit 0
