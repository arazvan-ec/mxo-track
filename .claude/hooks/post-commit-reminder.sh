#!/usr/bin/env bash
# PostToolUse hook: after a successful git commit, reminds to create
# an execution log if one doesn't exist for today.
#
# Non-blocking — only outputs a reminder message, never denies.

set -euo pipefail

INPUT=$(cat)
COMMAND=$(echo "$INPUT" | jq -r '.tool_input.command // ""')
STDOUT=$(echo "$INPUT" | jq -r '.tool_response.stdout // ""')

# Only trigger on git commit commands that succeeded
if ! echo "$COMMAND" | grep -q 'git commit'; then
  exit 0
fi

# Check if commit actually happened (output contains [branch hash] pattern)
if ! echo "$STDOUT" | grep -qE '^\['; then
  exit 0
fi

REPO="/home/user/mxo-track"
TODAY=$(date +%Y-%m-%d)
EXEC_LOG_DIR="$REPO/docs/superpowers/execution-logs"

# Check if execution log exists for today
if ! ls "$EXEC_LOG_DIR/${TODAY}-"*.md 1>/dev/null 2>&1; then
  # Check if this is a feat: or fix: commit (code changes need logs)
  if echo "$STDOUT" | grep -qE '(feat|fix):'; then
    echo "{\"systemMessage\":\"REMINDER: No execution log for today. Create docs/superpowers/execution-logs/${TODAY}-<feature>.md using the template in docs/superpowers/templates/execution-log-template.md\"}"
    exit 0
  fi
fi

exit 0
