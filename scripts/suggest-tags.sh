#!/usr/bin/env bash
# suggest-tags.sh — infer tags for execution logs based on keyword matching
# in the filename, inject into frontmatter tags: [...] array.
#
# Usage: suggest-tags.sh [--apply | --dry-run] [--force]
# Default mode: --dry-run (print suggestions without writing).
# --apply writes the suggestions. --force re-applies even if tags already present.
#
# Idempotent: existing tags are preserved; new tags are deduped.

set -uo pipefail

LOGS_DIR="${SUGGEST_TAGS_LOGS_DIR:-docs/superpowers/execution-logs}"
REGISTRY="${SUGGEST_TAGS_REGISTRY:-docs/knowledge/_graduations.yaml}"
MODE="dry-run"
FORCE=0

for arg in "$@"; do
  case "$arg" in
    --apply) MODE="apply" ;;
    --dry-run) MODE="dry-run" ;;
    --force) FORCE=1 ;;
    -*) echo "ERROR: unknown flag: $arg" >&2; exit 2 ;;
    *) echo "ERROR: unexpected arg: $arg" >&2; exit 2 ;;
  esac
done

# Keyword → tag mapping loaded from _graduations.yaml (keyword_mappings: section).
# Consumed for substring match on lowercased filename slug.
if [ ! -f "$REGISTRY" ]; then
  echo "ERROR: registry not found: $REGISTRY" >&2
  exit 2
fi

declare -A KEYWORD_TAGS=()
while IFS=":" read -r kw tag; do
  kw=$(echo "$kw" | tr -d ' ')
  tag=$(echo "$tag" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')
  [ -n "$kw" ] && [ -n "$tag" ] && KEYWORD_TAGS[$kw]="$tag"
done < <(awk '
  /^keyword_mappings:/  { s="km"; next }
  /^[a-z]/ && !/^#/     { s=""; next }
  s=="km" && /^  [a-z]/ && /:/ { print }
' "$REGISTRY")

if [ "${#KEYWORD_TAGS[@]}" -eq 0 ]; then
  echo "ERROR: no keyword_mappings loaded from $REGISTRY" >&2
  exit 2
fi

has_frontmatter() {
  head -1 "$1" 2>/dev/null | grep -q '^---[[:space:]]*$'
}

# Get current tags as space-separated list (strip brackets, commas)
get_current_tags() {
  awk '/^---[[:space:]]*$/{c++; if(c==2)exit; next} c==1 && /^tags:/{sub(/^tags:[[:space:]]*/,""); print; exit}' "$1" \
    | sed 's/\[//; s/\]//; s/,/ /g' \
    | awk '{$1=$1; print}'
}

# Suggest tags for a log based on filename (stripped of date prefix)
suggest_tags_for() {
  local log="$1"
  local filename slug
  filename=$(basename "$log" .md)
  slug=$(echo "$filename" | sed -E 's/^[0-9]{4}-[0-9]{2}-[0-9]{2}-//' | tr '[:upper:]' '[:lower:]')

  local suggested=""
  for kw in "${!KEYWORD_TAGS[@]}"; do
    if echo "$slug" | grep -qF "$kw"; then
      tag="${KEYWORD_TAGS[$kw]}"
      # Dedupe within suggestions
      if ! echo "$suggested" | grep -qw "$tag"; then
        suggested+="$tag "
      fi
    fi
  done
  echo "$suggested" | awk '{$1=$1; print}'
}

# Merge two space-separated tag lists, dedupe
merge_tags() {
  local a="$1" b="$2"
  echo "$a $b" | tr ' ' '\n' | grep -v '^$' | sort -u | paste -sd' ' -
}

# Format tag list as YAML inline array [a, b, c]
format_tags_yaml() {
  local tags="$1"
  if [ -z "$tags" ]; then
    echo "[]"
  else
    local joined
    joined=$(echo "$tags" | tr ' ' ',' | sed 's/,/, /g')
    echo "[$joined]"
  fi
}

PROCESSED=0
UPDATED=0
SKIPPED=0

shopt -s nullglob
for log in "$LOGS_DIR"/*.md; do
  PROCESSED=$((PROCESSED+1))
  has_frontmatter "$log" || { SKIPPED=$((SKIPPED+1)); continue; }

  current=$(get_current_tags "$log")
  suggested=$(suggest_tags_for "$log")

  if [ -z "$suggested" ]; then
    SKIPPED=$((SKIPPED+1))
    continue
  fi

  # If current is non-empty and --force not set, only add new ones
  if [ -n "$current" ] && [ "$FORCE" = "0" ]; then
    merged=$(merge_tags "$current" "$suggested")
    # Skip if no new tags
    if [ "$merged" = "$(echo "$current" | tr ' ' '\n' | sort -u | paste -sd' ' -)" ]; then
      SKIPPED=$((SKIPPED+1))
      continue
    fi
  else
    merged=$(merge_tags "$current" "$suggested")
  fi

  new_yaml=$(format_tags_yaml "$merged")

  if [ "$MODE" = "dry-run" ]; then
    printf "  %s: %s\n" "$(basename "$log")" "$new_yaml"
    UPDATED=$((UPDATED+1))
  else
    tmp=$(mktemp)
    awk -v val="$new_yaml" '
      /^---[[:space:]]*$/ { c++; print; next }
      c==1 && /^tags:/ {
        print "tags: " val
        next
      }
      { print }
    ' "$log" > "$tmp"
    mv "$tmp" "$log"
    UPDATED=$((UPDATED+1))
  fi
done
shopt -u nullglob

echo ""
echo "── Summary ──"
echo "Processed: $PROCESSED"
echo "Updated:   $UPDATED"
echo "Skipped:   $SKIPPED (no suggestions or already complete)"
[ "$MODE" = "dry-run" ] && echo "(dry-run mode — use --apply to write)"
exit 0
