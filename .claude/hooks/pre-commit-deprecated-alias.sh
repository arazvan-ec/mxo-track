#!/usr/bin/env bash
# pre-commit-deprecated-alias.sh — Hito 3 Phase C, C-1.
#
# Scans staged code (not docs/specs/logs/yaml) for deprecated aliases
# from docs/knowledge/_vocabulary.yaml. Emits WARN per occurrence.
#
# Default: WARN-only, exit 0. Variants:
#   STRICT=1                  → exit 1 if any deprecated alias found
#   SKIP_VOCAB_PRECOMMIT=1    → no-op (exit 0, no scan)
#
# Install (per developer, opt-in):
#   ln -s ../../.claude/hooks/pre-commit-deprecated-alias.sh \
#         .git/hooks/pre-commit
#
# Or chain it from an existing pre-commit hook by sourcing/invoking.
#
# Origin: 2026-04-29 hito 3 Phase C tooling. See spec
# docs/superpowers/specs/2026-04-29-uls-phase-c-tooling-design.md.

set -euo pipefail

if [ "${SKIP_VOCAB_PRECOMMIT:-0}" = "1" ]; then
  exit 0
fi

REPO_ROOT="$(git rev-parse --show-toplevel 2>/dev/null || echo "")"
if [ -z "$REPO_ROOT" ]; then
  echo "WARN: pre-commit-deprecated-alias.sh: not inside a git repo, skipping" >&2
  exit 0
fi

VOCAB_FILE="${VOCAB_FILE:-$REPO_ROOT/docs/knowledge/_vocabulary.yaml}"
LIB_FILE="$REPO_ROOT/.claude/hooks/lib/vocabulary-reader.sh"

if [ ! -f "$VOCAB_FILE" ] || [ ! -f "$LIB_FILE" ]; then
  exit 0
fi

# shellcheck disable=SC1090
source "$LIB_FILE"

pairs=$(vocab_deprecated_aliases "$VOCAB_FILE")
[ -z "$pairs" ] && exit 0

is_code_path() {
  case "$1" in
    docs/*|*.md|*.yaml|*.yml) return 1 ;;
    .claude/session-state.json|.claude/parallel-tasks.json) return 1 ;;
    *) return 0 ;;
  esac
}

found=0
staged=$(git diff --cached --name-only --diff-filter=ACMR 2>/dev/null || true)
[ -z "$staged" ] && exit 0

while IFS= read -r path; do
  [ -z "$path" ] && continue
  is_code_path "$path" || continue
  diff_added=$(git diff --cached -U0 -- "$path" 2>/dev/null \
    | awk '/^\+[^+]/' || true)
  [ -z "$diff_added" ] && continue
  while IFS='|' read -r alias canonical; do
    [ -z "$alias" ] && continue
    if echo "$diff_added" | grep -qiwE -- "${alias}" 2>/dev/null; then
      echo "WARN: deprecated alias '${alias}' (canonical '${canonical}') in ${path}" >&2
      found=$((found + 1))
    fi
  done <<< "$pairs"
done <<< "$staged"

if [ "$found" -gt 0 ] && [ "${STRICT:-0}" = "1" ]; then
  echo "ERROR: $found deprecated-alias usage(s); STRICT=1 → blocking commit." >&2
  exit 1
fi

exit 0
