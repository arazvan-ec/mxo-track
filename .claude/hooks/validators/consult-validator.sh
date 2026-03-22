#!/usr/bin/env bash
# Consult phase validator (SOFT gate)
# Checks: decisions_read OR logs_scanned
# Exit 0 = pass, Exit 1 = warn (soft), Exit 2 = block (hard)
set -euo pipefail

STATE_FILE="${1:-.claude/session-state.json}"

DECISIONS_READ=$(jq -r '.evidence.decisions_read // false' "$STATE_FILE" 2>/dev/null || echo "false")
LOGS_SCANNED=$(jq -r '.evidence.logs_scanned // false' "$STATE_FILE" 2>/dev/null || echo "false")

if [ "$DECISIONS_READ" != "true" ] && [ "$LOGS_SCANNED" != "true" ]; then
  echo "WARNING: Consult phase incomplete. Read docs/decisions/log.md or scan execution-logs before proceeding."
  exit 1
fi

exit 0
