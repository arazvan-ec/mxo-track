#!/usr/bin/env bash
# Implementation phase validator (HARD gate for plan, SOFT for TDD)
# For full-flow: requires plan exists
# TDD check: warns if no tests written yet
# Exit 0 = pass, Exit 1 = warn, Exit 2 = block
set -euo pipefail

STATE_FILE="${1:-.claude/session-state.json}"
REPO="/home/user/mxo-track"

FLOW_TYPE=$(jq -r '.flow_type // ""' "$STATE_FILE" 2>/dev/null || echo "")

# For full-flow: require plan exists with tasks
if [ "$FLOW_TYPE" = "full-flow" ] || [ "$FLOW_TYPE" = "full" ]; then
  PLAN_PATH=$(jq -r '.evidence.plan_path // ""' "$STATE_FILE" 2>/dev/null || echo "")
  PLAN_FULL=""
  if [ -n "$PLAN_PATH" ]; then
    if [ -f "$REPO/$PLAN_PATH" ]; then
      PLAN_FULL="$REPO/$PLAN_PATH"
    elif [ -f "$PLAN_PATH" ]; then
      PLAN_FULL="$PLAN_PATH"
    fi
  fi

  if [ -z "$PLAN_FULL" ] || [ ! -f "$PLAN_FULL" ]; then
    echo "BLOCKED: No hay plan de implementacion para full-flow."
    echo "Crea el plan (Skill 3) antes de implementar."
    exit 2
  fi
fi

# TDD check: warn if no tests written (soft gate)
TESTS_WRITTEN=$(jq -r '.evidence.tests_written // 0' "$STATE_FILE" 2>/dev/null || echo "0")
if [ "$TESTS_WRITTEN" -eq 0 ]; then
  # Check working tree for test changes (migrated from tdd-gate.sh)
  cd "$REPO"
  BACKEND_TESTS=$(
    git diff --name-only -- 'backend/tests/' 2>/dev/null
    git diff --cached --name-only -- 'backend/tests/' 2>/dev/null
    git ls-files --others --exclude-standard -- 'backend/tests/' 2>/dev/null
  )
  FRONTEND_TESTS=$(
    git diff --name-only -- 'frontend/src/' 2>/dev/null | grep -E '\.(test|spec)\.' || true
    git diff --cached --name-only -- 'frontend/src/' 2>/dev/null | grep -E '\.(test|spec)\.' || true
    git ls-files --others --exclude-standard -- 'frontend/src/' 2>/dev/null | grep -E '\.(test|spec)\.' || true
  )

  if [ -z "$BACKEND_TESTS" ] && [ -z "$FRONTEND_TESTS" ]; then
    echo "WARNING: TDD — No test changes detected. Write a failing test first (Skill 7)."
    exit 1
  fi
fi

exit 0
