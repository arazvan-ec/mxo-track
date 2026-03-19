#!/usr/bin/env bash
# Full-flow gate hook: blocks Edit/Write to source code unless
# an active-spec is registered AND a plan exists for today.
#
# Checks:
# 1. .claude/active-spec file exists (set during brainstorming)
# 2. The spec file it references actually exists
# 3. A plan file for today exists in docs/superpowers/plans/
#
# To pass this gate:
#   1. Brainstorm (Skill 2) -> write spec
#   2. echo "docs/superpowers/specs/YYYY-MM-DD-slug-design.md" > .claude/active-spec
#   3. Plan (Skill 3) -> write plan in docs/superpowers/plans/
#   4. Now you can edit source code

set -euo pipefail

INPUT=$(cat)
FILE_PATH=$(echo "$INPUT" | jq -r '.tool_input.file_path // empty')

# Only gate frontend/src and backend/src edits
case "$FILE_PATH" in
  */frontend/src/*|*/backend/src/*) ;;
  *) exit 0 ;;
esac

REPO="/home/user/mxo-track"
ACTIVE_SPEC_FILE="$REPO/.claude/active-spec"
TODAY=$(date +%Y-%m-%d)
PLANS_DIR="$REPO/docs/superpowers/plans"

deny() {
  local reason="$1"
  echo "{\"hookSpecificOutput\":{\"hookEventName\":\"PreToolUse\",\"permissionDecision\":\"deny\",\"permissionDecisionReason\":\"$reason\"}}"
  exit 0
}

# Check 1: active-spec file exists
if [ ! -f "$ACTIVE_SPEC_FILE" ]; then
  deny "FULL-FLOW GATE: No active spec registered. Follow the Full-flow: Brainstorm (Skill 2) -> write spec -> register it with: echo 'docs/superpowers/specs/YYYY-MM-DD-slug-design.md' > .claude/active-spec -> Plan (Skill 3) -> then implement."
fi

# Check 2: the referenced spec file exists
SPEC_PATH=$(tr -d '[:space:]' < "$ACTIVE_SPEC_FILE")
if [ -z "$SPEC_PATH" ]; then
  deny "FULL-FLOW GATE: .claude/active-spec is empty. Write the spec path into it."
fi

# Support both relative (from repo root) and absolute paths
if [ -f "$REPO/$SPEC_PATH" ]; then
  : # ok
elif [ -f "$SPEC_PATH" ]; then
  : # ok
else
  deny "FULL-FLOW GATE: active-spec points to '$SPEC_PATH' which does not exist. Create the spec first (Skill 2)."
fi

# Check 3: a plan file for today exists
if ! ls "$PLANS_DIR"/${TODAY}-*.md 1>/dev/null 2>&1; then
  deny "FULL-FLOW GATE: Spec registered but no plan found for today ($TODAY) in docs/superpowers/plans/. Write a plan (Skill 3) before implementing."
fi

# All checks passed
exit 0
