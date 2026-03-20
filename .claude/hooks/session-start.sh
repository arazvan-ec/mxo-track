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
CONTEXT_FILE="$REPO/.claude/session-context.md"
TODAY=$(date +%Y-%m-%d)

# --- Generate session context (always, on every start/resume) ---
{
  echo "# Session Context"
  echo ""
  echo "> Auto-generated at $(date '+%Y-%m-%d %H:%M'). Read this first for recent context."
  echo ""

  # Recent commits
  echo "## Recent Commits"
  echo ""
  echo '```'
  cd "$REPO" && git log --oneline -15 2>/dev/null || echo "(no git history)"
  echo '```'
  echo ""

  # Working tree status
  echo "## Working Tree Status"
  echo ""
  echo '```'
  cd "$REPO" && git status --short 2>/dev/null || echo "(no git repo)"
  echo '```'
  echo ""

  # Active branches
  echo "## Active Branches"
  echo ""
  echo '```'
  cd "$REPO" && git branch -v --sort=-committerdate 2>/dev/null | head -10 || echo "(none)"
  echo '```'
  echo ""

  # Recent decisions (last 5 entries from log.md)
  DECISIONS_LOG="$REPO/docs/decisions/log.md"
  if [ -f "$DECISIONS_LOG" ]; then
    echo "## Recent Decisions"
    echo ""
    # Extract last 5 ### headers with their content (up to 30 lines)
    grep -n "^### " "$DECISIONS_LOG" 2>/dev/null | tail -5 | while IFS=: read -r lineno _rest; do
      sed -n "${lineno},$((lineno+5))p" "$DECISIONS_LOG"
      echo ""
    done
  fi

  # Latest execution log
  EXEC_LOGS_DIR="$REPO/docs/superpowers/execution-logs"
  if [ -d "$EXEC_LOGS_DIR" ]; then
    LATEST_LOG=$(ls -t "$EXEC_LOGS_DIR"/*.md 2>/dev/null | head -1)
    if [ -n "$LATEST_LOG" ]; then
      echo "## Latest Execution Log"
      echo ""
      echo "File: \`$(basename "$LATEST_LOG")\`"
      echo ""
      head -20 "$LATEST_LOG" 2>/dev/null
      echo ""
      echo "_(truncated — read full file for details)_"
    fi
  fi
} > "$CONTEXT_FILE" 2>/dev/null || true

# --- Output context so Claude sees it directly (no Read needed) ---
if [ -f "$CONTEXT_FILE" ]; then
  cat "$CONTEXT_FILE"
fi

# --- Session state management ---

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
  "execution_log": null,
  "tdd_bypass": false
}
EOJSON

exit 0
