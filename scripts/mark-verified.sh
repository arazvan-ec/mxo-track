#!/usr/bin/env bash
# mark-verified.sh — set outcome_verified_at timestamp on an execution log.
#
# Usage: mark-verified.sh <log-filename> [--force]
#
# Preconditions:
#   - log exists in docs/superpowers/execution-logs/
#   - log has frontmatter with outcome: success
#   - outcome_verified_at is null (unless --force)

set -uo pipefail

LOGS_DIR="${MARK_VERIFIED_LOGS_DIR:-docs/superpowers/execution-logs}"
FORCE=0
FILENAME=""

for arg in "$@"; do
  case "$arg" in
    --force) FORCE=1 ;;
    -*) echo "ERROR: unknown flag: $arg" >&2; exit 2 ;;
    *) FILENAME="$arg" ;;
  esac
done

if [ -z "$FILENAME" ]; then
  echo "Usage: mark-verified.sh <log-filename> [--force]" >&2
  exit 2
fi

LOG_PATH="$LOGS_DIR/$FILENAME"
if [ ! -f "$LOG_PATH" ]; then
  echo "ERROR: log not found: $LOG_PATH" >&2
  exit 2
fi

# Must have frontmatter
if ! head -1 "$LOG_PATH" | grep -q '^---[[:space:]]*$'; then
  echo "ERROR: $FILENAME has no frontmatter" >&2
  exit 2
fi

# Must be outcome: success
outcome=$(awk '/^---[[:space:]]*$/{c++; if(c==2)exit; next} c==1 && /^outcome:[[:space:]]/{sub(/^outcome:[[:space:]]*/,""); print; exit}' "$LOG_PATH")
if [ "$outcome" != "success" ]; then
  echo "ERROR: $FILENAME has outcome=$outcome (expected: success). Nothing to verify." >&2
  exit 2
fi

# Check current verified_at
current=$(awk '/^---[[:space:]]*$/{c++; if(c==2)exit; next} c==1 && /^outcome_verified_at:[[:space:]]/{sub(/^outcome_verified_at:[[:space:]]*/,""); print; exit}' "$LOG_PATH")
if [ -n "$current" ] && [ "$current" != "null" ] && [ "$FORCE" = "0" ]; then
  echo "SKIP: $FILENAME already verified at $current (use --force to overwrite)" >&2
  exit 1
fi

TODAY=$(date +%Y-%m-%d)

# Replace the outcome_verified_at line within the frontmatter (lines 1..second ---)
tmp=$(mktemp)
awk -v today="$TODAY" '
  /^---[[:space:]]*$/ { c++; print; next }
  c==1 && /^outcome_verified_at:[[:space:]]/ {
    print "outcome_verified_at: " today
    next
  }
  { print }
' "$LOG_PATH" > "$tmp"
mv "$tmp" "$LOG_PATH"

echo "✓ $FILENAME: outcome_verified_at = $TODAY"
exit 0
