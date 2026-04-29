#!/usr/bin/env bash
# SessionStart hook: resets session-state.json for process enforcement
# AND outputs detailed session context so Claude has continuity across sessions.

set -euo pipefail

REPO="/home/user/mxo-track"
STATE_FILE="$REPO/.claude/session-state.json"
EXEC_LOGS_DIR="$REPO/docs/superpowers/execution-logs"
CONSULT="$REPO/.claude/hooks/consult.sh"
MARK_VERIFIED="$REPO/scripts/mark-verified.sh"
TODAY=$(date +%Y-%m-%d)

# ── Helper: check if previous state is resumable (spec+plan on disk, mid-flow) ──
is_resumable() {
  local state_file="$1"
  [ -f "$state_file" ] || return 1

  local spec plan phase
  spec=$(jq -r '.evidence.spec_path // ""' "$state_file" 2>/dev/null)
  plan=$(jq -r '.evidence.plan_path // ""' "$state_file" 2>/dev/null)
  phase=$(jq -r '.current_phase // ""' "$state_file" 2>/dev/null)

  [ -z "$spec" ] && return 1
  [ -z "$plan" ] && return 1
  [ ! -f "$REPO/$spec" ] && return 1
  [ ! -f "$REPO/$plan" ] && return 1

  case "$phase" in
    implementation|verification|capture|retrospective|finalize) return 0 ;;
  esac
  return 1
}

# ── Helper: restore user_approved if state is resumable and currently false ──
restore_approval_if_resumable() {
  local state_file="$1"
  local approved
  approved=$(jq -r '.evidence.user_approved // false' "$state_file" 2>/dev/null || echo "false")
  [ "$approved" = "true" ] && return 0

  if is_resumable "$state_file"; then
    jq '.evidence.user_approved = true' "$state_file" > /tmp/ss-resume.json && mv /tmp/ss-resume.json "$state_file"
  fi
}

# ── Helper: surface related past execution logs for the current branch ──
# Threshold: ≤5 modified files vs main. Skipped on main or when consult.sh missing.
surface_related_logs() {
  [ -x "$CONSULT" ] || return 0

  local branch
  branch=$(git -C "$REPO" branch --show-current 2>/dev/null || echo "")
  [ -z "$branch" ] && return 0
  [ "$branch" = "main" ] && return 0

  # Files changed in this branch vs main
  local files
  files=$(git -C "$REPO" diff --name-only main...HEAD 2>/dev/null || echo "")
  [ -z "$files" ] && return 0

  local count
  count=$(echo "$files" | wc -l | tr -d ' ')
  # Threshold: >5 files → skip to avoid noise
  [ "$count" -gt 5 ] && return 0

  # For each file, collect top 3 related logs
  local all_logs=""
  while IFS= read -r f; do
    [ -z "$f" ] && continue
    local logs
    logs=$("$CONSULT" --quiet file "$f" 2>/dev/null | head -3 || true)
    if [ -n "$logs" ]; then
      all_logs+="$logs"$'\n'
    fi
  done <<< "$files"

  # Dedupe by filename (4th column) and take top 10
  local deduped
  deduped=$(echo "$all_logs" | awk -F' \\| ' 'NF>=4 && !seen[$4]++' | head -10)
  [ -z "$deduped" ] && return 0

  local n
  n=$(echo "$deduped" | wc -l | tr -d ' ')
  echo ""
  echo "Related past logs for branch files ($n):"
  echo "$deduped" | while IFS= read -r line; do
    echo "  $line"
  done
}

# ── Helper: auto-verify logs whose branch was merged ≥3 days ago ──
auto_verify_merged_logs() {
  [ -x "$MARK_VERIFIED" ] || return 0
  [ -d "$EXEC_LOGS_DIR" ] || return 0

  local merged
  merged=$(git -C "$REPO" branch --merged main 2>/dev/null | grep 'claude/' | sed 's/^[* ]*//' || true)
  [ -z "$merged" ] && return 0

  local verified_count=0
  local cutoff
  cutoff=$(date -d '3 days ago' +%Y-%m-%d 2>/dev/null || date -v-3d +%Y-%m-%d 2>/dev/null || echo "")
  [ -z "$cutoff" ] && return 0

  while IFS= read -r branch; do
    [ -z "$branch" ] && continue
    # Find merge commit date for this branch
    local merge_date
    merge_date=$(git -C "$REPO" log --all --oneline --format="%ad" --date=short --merges --grep="$branch" 2>/dev/null | head -1)
    [ -z "$merge_date" ] && continue
    # Skip if merge happened within the last 3 days
    [[ "$merge_date" > "$cutoff" ]] && continue

    # Find log(s) referencing this branch
    while IFS= read -r log_path; do
      [ -z "$log_path" ] && continue
      local log_name
      log_name=$(basename "$log_path")
      # Only verify if outcome=success and outcome_verified_at=null
      local outcome verified
      outcome=$(awk '/^---[[:space:]]*$/{c++; if(c==2)exit; next} c==1 && /^outcome:[[:space:]]/{sub(/^outcome:[[:space:]]*/,""); print; exit}' "$log_path")
      verified=$(awk '/^---[[:space:]]*$/{c++; if(c==2)exit; next} c==1 && /^outcome_verified_at:[[:space:]]/{sub(/^outcome_verified_at:[[:space:]]*/,""); print; exit}' "$log_path")
      if [ "$outcome" = "success" ] && { [ -z "$verified" ] || [ "$verified" = "null" ]; }; then
        (cd "$REPO" && "$MARK_VERIFIED" "$log_name" >/dev/null 2>&1) && verified_count=$((verified_count+1))
      fi
    done < <(grep -lF "\`$branch\`" "$EXEC_LOGS_DIR"/*.md 2>/dev/null || true)
  done <<< "$merged"

  if [ "$verified_count" -gt 0 ]; then
    echo ""
    echo "Auto-verified $verified_count past log(s) (branches merged ≥3d ago)"
  fi
}

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

  # Surfacing: related past logs for current branch files (≤5 threshold)
  surface_related_logs

  # Auto-verify: logs whose branch was merged ≥3 days ago
  auto_verify_merged_logs

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
    restore_approval_if_resumable "$STATE_FILE"
    output_context "yes (same day)"
    exit 0
  fi
fi

# New day (or no state file): check if previous state is mid-flow resumable
if [ -f "$STATE_FILE" ] && is_resumable "$STATE_FILE"; then
  # Mid-flow continuation: just bump session_date, preserve all evidence
  jq --arg today "$TODAY" '.session_date = $today' "$STATE_FILE" > /tmp/ss-resume.json && mv /tmp/ss-resume.json "$STATE_FILE"
  restore_approval_if_resumable "$STATE_FILE"
  output_context "yes (resumed mid-flow across days)"
  exit 0
fi

# Not resumable: preserve summary, full reset, output context

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
    "retrospective_shown": false,
    "root_cause_identified": false,
    "pattern_wide_search_done": false,
    "task_progress": {"current": 0, "total": 0, "label": null, "completed_labels": [], "task_index": []},
    "work_context": {"description": null, "problems": {"total": 0, "current": 0, "labels": []}, "wave": {"total": 0, "current": 0, "label": null, "labels": []}},
    "todo_progress": {"total": 0, "completed": 0, "in_progress_label": null, "items": []}
  }
}
EOJSON

output_context "no (new day)"
exit 0
