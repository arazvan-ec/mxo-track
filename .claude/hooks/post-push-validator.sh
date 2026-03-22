#!/usr/bin/env bash
# PostToolUse hook: after git push
# Migrates manifest-auto-run.sh + adds workflow-status generation
#
# Non-blocking — manifest/commit/push failures never break the workflow.

set -euo pipefail

INPUT=$(cat)
TOOL_NAME=$(echo "$INPUT" | jq -r '.tool_name // ""')
COMMAND=$(echo "$INPUT" | jq -r '.tool_input.command // ""')
STDOUT=$(echo "$INPUT" | jq -r '.tool_response.stdout // ""')
STDERR=$(echo "$INPUT" | jq -r '.tool_response.stderr // ""')

# Only trigger on git push commands (not dry-run)
if [ "$TOOL_NAME" != "Bash" ] || ! echo "$COMMAND" | grep -q 'git push'; then
  exit 0
fi
if echo "$COMMAND" | grep -q '\-\-dry-run'; then
  exit 0
fi

# Skip if push failed
COMBINED="$STDOUT $STDERR"
if echo "$COMBINED" | grep -qiE '(rejected|failed|fatal:|error:)'; then
  exit 0
fi

REPO="/home/user/mxo-track"
cd "$REPO"

MESSAGES=""

# ── Run make manifest ──
if [ -f "Makefile" ] && grep -q "^manifest:" Makefile; then
  make manifest 2>/dev/null || true

  # Check if manifest changed
  if ! git diff --quiet docs/codebase-manifest.md 2>/dev/null; then
    git add docs/codebase-manifest.md
    git commit -m "chore: update codebase manifest" 2>/dev/null || true

    CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "")
    if [ -n "$CURRENT_BRANCH" ]; then
      git push origin "$CURRENT_BRANCH" 2>/dev/null || true
    fi

    MESSAGES="${MESSAGES}Manifest updated and pushed. "
  fi
fi

# ── Generate workflow status ──
WORKFLOW_STATUS_SCRIPT="$REPO/.claude/hooks/workflow-status.sh"
if [ -x "$WORKFLOW_STATUS_SCRIPT" ]; then
  "$WORKFLOW_STATUS_SCRIPT" 2>/dev/null || true
  MESSAGES="${MESSAGES}Workflow status updated. "
fi

if [ -n "$MESSAGES" ]; then
  echo "{\"systemMessage\":\"POST-PUSH: ${MESSAGES}\"}"
fi

exit 0
