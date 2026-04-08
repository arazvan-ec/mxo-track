#!/usr/bin/env bash
# UserPromptSubmit hook — injects workflow state into Claude's context
#
# Reads session-state.json and outputs a compact status line to stdout.
# Format: 📍 <phase> <index>/<total> <bar> + done/todo lines
#
# Non-blocking: always exits 0.

set -euo pipefail

REPO="/home/user/mxo-track"
STATE_FILE="$REPO/.claude/session-state.json"

# Graceful fallback
if [ ! -f "$STATE_FILE" ]; then
  echo "📍 Sin estado — session-state.json no encontrado"
  exit 0
fi

# Read user prompt from stdin (UserPromptSubmit provides it as JSON)
HOOK_INPUT=$(cat 2>/dev/null || echo "{}")
USER_PROMPT=$(echo "$HOOK_INPUT" | jq -r '.prompt // ""' 2>/dev/null || echo "")

# Read state
STATE=$(cat "$STATE_FILE" 2>/dev/null || echo "{}")
FLOW_TYPE=$(echo "$STATE" | jq -r '.flow_type // "null"')
CURRENT_PHASE=$(echo "$STATE" | jq -r '.current_phase // "null"')

# Auto-increment user_turns during brainstorming
if [ "$FLOW_TYPE" = "full" ] && [ "$CURRENT_PHASE" = "brainstorming" ]; then
  jq '.evidence.user_turns = (.evidence.user_turns + 1)' "$STATE_FILE" > /tmp/upt.json && mv /tmp/upt.json "$STATE_FILE"
  STATE=$(cat "$STATE_FILE" 2>/dev/null || echo "{}")
fi

# ── User Approval Detection ──
# user_approved is a HUMAN decision. Only this hook sets it — direct jq writes
# are reverted by phase-transition-controller.
# Flow: .prompt → strip <system-reminder> → lowercase → match approval/rejection
# If both match, rejection wins (conservative).
if [ "$FLOW_TYPE" = "full" ] && [ -n "$USER_PROMPT" ]; then
  CURRENT_APPROVED=$(echo "$STATE" | jq -r '.evidence.user_approved // false')
  CLEAN_PROMPT=$(echo "$USER_PROMPT" | sed '/<system-reminder>/,/<\/system-reminder>/d')
  PROMPT_LOWER=$(echo "$CLEAN_PROMPT" | tr '[:upper:]' '[:lower:]')

  # Approval patterns (Spanish + English)
  if echo "$PROMPT_LOWER" | grep -qiE '(^|\s)(sí|si,|si$|yes|ok|dale|adelante|aprobado|apruebo|perfecto|de acuerdo|estoy de acuerdo|me parece bien|prefiero|vamos con|go ahead|approved|lgtm|apruebo el plan|lo apruebo|suena bien|hazlo|implementa|proceed|me gusta|está bien|esta bien|correcto)(\s|$|[,.\!])'; then
    if [ "$CURRENT_APPROVED" != "true" ]; then
      jq '.evidence.user_approved = true' "$STATE_FILE" > /tmp/upt.json && mv /tmp/upt.json "$STATE_FILE"
      STATE=$(cat "$STATE_FILE" 2>/dev/null || echo "{}")
    fi
  fi

  # Rejection patterns (reset approval)
  if echo "$PROMPT_LOWER" | grep -qiE '(^|\s)(no[, ]|cambia|modifica|diferente|otra opci|no me convence|rechaz|no estoy de acuerdo)'; then
    if [ "$CURRENT_APPROVED" = "true" ]; then
      jq '.evidence.user_approved = false' "$STATE_FILE" > /tmp/upt.json && mv /tmp/upt.json "$STATE_FILE"
      STATE=$(cat "$STATE_FILE" 2>/dev/null || echo "{}")
    fi
  fi
fi
INTERACTION_ID=$(echo "$STATE" | jq -r '.interaction_id // 0')
DEV_ACTIVE=$(echo "$STATE" | jq -r '.deviation.active // false')

# No flow declared
if [ "$FLOW_TYPE" = "null" ] || [ -z "$FLOW_TYPE" ]; then
  echo "📍 Sin clasificar — clasificar antes de proceder"
  PENDING_COUNT=$(echo "$STATE" | jq -r '.pending_work // [] | length' 2>/dev/null || echo "0")
  if [ "$PENDING_COUNT" -gt 0 ]; then
    echo "  ⚠ Pendientes ($PENDING_COUNT):"
    echo "$STATE" | jq -r '.pending_work[] | "  [\(.priority)] \(.title)"' 2>/dev/null || true
  fi
  exit 0
fi

