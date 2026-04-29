#!/usr/bin/env bash
# UserPromptSubmit hook — injects workflow state into Claude's context
#
# Reads session-state.json and outputs a compact status line to stdout.
# Format: 📍 <phase> <index>/<total> <bar> + done/todo lines + Header template
#
# ── Design decisions (2026-04-08) ──
#
# WHY COMPACT: The previous format (DISPLAY RULE + Evidence key=value + decorators)
# cost ~88 tokens/turn. Over a 20-turn session that's 1,760 tokens spent on status
# alone. The compact format costs ~36 tokens/turn — same information, 60% less.
#
# WHY NO DISPLAY RULE: CLAUDE.md already instructs the model on response format
# (loaded once per session). Repeating it every turn via DISPLAY RULE was ~30 tokens
# of redundancy per turn. Instead, a one-line "Header:" template (~8 tokens) serves
# as a post-compaction reminder without the verbosity.
#
# WHY READABLE DONE/TODO: The old "Evidence: decisions=Y user_turns=2 alternatives=Y"
# was machine-readable but opaque to both the model and the user. "✅ consult,
# dialogo(2), alternativas" communicates the same state in natural language. The model
# can reason about it directly; the user can read it at a glance.
#
# WHY NO DECORATORS: "── WORKFLOW STATE ──" and "────────────────────" cost ~10 tokens
# per turn and add zero information. The 📍 prefix already signals "this is status."
#
# INVARIANT: Every exit path must output a "Header:" line. This is the only format
# instruction that survives context compaction. If you add a new flow type or exit
# path, include a Header line or the model will lose response format after compaction.
#
# Non-blocking: always exits 0.

set -euo pipefail

REPO="/home/user/mxo-track"
STATE_FILE="$REPO/.claude/session-state.json"

# Load phase sequences from the single source of truth
# shellcheck source=./lib/flow-phases.sh
source "$REPO/.claude/hooks/lib/flow-phases.sh"

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
  if echo "$PROMPT_LOWER" | grep -qiE '(^|\s)(sí|si,|si$|yes|ok|dale|adelante|aprobado|apruebo|perfecto|de acuerdo|estoy de acuerdo|me parece bien|prefiero|vamos con|go ahead|approved|lgtm|apruebo el plan|lo apruebo|suena bien|hazlo|implementa|proceed|me gusta|está bien|esta bien|correcto|confirmo|confirm|procede|continua|continúa|igual que|igual a|como las otras)(\s|$|[,.\!])'; then
    if [ "$CURRENT_APPROVED" != "true" ]; then
      jq '.evidence.user_approved = true' "$STATE_FILE" > /tmp/upt.json && mv /tmp/upt.json "$STATE_FILE"
      STATE=$(cat "$STATE_FILE" 2>/dev/null || echo "{}")
      cp "$STATE_FILE" /tmp/ptc-state-snapshot.json 2>/dev/null || true
    fi
  fi

  # Decision-ID approval: 2+ references like "D1a", "D2b" imply confirmation
  # of a numbered decision list (e.g. "1. D1a 2. D2b 3. D3a")
  DECISION_IDS=$(echo "$PROMPT_LOWER" | { grep -oE '\bd[0-9]+[a-e]?\b' || true; } | wc -l | tr -d ' ')
  if [ "$DECISION_IDS" -ge 2 ] && [ "$CURRENT_APPROVED" != "true" ]; then
    jq '.evidence.user_approved = true' "$STATE_FILE" > /tmp/upt.json && mv /tmp/upt.json "$STATE_FILE"
    STATE=$(cat "$STATE_FILE" 2>/dev/null || echo "{}")
    cp "$STATE_FILE" /tmp/ptc-state-snapshot.json 2>/dev/null || true
  fi

  # Rejection patterns (reset approval)
  if echo "$PROMPT_LOWER" | grep -qiE '(^|\s)(no[, ]|cambia|modifica|diferente|otra opci|no me convence|rechaz|no estoy de acuerdo)'; then
    if [ "$CURRENT_APPROVED" = "true" ]; then
      jq '.evidence.user_approved = false' "$STATE_FILE" > /tmp/upt.json && mv /tmp/upt.json "$STATE_FILE"
      STATE=$(cat "$STATE_FILE" 2>/dev/null || echo "{}")
      cp "$STATE_FILE" /tmp/ptc-state-snapshot.json 2>/dev/null || true
    fi
  fi

  # Retrospective approval: reuse approval regex but gate on phase=retrospective.
  # The flag gates phase-advance to finalize. Only this hook may set it; the
  # phase-transition-controller reverts direct jq writes (mirrors user_approved).
  # Origin: 2026-04-29 i10 — closes "stop-hook as approval proxy" pattern
  # observed in Hito 1, Hito 2, Hito 4, Phase A, Hito 5.
  CURRENT_RETRO_SHOWN=$(echo "$STATE" | jq -r '.evidence.retrospective_shown // false')
  if [ "$CURRENT_PHASE" = "retrospective" ] && [ "$CURRENT_RETRO_SHOWN" != "true" ]; then
    if echo "$PROMPT_LOWER" | grep -qiE '(^|\s)(sí|si,|si$|yes|ok|dale|adelante|aprobado|apruebo|perfecto|de acuerdo|estoy de acuerdo|me parece bien|prefiero|vamos con|go ahead|approved|lgtm|apruebo el plan|lo apruebo|suena bien|hazlo|implementa|proceed|me gusta|está bien|esta bien|correcto|confirmo|confirm|procede|continua|continúa|igual que|igual a|como las otras)(\s|$|[,.\!])'; then
      jq '.evidence.retrospective_shown = true' "$STATE_FILE" > /tmp/upt.json && mv /tmp/upt.json "$STATE_FILE"
      STATE=$(cat "$STATE_FILE" 2>/dev/null || echo "{}")
      cp "$STATE_FILE" /tmp/ptc-state-snapshot.json 2>/dev/null || true
    fi
  fi
