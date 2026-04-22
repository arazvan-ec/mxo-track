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

# Reject input with >1 in_progress (Layer C discipline).
# Single in_progress is the invariant per TodoWrite contract and CLAUDE.md.
N_IP=$(echo "$TODOS" | jq '[.[] | select(.status == "in_progress")] | length')
if [ "$N_IP" -gt 1 ] 2>/dev/null; then
  echo "BLOCKED: TodoWrite has $N_IP in_progress items. Exactly 1 allowed." >&2
  echo "Offending items:" >&2
  echo "$TODOS" | jq -r '.[] | select(.status == "in_progress") | "  - \(.content)"' >&2
  exit 2
fi

# Compute summary
TOTAL=$(echo "$TODOS" | jq 'length')
COMPLETED=$(echo "$TODOS" | jq '[.[] | select(.status == "completed")] | length')
IN_PROGRESS_LABEL=$(echo "$TODOS" | jq -r '[.[] | select(.status == "in_progress")] | .[0].activeForm // .[0].content // ""')

# Compact items list (truncate content to keep state small)
ITEMS=$(echo "$TODOS" | jq -c '[.[] | {status: .status, label: (.activeForm // .content // "" | .[0:60])}]')

jq --argjson total "$TOTAL" \
   --argjson ndone "$COMPLETED" \
   --arg ip "$IN_PROGRESS_LABEL" \
   --argjson items "$ITEMS" '
  .evidence.todo_progress = {
    "total": $total,
    "completed": $ndone,
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

# Derive problems.current from in_progress label's [prefix] (Layer C).
# Avoids manual jq bookkeeping; the active todo's prefix dictates current problem.
if [ -n "$IN_PROGRESS_LABEL" ]; then
  IP_PREFIX=$(echo "$IN_PROGRESS_LABEL" | sed -n 's/^\[\([^]]*\)\].*/\1/p')
  if [ -n "$IP_PREFIX" ]; then
    NEW_CURRENT=$(jq --arg p "$IP_PREFIX" '
      (.evidence.work_context.problems.labels // [])
      | to_entries
      | map(select(.value | ascii_downcase | contains($p | ascii_downcase)))
      | .[0].key // -1
      | if . >= 0 then . + 1 else 0 end
    ' "$STATE_FILE" 2>/dev/null || echo "0")
    if [ "$NEW_CURRENT" -gt 0 ] 2>/dev/null; then
      jq --argjson cur "$NEW_CURRENT" '.evidence.work_context.problems.current = $cur' \
        "$STATE_FILE" > /tmp/todo_prob.json && mv /tmp/todo_prob.json "$STATE_FILE"
    fi
  fi
fi

exit 0
