#!/usr/bin/env bash
# git-refs.sh — shared helper for git reference resolution across validators.
# Origin: 2026-05-20 P3 — extracted from sync-validator pattern for reuse by
# verification-validator (smart lint-skipped acceptance) and future validators.

# get_plan_commit_parent — returns the git ref of the commit BEFORE the one
# that introduced evidence.plan_path. Used as the "since" reference for diffs
# that should include the plan creation commit itself.
#
# Args: $1 = STATE_FILE path (optional, defaults to .claude/session-state.json)
# Output: commit SHA or ref string to stdout. Empty string if cannot resolve.
# Fallback: origin/main when plan_path missing or not yet committed.
get_plan_commit_parent() {
  local state_file="${1:-.claude/session-state.json}"
  local repo
  repo="$(cd "$(dirname "$state_file")/.." && pwd 2>/dev/null || echo "")"
  [ -z "$repo" ] && repo="$(pwd)"

  local plan_path
  plan_path=$(jq -r '.evidence.plan_path // ""' "$state_file" 2>/dev/null || echo "")

  if [ -z "$plan_path" ]; then
    # No plan declared — fall back to origin/main
    (cd "$repo" && git rev-parse origin/main 2>/dev/null || echo "")
    return
  fi

  # Find the commit that introduced this file (oldest commit touching it)
  local plan_commit
  plan_commit=$(cd "$repo" && git log --diff-filter=A --follow --format=%H -- "$plan_path" 2>/dev/null | tail -1)

  if [ -z "$plan_commit" ]; then
    # Plan not yet committed — fall back to origin/main
    (cd "$repo" && git rev-parse origin/main 2>/dev/null || echo "")
    return
  fi

  # Return parent of plan_commit (so diff includes the plan commit itself)
  (cd "$repo" && git rev-parse "${plan_commit}^" 2>/dev/null || echo "$plan_commit")
}
