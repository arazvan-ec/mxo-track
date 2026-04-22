#!/usr/bin/env bash
# Capture phase validator (HARD gate — hardened 2026-04-22, Option 3-Enforced)
# Checks: execution_log_path set AND file exists
# Exit 0 = pass, Exit 2 = block
set -euo pipefail

STATE_FILE="${1:-.claude/session-state.json}"
REPO="/home/user/mxo-track"

EXEC_LOG=$(jq -r '.evidence.execution_log_path // ""' "$STATE_FILE" 2>/dev/null || echo "")

if [ -z "$EXEC_LOG" ]; then
  echo "BLOCKED: No execution_log_path registrado."
  echo "Crea docs/superpowers/execution-logs/$(date +%Y-%m-%d)-<feature>.md y set evidence.execution_log_path."
  exit 2
fi

if [ ! -f "$REPO/$EXEC_LOG" ] && [ ! -f "$EXEC_LOG" ]; then
  echo "BLOCKED: Execution log '$EXEC_LOG' no existe en disco."
  exit 2
fi

exit 0
