#!/usr/bin/env bash
# graduate.sh — atomic graduation of a tag/pattern to the registry.
#
# Usage:
#   graduate.sh <name> --module=<file> --section=<heading> [--force] [--pattern]
#
# Validates:
#   - docs/knowledge/<file> exists
#   - <heading> appears as `^##+ <heading>` in that file, OR <heading> == "*"
#   - <name> has >= 3 occurrences in execution logs (via consult.sh stats)
#     unless --force is given
#
# Behaviour:
#   - Idempotent: if <name> is already present, outputs SKIP and exits 1
#   - --pattern: write under patterns: (default is tags:)
#
# Exit codes:
#   0 = graduated (entry added)
#   1 = skip (already graduated)
#   2 = error (missing args, validation failed)

set -uo pipefail

REPO_ROOT="$(cd "$(dirname "$(readlink -f "$0")")/.." && pwd)"
REGISTRY="${GRADUATE_REGISTRY:-$REPO_ROOT/docs/knowledge/_graduations.yaml}"
KNOWLEDGE_DIR="${GRADUATE_KNOWLEDGE_DIR:-$REPO_ROOT/docs/knowledge}"
CONSULT="${GRADUATE_CONSULT:-$REPO_ROOT/.claude/hooks/consult.sh}"

NAME=""
MODULE=""
SECTION=""
FORCE=0
IS_PATTERN=0

for arg in "$@"; do
  case "$arg" in
    --module=*)  MODULE="${arg#--module=}" ;;
    --section=*) SECTION="${arg#--section=}" ;;
    --force)     FORCE=1 ;;
    --pattern)   IS_PATTERN=1 ;;
    -*) echo "ERROR: unknown flag: $arg" >&2; exit 2 ;;
    *) NAME="$arg" ;;
  esac
done

[ -z "$NAME" ]    && { echo "ERROR: <name> required" >&2; exit 2; }
[ -z "$MODULE" ]  && { echo "ERROR: --module=<file> required" >&2; exit 2; }
[ -z "$SECTION" ] && { echo "ERROR: --section=<heading> required" >&2; exit 2; }
[ ! -f "$REGISTRY" ] && { echo "ERROR: registry not found: $REGISTRY" >&2; exit 2; }

# Validation 1: module exists
if [ ! -f "$KNOWLEDGE_DIR/$MODULE" ]; then
  echo "ERROR: module not found: $KNOWLEDGE_DIR/$MODULE" >&2
  exit 2
fi

# Validation 2: section is heading (or "*")
if [ "$SECTION" != "*" ]; then
  if ! grep -qE "^##+ $(echo "$SECTION" | sed 's/[][\.^$*+?()|{}/\\]/\\&/g')\s*$" "$KNOWLEDGE_DIR/$MODULE"; then
    echo "ERROR: section '$SECTION' not found as heading in $MODULE" >&2
    exit 2
  fi
fi

# Validation 3: name has >= 3 occurrences (unless --force)
if [ "$FORCE" = "0" ]; then
  if [ ! -x "$CONSULT" ]; then
    echo "ERROR: consult.sh not executable: $CONSULT" >&2
    exit 2
  fi
  count=$("$CONSULT" stats 2>/dev/null \
    | awk -v n="$NAME" '$1 == n { print $3; exit }')
  count=${count:-0}
  if [ "$count" -lt 3 ]; then
    echo "ERROR: '$NAME' has $count occurrences (< 3). Use --force to override." >&2
    exit 2
  fi
fi

# Idempotency: skip if already present in the target section
TARGET_SECTION=$([ "$IS_PATTERN" = "1" ] && echo "patterns" || echo "tags")
already=$(awk -v n="$NAME" -v target="$TARGET_SECTION" '
  /^tags:$/          { section = "tags"; next }
  /^patterns:$/      { section = "patterns"; next }
  /^keyword_mappings:$/ { section = "km"; next }
  (section == "tags" || section == "patterns") && $0 ~ "^  " n ":$" {
    print section; exit
  }
' "$REGISTRY")

if [ -n "$already" ]; then
  echo "SKIP: '$NAME' already graduated (in '$already:')"
  exit 1
fi

# Insert entry at the end of the target section
tmp=$(mktemp)
awk -v n="$NAME" -v m="$MODULE" -v s="$SECTION" -v target="$TARGET_SECTION" '
  function emit_entry() {
    print "  " n ":"
    print "    module: " m
    print "    section: \"" s "\""
    inserted = 1
  }
  BEGIN { section = ""; inserted = 0 }
  /^tags:$/ {
    if (section == target && !inserted) emit_entry()
    section = "tags"; print; next
  }
  /^patterns:$/ {
    if (section == target && !inserted) emit_entry()
    section = "patterns"; print; next
  }
  /^keyword_mappings:$/ {
    if (section == target && !inserted) emit_entry()
    section = "km"; print; next
  }
  { print }
  END {
    if (section == target && !inserted) emit_entry()
  }
' "$REGISTRY" > "$tmp" && mv "$tmp" "$REGISTRY"

echo "OK: graduated '$NAME' → $MODULE # $SECTION (under $TARGET_SECTION:)"
exit 0
