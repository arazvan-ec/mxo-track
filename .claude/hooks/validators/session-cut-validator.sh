#!/usr/bin/env bash
# session-cut-validator.sh — B3 session-cut gate.
#
# Blocks transitions where the prior phase's stamp matches today's
# session_date. Two transitions guarded:
#   planning → implementation     uses evidence.plan_session_date
#   retrospective → finalize      uses evidence.last_code_commit_session_date
#
# Invoked by phase-advance.sh at the relevant transitions.
#
# Args:  $1 = transition label (e.g., "planning->implementation")
#        $2 = state file path
#
# Exit:  0 = pass; 2 = block (HARD); honors SKIP_SESSION_CUT_GATE=1
#        with a stderr notice that a decision-log entry is required.
#
# Origin: 2026-04-30 cross-session resume hardening (B3).
# Spec: docs/superpowers/specs/2026-04-29-cross-session-resume-hardening-design.md

set -uo pipefail

TRANSITION="${1:-}"
STATE_FILE="${2:-}"

[ -z "$TRANSITION" ] && exit 0
[ -f "$STATE_FILE" ] || exit 0

if [ "${SKIP_SESSION_CUT_GATE:-0}" = "1" ]; then
  echo "⚠ SKIP_SESSION_CUT_GATE=1 — bypassing session-cut for '$TRANSITION'" >&2
  echo "   Reminder: emergency bypass requires entry in docs/decisions/log.md per CLAUDE.md policy." >&2
  exit 0
fi

session_date=$(jq -r '.session_date // ""' "$STATE_FILE" 2>/dev/null || echo "")
[ -z "$session_date" ] && exit 0

case "$TRANSITION" in
  planning-to-implementation)
    stamp_field="plan_session_date"
    transition_label="planning → implementation"
    ;;
  retrospective-to-finalize)
    stamp_field="last_code_commit_session_date"
    transition_label="retrospective → finalize"
    ;;
  *)
    exit 0
    ;;
esac

stamp=$(jq -r --arg f "$stamp_field" '.evidence[$f] // ""' "$STATE_FILE" 2>/dev/null || echo "")

# No stamp recorded → cannot enforce; pass with WARN to stderr.
if [ -z "$stamp" ]; then
  echo "⚠ session-cut: no '$stamp_field' recorded; advancing without enforcement" >&2
  exit 0
fi

if [ "$stamp" = "$session_date" ]; then
  echo "❌ Session-cut gate bloqueó '$transition_label':" >&2
  echo "   $stamp_field == session_date ($session_date)." >&2
  echo "   B3 requiere sesión fresca (fecha distinta) para revisión independiente del paso anterior." >&2
  echo "   Acciones: (1) cerrar sesión y retomar mañana; (2) bypass: SKIP_SESSION_CUT_GATE=1 + entrada en docs/decisions/log.md." >&2
  exit 2
fi

exit 0
