#!/usr/bin/env bash
# post-commit-session-stamp.sh — record code-commit session date.
#
# When invoked as a git post-commit hook, classifies the files in
# the most recent commit and, if any classify as `code` or `test`,
# stamps `evidence.last_code_commit_session_date` with today's date.
#
# Single-writer invariant for `last_code_commit_session_date`: this
# script is the only sanctioned writer. Direct jq writes by the
# model are intentionally NOT defended here (the field's purpose is
# to record real commits; if the model fakes it, it's gaming the
# B3 gate, which surfaces in the retrospective per CLAUDE.md
# bypass-heuristic policy).
#
# Origin: 2026-04-30 cross-session resume hardening (B3).
# Spec: docs/superpowers/specs/2026-04-29-cross-session-resume-hardening-design.md

set -euo pipefail

REPO="$(git rev-parse --show-toplevel 2>/dev/null || echo "")"
[ -z "$REPO" ] && exit 0

STATE_FILE="$REPO/.claude/session-state.json"
[ -f "$STATE_FILE" ] || exit 0

CLASSIFY_LIB="$REPO/.claude/hooks/lib/classify-file.sh"
[ -f "$CLASSIFY_LIB" ] || exit 0

# shellcheck disable=SC1090
source "$CLASSIFY_LIB"

# Files in the latest commit. Limit to ACMR (added/copied/modified/renamed).
files=$(cd "$REPO" && git diff-tree --no-commit-id --name-only --diff-filter=ACMR -r HEAD 2>/dev/null || true)
[ -z "$files" ] && exit 0

stamp=0
while IFS= read -r f; do
  [ -z "$f" ] && continue
  cls=$(classify_file "$REPO/$f" 2>/dev/null || echo "")
  if [ "$cls" = "code" ] || [ "$cls" = "test" ]; then
    stamp=1
    break
  fi
done <<< "$files"

[ "$stamp" -eq 1 ] || exit 0

today=$(date +%Y-%m-%d)
TMP=$(mktemp "${STATE_FILE}.XXXXXX") || exit 0
trap 'rm -f "$TMP"' EXIT
if jq --arg d "$today" '.evidence.last_code_commit_session_date = $d' "$STATE_FILE" > "$TMP" 2>/dev/null; then
  mv "$TMP" "$STATE_FILE"
  trap - EXIT
fi