# Auto-reset completed flows: if finalize is done (branch_strategy set), reset for next interaction
if [ "$FLOW_TYPE" = "full" ] && [ "$CURRENT_PHASE" = "finalize" ]; then
  BRANCH_STRATEGY_CHECK=$(echo "$STATE" | jq -r '.evidence.branch_strategy // ""')
  if [ -n "$BRANCH_STRATEGY_CHECK" ]; then
    LAST_SUMMARY=$(echo "$STATE" | jq -c '{flow_type: .flow_type, phase: .current_phase, branch_strategy: .evidence.branch_strategy}')
    jq --argjson summary "$LAST_SUMMARY" '
      .flow_type = null |
      .current_phase = null |
      .phase_history = [] |
      .last_work_summary = $summary |
      .interaction_id = (.interaction_id + 1) |
      .evidence.decisions_read = false |
      .evidence.logs_scanned = false |
      .evidence.user_turns = 0 |
      .evidence.alternatives_proposed = false |
      .evidence.user_approved = false |
      .evidence.spec_path = null |
      .evidence.plan_path = null |
      .evidence.tests_written = 0 |
      .evidence.tests_passed = null |
      .evidence.lint_clean = null |
      .evidence.execution_log_path = null |
      .evidence.branch_strategy = null |
      .evidence.root_cause_identified = false |
      .evidence.pattern_wide_search_done = false |
      .evidence.task_progress = {"current": 0, "total": 0, "label": null, "completed_labels": []}
    ' "$STATE_FILE" > /tmp/ss_reset.json && mv /tmp/ss_reset.json "$STATE_FILE"
    echo "📍 Sin clasificar — clasificar antes de proceder"
    exit 0
  fi
fi

# Simple flows — one line each
case "$FLOW_TYPE" in
  micro)
    echo "📍 micro | responder"
    echo "Header: 💬 [respuesta concisa]"
    exit 0
    ;;
  light)
    echo "📍 light | documentar"
    echo "Header: 📝 Light — [completado]"
    exit 0
    ;;
  explore)
    echo "📍 explore | investigar"
    echo "Header: 🔍 Explore — [encontrado]"
    exit 0
    ;;
  debug)
    ROOT_CAUSE=$(echo "$STATE" | jq -r '.evidence.root_cause_identified // false')
    PATTERN_WIDE=$(echo "$STATE" | jq -r '.evidence.pattern_wide_search_done // false')
    TESTS_PASSED_DBG=$(echo "$STATE" | jq -r '.evidence.tests_passed // false')
    if [ "$ROOT_CAUSE" = "true" ] && [ "$PATTERN_WIDE" = "true" ]; then
      DEBUG_PHASE="fix"
    elif [ "$ROOT_CAUSE" = "true" ]; then
      DEBUG_PHASE="pattern_search"
    else
      DEBUG_PHASE="root_cause"
    fi
    DONE=""
    [ "$ROOT_CAUSE" = "true" ] && DONE="${DONE:+$DONE, }causa raiz"
    [ "$PATTERN_WIDE" = "true" ] && DONE="${DONE:+$DONE, }patron"
    [ "$TESTS_PASSED_DBG" = "true" ] && DONE="${DONE:+$DONE, }tests"
    TODO=""
    case "$DEBUG_PHASE" in
      root_cause) TODO="identificar causa raiz" ;;
      pattern_search) TODO="busqueda patron-wide" ;;
      fix) TODO="TDD fix + verificar" ;;
    esac
    echo "📍 Debug $DEBUG_PHASE"
    [ -n "$DONE" ] && echo "  ✅ $DONE"
    echo "  ⏳ $TODO"
    echo "Header: 🐛 Debug ($DEBUG_PHASE) — [causa o fix]"
    exit 0
    ;;
esac

# ── Full flow ──

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

# Task progress
TASK_CURRENT=$(echo "$STATE" | jq -r '.evidence.task_progress.current // 0')
TASK_TOTAL=$(echo "$STATE" | jq -r '.evidence.task_progress.total // 0')
TASK_LABEL=$(echo "$STATE" | jq -r '.evidence.task_progress.label // ""')

