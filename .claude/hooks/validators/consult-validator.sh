#!/usr/bin/env bash
# Consult phase validator (SOFT gate — stress-test 2026-03-24)
# Checks: decisions_read OR logs_scanned
# Exit 0 = pass, Exit 1 = warn (soft, relaxed from HARD for stress-test)
set -euo pipefail

STATE_FILE="${1:-.claude/session-state.json}"

DECISIONS_READ=$(jq -r '.evidence.decisions_read // false' "$STATE_FILE" 2>/dev/null || echo "false")
LOGS_SCANNED=$(jq -r '.evidence.logs_scanned // false' "$STATE_FILE" 2>/dev/null || echo "false")

if [ "$DECISIONS_READ" != "true" ] && [ "$LOGS_SCANNED" != "true" ]; then
  echo "WARNING (SOFT — stress-test): Consult phase incompleta. Lee docs/decisions/log.md o escanea execution-logs/ antes de continuar."
  echo "Luego: jq '.evidence.decisions_read = true' .claude/session-state.json"
  exit 1
fi

exit 0
