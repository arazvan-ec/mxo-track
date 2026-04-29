#!/usr/bin/env bash
# Verification phase validator (MIXED gate)
#
# Philosophy: Evidence must be honest. In full/debug flows (real feature work),
# "skipped" is no longer accepted — shellcheck is available (make lint-shell)
# and test suites exist, so skip is a signal of negligence, not infrastructure gap.
#
# In light/informational/explore/micro flows, "skipped" remains valid because
# those flows do not produce testable code changes by definition.
#
# Flow (full/debug):  tests_passed must be true|false (HARD)
#                     lint_clean   must be true|false (HARD)
# Flow (other):       "skipped" accepted as soft warn
#
# Accepts: true (pass), "skipped" (soft warn in non-full flows), null/false (block)
# Exit 0 = pass, Exit 1 = warn (soft), Exit 2 = block (hard)
set -euo pipefail

STATE_FILE="${1:-.claude/session-state.json}"

TESTS_PASSED=$(jq -r '.evidence.tests_passed // "null"' "$STATE_FILE" 2>/dev/null || echo "null")
LINT_CLEAN=$(jq -r '.evidence.lint_clean // "null"' "$STATE_FILE" 2>/dev/null || echo "null")
FLOW_TYPE=$(jq -r '.flow_type // "null"' "$STATE_FILE" 2>/dev/null || echo "null")

STRICT_SKIPPED=0
case "$FLOW_TYPE" in
  full|debug) STRICT_SKIPPED=1 ;;
esac

ERRORS=""
WARNINGS=""

case "$TESTS_PASSED" in
  true)   ;;  # pass
  skipped)
    if [ "$STRICT_SKIPPED" = "1" ]; then
      ERRORS="${ERRORS}- tests_passed=skipped no es aceptable en flow=$FLOW_TYPE. Ejecuta el test suite completo.\n"
    else
      WARNINGS="${WARNINGS}- SOFT: tests_passed=skipped (test infrastructure unavailable). Verify tests pass before merging.\n"
    fi
    ;;
  *)
    ERRORS="${ERRORS}- Tests no han pasado (tests_passed: $TESTS_PASSED). Ejecuta el test suite.\n"
    ;;
esac

case "$LINT_CLEAN" in
  true)   ;;  # pass
  skipped)
    if [ "$STRICT_SKIPPED" = "1" ]; then
      ERRORS="${ERRORS}- lint_clean=skipped no es aceptable en flow=$FLOW_TYPE. Ejecuta 'make lint && make lint-shell'.\n"
    else
      WARNINGS="${WARNINGS}- SOFT: lint_clean=skipped (lint tooling unavailable). Verify lint passes before merging.\n"
    fi
    ;;
  *)
    ERRORS="${ERRORS}- Lint no esta limpio (lint_clean: $LINT_CLEAN). Ejecuta 'make lint && make lint-shell'.\n"
    ;;
esac

if [ -n "$ERRORS" ]; then
  echo "BLOCKED: Verification incompleta:"
  echo -e "$ERRORS"
  echo "Ejecuta tests y lint antes de continuar (Skill 9)."
  exit 2
fi

# Sub-invocation: sync-validator (plan↔diff drift detection, HARD)
# Mirrors Layer C pattern (brainstorm-validator → socratic-review-validator).
SYNC_VALIDATOR_PATH="$(dirname "$0")/sync-validator.sh"
if [ -x "$SYNC_VALIDATOR_PATH" ]; then
  SYNC_OUTPUT=$("$SYNC_VALIDATOR_PATH" "$STATE_FILE" 2>&1 || true)
  SYNC_EXIT=$("$SYNC_VALIDATOR_PATH" "$STATE_FILE" >/dev/null 2>&1 && echo 0 || echo $?)
  if [ "$SYNC_EXIT" = "2" ]; then
    echo "$SYNC_OUTPUT"
    exit 2
  elif [ "$SYNC_EXIT" = "1" ] && [ -n "$SYNC_OUTPUT" ]; then
    WARNINGS="${WARNINGS}${SYNC_OUTPUT}\n"
  fi
fi

if [ -n "$WARNINGS" ]; then
  echo -e "$WARNINGS"
  exit 1
fi

exit 0
