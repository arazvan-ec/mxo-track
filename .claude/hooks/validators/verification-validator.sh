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
    # P3 2026-05-20: Smart acceptance of lint_clean=skipped when the skip is
    # provably honest. Two scenarios accept automatically:
    #   1. shellcheck binary not available (sandbox without lint tooling)
    #   2. diff doesn't contain any *.sh / *.bash files (nothing to lint)
    # Spec: docs/superpowers/specs/2026-05-20-verification-lint-skipped-smart-acceptance-design.md
    SMART_ACCEPT=0
    LINT_SKIP_REASON=""

    # Scenario 1: shellcheck missing
    if ! command -v shellcheck >/dev/null 2>&1; then
      SMART_ACCEPT=1
      LINT_SKIP_REASON="shellcheck_missing"
    fi

    # Scenario 2: no shell files in diff (uses shared git-refs helper for consistency
    # with sync-validator).
    if [ "$SMART_ACCEPT" = "0" ]; then
      LIB_GIT_REFS="$(dirname "$0")/../lib/git-refs.sh"
      DIFF_BASE=""
      if [ -f "$LIB_GIT_REFS" ]; then
        # shellcheck source=../lib/git-refs.sh
        source "$LIB_GIT_REFS" 2>/dev/null || true
        if declare -F get_plan_commit_parent >/dev/null 2>&1; then
          DIFF_BASE=$(get_plan_commit_parent "$STATE_FILE" 2>/dev/null || echo "")
        fi
      fi
      [ -z "$DIFF_BASE" ] && DIFF_BASE=$(git rev-parse origin/main 2>/dev/null || echo "")

      if [ -n "$DIFF_BASE" ]; then
        SHELL_IN_DIFF=$(git diff --name-only "$DIFF_BASE...HEAD" 2>/dev/null | grep -E '\.(sh|bash)$' || true)
        SHELL_IN_WT=$(git status --porcelain 2>/dev/null | awk '{print $2}' | grep -E '\.(sh|bash)$' || true)
        if [ -z "$SHELL_IN_DIFF" ] && [ -z "$SHELL_IN_WT" ]; then
          SMART_ACCEPT=1
          LINT_SKIP_REASON="no_shell_files_in_diff"
        fi
      fi
    fi

    if [ "$SMART_ACCEPT" = "1" ]; then
      # Record the reason in evidence; propagate as ⚠ (not ✅) downstream.
      jq --arg r "$LINT_SKIP_REASON" '.evidence.lint_skip_reason = $r' "$STATE_FILE" > /tmp/vv-fix.json && mv /tmp/vv-fix.json "$STATE_FILE" 2>/dev/null || true
      WARNINGS="${WARNINGS}- SOFT: lint_clean=skipped accepted (reason: $LINT_SKIP_REASON). Propagates as ⚠ to pre-push-gate.\n"
    elif [ "$STRICT_SKIPPED" = "1" ]; then
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
