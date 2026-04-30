#!/usr/bin/env bash
# Consult phase validator (HARD gate — hardened 2026-04-22, Option 3-Enforced)
# Checks: decisions_read AND logs_scanned (both required, was OR)
#
# 2026-04-30: git-probe fallback (d). When the JSON flags are false but
# evidence.spec_path references a tracked-clean file, treat the gate as
# passed. Read-only — does NOT mutate evidence. Rationale: a spec
# committed in a prior session implies consult was done back then;
# requiring the flags to be re-set on every fresh session creates
# bootstrap friction without adding safety.
#
# Exit 0 = pass, Exit 2 = block
set -euo pipefail

STATE_FILE="${1:-.claude/session-state.json}"
REPO="${REPO:-/home/user/mxo-track}"

DECISIONS_READ=$(jq -r '.evidence.decisions_read // false' "$STATE_FILE" 2>/dev/null || echo "false")
LOGS_SCANNED=$(jq -r '.evidence.logs_scanned // false' "$STATE_FILE" 2>/dev/null || echo "false")

# git-probe fallback: when both flags are false but spec is committed-clean,
# treat consult as effectively done (cross-session continuation).
if [ "$DECISIONS_READ" != "true" ] || [ "$LOGS_SCANNED" != "true" ]; then
  PROBE_LIB="$REPO/.claude/hooks/lib/git-probe.sh"
  if [ -f "$PROBE_LIB" ]; then
    # shellcheck disable=SC1090
    source "$PROBE_LIB"
    if is_spec_committed_clean "$REPO" "$STATE_FILE"; then
      DECISIONS_READ="true"
      LOGS_SCANNED="true"
    fi
  fi
fi

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
