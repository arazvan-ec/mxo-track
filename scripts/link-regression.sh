#!/usr/bin/env bash
# link-regression.sh — add a new-log reference to an old-log's regressions_later array.
#
# Usage: link-regression.sh <new-log-filename> <old-log-filename>
#
# Idempotent: if new-log is already in regressions_later, no-op.
# Example: a bugfix log referencing a prior feature log that introduced the bug.

set -uo pipefail

LOGS_DIR="${LINK_REGRESSION_LOGS_DIR:-docs/superpowers/execution-logs}"

NEW_LOG="${1:-}"
OLD_LOG="${2:-}"

if [ -z "$NEW_LOG" ] || [ -z "$OLD_LOG" ]; then
  echo "Usage: link-regression.sh <new-log-filename> <old-log-filename>" >&2
  exit 2
fi

OLD_PATH="$LOGS_DIR/$OLD_LOG"
if [ ! -f "$OLD_PATH" ]; then
  echo "ERROR: old-log not found: $OLD_PATH" >&2
  exit 2
fi

# Must have frontmatter
if ! head -1 "$OLD_PATH" | grep -q '^---[[:space:]]*$'; then
  echo "ERROR: $OLD_LOG has no frontmatter" >&2
  exit 2
fi

# Extract current regressions_later value
current=$(awk '/^---[[:space:]]*$/{c++; if(c==2)exit; next} c==1 && /^regressions_later:[[:space:]]/{sub(/^regressions_later:[[:space:]]*/,""); print; exit}' "$OLD_PATH")

# Idempotence check: if NEW_LOG already present, no-op
if echo "$current" | grep -qF "$NEW_LOG"; then
  echo "SKIP: $NEW_LOG already linked in $OLD_LOG"
  exit 0
fi

# Build new value
# Strip [ and ], split on comma, trim, re-add NEW_LOG, reformat
inner="${current#[}"
inner="${inner%]}"
inner=$(echo "$inner" | sed 's/[[:space:]]//g')

if [ -z "$inner" ]; then
  new_value="[$NEW_LOG]"
else
  new_value="[$inner, $NEW_LOG]"
fi

# Write back
tmp=$(mktemp)
awk -v val="$new_value" '
  /^---[[:space:]]*$/ { c++; print; next }
  c==1 && /^regressions_later:[[:space:]]/ {
    print "regressions_later: " val
    next
  }
  { print }
' "$OLD_PATH" > "$tmp"
mv "$tmp" "$OLD_PATH"

echo "✓ Linked $NEW_LOG → $OLD_LOG (regressions_later)"
exit 0
