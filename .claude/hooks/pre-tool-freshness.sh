#!/usr/bin/env bash
# pre-tool-freshness.sh — PreToolUse hook (all tools).
# Layer D of Option 3-Enforced workflow gates. Non-blocking.
#
# Emits ⚠ POSIBLE STALE STATE: <reason> when the upcoming tool call
# signals that session-state likely lags reality. Visibility only;
# always exits 0.

set -uo pipefail

REPO="/home/user/mxo-track"
STATE_FILE="$REPO/.claude/session-state.json"

[ -f "$STATE_FILE" ] || exit 0

INPUT=$(cat || true)
[ -z "$INPUT" ] && exit 0

TOOL=$(echo "$INPUT" | jq -r '.tool_name // ""' 2>/dev/null)

PHASE=$(jq -r '.current_phase // "null"' "$STATE_FILE")
FLOW=$(jq -r '.flow_type // "null"' "$STATE_FILE")
SPEC_PATH=$(jq -r '.evidence.spec_path // ""' "$STATE_FILE")
PLAN_PATH=$(jq -r '.evidence.plan_path // ""' "$STATE_FILE")
TESTS_PASSED=$(jq -r '.evidence.tests_passed // "null"' "$STATE_FILE")
LINT_CLEAN=$(jq -r '.evidence.lint_clean // "null"' "$STATE_FILE")
BRANCH_STRATEGY=$(jq -r '.evidence.branch_strategy // ""' "$STATE_FILE")

warn() {
  echo "⚠ POSIBLE STALE STATE: $1" >&2
}

case "$TOOL" in
  Bash)
    CMD=$(echo "$INPUT" | jq -r '.tool_input.command // ""' 2>/dev/null)
    # About to git commit in full flow without having advanced past consult
    if echo "$CMD" | grep -qE '^\s*git\s+commit'; then
      if [ "$FLOW" = "full" ] && [ "$PHASE" = "consult" ]; then
        warn "git commit during consult phase — no design/plan artifacts yet"
      fi
    fi
    # About to git push without finalize declared (in full flow)
    if echo "$CMD" | grep -qE 'git\s+push'; then
      if [ "$FLOW" = "full" ] && [ "$PHASE" != "finalize" ] && [ -z "$BRANCH_STRATEGY" ]; then
        : # checkpoints are OK; only warn if declaring final push
      fi
      if [ "$PHASE" = "finalize" ] && [ -z "$BRANCH_STRATEGY" ]; then
        warn "git push in finalize phase but branch_strategy unset — declare strategy before final push"
      fi
    fi
    # About to claim tests passed without running them fresh
    if echo "$CMD" | grep -qE 'phpunit|vitest|npm\s+test|pytest'; then
      : # running tests is fine, no warning
    fi
    ;;
  Write|Edit)
    FP=$(echo "$INPUT" | jq -r '.tool_input.file_path // ""' 2>/dev/null)
    REL="${FP#"$REPO/"}"
    REL="${REL#/}"
    case "$REL" in
      docs/superpowers/specs/*.md)
        if [ "$PHASE" != "brainstorming" ] && [ "$PHASE" != "consult" ]; then
          warn "writing spec file but current_phase=$PHASE (expected brainstorming)"
        fi
        if [ -n "$SPEC_PATH" ] && [ "$SPEC_PATH" != "$REL" ] && [ "$SPEC_PATH" != "$FP" ]; then
          warn "writing new spec $REL but evidence.spec_path already set to $SPEC_PATH"
        fi
        ;;
      docs/superpowers/plans/*.md)
        if [ "$PHASE" != "planning" ]; then
          warn "writing plan file but current_phase=$PHASE (expected planning)"
        fi
        if [ -n "$PLAN_PATH" ] && [ "$PLAN_PATH" != "$REL" ] && [ "$PLAN_PATH" != "$FP" ]; then
          warn "writing new plan $REL but evidence.plan_path already set to $PLAN_PATH"
        fi
        ;;
      docs/superpowers/execution-logs/*.md)
        if [ "$PHASE" != "capture" ] && [ "$PHASE" != "retrospective" ]; then
          warn "writing execution log but current_phase=$PHASE (expected capture)"
        fi
        ;;
    esac
    ;;
esac

exit 0
