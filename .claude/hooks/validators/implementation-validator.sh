#!/usr/bin/env bash
# Implementation phase validator (HARD gates for plan and TDD)
# For full/full-flow: requires plan exists AND tests written
# Exit 0 = pass, Exit 1 = warn, Exit 2 = block
set -euo pipefail

STATE_FILE="${1:-.claude/session-state.json}"
EDIT_FILE="${2:-}"
REPO="/home/user/mxo-track"

FLOW_TYPE=$(jq -r '.flow_type // ""' "$STATE_FILE" 2>/dev/null || echo "")
IS_FULL=false
if [ "$FLOW_TYPE" = "full-flow" ] || [ "$FLOW_TYPE" = "full" ]; then
  IS_FULL=true
fi

# For full-flow: require plan exists with tasks (HARD gate)
if [ "$IS_FULL" = true ]; then
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

# Contradiction detection: tests_passed=true with tests_written=0 (HARD gate)
TESTS_PASSED=$(jq -r '.evidence.tests_passed // "null"' "$STATE_FILE" 2>/dev/null || echo "null")
TESTS_WRITTEN=$(jq -r '.evidence.tests_written // 0' "$STATE_FILE" 2>/dev/null || echo "0")
if [ "$TESTS_PASSED" = "true" ] && [ "$TESTS_WRITTEN" -eq 0 ]; then
  echo "BLOCKED: Contradiccion — tests_passed=true pero tests_written=0."
  echo "No se puede afirmar que tests pasan sin haber escrito tests."
  echo "Escribe tests primero (Skill 7) o corrige evidence.tests_passed."
  exit 2
fi

# TDD check: require tests for full-flow (HARD gate)
# Skip TDD check if the file being edited IS a test file (we're writing the test!)
IS_TEST_FILE=false
case "$EDIT_FILE" in
  */tests/*|*Test.php|*.test.*|*.spec.*) IS_TEST_FILE=true ;;
esac

if [ "$IS_FULL" = true ] && [ "$TESTS_WRITTEN" -eq 0 ] && [ "$IS_TEST_FILE" = false ]; then
  # Check working tree for test changes
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
    echo "BLOCKED: TDD — No test changes detected for full-flow."
    echo "Write a failing test first (Skill 7). NO PRODUCTION CODE WITHOUT A FAILING TEST FIRST."
    exit 2
  fi
fi

exit 0
