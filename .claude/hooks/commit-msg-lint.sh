#!/usr/bin/env bash
# PostToolUse hook: after a git commit, validates the commit message follows
# the project's conventional commit format.
#
# Required prefixes: feat:, fix:, refactor:, test:, docs:, chore:
# Non-blocking on first offense — emits warning via systemMessage.
# The commit already happened, so we can't block it, but we can remind
# Claude to amend or follow the convention next time.

set -euo pipefail

INPUT=$(cat)
COMMAND=$(echo "$INPUT" | jq -r '.tool_input.command // ""')
STDOUT=$(echo "$INPUT" | jq -r '.tool_response.stdout // ""')

# Only trigger on git commit commands
if ! echo "$COMMAND" | grep -q 'git commit'; then
  exit 0
fi

# Check if commit actually happened (output contains [branch hash] pattern)
if ! echo "$STDOUT" | grep -qE '^\['; then
  exit 0
fi

# Extract the commit message from the most recent commit
REPO="/home/user/mxo-track"
cd "$REPO"
COMMIT_MSG=$(git log -1 --pretty=%s 2>/dev/null || echo "")

if [ -z "$COMMIT_MSG" ]; then
  exit 0
fi

# Valid prefixes per CLAUDE.md "Formato del commit message"
VALID_PREFIXES="^(feat|fix|refactor|test|docs|chore):"

if ! echo "$COMMIT_MSG" | grep -qE "$VALID_PREFIXES"; then
  echo "{\"systemMessage\":\"COMMIT MSG LINT: The last commit message does not follow the required format. Expected prefix: feat:, fix:, refactor:, test:, docs:, or chore:. Got: '${COMMIT_MSG}'. Please use the correct prefix in future commits. If this was a mistake, amend with: git commit --amend -m 'prefix: description'\"}"
  exit 0
fi

# Check message is not too short (prefix + at least 3 chars of description)
MSG_BODY=$(echo "$COMMIT_MSG" | sed -E 's/^(feat|fix|refactor|test|docs|chore):\s*//')
if [ ${#MSG_BODY} -lt 3 ]; then
  echo "{\"systemMessage\":\"COMMIT MSG LINT: Commit message description is too short ('${MSG_BODY}'). Write a descriptive message about what and why.\"}"
  exit 0
fi

# Check for anti-pattern generic messages
GENERIC_PATTERNS="^(WIP|updates|changes|fix|wip|misc|tmp|temp)$"
if echo "$MSG_BODY" | grep -qiE "$GENERIC_PATTERNS"; then
  echo "{\"systemMessage\":\"COMMIT MSG LINT: Generic commit message detected ('${COMMIT_MSG}'). Per CLAUDE.md anti-patterns, avoid: WIP, updates, changes. Describe the what and why.\"}"
  exit 0
fi

exit 0
