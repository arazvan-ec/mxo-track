#!/usr/bin/env bash
# Pre-Agent Check — PreToolUse hook for Agent
#
# Blocks Agent dispatch (except read-only "Explore" subagents)
# when there are uncommitted changes in the repo.
# This ensures subagents see a clean, pushed state.

set -euo pipefail

REPO="/home/user/mxo-track"

# Parse tool input from stdin
INPUT=$(cat)
SUBAGENT_TYPE=$(echo "$INPUT" | jq -r '.tool_input.subagent_type // ""')

# Read-only agents are safe — skip check
if [ "$SUBAGENT_TYPE" = "Explore" ]; then
  exit 0
fi

# Check for uncommitted changes
DIRTY=$(git -C "$REPO" status --porcelain 2>/dev/null || true)

if [ -n "$DIRTY" ]; then
  # Build a compact file list (one per line, truncated to first 10)
  FILE_LIST=$(echo "$DIRTY" | head -10 | awk '{print $NF}' | tr '\n' ', ' | sed 's/,$//')
  TOTAL=$(echo "$DIRTY" | wc -l | tr -d ' ')
  SUFFIX=""
  if [ "$TOTAL" -gt 10 ]; then
    SUFFIX=" ... y $((TOTAL - 10)) mas"
  fi

  echo "{\"hookSpecificOutput\":{\"hookEventName\":\"PreToolUse\",\"permissionDecision\":\"deny\",\"permissionDecisionReason\":\"❌ Commit changes before dispatching agents. Uncommitted files ($TOTAL): $FILE_LIST$SUFFIX\"}}"
  exit 0
fi

# Clean repo — allow
exit 0
