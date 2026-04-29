#!/usr/bin/env bash
# files-decl-parser.sh — extract `→ files:` declarations from a plan
#
# Source me. Echoes path-like tokens (one per line, sorted unique) extracted
# from any `→ files:` annotation in the plan. Strips backticks (markdown
# formatting artifact) and parenthesized payloads. Filters tokens to
# path-like ones (containing `/` or `.`) plus a sentinel set of bare
# build-system filenames.
#
# Usage:
#   source .claude/hooks/lib/files-decl-parser.sh
#   declared=$(parse_files_decl "$PLAN_FILE")
#
# Origin: 2026-04-28 harness consolidation (unification of the parser used
# by brainstorm-validator parallel-conflict and sync-validator drift detection).

# ── tokenize_files_payload <payload> ──
# Tokenize a single payload string (already extracted from a `→ files:` line).
# Strips backticks + parentheses, splits on comma/space, keeps path-like tokens.
# Used by per-line scanners (e.g., brainstorm-validator parallel-conflict).
# Output is line-per-token, NOT sorted (preserves input order for keyed lookups).
tokenize_files_payload() {
  local payload="$1"
  echo "$payload" | tr ',' '\n' | tr ' ' '\n' \
    | tr -d '`' \
    | sed 's/^[[:space:]]*//' | sed 's/[[:space:]]*$//' \
    | { grep -v '^$' || true; } \
    | { grep -E '/|\.|^(Makefile|Dockerfile|Rakefile|Gemfile|Procfile|Caddyfile)$' || true; }
}

# ── parse_files_decl <plan_file> ──
# Extract all path-like tokens from every `→ files:` line in the plan.
# Output is sorted unique. Used for whole-plan checks (e.g., sync-validator drift).
parse_files_decl() {
  local plan_file="$1"
  awk '
    /→ files?:/ {
      sub(/^[^→]*→ files?:[[:space:]]*/, "")
      if (match($0, /^\([^)]*\)$/)) {
        sub(/^\(/, "")
        sub(/\)$/, "")
      }
      print
    }
  ' "$plan_file" \
    | while IFS= read -r payload; do
        tokenize_files_payload "$payload"
      done \
    | sort -u
}
