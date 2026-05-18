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

# ── Detection 1: Tags/patterns with ≥3 occurrences not in _graduations.yaml ──
# Line format: "  <name>                : N logs ⚠ PATTERN (≥3)"
patterns=$("$CONSULT" stats 2>/dev/null | awk '/⚠ PATTERN/ { print $1, $3 }' || true)

if [ -n "$patterns" ]; then
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

  if [ ${#CANDIDATES[@]} -gt 0 ]; then
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
  fi
fi

# ── Phase B B-3: Deprecated-alias scan in execution logs ──
# Scan recent logs for terms matching aliases with surface: deprecated
# in _vocabulary.yaml. Surface as suggestion (not blocking).
# Origin: 2026-04-29 hito 3 phase B-3.
VOCAB_FILE="${VOCAB_FILE:-$REPO_ROOT/docs/knowledge/_vocabulary.yaml}"
EXEC_LOGS_DIR="${EXEC_LOGS_DIR:-$REPO_ROOT/docs/superpowers/execution-logs}"
if [ -f "$VOCAB_FILE" ] && [ -d "$EXEC_LOGS_DIR" ]; then
  RECENT_LOGS=$(find "$EXEC_LOGS_DIR" -name '*.md' -type f -mtime -30 2>/dev/null | head -10)
  if [ -n "$RECENT_LOGS" ]; then
    # shellcheck source=lib/vocabulary-reader.sh
    source "$REPO_ROOT/.claude/hooks/lib/vocabulary-reader.sh"

    DEPRECATED_HITS=""
    while IFS='|' read -r alias canonical; do
      [ -z "$alias" ] && continue
      if echo "$RECENT_LOGS" | xargs grep -liwE -- "\b${alias}\b" 2>/dev/null | head -1 | grep -q .; then
        DEPRECATED_HITS="${DEPRECATED_HITS}  • \"${alias}\" → use canonical \"${canonical}\"\n"
      fi
    done <<< "$(vocab_deprecated_aliases "$VOCAB_FILE")"

    DEPRECATED_HITS=$(printf "%b" "$DEPRECATED_HITS" | sort -u | head -5)

    if [ -n "$DEPRECATED_HITS" ]; then
      echo "⚠ pattern-audit: deprecated-alias mentions in recent logs (≤30 days):"
      echo "$DEPRECATED_HITS"
      echo ""
    fi
  fi
fi

# ── Gate-drift detection ──
# Parse decision log for SKIP_*_GATE bypass entries grouped by gate; surface
# gates with ≥THRESHOLD bypasses in the last WINDOW_DAYS days as advisory.
# Output emits both [TUNE] and [LEGITIMIZE] options per flagged gate so the
# user must explicitly disambiguate (gate too strict vs. legitimate recurring
# exception). Origin: 2026-05-18 P2 — spec
# docs/superpowers/specs/2026-05-18-pattern-audit-gate-drift-design.md
DECISION_LOG="${PATTERN_AUDIT_DECISION_LOG:-$REPO_ROOT/docs/decisions/log.md}"
WINDOW_DAYS="${PATTERN_AUDIT_BYPASS_WINDOW_DAYS:-90}"
THRESHOLD="${PATTERN_AUDIT_BYPASS_THRESHOLD:-3}"

if [ -f "$DECISION_LOG" ]; then
  CUTOFF=$(date -u -d "-${WINDOW_DAYS} days" +%Y-%m-%d 2>/dev/null \
        || date -u -v-"${WINDOW_DAYS}"d +%Y-%m-%d 2>/dev/null)

  if [ -n "$CUTOFF" ]; then
    GATE_HITS=$(awk -v cutoff="$CUTOFF" '
      /^### \[[0-9]{4}-[0-9]{2}-[0-9]{2}\]/ {
        match($0, /[0-9]{4}-[0-9]{2}-[0-9]{2}/)
        date = substr($0, RSTART, RLENGTH)
        in_window = (date >= cutoff) ? 1 : 0
        next
      }
      in_window && match($0, /SKIP_[A-Z][A-Z_]+_GATE/) {
        gate = substr($0, RSTART, RLENGTH)
        key = gate "|" date
        if (!seen[key]) { print gate "|" date; seen[key] = 1 }
      }
    ' "$DECISION_LOG" 2>/dev/null)

    if [ -n "$GATE_HITS" ]; then
      FLAGGED=$(echo "$GATE_HITS" | awk -F'|' -v th="$THRESHOLD" '
        { gates[$1] = (gates[$1] ? gates[$1] "," : "") $2; counts[$1]++ }
        END {
          for (g in counts) {
            if (counts[g] >= th) print g "|" counts[g] "|" gates[g]
          }
        }
      ' | sort)

      if [ -n "$FLAGGED" ]; then
        echo ""
        echo "⚠ pattern-audit: gates with ≥${THRESHOLD} bypasses in last ${WINDOW_DAYS} days:"
        while IFS='|' read -r gate count dates; do
          [ -z "$gate" ] && continue
          printf "  • %s (%s entries: %s)\n" "$gate" "$count" "$dates"
          echo "    Choose one structural response:"
          echo "    [TUNE]       Update validator heuristic — gate fires on legitimate work."
          echo "                 → review the relevant validator in .claude/hooks/validators/"
          echo "    [LEGITIMIZE] Document as accepted bypass case in CLAUDE.md."
          echo "                 → add row to § Bypass env vars with the recurring justification"
        done <<< "$FLAGGED"
        echo ""
      fi
    fi
  fi
fi

exit 0
