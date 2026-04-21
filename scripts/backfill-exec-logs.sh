#!/usr/bin/env bash
# backfill-exec-logs.sh — one-shot script to inject YAML frontmatter into
# existing execution logs in docs/superpowers/execution-logs/.
#
# Idempotent: logs that already have frontmatter are skipped.
# Use --dry-run to preview without writing.
#
# Extraction heuristics:
#   type            ← normalize from "**Type:**" line
#   files_touched   ← backtick-quoted paths in "## Files" sections
#   actual_lines    ← sum of "(+X / -Y)" patterns in the same section
#   outcome         ← "success" if the branch was merged to main, else "null"
#   pr_number       ← extracted from merge commit message
#   tags,patterns   ← empty (filled oportunistically later)

set -uo pipefail

DRY_RUN=0
if [ "${1:-}" = "--dry-run" ]; then
  DRY_RUN=1
fi

LOGS_DIR="docs/superpowers/execution-logs"
PROCESSED=0
SKIPPED=0
MODIFIED=0
WARNINGS=0

has_frontmatter() {
  head -1 "$1" 2>/dev/null | grep -q '^---[[:space:]]*$'
}

extract_type() {
  local text
  text=$(grep -m1 -i '\*\*type:\*\*' "$1" 2>/dev/null | sed -E 's/.*\*\*[Tt]ype:\*\*[[:space:]]*//' | tr '[:upper:]' '[:lower:]')
  case "$text" in
    *bug*|*fix*|*debug*) echo "bugfix" ;;
    *feature*|*feat*) echo "feature" ;;
    *refactor*) echo "refactor" ;;
    *doc*) echo "docs" ;;
    *) echo "process" ;;
  esac
}

extract_branch() {
  grep -m1 -i '\*\*branch:\*\*' "$1" 2>/dev/null \
    | sed -nE 's/.*`([^`]+)`.*/\1/p' \
    | head -1
}

extract_files() {
  # Scan entire document for backticked paths containing a directory separator
  # and a file extension. This catches prose mentions, Changes tables, and
  # dedicated Files sections uniformly.
  awk '
    {
      line = $0
      while (match(line, /`[^`]+`/)) {
        path = substr(line, RSTART+1, RLENGTH-2)
        # Must contain a / (path separator) AND end with .ext (keep alphanum/./-/_)
        if (path ~ /\// && path ~ /\.[a-zA-Z0-9]+$/ && path !~ /[[:space:]]/) print path
        line = substr(line, RSTART+RLENGTH)
      }
    }
  ' "$1" | sort -u
}

extract_lines() {
  awk '
    /^##[[:space:]]+[Ff]iles/ { in_section=1; next }
    in_section && /^##[[:space:]]/ { exit }
    in_section {
      line = $0
      while (match(line, /\(\+[0-9]+/)) {
        n = substr(line, RSTART+2, RLENGTH-2)
        total += n
        line = substr(line, RSTART+RLENGTH)
      }
    }
    END { if (total>0) print total; else print "null" }
  ' "$1"
}

branch_merged() {
  local branch="$1"
  [ -z "$branch" ] && return 1
  git log --all --oneline --grep="$branch" --merges 2>/dev/null | head -1 | grep -q .
}

extract_pr() {
  local branch="$1"
  [ -z "$branch" ] && { echo "null"; return; }
  local pr
  pr=$(git log --all --oneline --grep="$branch" --merges 2>/dev/null | head -1 | grep -oE '#[0-9]+' | head -1 | tr -d '#')
  [ -z "$pr" ] && echo "null" || echo "$pr"
}

format_array() {
  local items="$1"
  if [ -z "$items" ]; then
    echo "[]"
  else
    local joined
    joined=$(echo "$items" | paste -sd, - | sed 's/,/, /g')
    echo "[$joined]"
  fi
}

process_log() {
  local log="$1"
  PROCESSED=$((PROCESSED+1))

  if has_frontmatter "$log"; then
    SKIPPED=$((SKIPPED+1))
    return 0
  fi

  local type branch files_list files_str lines outcome pr
  type=$(extract_type "$log")
  branch=$(extract_branch "$log")
  files_list=$(extract_files "$log")

  if [ -z "$files_list" ]; then
    WARNINGS=$((WARNINGS+1))
    echo "  ⚠ $(basename "$log"): no files_touched extracted" >&2
  fi

  files_str=$(format_array "$files_list")
  lines=$(extract_lines "$log")

  if [ -n "$branch" ] && branch_merged "$branch"; then
    outcome="success"
    pr=$(extract_pr "$branch")
  else
    outcome="null"
    pr="null"
  fi

  local fm
  fm=$(cat <<EOF
---
type: $type
tags: []
files_touched: $files_str
patterns: []
outcome: $outcome
outcome_verified_at: null
regressions_later: []
pr_number: $pr
estimated_lines: null
actual_lines: $lines
duration_minutes: null
consulted_in_future: []
---
EOF
)

  if [ "$DRY_RUN" = "1" ]; then
    echo "── $(basename "$log") ──"
    echo "$fm"
    echo ""
    return 0
  fi

  local tmp
  tmp=$(mktemp)
  {
    echo "$fm"
    echo ""
    cat "$log"
  } > "$tmp"
  mv "$tmp" "$log"
  MODIFIED=$((MODIFIED+1))
}

if [ ! -d "$LOGS_DIR" ]; then
  echo "ERROR: $LOGS_DIR not found" >&2
  exit 1
fi

shopt -s nullglob
for log in "$LOGS_DIR"/*.md; do
  process_log "$log"
done
shopt -u nullglob

echo ""
echo "── Summary ──"
echo "Processed: $PROCESSED"
echo "Skipped (already had frontmatter): $SKIPPED"
echo "Modified: $MODIFIED"
echo "Warnings: $WARNINGS"
[ "$DRY_RUN" = "1" ] && echo "(dry-run mode — no files written)"
