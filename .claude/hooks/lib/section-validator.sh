#!/usr/bin/env bash
# section-validator.sh — primitives for spec section presence + content checks
#
# Source me; do not execute directly. The functions echo to stdout and return
# 0 on success, 1 on failure. Callers compose the primitives and decide the
# error message + block/warn semantics.
#
# Usage:
#   source .claude/hooks/lib/section-validator.sh
#
#   if ! section_present "$SPEC" "Norms"; then ...; fi
#   body=$(section_body "$SPEC" "Norms")
#   if section_satisfied_inline_or_ref "$body" "Norms" imperative; then ...; fi
#
# Origin: 2026-04-28 harness consolidation (graduation of section-validation
# pattern after 5 occurrences: Layer H, K, N, S, Layer Agent).

# ── section_present <file> <heading> ──
# Return 0 if the file contains a line `## <heading>`, else 1.
section_present() {
  local file="$1"
  local heading="$2"
  grep -qE "^## ${heading}" "$file" 2>/dev/null
}

# ── section_body <file> <heading> ──
# Echo the lines between `## <heading>` and the next `## ` heading (exclusive).
# Empty output if the section is absent.
section_body() {
  local file="$1"
  local heading="$2"
  awk -v h="^## ${heading}" '
    $0 ~ h { flag=1; next }
    /^## / { flag=0 }
    flag { print }
  ' "$file"
}

# ── section_satisfied_inline_or_ref <body> <heading_token> <inline_check> [ref_pattern] ──
# Return 0 if the body satisfies either:
#   - the inline_check criterion (mode-specific test on the body), OR
#   - a spec-reference: the pattern `docs/superpowers/specs/.+\.md` appears
#     within ~200 chars of <heading_token> in the body, in either order.
# When ref_pattern is provided it overrides the default spec-path regex.
#
# Inline check modes:
#   imperative              — ≥1 line contains imperative keyword
#   risk-mitigation-table   — ≥1 markdown table header line has Risk + Mitigation columns
#   classified-rows         — ≥1 row contains ✅ | ❌ tech-debt | new (Layer H)
#
# Note: modes `positive-signal` and `multiline-bullet` were removed 2026-05-04
# alongside Layer K (Hito 0.b). `section_extract_bullet` likewise removed.
section_satisfied_inline_or_ref() {
  local body="$1"
  local heading_token="$2"
  local mode="$3"
  local ref_pattern="${4:-docs/superpowers/specs/[^[:space:]]+\\.md}"

  # 1. Spec-reference check (compact body to single line for proximity match)
  local body_oneline
  body_oneline=$(echo "$body" | tr '\n' ' ')
  if echo "$body_oneline" | grep -qE "${ref_pattern}.{0,200}${heading_token}"; then
    return 0
  fi
  if echo "$body_oneline" | grep -qE "${heading_token}.{0,200}${ref_pattern}"; then
    return 0
  fi

  # 2. Inline check by mode
  case "$mode" in
    imperative)
      echo "$body" | grep -qiE '\<(must|shall|never|always|no se permite|no debe|siempre|jamás|jamas)\>'
      ;;
    risk-mitigation-table)
      echo "$body" | grep -qiE '^\|.*risk.*\|.*mitigation.*\||^\|.*mitigation.*\|.*risk.*\|'
      ;;
    classified-rows)
      echo "$body" | grep -qE '(✅|❌ tech-debt|\| new \|)'
      ;;
    *)
      return 1
      ;;
  esac
}
