#!/usr/bin/env bash
# UserPromptSubmit hook — injects workflow state into Claude's context
#
# Reads session-state.json and outputs a compact summary to stdout.
# Since UserPromptSubmit stdout is injected into Claude's context,
# this ensures Claude always knows the current workflow state.
#
# Non-blocking: always exits 0.

set -euo pipefail

REPO="/home/user/mxo-track"
STATE_FILE="$REPO/.claude/session-state.json"

# Graceful fallback
if [ ! -f "$STATE_FILE" ]; then
  echo "── WORKFLOW STATE ──"
  echo "Flow: unknown | No session-state.json found"
  echo "────────────────────"
  exit 0
fi

# Read state
STATE=$(cat "$STATE_FILE" 2>/dev/null || echo "{}")
FLOW_TYPE=$(echo "$STATE" | jq -r '.flow_type // "null"')
CURRENT_PHASE=$(echo "$STATE" | jq -r '.current_phase // "null"')
INTERACTION_ID=$(echo "$STATE" | jq -r '.interaction_id // 0')
DEV_ACTIVE=$(echo "$STATE" | jq -r '.deviation.active // false')

# No flow declared
if [ "$FLOW_TYPE" = "null" ] || [ -z "$FLOW_TYPE" ]; then
  echo "── WORKFLOW STATE ──"
  echo "Flow: not declared | Classify before proceeding"
  echo "────────────────────"
  exit 0
fi

# Simple flows
case "$FLOW_TYPE" in
  micro)
    echo "── WORKFLOW STATE ──"
    echo "Flow: micro | Respond"
    echo "────────────────────"
    exit 0
    ;;
  light)
    echo "── WORKFLOW STATE ──"
    echo "Flow: light | Document"
    echo "────────────────────"
    exit 0
    ;;
  explore)
    echo "── WORKFLOW STATE ──"
    echo "Flow: explore | Investigate"
    echo "────────────────────"
    exit 0
    ;;
esac

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

# Task progress
TASK_CURRENT=$(echo "$STATE" | jq -r '.evidence.task_progress.current // 0')
TASK_TOTAL=$(echo "$STATE" | jq -r '.evidence.task_progress.total // 0')
TASK_LABEL=$(echo "$STATE" | jq -r '.evidence.task_progress.label // ""')

# Helper: Y/N from bool
yn() { [ "$1" = "true" ] && echo "Y" || echo "N"; }

