#!/usr/bin/env bash
# SessionStart hook: resets session-state.json for process enforcement
# AND outputs detailed session context so Claude has continuity across sessions.

set -euo pipefail

REPO="/home/user/mxo-track"
STATE_FILE="$REPO/.claude/session-state.json"
EXEC_LOGS_DIR="$REPO/docs/superpowers/execution-logs"
TODAY=$(date +%Y-%m-%d)

# ── Helper: gather and output session context ──
output_context() {
  local resume_status="$1"

  echo "=== SESSION CONTEXT ==="
  echo "Date: $TODAY | Resume: $resume_status"

  # Current branch
  local branch
  branch=$(git -C "$REPO" branch --show-current 2>/dev/null || echo "(detached or unknown)")
  echo "Branch: $branch"

  # Previous session info from state file
  if [ -f "$STATE_FILE" ]; then
    local has_summary prev_date prev_flow prev_phase
    has_summary=$(jq -r '.last_work_summary.previous_date // ""' "$STATE_FILE" 2>/dev/null || echo "")
    if [ -n "$has_summary" ]; then
      # New day: read from preserved summary
      prev_date=$(jq -r '.last_work_summary.previous_date' "$STATE_FILE" 2>/dev/null)
      prev_flow=$(jq -r '.last_work_summary.previous_flow' "$STATE_FILE" 2>/dev/null)
      prev_phase=$(jq -r '.last_work_summary.previous_phase' "$STATE_FILE" 2>/dev/null)
    else
      # Resume: read from current state
      prev_date=$(jq -r '.session_date // "unknown"' "$STATE_FILE" 2>/dev/null)
      prev_flow=$(jq -r '.flow_type // "none"' "$STATE_FILE" 2>/dev/null)
      prev_phase=$(jq -r '.current_phase // "none"' "$STATE_FILE" 2>/dev/null)
    fi
    echo "Previous session: $prev_date, flow=$prev_flow, phase=$prev_phase"
  else
    echo "Previous session: (no previous session)"
  fi

  echo ""

  # Recent commits
  echo "Recent commits (last 10):"
  local commits
  commits=$(git -C "$REPO" log --oneline -10 2>/dev/null || echo "  (no commits available)")
  echo "$commits" | while IFS= read -r line; do
    echo "  $line"
  done

  echo ""

  # Merged claude/* branches
  local merged
  merged=$(git -C "$REPO" branch --merged main 2>/dev/null | grep 'claude/' | sed 's/^[* ]*//' || true)
  if [ -n "$merged" ]; then
    echo "Recently merged branches (claude/*):"
    echo "$merged" | while IFS= read -r line; do
      echo "  $line"
    done
  fi

  # Pending work items
  if [ -f "$STATE_FILE" ]; then
    local pending_count
    pending_count=$(jq -r '.pending_work // [] | length' "$STATE_FILE" 2>/dev/null || echo "0")
    if [ "$pending_count" -gt 0 ]; then
      echo ""
      echo "⚠ Pending work ($pending_count items):"
      jq -r '.pending_work[] | "  [\(.priority)] \(.title)"' "$STATE_FILE" 2>/dev/null || true
      echo "  Spec: $(jq -r '.pending_work[0].spec // "N/A"' "$STATE_FILE" 2>/dev/null)"
    fi
  fi

  # Last execution log with preview
  if [ -d "$EXEC_LOGS_DIR" ]; then
    local latest_log
    latest_log=$(ls -t "$EXEC_LOGS_DIR"/*.md 2>/dev/null | head -1 || true)
    if [ -n "$latest_log" ]; then
      local log_name
      log_name=$(basename "$latest_log")
      echo ""
      echo "Last execution log: $log_name"
      head -6 "$latest_log" 2>/dev/null | while IFS= read -r line; do
        echo "  $line"
      done
    fi
  fi

  echo "=== END CONTEXT ==="
}

# ── Helper: build last_work_summary JSON from current state + git ──
build_last_work_summary() {
  local prev_date prev_flow prev_phase prev_branch
  prev_date=$(jq -r '.session_date // ""' "$STATE_FILE" 2>/dev/null || echo "")
  prev_flow=$(jq -r '.flow_type // "null"' "$STATE_FILE" 2>/dev/null || echo "null")
  prev_phase=$(jq -r '.current_phase // "null"' "$STATE_FILE" 2>/dev/null || echo "null")
  prev_branch=$(git -C "$REPO" branch --show-current 2>/dev/null || echo "unknown")

  # Recent commits as JSON array
  local commits_json
  commits_json=$(git -C "$REPO" log --oneline -10 2>/dev/null | jq -R -s 'split("\n") | map(select(length > 0))' || echo '[]')

  # Merged claude/* branches as JSON array
  local merged_raw merged_json
  merged_raw=$(git -C "$REPO" branch --merged main 2>/dev/null | grep 'claude/' | sed 's/^[* ]*//' || true)
  if [ -n "$merged_raw" ]; then
    merged_json=$(echo "$merged_raw" | jq -R -s 'split("\n") | map(select(length > 0))')
  else
    merged_json='[]'
  fi

  # Last execution log
  local log_file="" log_preview=""
  if [ -d "$EXEC_LOGS_DIR" ]; then
    local latest_log
    latest_log=$(ls -t "$EXEC_LOGS_DIR"/*.md 2>/dev/null | head -1 || true)
    if [ -n "$latest_log" ]; then
      log_file=$(basename "$latest_log")
      log_preview=$(head -6 "$latest_log" 2>/dev/null | jq -R -s '.' || echo '""')
    fi
  fi
  [ -z "$log_preview" ] && log_preview='""'

  cat <<EOJSON
{
    "previous_date": "$prev_date",
    "previous_branch": "$prev_branch",
    "previous_flow": "$prev_flow",
    "previous_phase": "$prev_phase",
    "recent_commits": $commits_json,
    "merged_branches": $merged_json,
    "last_execution_log": {
      "file": "$log_file",
      "preview": $log_preview
    }
  }
EOJSON
}

# ── Main flow ──

# Same day: keep state, output context, exit
if [ -f "$STATE_FILE" ]; then
  EXISTING_DATE=$(jq -r '.session_date // ""' "$STATE_FILE" 2>/dev/null || echo "")
  if [ "$EXISTING_DATE" = "$TODAY" ]; then
    # Auto-restore user_approved if phase >= implementation and spec+plan exist
    # This prevents deadlock on resume: can't edit code because approved=false,
    # can't get approval because no user message yet.
    CURRENT_PHASE=$(jq -r '.current_phase // ""' "$STATE_FILE" 2>/dev/null || echo "")
    SPEC_EXISTS=$(jq -r '.evidence.spec_path // ""' "$STATE_FILE" 2>/dev/null || echo "")
    PLAN_EXISTS=$(jq -r '.evidence.plan_path // ""' "$STATE_FILE" 2>/dev/null || echo "")
    USER_APPROVED=$(jq -r '.evidence.user_approved // false' "$STATE_FILE" 2>/dev/null || echo "false")

    if [ "$USER_APPROVED" != "true" ] && [ -n "$SPEC_EXISTS" ] && [ -n "$PLAN_EXISTS" ]; then
      case "$CURRENT_PHASE" in
        implementation|verification|capture|retrospective|finalize)
          jq '.evidence.user_approved = true' "$STATE_FILE" > /tmp/ss-resume.json && mv /tmp/ss-resume.json "$STATE_FILE"
          ;;
      esac
    fi

    output_context "yes (same day)"
    exit 0
  fi
fi

# New day (or no state file): preserve summary, reset state, output context

# Build last_work_summary before resetting
LAST_WORK_SUMMARY='{}'
PENDING_WORK='[]'
if [ -f "$STATE_FILE" ]; then
  LAST_WORK_SUMMARY=$(build_last_work_summary)
  PENDING_WORK=$(jq -c '.pending_work // []' "$STATE_FILE" 2>/dev/null || echo '[]')
fi

# Create fresh session state with preserved last_work_summary and pending_work
cat > "$STATE_FILE" <<EOJSON
{
  "session_date": "$TODAY",
  "flow_type": null,
  "current_phase": null,
  "interaction_id": 0,
  "interaction_classification": null,
  "phase_history": [],
  "last_work_summary": $LAST_WORK_SUMMARY,
  "pending_work": $PENDING_WORK,
  "evidence": {
    "interaction_id": 0,
    "decisions_read": false,
    "logs_scanned": false,
    "user_turns": 0,
    "alternatives_proposed": false,
    "user_approved": false,
    "spec_path": null,
    "plan_path": null,
    "tests_written": 0,
    "tests_passed": null,
    "lint_clean": null,
    "execution_log_path": null,
    "branch_strategy": null,
    "root_cause_identified": false,
    "pattern_wide_search_done": false,
    "task_progress": {"current": 0, "total": 0, "label": null, "completed_labels": [], "task_index": []},
    "work_context": {"description": null, "problems": {"total": 0, "current": 0, "labels": []}, "wave": {"total": 0, "current": 0, "label": null, "labels": []}},
    "todo_progress": {"total": 0, "completed": 0, "in_progress_label": null, "items": []}
  },
  "deviation": {
    "active": false,
    "reason": null,
    "skipped_phases": [],
    "return_to_phase": null,
    "acknowledged_by_user": false
  }
}
EOJSON

output_context "no (new day)"
exit 0
