#!/usr/bin/env bash
# pattern-audit.sh — surface tags/patterns with ≥3 occurrences in execution logs
# that haven't yet been registered in docs/knowledge/_graduations.yaml.
#
# Called automatically by phase-advance.sh after retrospective → finalize.
# Can also be invoked on-demand.
#
# Exit 0 always — advisory only, never blocks.
#
# Env overrides:
#   PATTERN_AUDIT_REGISTRY     — path to _graduations.yaml (default docs/knowledge/)
#   PATTERN_AUDIT_KNOWLEDGE_DIR — path to knowledge dir (for suggestion heuristic)

set -uo pipefail

REPO_ROOT="$(cd "$(dirname "$(readlink -f "$0")")/../.." && pwd)"
CONSULT="$REPO_ROOT/.claude/hooks/consult.sh"
REGISTRY="${PATTERN_AUDIT_REGISTRY:-$REPO_ROOT/docs/knowledge/_graduations.yaml}"
KNOWLEDGE_DIR="${PATTERN_AUDIT_KNOWLEDGE_DIR:-$REPO_ROOT/docs/knowledge}"

if [ ! -x "$CONSULT" ]; then
  echo "pattern-audit: consult.sh not found or not executable" >&2
  exit 0
fi

# Extract ≥3 tags/patterns from consult.sh stats output.
# Line format: "  <name>                : N logs ⚠ PATTERN (≥3)"
patterns=$("$CONSULT" stats 2>/dev/null | awk '/⚠ PATTERN/ { print $1, $3 }' || true)

if [ -z "$patterns" ]; then
  exit 0
fi

# Parse graduated names from registry (keys under tags: and patterns:)
graduated_names=""
if [ -f "$REGISTRY" ]; then
  graduated_names=$(awk '
    /^tags:$/             { s="tags"; next }
    /^patterns:/          { s="patterns"; next }
    /^keyword_mappings:/  { s="km"; next }
    /^[a-z]/ && !/^#/     { s=""; next }
    (s=="tags" || s=="patterns") && /^  [a-z][a-z0-9-]*:$/ {
      name=$1; sub(/:$/, "", name); print name
    }
  ' "$REGISTRY")
fi

# Heuristic: find best-guess module for an ungraduated name via substring match.
suggest_module() {
  local name="$1"
  [ ! -d "$KNOWLEDGE_DIR" ] && { echo "???"; return; }
  local hit
  hit=$(grep -lF "$name" "$KNOWLEDGE_DIR"/*.md 2>/dev/null | head -1)
  if [ -z "$hit" ]; then
    echo "???"
  else
    basename "$hit"
  fi
}

CANDIDATES=()
while IFS= read -r row; do
  [ -z "$row" ] && continue
  tag=$(echo "$row" | awk '{print $1}')
  count=$(echo "$row" | awk '{print $2}')
  [ -z "$tag" ] && continue

  if echo "$graduated_names" | grep -qxF "$tag"; then
    continue
  fi

  CANDIDATES+=("$tag|$count")
done <<< "$patterns"

if [ ${#CANDIDATES[@]} -eq 0 ]; then
  exit 0
fi

echo ""
echo "⚠ pattern-audit: tags/patterns with ≥3 occurrences not in _graduations.yaml:"
for c in "${CANDIDATES[@]}"; do
  tag="${c%%|*}"
  count="${c##*|}"
  module=$(suggest_module "$tag")
  printf "  • %-30s (%s logs)\n" "$tag" "$count"
  printf "    → graduate.sh %s --module=%s --section=\"???\"\n" "$tag" "$module"
done
echo ""

# ── Phase B B-3: Deprecated-alias scan in execution logs ──
# Scan recent logs for terms matching aliases with surface: deprecated
# in _vocabulary.yaml. Surface as suggestion (not blocking).
# Origin: 2026-04-29 hito 3 phase B-3.
VOCAB_FILE="${VOCAB_FILE:-$REPO_ROOT/docs/knowledge/_vocabulary.yaml}"
EXEC_LOGS_DIR="${EXEC_LOGS_DIR:-$REPO_ROOT/docs/superpowers/execution-logs}"
if [ -f "$VOCAB_FILE" ] && [ -d "$EXEC_LOGS_DIR" ]; then
  RECENT_LOGS=$(find "$EXEC_LOGS_DIR" -name '*.md' -type f -mtime -30 2>/dev/null | head -10)
  if [ -n "$RECENT_LOGS" ]; then
    # Extract deprecated alias → canonical pairs into a temporary map.
    DEPRECATED_PAIRS=$(awk '
      /^  - canonical: / { canonical=$0; sub(/^  - canonical: /, "", canonical); next }
      /^      - \{term: / && /surface: "deprecated"/ {
        t=$0
        sub(/^.*term: "/, "", t)
        sub(/", lang:.*$/, "", t)
        print t "|" canonical
      }
    ' "$VOCAB_FILE" 2>/dev/null)

    DEPRECATED_HITS=""
    while IFS='|' read -r alias canonical; do
      [ -z "$alias" ] && continue
      # Whole-word, case-insensitive match across all recent logs
      if echo "$RECENT_LOGS" | xargs grep -liwE -- "\b${alias}\b" 2>/dev/null | head -1 | grep -q .; then
        DEPRECATED_HITS="${DEPRECATED_HITS}  • \"${alias}\" → use canonical \"${canonical}\"\n"
      fi
    done <<< "$DEPRECATED_PAIRS"

    DEPRECATED_HITS=$(printf "%b" "$DEPRECATED_HITS" | sort -u | head -5)

    if [ -n "$DEPRECATED_HITS" ]; then
      echo "⚠ pattern-audit: deprecated-alias mentions in recent logs (≤30 days):"
      echo "$DEPRECATED_HITS"
      echo ""
    fi
  fi
fi

exit 0
