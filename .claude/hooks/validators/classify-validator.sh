#!/usr/bin/env bash
# classify-validator.sh — PreToolUse hook for Edit/Write.
# Layer A of Option 3-Enforced workflow gates.
#
# Blocks edits to framework/code paths when interaction_classification is
# insufficient (micro/light/explore/informational/null). Framework changes
# require full or debug classification because they bypass brainstorming
# and design review when labeled as "trivial".
#
# Bypass: export SKIP_CLASSIFY_GATE=1

set -uo pipefail

REPO="/home/user/mxo-track"
STATE_FILE="$REPO/.claude/session-state.json"

# Bypass
if [ "${SKIP_CLASSIFY_GATE:-0}" = "1" ]; then
  exit 0
fi

# State file missing → bootstrap situation, allow
[ -f "$STATE_FILE" ] || exit 0

INPUT=$(cat || true)
[ -z "$INPUT" ] && exit 0

# Extract file_path (Edit/Write both use it)
FILE_PATH=$(echo "$INPUT" | jq -r '.tool_input.file_path // ""' 2>/dev/null)
[ -z "$FILE_PATH" ] && exit 0

# Normalize path: strip the configured REPO prefix if present, then strip any
# leading path segments up to the first known framework directory. This handles
# both repo-relative ("backend/src/x.php") and absolute
# ("/home/user/mxo-track/backend/src/x.php") file_paths.
REL_PATH="${FILE_PATH#"$REPO/"}"
REL_PATH="${REL_PATH#/}"
REL_PATH=$(echo "$REL_PATH" | sed -E 's#^.*/(\.claude/|scripts/|backend/|frontend/|ml-service/|docker/)#\1#')

# Carve-outs: always allowed
case "$REL_PATH" in
  docs/*) exit 0 ;;
  *.md) exit 0 ;;
  /tmp/*) exit 0 ;;
  .claude/session-state.json) exit 0 ;;
esac

# Framework path patterns that require full/debug classification
FRAMEWORK_REGEX='^(\.claude/|scripts/|backend/src/|backend/templates/|backend/config/|backend/migrations/|backend/tests/|frontend/src/|ml-service/|docker/)'

if ! echo "$REL_PATH" | grep -qE "$FRAMEWORK_REGEX"; then
  # Not a framework path → allow
  exit 0
fi

# Read classification
CLASS=$(jq -r '.interaction_classification // "null"' "$STATE_FILE" 2>/dev/null)

# Insufficient classifications
case "$CLASS" in
  full|debug|refactor)
    exit 0
    ;;
  micro|light|explore|informational|null|"")
    cat >&2 <<EOF
BLOCKED: classify-validator
Framework/code change requires 'full' or 'debug' classification.
  Path:           $REL_PATH
  Current class:  $CLASS
Reclassify before editing:
  jq '.interaction_classification = "full" | .flow_type = "full"' .claude/session-state.json > /tmp/ss.json && mv /tmp/ss.json .claude/session-state.json
Bypass (last resort): export SKIP_CLASSIFY_GATE=1
EOF
    exit 2
    ;;
  *)
    # Unknown class — allow but log once (stderr visible to model)
    echo "classify-validator: unknown classification '$CLASS', allowing" >&2
    exit 0
    ;;
esac
