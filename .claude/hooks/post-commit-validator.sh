#!/usr/bin/env bash
# PostToolUse hook: after git commit
# Merges: commit-msg-lint.sh + post-commit-reminder.sh
#
# Validates commit message format and reminds about execution logs.
# Non-blocking — emits systemMessage warnings only.

set -euo pipefail

INPUT=$(cat)
TOOL_NAME=$(echo "$INPUT" | jq -r '.tool_name // ""')
COMMAND=$(echo "$INPUT" | jq -r '.tool_input.command // ""')
STDOUT=$(echo "$INPUT" | jq -r '.tool_response.stdout // ""')

# Only trigger on git commit commands
if [ "$TOOL_NAME" != "Bash" ] || ! echo "$COMMAND" | grep -q 'git commit'; then
  exit 0
fi

# Check if commit actually happened
if ! echo "$STDOUT" | grep -qE '^\['; then
  exit 0
fi

REPO="/home/user/mxo-track"
cd "$REPO"

COMMIT_MSG=$(git log -1 --pretty=%s 2>/dev/null || echo "")
if [ -z "$COMMIT_MSG" ]; then
  exit 0
fi

WARNINGS=""

# ── Validate commit message prefix ──
VALID_PREFIXES="^(feat|fix|refactor|test|docs|chore):"
if ! echo "$COMMIT_MSG" | grep -qE "$VALID_PREFIXES"; then
  WARNINGS="${WARNINGS}COMMIT FORMAT: Expected prefix (feat:|fix:|refactor:|test:|docs:|chore:). Got: '${COMMIT_MSG}'. "
fi

# ── Check message length ──
MSG_LEN=${#COMMIT_MSG}
if [ "$MSG_LEN" -gt 72 ]; then
  WARNINGS="${WARNINGS}COMMIT LENGTH: Message too long ($MSG_LEN chars, recommended <= 72). "
fi

# ── Check for generic messages ──
MSG_BODY=$(echo "$COMMIT_MSG" | sed -E 's/^(feat|fix|refactor|test|docs|chore):\s*//')
GENERIC_PATTERNS="^(WIP|updates|changes|fix|wip|misc|tmp|temp)$"
if echo "$MSG_BODY" | grep -qiE "$GENERIC_PATTERNS"; then
  WARNINGS="${WARNINGS}GENERIC MSG: Avoid generic messages ('$MSG_BODY'). Describe the what and why. "
fi

# ── Execution log reminder ──
TODAY=$(date +%Y-%m-%d)
EXEC_LOG_DIR="$REPO/docs/superpowers/execution-logs"
if ! ls "$EXEC_LOG_DIR/${TODAY}-"*.md 1>/dev/null 2>&1; then
  if echo "$COMMIT_MSG" | grep -qE "^(feat|fix):"; then
    WARNINGS="${WARNINGS}EXEC LOG: No execution log for today. Create ${EXEC_LOG_DIR}/${TODAY}-<feature>.md. "
  fi
fi

# ── Unpushed commits warning ──
UNPUSHED=$(git log @{u}..HEAD --oneline 2>/dev/null | wc -l || echo "0")
if [ "$UNPUSHED" -gt 3 ]; then
  WARNINGS="${WARNINGS}PUSH REMINDER: $UNPUSHED commits sin push. Considera hacer push. "
fi

# ── Output warnings if any ──
if [ -n "$WARNINGS" ]; then
  echo "{\"systemMessage\":\"POST-COMMIT: ${WARNINGS}\"}"
fi

exit 0
