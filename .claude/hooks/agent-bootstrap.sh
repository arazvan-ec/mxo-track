#!/usr/bin/env bash
# agent-bootstrap.sh — Ensure the parent session-state has the classification
# and phase the orchestrator expects for a planned-work agent.
#
# Usage:
#   bash .claude/hooks/agent-bootstrap.sh <classification> [phase]
#
# Example:
#   bash .claude/hooks/agent-bootstrap.sh full implementation
#
# Designed to be the FIRST command a background agent runs. Solves the
# intermittent race where the parent's interaction_classification drops to
# null between the orchestrator setting it and the agent's first Edit/Write.
# Because planned-work agents already know what flow they belong to (the
# orchestrator briefed them), reasserting the classification is safe.
#
# Idempotent: if classification is already correct, does nothing. If it's
# null or different, sets it. Concurrent agents writing the same values
# don't race harmfully.
#
# Never resets evidence — only fixes classification/flow_type/phase.

set -euo pipefail

REPO="/home/user/mxo-track"
STATE_FILE="$REPO/.claude/session-state.json"

CLASS="${1:-full}"
PHASE="${2:-implementation}"

if [ ! -f "$STATE_FILE" ]; then
  echo "ERROR: session-state.json not found at $STATE_FILE" >&2
  exit 1
fi

case "$CLASS" in
  full|debug|light|explore|micro|informational) ;;
  *)
    echo "ERROR: unknown classification '$CLASS'" >&2
    exit 2
    ;;
esac

# Read current values
CUR_CLASS=$(jq -r '.interaction_classification // ""' "$STATE_FILE")
CUR_FLOW=$(jq -r '.flow_type // ""' "$STATE_FILE")
CUR_PHASE=$(jq -r '.current_phase // ""' "$STATE_FILE")

NEED_WRITE=0
[ "$CUR_CLASS" != "$CLASS" ] && NEED_WRITE=1
[ "$CUR_FLOW" != "$CLASS" ] && NEED_WRITE=1
[ -z "$CUR_PHASE" ] && NEED_WRITE=1

if [ "$NEED_WRITE" = "0" ]; then
  echo "bootstrap: state already at classification=$CLASS flow=$CLASS phase=$CUR_PHASE — no-op"
  exit 0
fi

# Retry loop protects against concurrent jq+mv races between sibling agents.
# Each attempt is atomic (jq to /tmp, mv); the loop just guards against
# losing a write under concurrency.
for attempt in 1 2 3; do
  TMP=$(mktemp)
  if jq --arg c "$CLASS" --arg p "$PHASE" '
    .interaction_classification = $c |
    .flow_type = $c |
    .current_phase = (.current_phase // $p)
  ' "$STATE_FILE" > "$TMP"; then
    mv "$TMP" "$STATE_FILE"
    break
  else
    rm -f "$TMP"
    if [ "$attempt" -eq 3 ]; then
      echo "ERROR: failed to update session-state after 3 attempts" >&2
      exit 3
    fi
    sleep 1
  fi
done

FINAL_CLASS=$(jq -r '.interaction_classification' "$STATE_FILE")
FINAL_PHASE=$(jq -r '.current_phase' "$STATE_FILE")
echo "bootstrap: classification=$FINAL_CLASS phase=$FINAL_PHASE"
