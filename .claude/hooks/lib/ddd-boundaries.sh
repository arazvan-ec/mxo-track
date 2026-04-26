#!/usr/bin/env bash
# ddd-boundaries.sh — shared helper for reading docs/knowledge/_ddd-boundaries.yaml.
#
# Source me; do not execute.
#
# Provides:
#   ddd_critical_regex  — extended regex matching paths in critical contexts.
#                         Includes the admin-API surface (high-risk, treated
#                         like a critical context for Prior Art Audit purposes).
#                         The regex matches both "backend/src/Domain/Route/"
#                         and "src/Domain/Route/" forms (specs commonly drop
#                         the backend/ prefix).
#
# Consumers: brainstorm-validator.sh (Layer H/C trigger), ddd-boundary-check.sh
# (Layer F via direct YAML read, kept independent for hot-path performance).

REPO="${REPO:-/home/user/mxo-track}"
BOUNDARIES_FILE="$REPO/docs/knowledge/_ddd-boundaries.yaml"

ddd_critical_regex() {
  local out='src/Controller/Api/Admin/'
  [ -f "$BOUNDARIES_FILE" ] || { echo "$out"; return; }

  local paths=""
  if command -v yq >/dev/null 2>&1; then
    paths=$(yq -r '.critical_contexts[].path' "$BOUNDARIES_FILE" 2>/dev/null | grep -v '^null$' || true)
  fi
  if [ -z "$paths" ]; then
    paths=$(sed -n '/^critical_contexts:/,/^[a-z_]/p' "$BOUNDARIES_FILE" \
      | grep -E '^[[:space:]]*-[[:space:]]*path:' \
      | sed -E 's/^[[:space:]]*-[[:space:]]*path:[[:space:]]*//' \
      | sed -E 's/[[:space:]]*#.*$//' \
      | sed -E 's/^"(.*)"$/\1/' \
      | sed -E "s/^'(.*)'\$/\\1/" \
      || true)
  fi

  if [ -n "$paths" ]; then
    while IFS= read -r p; do
      [ -z "$p" ] && continue
      local bare="${p%/\*\*}"
      local rel="${bare#backend/}"
      out="${out}|${rel}/"
    done <<< "$paths"
  fi

  # Normalize: collapse double pipes, strip trailing pipe
  echo "$out" | sed -E 's/\|\|+/|/g; s/\|$//'
}
