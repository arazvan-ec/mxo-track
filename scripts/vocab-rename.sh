#!/usr/bin/env bash
# vocab-rename.sh — Hito 3 Phase C, C-4.
#
# Atomic helper to rename a canonical in docs/knowledge/_vocabulary.yaml:
#   - Replace `canonical: <old>` with `canonical: <new>`.
#   - Insert old as alias `{term: "<old>", lang: "en", surface: "deprecated"}`
#     into the entry's `aliases:` list (handles `[]`, missing list,
#     and existing list cases).
#   - If <new_authoritative_path> provided, replace
#     `authoritative_path:` for that entry.
#
# Usage:
#   scripts/vocab-rename.sh <old_canonical> <new_canonical> [<new_authoritative_path>]
#
# Exit codes:
#   0  success
#   2  validation failure (missing args, old not found, new already
#      taken, new path missing, etc.)
#   3  multi-match (registry data bug — refuse to write)
#   4  internal write error (atomic mv aborted)
#
# Origin: 2026-04-29 hito 3 Phase C tooling.

set -euo pipefail

REPO_ROOT="$(git rev-parse --show-toplevel 2>/dev/null)"
if [ -z "$REPO_ROOT" ]; then
  REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
fi
VOCAB_FILE="${VOCAB_FILE:-$REPO_ROOT/docs/knowledge/_vocabulary.yaml}"

usage() {
  echo "Usage: $0 <old_canonical> <new_canonical> [<new_authoritative_path>]" >&2
  exit 2
}

[ "$#" -ge 2 ] || usage
OLD="$1"
NEW="$2"
NEW_PATH="${3:-}"

if [ ! -f "$VOCAB_FILE" ]; then
  echo "ERROR: vocab file not found: $VOCAB_FILE" >&2
  exit 2
fi

if [ "$OLD" = "$NEW" ]; then
  echo "ERROR: old_canonical and new_canonical are identical" >&2
  exit 2
fi

# Validation: count matches for OLD (must be exactly 1) and NEW (must be 0).
old_count=$(grep -c -E "^  - canonical: ${OLD}\$" "$VOCAB_FILE" 2>/dev/null || true)
new_count=$(grep -c -E "^  - canonical: ${NEW}\$" "$VOCAB_FILE" 2>/dev/null || true)

if [ "$old_count" -eq 0 ]; then
  echo "ERROR: old canonical '$OLD' not found in registry" >&2
  exit 2
fi
if [ "$old_count" -gt 1 ]; then
  echo "ERROR: old canonical '$OLD' appears $old_count times — registry data bug" >&2
  exit 3
fi
if [ "$new_count" -gt 0 ]; then
  echo "ERROR: new canonical '$NEW' already exists in registry" >&2
  exit 2
fi

if [ -n "$NEW_PATH" ]; then
  if [ ! -f "$REPO_ROOT/$NEW_PATH" ] && [ ! -f "$NEW_PATH" ]; then
    echo "ERROR: new authoritative_path does not exist: $NEW_PATH" >&2
    exit 2
  fi
fi

TMP_FILE=$(mktemp "${VOCAB_FILE}.XXXXXX") || { echo "ERROR: mktemp failed" >&2; exit 4; }
trap 'rm -f "$TMP_FILE"' EXIT

awk_status=0
awk -v old="$OLD" -v new="$NEW" -v new_path="$NEW_PATH" '
  BEGIN {
    in_entry = 0
    alias_inserted = 0
    alias_line = "      - {term: \"" old "\", lang: \"en\", surface: \"deprecated\"}"
  }

  /^  - canonical: / {
    in_entry = ($0 == "  - canonical: " old) ? 1 : 0
    if (in_entry) {
      print "  - canonical: " new
      next
    }
  }

  # aliases shapes (mawk-safe: literal patterns, no \s):
  #   "    aliases: []"
  #   "    aliases:"
  #   "    aliases:" followed by list lines
  in_entry && $0 == "    aliases: []" {
    print "    aliases:"
    print alias_line
    alias_inserted = 1
    next
  }
  in_entry && $0 == "    aliases:" {
    print $0
    print alias_line
    alias_inserted = 1
    next
  }

  in_entry && new_path != "" && /^    authoritative_path: / {
    print "    authoritative_path: " new_path
    next
  }

  { print }

  END {
    if (alias_inserted == 0) {
      exit 7
    }
  }
' "$VOCAB_FILE" > "$TMP_FILE" || awk_status=$?

if [ "$awk_status" -ne 0 ]; then
  echo "ERROR: awk pass failed (status=$awk_status); aborting without write" >&2
  exit 4
fi

# Sanity: NEW must now be present, OLD as canonical must be gone.
if ! grep -q -E "^  - canonical: ${NEW}\$" "$TMP_FILE"; then
  echo "ERROR: post-write verification failed (new canonical absent)" >&2
  exit 4
fi
if grep -q -E "^  - canonical: ${OLD}\$" "$TMP_FILE"; then
  echo "ERROR: post-write verification failed (old canonical still present)" >&2
  exit 4
fi

mv "$TMP_FILE" "$VOCAB_FILE"
trap - EXIT

if [ -n "$NEW_PATH" ]; then
  echo "renamed $OLD → $NEW; alias added (surface: deprecated); path: $NEW_PATH"
else
  echo "renamed $OLD → $NEW; alias added (surface: deprecated); path unchanged"
fi
