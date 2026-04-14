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

# Mirror into task_progress unless a plan has been parsed (plan is authoritative).
# This enables the status line to show TodoWrite-driven progress for flows where
# the model uses TodoWrite directly instead of a parsed plan file.
HAS_PLAN_INDEX=$(jq -r '(.evidence.task_progress.task_index // []) | length' "$STATE_FILE" 2>/dev/null || echo "0")
if [ "$HAS_PLAN_INDEX" = "0" ] && [ "$TOTAL" != "0" ]; then
  COMPLETED_LABELS=$(echo "$TODOS" | jq -c '[.[] | select(.status == "completed") | (.activeForm // .content // "" | .[0:60])]' 2>/dev/null || echo "[]")
  CURRENT_IDX=$((COMPLETED + 1))
  if [ "$CURRENT_IDX" -gt "$TOTAL" ]; then
    CURRENT_IDX="$TOTAL"
  fi
  jq --argjson total "$TOTAL" \
     --argjson cur "$CURRENT_IDX" \
     --arg lbl "$IN_PROGRESS_LABEL" \
     --argjson cl "$COMPLETED_LABELS" '
    .evidence.task_progress.total = $total |
    .evidence.task_progress.current = $cur |
    .evidence.task_progress.label = (if $lbl == "" then null else $lbl end) |
    .evidence.task_progress.completed_labels = $cl
  ' "$STATE_FILE" > /tmp/todo_task.json && mv /tmp/todo_task.json "$STATE_FILE"
fi

exit 0
