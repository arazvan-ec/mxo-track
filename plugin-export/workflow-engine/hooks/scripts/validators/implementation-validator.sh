#!/usr/bin/env bash
# Implementation phase validator (SOFT gates)
# For full/full-flow: requires plan exists AND tests written
# Exit 0 = pass, Exit 1 = warn
set -euo pipefail
source "$(dirname "$0")/../config-helper.sh"

EDIT_FILE="${1:-}"

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
    echo "WARNING (SOFT): No hay plan de implementacion para full-flow."
    echo "Crea el plan antes de implementar."
    exit 1
  fi
fi

# Contradiction detection: tests_passed=true with tests_written=0 (HARD gate)
TESTS_PASSED=$(jq -r '.evidence.tests_passed // "null"' "$STATE_FILE" 2>/dev/null || echo "null")
TESTS_WRITTEN=$(jq -r '.evidence.tests_written // 0' "$STATE_FILE" 2>/dev/null || echo "0")
if [ "$TESTS_PASSED" = "true" ] && [ "$TESTS_WRITTEN" -eq 0 ]; then
  echo "WARNING (SOFT): Contradiccion — tests_passed=true pero tests_written=0."
  echo "No se puede afirmar que tests pasan sin haber escrito tests."
  echo "Escribe tests primero o corrige evidence.tests_passed."
  exit 1
fi

# TDD check: require tests for full-flow (HARD gate)
# Skip TDD check if the file being edited IS a test file (we're writing the test!)
IS_TEST_FILE=false
case "$EDIT_FILE" in
  */tests/*|*Test.php|*.test.*|*.spec.*) IS_TEST_FILE=true ;;
esac

if [ "$IS_FULL" = true ] && [ "$IS_TEST_FILE" = false ]; then
  cd "$REPO"

  # Gather test file evidence from working tree using configured test paths
  TEST_FILES=""
  local_test_paths=$(read_config_array "test_paths" "tests")
  while IFS= read -r tpath; do
    TEST_FILES="${TEST_FILES}$(git diff --name-only -- "$tpath" 2>/dev/null || true)"
    TEST_FILES="${TEST_FILES}$(git diff --cached --name-only -- "$tpath" 2>/dev/null || true)"
    TEST_FILES="${TEST_FILES}$(git ls-files --others --exclude-standard -- "$tpath" 2>/dev/null || true)"
  done <<< "$local_test_paths"

  # Also check recent commits on this branch for test files (last 20 commits)
  COMMITTED_TESTS=""
  while IFS= read -r tpath; do
    COMMITTED_TESTS="${COMMITTED_TESTS}$(git log -20 --diff-filter=AM --name-only --pretty=format: -- "$tpath" 2>/dev/null | grep -v '^$' || true)"
  done <<< "$local_test_paths"

  HAS_REAL_TESTS=false
  if [ -n "$TEST_FILES" ] || [ -n "$COMMITTED_TESTS" ]; then
    HAS_REAL_TESTS=true
  fi

  # Gate A: tests_written == 0 AND no real tests → TDD violation
  if [ "$TESTS_WRITTEN" -eq 0 ] && [ "$HAS_REAL_TESTS" = false ]; then
    echo "WARNING (SOFT): TDD — No test changes detected for full-flow."
    echo "Write a failing test first. NO PRODUCTION CODE WITHOUT A FAILING TEST FIRST."
    exit 1
  fi

  # Gate B (ANTI-GAMING): tests_written > 0 BUT no real test files anywhere → contradiction
  if [ "$TESTS_WRITTEN" -gt 0 ] && [ "$HAS_REAL_TESTS" = false ]; then
    echo "WARNING (SOFT): ANTI-GAMING — tests_written=$TESTS_WRITTEN pero no se encontraron archivos de test en git diff ni en commits recientes."
    echo "Si realmente escribiste tests, deben estar en las rutas de test configuradas."
    echo "Si no escribiste tests, corrige evidence.tests_written=0 y escribe tests primero."
    exit 1
  fi
fi

exit 0