if [ "$FLOW_TYPE" = "full" ]; then
  PHASES=("consult" "brainstorming" "planning" "implementation" "verification" "capture" "retrospective" "finalize")
  TOTAL=8

  # Handle null/undeclared phase
  if [ "$CURRENT_PHASE" = "null" ] || [ -z "$CURRENT_PHASE" ]; then
    echo "📍 Pendiente 0/$TOTAL ⬚⬚⬚⬚⬚⬚⬚⬚"
    echo "  ⏳ avanzar a consult"
    echo "Header: ⬚⬚⬚⬚⬚⬚⬚⬚ Pendiente (0/$TOTAL) — [avanzar a consult]"
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

  # Find current phase index
  CURRENT_INDEX=0
  for i in "${!PHASES[@]}"; do
    if [ "${PHASES[$i]}" = "$CURRENT_PHASE" ]; then
      CURRENT_INDEX=$((i + 1))
      break
    fi
  done

  # Build done/todo lines based on current phase
  DONE=""
  TODO=""
  case "$CURRENT_PHASE" in
    consult)
      [ "$DECISIONS_READ" = "true" ] && DONE="${DONE:+$DONE, }decisions"
      [ "$LOGS_SCANNED" = "true" ] && DONE="${DONE:+$DONE, }logs"
      if [ -z "$DONE" ]; then
        TODO="leer decisions y logs"
      else
        TODO="avanzar a brainstorming"
      fi
      ;;
    brainstorming)
      if [ "$DECISIONS_READ" = "true" ] || [ "$LOGS_SCANNED" = "true" ]; then
        DONE="${DONE:+$DONE, }consult"
      fi
      [ "$USER_TURNS" -gt 0 ] 2>/dev/null && DONE="${DONE:+$DONE, }dialogo($USER_TURNS)"
      [ "$ALTERNATIVES" = "true" ] && DONE="${DONE:+$DONE, }alternativas"
      [ "$USER_APPROVED" = "true" ] && DONE="${DONE:+$DONE, }aprobado"
      [ -n "$SPEC_PATH" ] && DONE="${DONE:+$DONE, }spec"
      PARTS=""
      [ "$USER_TURNS" -lt 1 ] 2>/dev/null && PARTS="dialogo"
      [ "$ALTERNATIVES" != "true" ] && PARTS="${PARTS:+$PARTS, }alternativas"
      [ "$USER_APPROVED" != "true" ] && PARTS="${PARTS:+$PARTS, }aprobacion"
      [ -z "$SPEC_PATH" ] && PARTS="${PARTS:+$PARTS, }escribir spec"
      [ -z "$PARTS" ] && PARTS="avanzar a planning"
      TODO="$PARTS"
      ;;
    planning)
      [ -n "$SPEC_PATH" ] && DONE="${DONE:+$DONE, }spec"
      if [ -n "$PLAN_PATH" ]; then
        DONE="${DONE:+$DONE, }plan"
        TODO="avanzar a implementation"
      else
        TODO="escribir plan"
      fi
      ;;
    implementation)
      [ -n "$PLAN_PATH" ] && DONE="${DONE:+$DONE, }plan"
      [ "$TESTS_WRITTEN" -gt 0 ] 2>/dev/null && DONE="${DONE:+$DONE, }${TESTS_WRITTEN} tests"
      if [ "$TASK_TOTAL" -gt 0 ] 2>/dev/null && [ "$TASK_CURRENT" -gt 0 ] 2>/dev/null; then
        TODO="tarea ${TASK_CURRENT}/${TASK_TOTAL}"
        [ -n "$TASK_LABEL" ] && TODO="$TODO: ${TASK_LABEL}"
      else
        TODO="TDD cycle, commit por tarea"
      fi
      ;;
    verification)
      [ "$TESTS_PASSED" = "true" ] && DONE="${DONE:+$DONE, }tests"
      [ "$LINT_CLEAN" = "true" ] && DONE="${DONE:+$DONE, }lint"
      PARTS=""
      [ "$TESTS_PASSED" != "true" ] && PARTS="tests"
      [ "$LINT_CLEAN" != "true" ] && PARTS="${PARTS:+$PARTS, }lint"
      [ -z "$PARTS" ] && PARTS="avanzar a capture"
      TODO="$PARTS"
      ;;
    capture)
      if [ -n "$EXEC_LOG" ]; then
        DONE="execution log"
        TODO="avanzar a retrospective"
      else
        TODO="escribir execution log"
      fi
      ;;
    retrospective)
      [ -n "$EXEC_LOG" ] && DONE="${DONE:+$DONE, }execution log"
      TODO="actualizar decision log, avanzar a finalize"
      ;;
    finalize)
      if [ -n "$BRANCH_STRATEGY" ]; then
        DONE="strategy: $BRANCH_STRATEGY"
        TODO="ejecutar strategy"
      else
        TODO="declarar strategy (merge/pr/keep/discard)"
      fi
      ;;
  esac

  DEV_SUFFIX=""
  [ "$DEV_ACTIVE" = "true" ] && DEV_SUFFIX=" | DEVIATION"

  # Build visual phase progress bar (✅🔄⬚)
  PHASE_BAR=""
  for i in "${!PHASES[@]}"; do
    IDX=$((i + 1))
    if [ "$IDX" -lt "$CURRENT_INDEX" ]; then
      PHASE_BAR="${PHASE_BAR}✅"
    elif [ "$IDX" -eq "$CURRENT_INDEX" ]; then
      PHASE_BAR="${PHASE_BAR}🔄"
    else
      PHASE_BAR="${PHASE_BAR}⬚"
    fi
  done

  echo "📍 ${CURRENT_PHASE^} $CURRENT_INDEX/$TOTAL $PHASE_BAR${DEV_SUFFIX}"
  [ -n "$DONE" ] && echo "  ✅ $DONE"
  echo "  ⏳ $TODO"
  echo "Header: $PHASE_BAR ${CURRENT_PHASE^} ($CURRENT_INDEX/$TOTAL) — [completado]"
  exit 0
fi

# Unknown flow type
echo "📍 $FLOW_TYPE | $CURRENT_PHASE"
exit 0
