#!/usr/bin/env bash
# Pre-Push Gate — PreToolUse hook for Bash
#
# Detects `git push` commands and gates them:
# - For full/debug flows: tests_passed must be true (HARD)
# - For full/debug flows: execution log for today should exist (SOFT warning)
# - All other cases: pass
#
# Skips git push --dry-run commands.

set -euo pipefail

REPO="/home/user/mxo-track"
STATE_FILE="$REPO/.claude/session-state.json"

# Parse tool input from stdin
INPUT=$(cat)
COMMAND=$(echo "$INPUT" | jq -r '.tool_input.command // ""')

# Only activate on git push commands
if ! echo "$COMMAND" | grep -qE '\bgit\s+push\b'; then
  exit 0
fi

# Skip --dry-run
if echo "$COMMAND" | grep -qE '\-\-dry-run'; then
  exit 0
fi

deny() {
  local reason="$1"
  echo "{\"hookSpecificOutput\":{\"hookEventName\":\"PreToolUse\",\"permissionDecision\":\"deny\",\"permissionDecisionReason\":\"$reason\"}}"
  exit 0
}

warn() {
  local msg="$1"
  echo "{\"systemMessage\":\"$msg\"}"
  exit 0
}

# If no session-state, pass (don't block pushes when engine isn't active)
if [ ! -f "$STATE_FILE" ]; then
  exit 0
fi

FLOW_TYPE=$(jq -r '.flow_type // "null"' "$STATE_FILE" 2>/dev/null || echo "null")

# Only gate full and debug flows
if [ "$FLOW_TYPE" != "full" ] && [ "$FLOW_TYPE" != "debug" ]; then
  exit 0
fi

# HARD gate: tests_passed must be true
TESTS_PASSED=$(jq -r '.evidence.tests_passed // "null"' "$STATE_FILE" 2>/dev/null || echo "null")
if [ "$TESTS_PASSED" != "true" ]; then
  deny "PRE-PUSH GATE: No puedes pushear en flow '$FLOW_TYPE' sin haber verificado que los tests pasan. Ejecuta los tests y actualiza: jq '.evidence.tests_passed = true' session-state.json"
fi

# SOFT warning: execution log for today should exist
TODAY=$(date +%Y-%m-%d)
EXEC_LOG_PATH=$(jq -r '.evidence.execution_log_path // ""' "$STATE_FILE" 2>/dev/null || echo "")

if [ -z "$EXEC_LOG_PATH" ]; then
  # Check if any execution log exists for today
  TODAYS_LOGS=$(find "$REPO/docs/superpowers/execution-logs/" -name "${TODAY}-*" -type f 2>/dev/null | head -1)
  if [ -z "$TODAYS_LOGS" ]; then
    warn "PRE-PUSH WARNING ($FLOW_TYPE): No hay execution log para hoy ($TODAY). Considera crear uno en docs/superpowers/execution-logs/ antes del push final."
  fi
fi

exit 0
