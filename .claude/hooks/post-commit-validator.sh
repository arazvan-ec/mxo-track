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

ITEMS=""
HAS_WARNINGS=false

# ── Validate commit message prefix ──
VALID_PREFIXES="^(feat|fix|refactor|test|docs|chore):"
if echo "$COMMIT_MSG" | grep -qE "$VALID_PREFIXES"; then
  PREFIX=$(echo "$COMMIT_MSG" | grep -oE "^(feat|fix|refactor|test|docs|chore)")
  ITEMS="${ITEMS}✅ prefix:$PREFIX | "
else
  ITEMS="${ITEMS}❌ prefix invalido (esperado: feat|fix|refactor|test|docs|chore) | "
  HAS_WARNINGS=true
fi

# ── Check message length ──
MSG_LEN=${#COMMIT_MSG}
if [ "$MSG_LEN" -le 72 ]; then
  ITEMS="${ITEMS}✅ largo:${MSG_LEN}c | "
else
  ITEMS="${ITEMS}⚠ largo:${MSG_LEN}c (max 72) | "
  HAS_WARNINGS=true
fi

# ── Check for generic messages ──
MSG_BODY=$(echo "$COMMIT_MSG" | sed -E 's/^(feat|fix|refactor|test|docs|chore):\s*//')
GENERIC_PATTERNS="^(WIP|updates|changes|fix|wip|misc|tmp|temp)$"
if echo "$MSG_BODY" | grep -qiE "$GENERIC_PATTERNS"; then
  ITEMS="${ITEMS}❌ mensaje generico ('$MSG_BODY') | "
  HAS_WARNINGS=true
fi

# ── Execution log reminder ──
TODAY=$(date +%Y-%m-%d)
EXEC_LOG_DIR="$REPO/docs/superpowers/execution-logs"
if ! ls "$EXEC_LOG_DIR/${TODAY}-"*.md 1>/dev/null 2>&1; then
  if echo "$COMMIT_MSG" | grep -qE "^(feat|fix):"; then
    ITEMS="${ITEMS}⚠ sin execution log para hoy | "
    HAS_WARNINGS=true
  fi
fi

# ── Regressions link: detect "Fixes previously: `...`" in new execution logs ──
# If the commit added an execution log containing that line, invoke link-regression.sh
# to update the referenced older log's regressions_later array.
CHANGED_LOGS=$(git show --name-only --format="" HEAD 2>/dev/null \
  | grep -E '^docs/superpowers/execution-logs/[0-9]{4}-[0-9]{2}-[0-9]{2}-.*\.md$' || true)
if [ -n "$CHANGED_LOGS" ]; then
  while IFS= read -r new_log_path; do
    [ -z "$new_log_path" ] && continue
    [ -f "$new_log_path" ] || continue
    new_log_name=$(basename "$new_log_path")
    # Extract "Fixes previously: `YYYY-MM-DD-xxx.md`" reference
    old_log_ref=$(grep -oE '\*\*Fixes previously:\*\*[[:space:]]*`[0-9]{4}-[0-9]{2}-[0-9]{2}-[^`]+\.md`' "$new_log_path" \
      | head -1 | sed -E "s/.*\`([^\`]+)\`.*/\\1/" || true)
    if [ -n "$old_log_ref" ] && [ -x "$REPO/scripts/link-regression.sh" ]; then
      bash "$REPO/scripts/link-regression.sh" "$new_log_name" "$old_log_ref" >/dev/null 2>&1 \
        && ITEMS="${ITEMS}🔗 linked regression $old_log_ref | " \
        || true
    fi
  done <<< "$CHANGED_LOGS"
fi

# ── Unpushed commits warning ──
# shellcheck disable=SC1083  # @{u} is git revision syntax, not brace expansion
UNPUSHED=$(git log @{u}..HEAD --oneline 2>/dev/null | wc -l || echo "0")
if [ "$UNPUSHED" -gt 3 ]; then
  ITEMS="${ITEMS}⚠ $UNPUSHED commits sin push"
  HAS_WARNINGS=true
else
  ITEMS="${ITEMS}$UNPUSHED commits sin push"
fi

# ── Output ──
if [ "$HAS_WARNINGS" = true ]; then
  echo "{\"systemMessage\":\"COMMIT: ${ITEMS}\"}"
fi

exit 0
