#!/usr/bin/env bash
# UserPromptSubmit hook — injects parallel subagent task status into context.
#
# Reads .claude/parallel-tasks.json and outputs a compact summary of active
# (non-done, non-failed) tasks. If no active tasks, outputs nothing.
#
# Also prunes stale entries: tasks in terminal state (done/failed) older than
# 1 hour are removed to prevent the file from growing unbounded.
#
# Runs alongside user-prompt-state.sh (the main status hook). Does not block.

set -euo pipefail

REPO="/home/user/mxo-track"
FILE="$REPO/.claude/parallel-tasks.json"

[ ! -f "$FILE" ] && exit 0

# Prune entries in terminal state older than 1h
NOW_EPOCH=$(date +%s)
TMP=$(mktemp)
jq --argjson now "$NOW_EPOCH" '
  .tasks |= with_entries(
    select(
      (.value.phase != "done" and .value.phase != "failed")
      or (($now - (.value.updated_at | fromdate? // $now)) < 3600)
    )
  )
' "$FILE" > "$TMP" 2>/dev/null && mv "$TMP" "$FILE" || rm -f "$TMP"

# Count active tasks
ACTIVE_COUNT=$(jq '[.tasks | to_entries[] | select(.value.phase != "done" and .value.phase != "failed")] | length' "$FILE" 2>/dev/null || echo 0)
TOTAL_COUNT=$(jq '.tasks | length' "$FILE" 2>/dev/null || echo 0)

[ "$TOTAL_COUNT" -eq 0 ] && exit 0

# Emoji per phase
phase_emoji() {
  case "$1" in
    started)      echo "🟢" ;;
    reading)      echo "📖" ;;
    implementing) echo "✍️" ;;
    verifying)    echo "🔍" ;;
    done)         echo "✅" ;;
    failed)       echo "❌" ;;
    *)            echo "•" ;;
  esac
}

# Header line
if [ "$ACTIVE_COUNT" -gt 0 ]; then
  DONE_COUNT=$(jq '[.tasks | to_entries[] | select(.value.phase == "done")] | length' "$FILE")
  FAILED_COUNT=$(jq '[.tasks | to_entries[] | select(.value.phase == "failed")] | length' "$FILE")
  echo "🔀 Parallel tasks: ${ACTIVE_COUNT} active, ${DONE_COUNT} done, ${FAILED_COUNT} failed"
else
  # All tasks terminal — show brief summary then exit
  DONE_COUNT=$(jq '[.tasks | to_entries[] | select(.value.phase == "done")] | length' "$FILE")
  FAILED_COUNT=$(jq '[.tasks | to_entries[] | select(.value.phase == "failed")] | length' "$FILE")
  echo "🔀 Parallel tasks complete: ${DONE_COUNT} done, ${FAILED_COUNT} failed"
  exit 0
fi

# One line per task (max 10 to keep status compact)
jq -r '.tasks | to_entries[] | "\(.key)\t\(.value.phase)\t\(.value.progress // "")"' "$FILE" 2>/dev/null | head -10 | while IFS=$'\t' read -r ID PHASE NOTE; do
  EMOJI=$(phase_emoji "$PHASE")
  if [ -n "$NOTE" ]; then
    echo "  ${EMOJI} ${ID} — ${PHASE}: ${NOTE}"
  else
    echo "  ${EMOJI} ${ID} — ${PHASE}"
  fi
done

exit 0
