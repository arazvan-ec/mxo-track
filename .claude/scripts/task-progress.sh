#!/usr/bin/env bash
# task-progress.sh — atomic updater for parallel subagent progress
#
# Usage:
#   task-progress.sh <task_id> <phase> [progress_note]
#
# Where:
#   task_id       unique identifier for this subagent task (e.g. "widget-system")
#   phase         one of: started, reading, implementing, verifying, done, failed
#   progress_note optional free-text describing current sub-step
#
# Writes to .claude/parallel-tasks.json atomically. Safe to call from multiple
# concurrent subagents — each only writes its own entry.
#
# Example:
#   bash .claude/scripts/task-progress.sh widget-system reading "2/10 files"
#   bash .claude/scripts/task-progress.sh widget-system implementing "section 4/7"
#   bash .claude/scripts/task-progress.sh widget-system done "222 lines written"

set -euo pipefail

REPO="$(git rev-parse --show-toplevel 2>/dev/null || echo "/home/user/mxo-track")"
FILE="$REPO/.claude/parallel-tasks.json"

TASK_ID="${1:-}"
PHASE="${2:-}"
NOTE="${3:-}"

if [ -z "$TASK_ID" ] || [ -z "$PHASE" ]; then
  echo "Usage: task-progress.sh <task_id> <phase> [progress_note]" >&2
  exit 2
fi

VALID_PHASES="started reading implementing verifying done failed"
if ! echo " $VALID_PHASES " | grep -q " $PHASE "; then
  echo "Invalid phase: $PHASE. Must be one of: $VALID_PHASES" >&2
  exit 2
fi

TS=$(date -Iseconds 2>/dev/null || date +%Y-%m-%dT%H:%M:%S%z)

[ ! -f "$FILE" ] && echo '{"tasks":{}}' > "$FILE"

TMP=$(mktemp)
jq --arg id "$TASK_ID" \
   --arg phase "$PHASE" \
   --arg note "$NOTE" \
   --arg ts "$TS" \
   '.tasks[$id] = ((.tasks[$id] // {"started_at": $ts}) + {"phase": $phase, "progress": $note, "updated_at": $ts})' \
   "$FILE" > "$TMP" && mv "$TMP" "$FILE"
