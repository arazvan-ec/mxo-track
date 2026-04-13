#!/usr/bin/env bash
# todowrite-mirror.sh — PostToolUse hook for TodoWrite tool.
# Mirrors the model's todo list into evidence.todo_progress so the status line
# can show fine-grained progress between phase milestones.
#
# Non-blocking: always exits 0 even if input is malformed.

set -euo pipefail

REPO="/home/user/mxo-track"
STATE_FILE="$REPO/.claude/session-state.json"

[ -f "$STATE_FILE" ] || exit 0

INPUT=$(cat || true)
if [ -z "$INPUT" ]; then
  exit 0
fi

# Extract todos array from tool_input. Defensive: tolerate missing fields.
TODOS=$(echo "$INPUT" | jq -c '.tool_input.todos // []' 2>/dev/null || echo "[]")

# Compute summary
TOTAL=$(echo "$TODOS" | jq 'length')
COMPLETED=$(echo "$TODOS" | jq '[.[] | select(.status == "completed")] | length')
IN_PROGRESS_LABEL=$(echo "$TODOS" | jq -r '[.[] | select(.status == "in_progress")] | .[0].activeForm // .[0].content // ""')

# Compact items list (truncate content to keep state small)
ITEMS=$(echo "$TODOS" | jq -c '[.[] | {status: .status, label: (.activeForm // .content // "" | .[0:60])}]')

jq --argjson total "$TOTAL" \
   --argjson done "$COMPLETED" \
   --arg ip "$IN_PROGRESS_LABEL" \
   --argjson items "$ITEMS" '
  .evidence.todo_progress = {
    "total": $total,
    "completed": $done,
    "in_progress_label": (if $ip == "" then null else $ip end),
    "items": $items
  }
' "$STATE_FILE" > /tmp/todo_mirror.json && mv /tmp/todo_mirror.json "$STATE_FILE"

exit 0
