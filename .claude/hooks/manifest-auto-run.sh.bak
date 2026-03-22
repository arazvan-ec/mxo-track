#!/usr/bin/env bash
# Manifest Auto-Run hook: after a successful git push, regenerates
# docs/codebase-manifest.md and auto-commits+pushes if changed.
#
# Non-blocking — manifest/commit/push failures never break the workflow.

set -euo pipefail

INPUT=$(cat)
COMMAND=$(echo "$INPUT" | jq -r '.tool_input.command // ""')
STDOUT=$(echo "$INPUT" | jq -r '.tool_response.stdout // ""')
STDERR=$(echo "$INPUT" | jq -r '.tool_response.stderr // ""')

# Only trigger on git push commands (not dry-run)
if ! echo "$COMMAND" | grep -q 'git push'; then
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

# Run make manifest (non-blocking)
make manifest 2>/dev/null || exit 0

# Check if manifest changed
if git diff --quiet docs/codebase-manifest.md 2>/dev/null; then
  echo '{"systemMessage":"Manifest is already up to date."}'
  exit 0
fi

# Auto-commit and push
git add docs/codebase-manifest.md
git commit -m "chore: update codebase manifest" 2>/dev/null || exit 0

CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "")
if [ -n "$CURRENT_BRANCH" ]; then
  git push origin "$CURRENT_BRANCH" 2>/dev/null || true
fi

echo '{"systemMessage":"Manifest updated and pushed (chore: update codebase manifest)."}'
exit 0
