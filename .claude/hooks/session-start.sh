#!/usr/bin/env bash
# SessionStart hook: resets session-state.json for process enforcement.
#
# This hook ONLY manages session state. Context (git log, decisions, etc.)
# is consulted on-demand by Claude per CLAUDE.md rules, not pre-generated.

set -euo pipefail

REPO="/home/user/mxo-track"
STATE_FILE="$REPO/.claude/session-state.json"
TODAY=$(date +%Y-%m-%d)

# Only reset if the session date has changed (new day) or file doesn't exist
if [ -f "$STATE_FILE" ]; then
  EXISTING_DATE=$(jq -r '.session_date // ""' "$STATE_FILE" 2>/dev/null || echo "")
  if [ "$EXISTING_DATE" = "$TODAY" ]; then
    # Same day, keep existing state (supports session resume)
    exit 0
  fi
fi

# Create fresh session state
cat > "$STATE_FILE" <<EOJSON
{
  "session_date": "$TODAY",
  "flow_type": null,
  "flow_declared": false,
  "learning_loop_done": false,
  "brainstorm_done": false,
  "brainstorm_user_turns": 0,
  "active_spec": null,
  "active_plan": null,
  "execution_log": null,
  "tdd_bypass": false
}
EOJSON

exit 0
