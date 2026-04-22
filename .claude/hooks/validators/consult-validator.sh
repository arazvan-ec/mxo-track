#!/usr/bin/env bash
# Consult phase validator (HARD gate — hardened 2026-04-22, Option 3-Enforced)
# Checks: decisions_read AND logs_scanned (both required, was OR)
# Exit 0 = pass, Exit 2 = block
set -euo pipefail

STATE_FILE="${1:-.claude/session-state.json}"

DECISIONS_READ=$(jq -r '.evidence.decisions_read // false' "$STATE_FILE" 2>/dev/null || echo "false")
LOGS_SCANNED=$(jq -r '.evidence.logs_scanned // false' "$STATE_FILE" 2>/dev/null || echo "false")

MISSING=""
[ "$DECISIONS_READ" != "true" ] && MISSING="${MISSING}- decisions_read=false. Lee docs/decisions/log.md relevante.\n"
[ "$LOGS_SCANNED" != "true" ] && MISSING="${MISSING}- logs_scanned=false. Escanea execution-logs/ (consult.sh tag|file|pattern).\n"

if [ -n "$MISSING" ]; then
  echo "BLOCKED: Consult phase incompleta (ambos evidence flags son requeridos):"
  echo -e "$MISSING"
  echo "Set con: jq '.evidence.decisions_read=true | .evidence.logs_scanned=true' .claude/session-state.json > /tmp/ss.json && mv /tmp/ss.json .claude/session-state.json"
  exit 2
fi

exit 0