fi

# Work context — hierarchical progress tracking
INTERACTION_CLASS=$(echo "$STATE" | jq -r '.interaction_classification // ""')
WC_DESCRIPTION=$(echo "$STATE" | jq -r '.evidence.work_context.description // ""')
WC_PROB_TOTAL=$(echo "$STATE" | jq -r '.evidence.work_context.problems.total // 0')
WC_PROB_CURRENT=$(echo "$STATE" | jq -r '.evidence.work_context.problems.current // 0')
WC_PROB_LABEL=$(echo "$STATE" | jq -r 'if .evidence.work_context.problems.current > 0 then .evidence.work_context.problems.labels[.evidence.work_context.problems.current - 1] // "" else "" end')
WC_WAVE_TOTAL=$(echo "$STATE" | jq -r '.evidence.work_context.wave.total // 0')
WC_WAVE_CURRENT=$(echo "$STATE" | jq -r '.evidence.work_context.wave.current // 0')
WC_WAVE_LABEL=$(echo "$STATE" | jq -r '.evidence.work_context.wave.label // ""')

# Auto-init: copy interaction_classification → work_context.description if empty
if [ -n "$INTERACTION_CLASS" ] && [ -z "$WC_DESCRIPTION" ]; then
  jq '.evidence.work_context.description = .interaction_classification' "$STATE_FILE" > /tmp/wc.json && mv /tmp/wc.json "$STATE_FILE"
  WC_DESCRIPTION="$INTERACTION_CLASS"
fi

# Helper: truncate description to ~40 chars
WC_DESC_SHORT="$WC_DESCRIPTION"
if [ "${#WC_DESC_SHORT}" -gt 40 ]; then
  WC_DESC_SHORT="${WC_DESC_SHORT:0:37}..."
fi

# Todo progress (mirror of TodoWrite) — read early so all flow branches can render.
TODO_TOTAL_EARLY=$(echo "$STATE" | jq -r '.evidence.todo_progress.total // 0')
TODO_DONE_EARLY=$(echo "$STATE" | jq -r '.evidence.todo_progress.completed // 0')
TODO_IP_EARLY=$(echo "$STATE" | jq -r '.evidence.todo_progress.in_progress_label // ""')

# Multi-problem prefix helper — returns "[label] " for ≥2 problems, empty otherwise.
render_problem_prefix() {
  if [ "$WC_PROB_TOTAL" -ge 2 ] 2>/dev/null; then
    if [ "$WC_PROB_CURRENT" -gt 0 ] 2>/dev/null && [ -n "$WC_PROB_LABEL" ]; then
      echo "[${WC_PROB_LABEL}] "
    fi
  fi
}

