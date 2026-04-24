#!/usr/bin/env bash
# Pre-Agent Check — PreToolUse hook for Agent
#
# Behavior:
# 1. Block Agent dispatch (except "Explore") when uncommitted changes exist.
# 2. Warn when the agent prompt references `.claude/**` paths AND the current
#    interaction_classification is insufficient for framework-path edits —
#    subagents inherit the same classify-validator as main, so the dispatch
#    would fail silently without this guard.

set -euo pipefail

REPO="/home/user/mxo-track"
STATE_FILE="$REPO/.claude/session-state.json"

# Parse tool input from stdin
INPUT=$(cat)
SUBAGENT_TYPE=$(echo "$INPUT" | jq -r '.tool_input.subagent_type // ""')
AGENT_PROMPT=$(echo "$INPUT" | jq -r '.tool_input.prompt // ""')

# Read-only agents are safe — skip check
if [ "$SUBAGENT_TYPE" = "Explore" ]; then
  exit 0
fi

# ── Gate 1: uncommitted changes ──
DIRTY=$(git -C "$REPO" status --porcelain 2>/dev/null || true)

if [ -n "$DIRTY" ]; then
  FILE_LIST=$(echo "$DIRTY" | head -10 | awk '{print $NF}' | tr '\n' ', ' | sed 's/,$//')
  TOTAL=$(echo "$DIRTY" | wc -l | tr -d ' ')
  SUFFIX=""
  if [ "$TOTAL" -gt 10 ]; then
    SUFFIX=" ... y $((TOTAL - 10)) mas"
  fi

  echo "{\"hookSpecificOutput\":{\"hookEventName\":\"PreToolUse\",\"permissionDecision\":\"deny\",\"permissionDecisionReason\":\"❌ Commit changes before dispatching agents. Uncommitted files ($TOTAL): $FILE_LIST$SUFFIX\"}}"
  exit 0
fi

# ── Gate 2: classification sufficient for .claude/** prompt references ──
# If the agent prompt mentions .claude/ paths and classification is weak,
# the agent will hit classify-validator mid-task. Warn so the dispatcher
# can reclassify before committing.
if [ -f "$STATE_FILE" ] && echo "$AGENT_PROMPT" | grep -qE '\.claude/'; then
  CLASS=$(jq -r '.interaction_classification // "null"' "$STATE_FILE" 2>/dev/null)
  case "$CLASS" in
    full|debug)
      : # OK — subagent will pass classify-validator for .claude/** writes.
      ;;
    *)
      MSG="⚠ Agent prompt references .claude/** paths but classification='$CLASS' is insufficient. classify-validator will block the agent's writes. Reclassify before retrying: jq '.interaction_classification=\"full\" | .flow_type=\"full\"' .claude/session-state.json > /tmp/ss.json && mv /tmp/ss.json .claude/session-state.json"
      echo "{\"systemMessage\":\"$MSG\"}"
      ;;
  esac
fi

# Clean repo — allow (warnings emitted above do not block)
exit 0
