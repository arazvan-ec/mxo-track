#!/usr/bin/env bash
# SessionStart hook: initializes session-state.json for process enforcement.
#
# This hook runs at the start of every Claude Code session (startup/resume).
# It creates a fresh session-state.json that the full-flow-gate.sh checks
# before allowing Edit/Write operations on source code.
#
# Claude must update session-state.json fields by writing to it before
# editing source code. The full-flow-gate.sh enforces this.

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
  "active_spec": null,
  "active_plan": null,
  "execution_log": null
}
EOJSON

exit 0