# Todo progress line helper — returns "  · <label> (done/total)" or empty.
render_todo_line() {
  if [ "$TODO_TOTAL_EARLY" -gt 0 ] 2>/dev/null && [ -n "$TODO_IP_EARLY" ]; then
    echo "  · ${TODO_IP_EARLY} (${TODO_DONE_EARLY}/${TODO_TOTAL_EARLY})"
  fi
}

# Warn if flow_type is set but interaction_classification is missing
if [ "$FLOW_TYPE" != "null" ] && [ -n "$FLOW_TYPE" ] && [ -z "$INTERACTION_CLASS" ]; then
  echo "  ⚠ Falta interaction_classification — setear antes de continuar"
fi

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

# Auto-reset completed flows: if finalize is done (branch_strategy set) AND
# the latest commit is already pushed to upstream, reset for next interaction.
# The HEAD-vs-upstream check prevents premature reset when the user types
# something between commit and push (which previously cleared branch_strategy
# and forced manual re-set before pushing — recurring bug across 5 Hitos).
if [ "$FLOW_TYPE" = "full" ] && [ "$CURRENT_PHASE" = "finalize" ]; then
  BRANCH_STRATEGY_CHECK=$(echo "$STATE" | jq -r '.evidence.branch_strategy // ""')
  HEAD_SHA=$(cd "$REPO" && git rev-parse HEAD 2>/dev/null || echo "")
  UPSTREAM_SHA=$(cd "$REPO" && git rev-parse @{upstream} 2>/dev/null || echo "")
  PUSHED="false"
  [ -n "$HEAD_SHA" ] && [ "$HEAD_SHA" = "$UPSTREAM_SHA" ] && PUSHED="true"
  if [ -n "$BRANCH_STRATEGY_CHECK" ] && [ "$PUSHED" = "true" ]; then
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
      .evidence.retrospective_shown = false |
      .evidence.root_cause_identified = false |
      .evidence.pattern_wide_search_done = false |
      .evidence.task_progress = {"current": 0, "total": 0, "label": null, "completed_labels": [], "task_index": []} |
      .evidence.work_context = {"description": null, "problems": {"total": 0, "current": 0, "labels": []}, "wave": {"total": 0, "current": 0, "label": null, "labels": []}} |
      .evidence.todo_progress = {"total": 0, "completed": 0, "in_progress_label": null, "items": []}
    ' "$STATE_FILE" > /tmp/ss_reset.json && mv /tmp/ss_reset.json "$STATE_FILE"
    cp "$STATE_FILE" /tmp/ptc-state-snapshot.json 2>/dev/null || true
    echo "📍 Sin clasificar — clasificar antes de proceder"
    exit 0
  fi
fi

