#!/usr/bin/env bash
# Debug-flow validator — validates that root cause investigation and
# pattern-wide search were completed before editing code.
#
# Exit codes (relaxed for stress-test 2026-03-24):
#   0 = pass
#   1 = warn (soft gate, relaxed from HARD)

set -euo pipefail

STATE_FILE="${1:?Usage: debug-validator.sh STATE_FILE FILE_PATH}"
FILE_PATH="${2:-}"

if [ ! -f "$STATE_FILE" ]; then
  echo "No session-state.json found"
  exit 1
fi

ROOT_CAUSE=$(jq -r '.evidence.root_cause_identified // false' "$STATE_FILE" 2>/dev/null || echo "false")
ROOT_CAUSE_DESC=$(jq -r '.evidence.root_cause_description // ""' "$STATE_FILE" 2>/dev/null || echo "")
PATTERN_WIDE=$(jq -r '.evidence.pattern_wide_search_done // false' "$STATE_FILE" 2>/dev/null || echo "false")
PATTERN_WIDE_DESC=$(jq -r '.evidence.pattern_wide_description // ""' "$STATE_FILE" 2>/dev/null || echo "")
CONSULT_DONE=$(jq -r '.evidence.decisions_read // false' "$STATE_FILE" 2>/dev/null || echo "false")
LOGS_SCANNED=$(jq -r '.evidence.logs_scanned // false' "$STATE_FILE" 2>/dev/null || echo "false")

ERRORS=""

# Gate 1: Consult phase (HARD) — must have checked retrospectives/decisions
if [ "$CONSULT_DONE" != "true" ] && [ "$LOGS_SCANNED" != "true" ]; then
  ERRORS="${ERRORS}DEBUG GATE: Completa la fase Consultar antes de editar codigo. Busca en docs/superpowers/retrospectives/ y docs/decisions/log.md bugs similares. Luego: jq '.evidence.decisions_read = true' session-state.json. "
fi

# Gate 2: Root cause identified (HARD) — must have investigated before fixing
# Anti-gaming: require a textual description of the root cause (>=20 chars)
if [ "$ROOT_CAUSE" != "true" ]; then
  ERRORS="${ERRORS}DEBUG GATE: Identifica el root cause antes de implementar el fix. Sigue Skill 8 Phase 1 (Root Cause Investigation). Luego: jq '.evidence.root_cause_identified = true | .evidence.root_cause_description = \"<descripcion>\"' session-state.json. "
elif [ ${#ROOT_CAUSE_DESC} -lt 20 ]; then
  ERRORS="${ERRORS}DEBUG GATE: root_cause_identified=true pero falta root_cause_description (min 20 chars). Describe el root cause: jq '.evidence.root_cause_description = \"<descripcion detallada>\"' session-state.json. "
fi

# Gate 3: Pattern-wide investigation (HARD) — must have searched for same pattern elsewhere
# Anti-gaming: require a textual description of the search performed (>=20 chars)
if [ "$PATTERN_WIDE" != "true" ]; then
  ERRORS="${ERRORS}DEBUG GATE: Completa Pattern-Wide Investigation (Skill 8 Phase 2.5) antes del fix. Busca el mismo patron defectuoso en todo el codebase. Luego: jq '.evidence.pattern_wide_search_done = true | .evidence.pattern_wide_description = \"<descripcion>\"' session-state.json. "
elif [ ${#PATTERN_WIDE_DESC} -lt 20 ]; then
  ERRORS="${ERRORS}DEBUG GATE: pattern_wide_search_done=true pero falta pattern_wide_description (min 20 chars). Describe la busqueda realizada: jq '.evidence.pattern_wide_description = \"<descripcion detallada>\"' session-state.json. "
fi

if [ -n "$ERRORS" ]; then
  echo "WARNING (SOFT — stress-test): $ERRORS"
  exit 1
fi

exit 0
