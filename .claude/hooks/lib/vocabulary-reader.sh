#!/usr/bin/env bash
# vocabulary-reader.sh — primitives for reading docs/knowledge/_vocabulary.yaml
#
# Source me; do not execute directly. Functions echo to stdout and return 0/1.
# Callers compose the primitives and decide error handling + match semantics.
#
# Usage:
#   source /home/user/mxo-track/.claude/hooks/lib/vocabulary-reader.sh
#
#   pairs=$(vocab_deprecated_aliases "$VOCAB_FILE")
#   canonicals=$(vocab_canonicals_in_text "$VOCAB_FILE" "$some_text")
#   ctx=$(vocab_bounded_context "$VOCAB_FILE" "Route")
#
# Origin: 2026-04-29 i12 — graduation of vocabulary consumer pattern after
# 3 occurrences in Hito 3 Phase B (B-1 pre-agent, B-2 ddd-boundary,
# B-3 pattern-audit).
#
# Portability constraints (see .claude/README.md § Shell Portability):
#   - awk regex avoids \<\> word boundaries (mawk doesn't support).
#   - awk avoids IGNORECASE (mawk doesn't honor it).
#   - When word-boundary or case-insensitive matching is required, this
#     lib delegates to bash `grep -wE` / `grep -i` instead of awk regex.

# ── vocab_deprecated_aliases <vocab_file> ──
# Echo `alias|canonical` pairs (one per line) for entries with
# `surface: "deprecated"`. Returns empty when the file is missing.
vocab_deprecated_aliases() {
  local vocab_file="$1"
  [ -f "$vocab_file" ] || return 0
  awk '
    /^  - canonical: / { canonical=$0; sub(/^  - canonical: /, "", canonical); next }
    /^      - \{term: / && /surface: "deprecated"/ {
      t=$0
      sub(/^.*term: "/, "", t)
      sub(/", lang:.*$/, "", t)
      print t "|" canonical
    }
  ' "$vocab_file" 2>/dev/null
}

# ── vocab_canonicals_in_text <vocab_file> <text> ──
# Echo canonicals (one per line) that appear as whole words (case-insensitive)
# in the given text. Empty output when no matches or vocab missing.
vocab_canonicals_in_text() {
  local vocab_file="$1"
  local text="$2"
  [ -f "$vocab_file" ] || return 0
  local canonicals
  canonicals=$(awk '/^  - canonical: / { sub(/^  - canonical: /, ""); print }' "$vocab_file" 2>/dev/null)
  while IFS= read -r canonical; do
    [ -z "$canonical" ] && continue
    if echo "$text" | grep -qiwE -- "\b${canonical}\b" 2>/dev/null; then
      echo "$canonical"
    fi
  done <<< "$canonicals"
}

# ── vocab_bounded_context <vocab_file> <canonical> ──
# Echo the bounded_context value for the named canonical, or empty if the
# canonical is absent or its bounded_context is empty/TODO.
vocab_bounded_context() {
  local vocab_file="$1"
  local target="$2"
  [ -f "$vocab_file" ] || return 0
  awk -v target="$target" '
    /^  - canonical: / {
      canonical=$0; sub(/^  - canonical: /, "", canonical)
      hit = (canonical == target) ? 1 : 0
      next
    }
    hit && /^    bounded_context: / {
      ctx=$0; sub(/^    bounded_context: /, "", ctx)
      if (ctx != "" && ctx != "TODO") print ctx
      hit=0
    }
    /^  - canonical: / { next }
  ' "$vocab_file" 2>/dev/null
}
