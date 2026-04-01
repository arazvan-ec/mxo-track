#!/usr/bin/env bash
# Consult phase validator (SOFT gate)
# Checks: decisions_read OR logs_scanned
# Exit 0 = pass, Exit 1 = warn
set -euo pipefail
source "$(dirname "$0")/../config-helper.sh"

DECISIONS_READ=$(jq -r '.evidence.decisions_read // false' "$STATE_FILE" 2>/dev/null || echo "false")
LOGS_SCANNED=$(jq -r '.evidence.logs_scanned // false' "$STATE_FILE" 2>/dev/null || echo "false")

if [ "$DECISIONS_READ" != "true" ] && [ "$LOGS_SCANNED" != "true" ]; then
  echo "WARNING (SOFT): Consult phase incompleta. Lee $DECISIONS_LOG o escanea $EXEC_LOGS_PATH/ antes de continuar."
  echo "Luego: jq '.evidence.decisions_read = true' $STATE_FILE"
  exit 1
fi

exit 0
