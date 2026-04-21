#!/usr/bin/env bash
# pattern-audit.sh — surface tags with 3+ occurrences in execution logs that
# haven't yet graduated to a knowledge module.
#
# Called automatically by phase-advance.sh after retrospective → finalize.
# Can also be invoked on-demand.
#
# Exit 0 always — advisory only, never blocks.

set -uo pipefail

REPO_ROOT="$(cd "$(dirname "$(readlink -f "$0")")/../.." && pwd)"
CONSULT="$REPO_ROOT/.claude/hooks/consult.sh"
KNOWLEDGE_DIR="$REPO_ROOT/docs/knowledge"

if [ ! -x "$CONSULT" ]; then
  echo "pattern-audit: consult.sh not found or not executable" >&2
  exit 0
fi

# Extract lines marked with ⚠ PATTERN (≥3) from consult.sh stats output.
# Line format: "  <tag-name>                : N logs ⚠ PATTERN (≥3)"
patterns=$("$CONSULT" stats 2>/dev/null | awk '/⚠ PATTERN/ { print $1, $3 }' || true)

if [ -z "$patterns" ]; then
  exit 0
fi

CANDIDATES=()
while IFS= read -r row; do
  [ -z "$row" ] && continue
  tag=$(echo "$row" | awk '{print $1}')
  count=$(echo "$row" | awk '{print $2}')
  [ -z "$tag" ] && continue

  # Check if the tag appears in any knowledge module
  if [ -d "$KNOWLEDGE_DIR" ] && grep -rq --include='*.md' -F "$tag" "$KNOWLEDGE_DIR" 2>/dev/null; then
    continue
  fi

  CANDIDATES+=("$tag|$count")
done <<< "$patterns"

if [ ${#CANDIDATES[@]} -eq 0 ]; then
  exit 0
fi

echo ""
echo "⚠ pattern-audit: tags with ≥3 occurrences not yet in knowledge modules:"
for c in "${CANDIDATES[@]}"; do
  tag="${c%%|*}"
  count="${c##*|}"
  printf "  • %-30s (%s logs) — consider graduation to docs/knowledge/\n" "$tag" "$count"
done
echo ""

exit 0