# Simple flows — one line each
case "$FLOW_TYPE" in
  micro)
    PREFIX=$(render_problem_prefix)
    if [ -n "$WC_DESC_SHORT" ]; then
      echo "📍 ${PREFIX}micro | $WC_DESC_SHORT"
    else
      echo "📍 ${PREFIX}micro | responder"
    fi
    TODO_LINE=$(render_todo_line)
    [ -n "$TODO_LINE" ] && echo "$TODO_LINE"
    echo "Header: 💬 [respuesta concisa]"
    exit 0
    ;;
  light)
    PREFIX=$(render_problem_prefix)
    if [ -n "$WC_DESC_SHORT" ]; then
      echo "📍 ${PREFIX}light | $WC_DESC_SHORT"
    else
      echo "📍 ${PREFIX}light | documentar"
    fi
    TODO_LINE=$(render_todo_line)
    [ -n "$TODO_LINE" ] && echo "$TODO_LINE"
    echo "Header: 📝 Light — [completado]"
    exit 0
    ;;
  explore)
    PREFIX=$(render_problem_prefix)
    if [ -n "$WC_DESC_SHORT" ]; then
      echo "📍 ${PREFIX}explore | $WC_DESC_SHORT"
    else
      echo "📍 ${PREFIX}explore | investigar"
    fi
    TODO_LINE=$(render_todo_line)
    [ -n "$TODO_LINE" ] && echo "$TODO_LINE"
    echo "Header: 🔍 Explore — [encontrado]"
    exit 0
    ;;
  debug)
    ROOT_CAUSE=$(echo "$STATE" | jq -r '.evidence.root_cause_identified // false')
    PATTERN_WIDE=$(echo "$STATE" | jq -r '.evidence.pattern_wide_search_done // false')
    TESTS_PASSED_DBG=$(echo "$STATE" | jq -r '.evidence.tests_passed // false')
    # Prefer explicit current_phase for late phases (verification onward);
    # otherwise derive from evidence flags for the detective portion.
    case "$CURRENT_PHASE" in
      verification|capture|retrospective|finalize)
        DEBUG_PHASE="$CURRENT_PHASE"
        DEBUG_INDEX=0
        for i in "${!DEBUG_PHASES[@]}"; do
          if [ "${DEBUG_PHASES[$i]}" = "$CURRENT_PHASE" ]; then
            DEBUG_INDEX=$((i + 1))
            break
          fi
        done
        ;;
      *)
        if [ "$ROOT_CAUSE" = "true" ] && [ "$PATTERN_WIDE" = "true" ]; then
          DEBUG_PHASE="fix"; DEBUG_INDEX=3
        elif [ "$ROOT_CAUSE" = "true" ]; then
          DEBUG_PHASE="pattern_wide"; DEBUG_INDEX=2
        else
          DEBUG_PHASE="root_cause"; DEBUG_INDEX=1
        fi
        ;;
    esac
    DEBUG_TOTAL=${#DEBUG_PHASES[@]}
    DISPLAY_PHASE="$(echo "${DEBUG_PHASE:0:1}" | tr '[:lower:]' '[:upper:]')${DEBUG_PHASE:1}"
    # Build problem suffix: " — Problema 1/2: label" (only if multiple problems)
    PROB_SUFFIX=""
    if [ "$WC_PROB_TOTAL" -gt 1 ] 2>/dev/null && [ "$WC_PROB_CURRENT" -gt 0 ] 2>/dev/null; then
      PROB_SUFFIX=" — Problema ${WC_PROB_CURRENT}/${WC_PROB_TOTAL}"
      [ -n "$WC_PROB_LABEL" ] && PROB_SUFFIX="${PROB_SUFFIX}: ${WC_PROB_LABEL}"
    elif [ -n "$WC_DESC_SHORT" ]; then
      PROB_SUFFIX=" — ${WC_DESC_SHORT}"
    fi
    PREFIX=$(render_problem_prefix)
    echo "📍 ${PREFIX}Debug: ${DISPLAY_PHASE} (${DEBUG_INDEX}/${DEBUG_TOTAL})${PROB_SUFFIX}"
    TODO_LINE=$(render_todo_line)
    [ -n "$TODO_LINE" ] && echo "$TODO_LINE"
    # Timeline
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
    echo "  $TIMELINE"
    # Detail
    DETAIL=""
    [ "$ROOT_CAUSE" = "true" ] && DETAIL="${DETAIL:+$DETAIL, }causa raiz"
    [ "$PATTERN_WIDE" = "true" ] && DETAIL="${DETAIL:+$DETAIL, }patron"
    [ "$TESTS_PASSED_DBG" = "true" ] && DETAIL="${DETAIL:+$DETAIL, }tests ✓"
    [ -n "$DETAIL" ] && echo "  Estado: $DETAIL"
    # Next
    NEXT=""
    case "$DEBUG_PHASE" in
      root_cause) NEXT="identificar causa raiz" ;;
      pattern_wide) NEXT="busqueda patron-wide" ;;
      fix) NEXT="TDD fix + verificar" ;;
      verification) NEXT="tests + lint verdes" ;;
      capture) NEXT="escribir execution log" ;;
      retrospective) NEXT="presentar retro + set retrospective_shown=true" ;;
      finalize) NEXT="branch strategy + push" ;;
    esac
    echo "  Siguiente: $NEXT"
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
TASK_DONE=$(echo "$STATE" | jq -r '.evidence.task_progress.completed_labels // [] | length')

# Todo progress (mirror of TodoWrite)
TODO_TOTAL=$(echo "$STATE" | jq -r '.evidence.todo_progress.total // 0')
TODO_DONE=$(echo "$STATE" | jq -r '.evidence.todo_progress.completed // 0')
TODO_IP=$(echo "$STATE" | jq -r '.evidence.todo_progress.in_progress_label // ""')

