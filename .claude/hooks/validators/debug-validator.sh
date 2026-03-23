#!/usr/bin/env bash
# Debug-flow validator — validates that root cause investigation and
# pattern-wide search were completed before editing code.
#
# Exit codes:
#   0 = pass
#   1 = warn (soft gate)
#   2 = block (hard gate)

set -euo pipefail

STATE_FILE="${1:?Usage: debug-validator.sh STATE_FILE FILE_PATH}"
FILE_PATH="${2:-}"

if [ ! -f "$STATE_FILE" ]; then
  echo "No session-state.json found"
  exit 2
fi

ROOT_CAUSE=$(jq -r '.evidence.root_cause_identified // false' "$STATE_FILE" 2>/dev/null || echo "false")
PATTERN_WIDE=$(jq -r '.evidence.pattern_wide_search_done // false' "$STATE_FILE" 2>/dev/null || echo "false")
CONSULT_DONE=$(jq -r '.evidence.decisions_read // false' "$STATE_FILE" 2>/dev/null || echo "false")
LOGS_SCANNED=$(jq -r '.evidence.logs_scanned // false' "$STATE_FILE" 2>/dev/null || echo "false")

ERRORS=""

# Gate 1: Consult phase (HARD) — must have checked retrospectives/decisions
if [ "$CONSULT_DONE" != "true" ] && [ "$LOGS_SCANNED" != "true" ]; then
  ERRORS="${ERRORS}DEBUG GATE: Completa la fase Consultar antes de editar codigo. Busca en docs/superpowers/retrospectives/ y docs/decisions/log.md bugs similares. Luego: jq '.evidence.decisions_read = true' session-state.json. "
fi

# Gate 2: Root cause identified (HARD) — must have investigated before fixing
if [ "$ROOT_CAUSE" != "true" ]; then
  ERRORS="${ERRORS}DEBUG GATE: Identifica el root cause antes de implementar el fix. Sigue Skill 8 Phase 1 (Root Cause Investigation). Luego: jq '.evidence.root_cause_identified = true' session-state.json. "
fi

# Gate 3: Pattern-wide investigation (HARD) — must have searched for same pattern elsewhere
if [ "$PATTERN_WIDE" != "true" ]; then
  ERRORS="${ERRORS}DEBUG GATE: Completa Pattern-Wide Investigation (Skill 8 Phase 2.5) antes del fix. Busca el mismo patron defectuoso en todo el codebase. Luego: jq '.evidence.pattern_wide_search_done = true' session-state.json. "
fi

if [ -n "$ERRORS" ]; then
  echo "$ERRORS"
  exit 2
fi

exit 0
