#!/usr/bin/env bash
# validate-graduations.sh — verify _graduations.yaml entries reference real sections.
#
# For each entry under `tags:` and `patterns:`:
#   - `module: X.md` must exist in the knowledge dir
#   - `section: Y` must appear as `^##+ Y` in that module, OR `Y == "*"`
#
# For each entry under `keyword_mappings:`:
#   - Value must be a non-empty string (syntactic check only; graduation is independent)
#
# Exit 0 = valid, 1 = drift detected, 2 = error (missing registry etc.)

set -uo pipefail

REPO_ROOT="$(cd "$(dirname "$(readlink -f "$0")")/.." && pwd)"
REGISTRY="${VALIDATE_GRADUATIONS_REGISTRY:-$REPO_ROOT/docs/knowledge/_graduations.yaml}"
KNOWLEDGE_DIR="${VALIDATE_GRADUATIONS_KNOWLEDGE_DIR:-$REPO_ROOT/docs/knowledge}"

[ ! -f "$REGISTRY" ] && { echo "ERROR: registry not found: $REGISTRY" >&2; exit 2; }
[ ! -d "$KNOWLEDGE_DIR" ] && { echo "ERROR: knowledge dir not found: $KNOWLEDGE_DIR" >&2; exit 2; }

ERRORS=0

# Extract entries: each line is "section|name|module|section-heading"
entries=$(awk '
  BEGIN { s=""; entry_s=""; entry_name=""; entry_module=""; entry_section="" }
  function flush() {
    if (entry_name != "") {
      print entry_s "|" entry_name "|" entry_module "|" entry_section
      entry_name=""; entry_module=""; entry_section=""
    }
  }

  /^tags:$/             { flush(); s="tags"; next }
  /^patterns:/          { flush(); s="patterns"; next }
  /^keyword_mappings:/  { flush(); s="km"; next }
  /^[a-z]/ && !/^#/     { flush(); s=""; next }

  (s=="tags" || s=="patterns") && /^  [a-z][a-z0-9-]*:$/ {
    flush()
    entry_s=s
    entry_name=$1; sub(/:$/, "", entry_name)
    next
  }
  (s=="tags" || s=="patterns") && /^    module:/ {
    entry_module=$0
    sub(/^    module:[[:space:]]*/, "", entry_module)
    next
  }
  (s=="tags" || s=="patterns") && /^    section:/ {
    entry_section=$0
    sub(/^    section:[[:space:]]*/, "", entry_section)
    gsub(/^"|"$/, "", entry_section)
    next
  }

  END { flush() }
' "$REGISTRY")

while IFS="|" read -r section_kind name module section; do
  [ -z "$name" ] && continue

  if [ -z "$module" ]; then
    echo "✗ [$section_kind] $name: missing module"
    ERRORS=$((ERRORS+1))
    continue
  fi

  if [ ! -f "$KNOWLEDGE_DIR/$module" ]; then
    echo "✗ [$section_kind] $name → $module: module not found"
    ERRORS=$((ERRORS+1))
    continue
  fi

  if [ "$section" = "*" ]; then
    continue
  fi

  if [ -z "$section" ]; then
    echo "✗ [$section_kind] $name: missing section"
    ERRORS=$((ERRORS+1))
    continue
  fi

  # Check that section appears as heading (escape regex metachars)
  section_escaped=$(echo "$section" | sed 's/[][\.^$*+?()|{}/\\]/\\&/g')
  if ! grep -qE "^##+ ${section_escaped}\s*$" "$KNOWLEDGE_DIR/$module"; then
    echo "✗ [$section_kind] $name → $module # $section: section heading not found"
    ERRORS=$((ERRORS+1))
  fi
done <<< "$entries"

if [ "$ERRORS" -eq 0 ]; then
  echo "✓ registry valid"
  exit 0
else
  echo ""
  echo "✗ $ERRORS drift issue(s) detected in $REGISTRY"
  exit 1
fi
