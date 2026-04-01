#!/usr/bin/env bash
# Capture phase validator (SOFT gate)
# Checks: execution_log_path exists
# Exit 0 = pass, Exit 1 = warn
set -euo pipefail
source "$(dirname "$0")/../config-helper.sh"

EXEC_LOG=$(jq -r '.evidence.execution_log_path // ""' "$STATE_FILE" 2>/dev/null || echo "")

if [ -z "$EXEC_LOG" ]; then
  echo "WARNING: No execution log registrado. Crea $EXEC_LOGS_PATH/$(date +%Y-%m-%d)-<feature>.md"
  exit 1
fi

# Check file actually exists
if [ ! -f "$REPO/$EXEC_LOG" ] && [ ! -f "$EXEC_LOG" ]; then
  echo "WARNING: Execution log '$EXEC_LOG' no existe. Crealo antes de continuar."
  exit 1
fi

exit 0
