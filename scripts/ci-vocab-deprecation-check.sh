#!/usr/bin/env bash
# ci-vocab-deprecation-check.sh — Hito 3 Phase C, C-2.
#
# CI-side HARD enforcement. Scans the diff between origin/main and
# HEAD (or HEAD~1..HEAD as fallback) for deprecated-alias usage in
# code paths. Exits non-zero on any occurrence.
#
# Usage (CI):
#   bash scripts/ci-vocab-deprecation-check.sh
#
# Path filter mirrors C-1 pre-commit hook.
# Origin: 2026-04-29 hito 3 Phase C tooling.

set -euo pipefail

REPO_ROOT="$(git rev-parse --show-toplevel 2>/dev/null || echo "")"
if [ -z "$REPO_ROOT" ]; then
  echo "ERROR: ci-vocab-deprecation-check.sh: not inside a git repo" >&2
  exit 2
fi

VOCAB_FILE="${VOCAB_FILE:-$REPO_ROOT/docs/knowledge/_vocabulary.yaml}"
LIB_FILE="$REPO_ROOT/.claude/hooks/lib/vocabulary-reader.sh"

if [ ! -f "$VOCAB_FILE" ] || [ ! -f "$LIB_FILE" ]; then
  echo "WARN: vocab registry or reader lib missing; skipping" >&2
  exit 0
fi

# shellcheck disable=SC1090
source "$LIB_FILE"

pairs=$(vocab_deprecated_aliases "$VOCAB_FILE")
[ -z "$pairs" ] && exit 0

# Pick a diff range. Prefer origin/main; fall back to HEAD~1..HEAD.
RANGE=""
if git rev-parse --verify --quiet origin/main >/dev/null 2>&1; then
  RANGE="origin/main...HEAD"
elif git rev-parse --verify --quiet HEAD~1 >/dev/null 2>&1; then
  RANGE="HEAD~1..HEAD"
else
  echo "WARN: no diff range available (single-commit repo); skipping" >&2
  exit 0
fi

is_code_path() {
  case "$1" in
    docs/*|*.md|*.yaml|*.yml) return 1 ;;
    .claude/session-state.json|.claude/parallel-tasks.json) return 1 ;;
    *) return 0 ;;
  esac
}

found=0
changed=$(git diff --name-only --diff-filter=ACMR "$RANGE" 2>/dev/null || true)
[ -z "$changed" ] && exit 0

while IFS= read -r path; do
  [ -z "$path" ] && continue
  is_code_path "$path" || continue
  diff_added=$(git diff -U0 "$RANGE" -- "$path" 2>/dev/null \
    | awk '/^\+[^+]/' || true)
  [ -z "$diff_added" ] && continue
  while IFS='|' read -r alias canonical; do
    [ -z "$alias" ] && continue
    if echo "$diff_added" | grep -qiwE -- "${alias}" 2>/dev/null; then
      echo "ERROR: deprecated alias '${alias}' (canonical '${canonical}') introduced in ${path}" >&2
      found=$((found + 1))
    fi
  done <<< "$pairs"
done <<< "$changed"

if [ "$found" -gt 0 ]; then
  echo "ci-vocab-deprecation-check: $found deprecated-alias occurrence(s) in diff (range $RANGE)" >&2
  exit 1
fi

exit 0