# Full-flow
if [ "$FLOW_TYPE" = "full" ]; then
  PHASES=("consult" "brainstorming" "planning" "implementation" "verification" "capture" "retrospective" "finalize")
  TOTAL=8

  # Find current phase index
  CURRENT_INDEX=0
  for i in "${!PHASES[@]}"; do
    if [ "${PHASES[$i]}" = "$CURRENT_PHASE" ]; then
      CURRENT_INDEX=$((i + 1))
      break
    fi
  done

  # Build evidence line based on current phase
  EVIDENCE=""
  case "$CURRENT_PHASE" in
    consult)
      EVIDENCE="decisions_read=$(yn $DECISIONS_READ) logs_scanned=$(yn $LOGS_SCANNED)"
      ;;
    brainstorming)
      SPEC_STATUS="N"
      [ -n "$SPEC_PATH" ] && SPEC_STATUS="Y"
      EVIDENCE="decisions=$(yn $DECISIONS_READ) user_turns=$USER_TURNS alternatives=$(yn $ALTERNATIVES) approved=$(yn $USER_APPROVED) spec=$SPEC_STATUS"
      ;;
    planning)
      PLAN_STATUS="N"
      [ -n "$PLAN_PATH" ] && PLAN_STATUS="Y"
      EVIDENCE="spec=$([ -n "$SPEC_PATH" ] && echo "Y" || echo "N") plan=$PLAN_STATUS"
      ;;
    implementation)
      EVIDENCE="plan=$([ -n "$PLAN_PATH" ] && echo "Y" || echo "N") tests_written=$TESTS_WRITTEN"
      if [ "$TASK_TOTAL" -gt 0 ] 2>/dev/null; then
        EVIDENCE="$EVIDENCE task=${TASK_CURRENT}/${TASK_TOTAL}"
        [ -n "$TASK_LABEL" ] && EVIDENCE="$EVIDENCE (${TASK_LABEL})"
      fi
      ;;
    verification)
      EVIDENCE="tests_passed=$(yn ${TESTS_PASSED:-false}) lint_clean=$(yn ${LINT_CLEAN:-false})"
      ;;
    capture)
      EVIDENCE="exec_log=$([ -n "$EXEC_LOG" ] && echo "Y" || echo "N")"
      ;;
    retrospective)
      EVIDENCE="exec_log=$([ -n "$EXEC_LOG" ] && echo "Y" || echo "N")"
      ;;
    finalize)
      EVIDENCE="branch_strategy=$([ -n "$BRANCH_STRATEGY" ] && echo "$BRANCH_STRATEGY" || echo "N")"
      ;;
  esac

  # Build next actions
  NEXT=""
  case "$CURRENT_PHASE" in
    consult)
      [ "$DECISIONS_READ" != "true" ] && [ "$LOGS_SCANNED" != "true" ] && NEXT="read decisions/logs"
      [ -z "$NEXT" ] && NEXT="transition to brainstorming"
      ;;
    brainstorming)
      PARTS=""
      [ "$USER_TURNS" -lt 1 ] 2>/dev/null && PARTS="user dialog"
      [ "$ALTERNATIVES" != "true" ] && PARTS="${PARTS:+$PARTS, }propose alternatives"
      [ "$USER_APPROVED" != "true" ] && PARTS="${PARTS:+$PARTS, }get approval"
      [ -z "$SPEC_PATH" ] && PARTS="${PARTS:+$PARTS, }write spec"
      [ -z "$PARTS" ] && PARTS="transition to planning"
      NEXT="$PARTS"
      ;;
    planning)
      [ -z "$PLAN_PATH" ] && NEXT="write plan" || NEXT="transition to implementation"
      ;;
    implementation)
      if [ "$TASK_TOTAL" -gt 0 ] 2>/dev/null && [ "$TASK_CURRENT" -gt 0 ] 2>/dev/null; then
        NEXT="task ${TASK_CURRENT}/${TASK_TOTAL}"
        [ -n "$TASK_LABEL" ] && NEXT="$NEXT: ${TASK_LABEL}"
        NEXT="$NEXT (TDD, commit after each task)"
      else
        NEXT="follow TDD cycle (test first), commit frequently"
      fi
      ;;
    verification)
      PARTS=""
      [ "$TESTS_PASSED" != "true" ] && PARTS="run tests"
      [ "$LINT_CLEAN" != "true" ] && PARTS="${PARTS:+$PARTS, }run lint"
      [ -z "$PARTS" ] && PARTS="transition to capture"
      NEXT="$PARTS"
      ;;
    capture)
      [ -z "$EXEC_LOG" ] && NEXT="write execution log" || NEXT="transition to retrospective"
      ;;
    retrospective)
      NEXT="update decision log if needed, transition to finalize"
      ;;
    finalize)
      [ -z "$BRANCH_STRATEGY" ] && NEXT="declare branch strategy (merge/pr/keep/discard)" || NEXT="execute branch strategy"
      ;;
  esac

  DEV_SUFFIX=""
  [ "$DEV_ACTIVE" = "true" ] && DEV_SUFFIX=" | DEVIATION ACTIVE"

  echo "── WORKFLOW STATE ──"
  echo "Flow: full | Phase: $CURRENT_PHASE ($CURRENT_INDEX/$TOTAL)${DEV_SUFFIX}"
  echo "Evidence: $EVIDENCE"
  echo "Next: $NEXT"
  echo "────────────────────"
  exit 0
fi

# Debug-flow
if [ "$FLOW_TYPE" = "debug" ]; then
  # Determine debug phase
  if [ "$ROOT_CAUSE" = "true" ] && [ "$PATTERN_WIDE" = "true" ]; then
    DEBUG_PHASE="fix"
    DEBUG_INDEX=4
  elif [ "$ROOT_CAUSE" = "true" ]; then
    DEBUG_PHASE="pattern_search"
    DEBUG_INDEX=3
  elif [ "$DECISIONS_READ" = "true" ] || [ "$LOGS_SCANNED" = "true" ]; then
    DEBUG_PHASE="root_cause"
    DEBUG_INDEX=2
  else
    DEBUG_PHASE="consult"
    DEBUG_INDEX=1
  fi

  EVIDENCE="decisions=$(yn $DECISIONS_READ) root_cause=$(yn $ROOT_CAUSE) pattern_wide=$(yn $PATTERN_WIDE) tests_passed=$(yn ${TESTS_PASSED:-false})"

  NEXT=""
  case "$DEBUG_PHASE" in
    consult) NEXT="read decisions/logs" ;;
    root_cause) NEXT="identify root cause (Skill 8)" ;;
    pattern_search) NEXT="pattern-wide search (Skill 8 Phase 2.5)" ;;
    fix) NEXT="TDD fix + verify" ;;
  esac

  echo "── WORKFLOW STATE ──"
  echo "Flow: debug | Phase: $DEBUG_PHASE ($DEBUG_INDEX/4)"
  echo "Evidence: $EVIDENCE"
  echo "Next: $NEXT"
  echo "────────────────────"
  exit 0
fi

# Unknown flow type
echo "── WORKFLOW STATE ──"
echo "Flow: $FLOW_TYPE | Phase: $CURRENT_PHASE"
echo "────────────────────"
exit 0
