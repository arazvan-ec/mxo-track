#!/usr/bin/env bash
# Verification phase validator (HARD gate)
# Checks: tests_passed AND lint_clean
# Exit 0 = pass, Exit 2 = block
set -euo pipefail
source "$(dirname "$0")/../config-helper.sh"

TESTS_PASSED=$(jq -r '.evidence.tests_passed // "null"' "$STATE_FILE" 2>/dev/null || echo "null")
LINT_CLEAN=$(jq -r '.evidence.lint_clean // "null"' "$STATE_FILE" 2>/dev/null || echo "null")

ERRORS=""

if [ "$TESTS_PASSED" != "true" ]; then
  ERRORS="${ERRORS}- Tests no han pasado (tests_passed: $TESTS_PASSED). Ejecuta el test suite.\n"
fi

if [ "$LINT_CLEAN" != "true" ]; then
  ERRORS="${ERRORS}- Lint no esta limpio (lint_clean: $LINT_CLEAN). Ejecuta lint.\n"
fi

if [ -n "$ERRORS" ]; then
  echo "BLOCKED: Verification incompleta:"
  echo -e "$ERRORS"
  echo "Ejecuta tests y lint antes de continuar."
  exit 2
fi

exit 0
