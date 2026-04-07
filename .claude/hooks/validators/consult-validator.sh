#!/usr/bin/env bash
# Consult phase validator (HARD gate — hardened 2026-04-07)
# Checks: decisions_read OR logs_scanned
# Exit 0 = pass, Exit 2 = block
set -euo pipefail

STATE_FILE="${1:-.claude/session-state.json}"

DECISIONS_READ=$(jq -r '.evidence.decisions_read // false' "$STATE_FILE" 2>/dev/null || echo "false")
LOGS_SCANNED=$(jq -r '.evidence.logs_scanned // false' "$STATE_FILE" 2>/dev/null || echo "false")

if [ "$DECISIONS_READ" != "true" ] && [ "$LOGS_SCANNED" != "true" ]; then
  echo "BLOCKED: Consult phase incompleta. Lee docs/decisions/log.md o escanea execution-logs/ antes de continuar."
  exit 2
fi

exit 0
