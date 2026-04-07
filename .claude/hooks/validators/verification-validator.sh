#!/usr/bin/env bash
# Verification phase validator (MIXED gate)
#
# Philosophy: Evidence must be honest. Forcing tests_passed=true when tests cannot
# run teaches the model to lie — the opposite of what evidence exists for. The
# "skipped" value is an honest declaration that propagates as a soft warning through
# the entire gate chain (verification → pre-push → PR reviewer), ensuring the gap
# is never hidden.
#
# Flow: tests available → run → true|false (HARD)
#       tests unavailable → "skipped" → SOFT warning → reviewer verifies before merge
#
# Accepts: true (pass), "skipped" (soft warn), null/false (block)
# Exit 0 = pass, Exit 1 = warn (soft), Exit 2 = block (hard)
set -euo pipefail

STATE_FILE="${1:-.claude/session-state.json}"

TESTS_PASSED=$(jq -r '.evidence.tests_passed // "null"' "$STATE_FILE" 2>/dev/null || echo "null")
LINT_CLEAN=$(jq -r '.evidence.lint_clean // "null"' "$STATE_FILE" 2>/dev/null || echo "null")

ERRORS=""
WARNINGS=""

case "$TESTS_PASSED" in
  true)   ;;  # pass
  skipped)
    WARNINGS="${WARNINGS}- SOFT: tests_passed=skipped (test infrastructure unavailable). Verify tests pass before merging.\n"
    ;;
  *)
    ERRORS="${ERRORS}- Tests no han pasado (tests_passed: $TESTS_PASSED). Ejecuta el test suite o usa 'skipped' si no hay infraestructura de test.\n"
    ;;
esac

case "$LINT_CLEAN" in
  true)   ;;  # pass
  skipped)
    WARNINGS="${WARNINGS}- SOFT: lint_clean=skipped (lint tooling unavailable). Verify lint passes before merging.\n"
    ;;
  *)
    ERRORS="${ERRORS}- Lint no esta limpio (lint_clean: $LINT_CLEAN). Ejecuta make lint o usa 'skipped' si no hay tooling.\n"
    ;;
esac

if [ -n "$ERRORS" ]; then
  echo "BLOCKED: Verification incompleta:"
  echo -e "$ERRORS"
  echo "Ejecuta tests y lint antes de continuar (Skill 9)."
  exit 2
fi

if [ -n "$WARNINGS" ]; then
  echo -e "$WARNINGS"
  exit 1
fi

exit 0