if [ "$FLOW_TYPE" = "full" ]; then
  PHASES=("consult" "brainstorming" "planning" "implementation" "verification" "capture" "retrospective" "finalize")
  PHASE_SHORT=("consult" "brainstorm" "planning" "impl" "verify" "capture" "retro" "finalize")
  TOTAL=8

  # Handle null/undeclared phase
  if [ "$CURRENT_PHASE" = "null" ] || [ -z "$CURRENT_PHASE" ]; then
    echo "📍 Pendiente — avanzar a consult"
    echo "  ⬚ consult → brainstorm → planning → impl → verify → capture → retro → finalize"
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

  # Capitalize current phase for display
  DISPLAY_PHASE="$(echo "${CURRENT_PHASE:0:1}" | tr '[:lower:]' '[:upper:]')${CURRENT_PHASE:1}"

  # ── Line 1: Current phase + work context ──
  # Multi-problem prefix
  PROB_PREFIX=""
  if [ "$WC_PROB_TOTAL" -ge 2 ] 2>/dev/null; then
    if [ "$WC_PROB_CURRENT" -gt 0 ] 2>/dev/null && [ -n "$WC_PROB_LABEL" ]; then
      PROB_PREFIX="[${WC_PROB_LABEL}] "
    else
      PROB_PREFIX="⚠ MULTI-PROBLEMA (${WC_PROB_TOTAL}) sin current — setear problems.current | "
    fi
  fi
  LINE1="📍 ${PROB_PREFIX}${DISPLAY_PHASE} (${CURRENT_INDEX}/${TOTAL})"
  # Show wave hierarchy from planning onwards (when plan has been parsed)
  case "$CURRENT_PHASE" in
    planning|implementation|verification|capture|retrospective)
      if [ "$WC_WAVE_TOTAL" -gt 0 ] 2>/dev/null; then
        if [ "$WC_WAVE_CURRENT" -gt 0 ] 2>/dev/null; then
          LINE1="${LINE1} — Wave ${WC_WAVE_CURRENT}/${WC_WAVE_TOTAL}"
          [ -n "$WC_WAVE_LABEL" ] && LINE1="${LINE1}: ${WC_WAVE_LABEL}"
        else
          LINE1="${LINE1} — ${WC_WAVE_TOTAL} waves planificadas"
        fi
      elif [ -n "$WC_DESC_SHORT" ]; then
        LINE1="${LINE1} — ${WC_DESC_SHORT}"
      fi
      ;;
    *)
      [ -n "$WC_DESC_SHORT" ] && LINE1="${LINE1} — ${WC_DESC_SHORT}"
      ;;
  esac
  echo "$LINE1"

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
  echo "  $TIMELINE"

  # ── Line 2.5: Plan progress (when plan parsed and we're in execution phases) ──
  # Also renders todo line in consult/brainstorming so early phases get granularity.
  case "$CURRENT_PHASE" in
    consult|brainstorming)
      if [ "$TODO_TOTAL" -gt 0 ] 2>/dev/null && [ -n "$TODO_IP" ]; then
        echo "  Todos: 🔄 ${TODO_IP} (${TODO_DONE}/${TODO_TOTAL})"
      fi
      ;;
    planning|implementation|verification|capture|retrospective)
      if [ "$TASK_TOTAL" -gt 0 ] 2>/dev/null; then
        # Build a compact ✅✅⬚⬚⬚ visual (max 12 cells to keep line short)
        PLAN_BAR=""
        VISUAL_LIMIT=12
        DISPLAY_TOTAL=$TASK_TOTAL
        [ "$DISPLAY_TOTAL" -gt "$VISUAL_LIMIT" ] && DISPLAY_TOTAL=$VISUAL_LIMIT
        for ((i=1; i<=DISPLAY_TOTAL; i++)); do
          if [ "$i" -le "$TASK_DONE" ]; then
            PLAN_BAR="${PLAN_BAR}✅"
          elif [ "$i" -eq "$TASK_CURRENT" ]; then
            PLAN_BAR="${PLAN_BAR}🔄"
          else
            PLAN_BAR="${PLAN_BAR}⬚"
          fi
        done
        PLAN_LINE="  Plan: ${PLAN_BAR} ${TASK_DONE}/${TASK_TOTAL}"
        if [ "$TASK_CURRENT" -gt 0 ] 2>/dev/null && [ -n "$TASK_LABEL" ]; then
          PLAN_LINE="${PLAN_LINE} — Tarea ${TASK_CURRENT}: ${TASK_LABEL}"
        fi
        echo "$PLAN_LINE"
      fi
      # Todos mirror line (only when there's an active in_progress)
      if [ "$TODO_TOTAL" -gt 0 ] 2>/dev/null && [ -n "$TODO_IP" ]; then
        echo "  Todos: 🔄 ${TODO_IP} (${TODO_DONE}/${TODO_TOTAL})"
      fi
      ;;
  esac

  # ── Line 3: Current phase detail (what's done inside this phase) ──
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
      [ -n "$PLAN_PATH" ] && DETAIL="${DETAIL:+$DETAIL, }plan"
      [ "$TESTS_WRITTEN" -gt 0 ] 2>/dev/null && DETAIL="${DETAIL:+$DETAIL, }${TESTS_WRITTEN} tests"
      if [ "$TASK_TOTAL" -gt 0 ] 2>/dev/null && [ "$TASK_CURRENT" -gt 0 ] 2>/dev/null; then
        DETAIL="${DETAIL:+$DETAIL, }tarea ${TASK_CURRENT}/${TASK_TOTAL}"
        [ -n "$TASK_LABEL" ] && DETAIL="${DETAIL}: ${TASK_LABEL}"
      fi
      ;;
    verification)
      [ "$TESTS_PASSED" = "true" ] && DETAIL="${DETAIL:+$DETAIL, }tests ✓"
      [ "$LINT_CLEAN" = "true" ] && DETAIL="${DETAIL:+$DETAIL, }lint ✓"
      [ "$TESTS_PASSED" != "true" ] && DETAIL="${DETAIL:+$DETAIL, }tests pendiente"
      [ "$LINT_CLEAN" != "true" ] && DETAIL="${DETAIL:+$DETAIL, }lint pendiente"
      ;;
    capture)
      [ -n "$EXEC_LOG" ] && DETAIL="execution log escrito" || DETAIL="escribir execution log"
      ;;
    retrospective)
      [ -n "$EXEC_LOG" ] && DETAIL="${DETAIL:+$DETAIL, }log escrito"
      DETAIL="${DETAIL:+$DETAIL, }reflexionar y actualizar"
      ;;
    finalize)
      [ -n "$BRANCH_STRATEGY" ] && DETAIL="strategy: $BRANCH_STRATEGY" || DETAIL="declarar strategy"
      ;;
  esac
  [ -n "$DETAIL" ] && echo "  Estado: $DETAIL"

  # ── Line 4: Next action ──
  NEXT=""
  case "$CURRENT_PHASE" in
    consult)
      [ "$DECISIONS_READ" = "true" ] && [ "$LOGS_SCANNED" = "true" ] && NEXT="→ brainstorming" || NEXT="leer decisions/logs"
      ;;
    brainstorming)
      [ "$USER_APPROVED" != "true" ] && NEXT="obtener aprobacion" || { [ -z "$SPEC_PATH" ] && NEXT="escribir spec" || NEXT="→ planning"; }
      ;;
    planning)
      [ -z "$PLAN_PATH" ] && NEXT="escribir plan" || NEXT="→ implementation"
      ;;
    implementation)
      NEXT="TDD cycle, commit por tarea"
      if [ "$TASK_TOTAL" -gt 0 ] 2>/dev/null && [ "$TASK_CURRENT" -gt 0 ] 2>/dev/null; then
        NEXT="tarea ${TASK_CURRENT}/${TASK_TOTAL}"
        [ -n "$TASK_LABEL" ] && NEXT="$NEXT: $TASK_LABEL"
      fi
      ;;
    verification)
      [ "$TESTS_PASSED" = "true" ] && [ "$LINT_CLEAN" = "true" ] && NEXT="→ capture" || NEXT="ejecutar tests y lint"
      ;;
    capture)
      [ -n "$EXEC_LOG" ] && NEXT="→ retrospective" || NEXT="escribir execution log"
      ;;
    retrospective) NEXT="→ finalize" ;;
    finalize) [ -n "$BRANCH_STRATEGY" ] && NEXT="ejecutar $BRANCH_STRATEGY" || NEXT="declarar strategy" ;;
  esac
  echo "  Siguiente: $NEXT"

  # Narration guard during active work phases
  case "$CURRENT_PHASE" in
    implementation|verification|capture|retrospective|finalize)
      echo "  ⛔ No narrar proceso entre tools. Solo texto si: resultado concreto, cambio de fase, o decisión del usuario."
      ;;
  esac
  exit 0
fi

# Unknown flow type
echo "📍 $FLOW_TYPE | $CURRENT_PHASE"
exit 0
