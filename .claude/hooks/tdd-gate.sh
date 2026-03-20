#!/usr/bin/env bash
# TDD Gate hook: blocks Edit/Write to production source code unless
# test changes exist in the working tree (staged, unstaged, or untracked).
#
# Enforces Skill 7 (Test-Driven Development): "NO PRODUCTION CODE WITHOUT A FAILING TEST FIRST"
#
# Bypasses:
# - Files outside backend/src and frontend/src
# - Non-code files (.xml, .yaml, .twig, etc.)
# - flow_type: micro, light, documentation
# - tdd_bypass: true in session-state.json (temporary refactoring flag)
# - Missing session-state.json (full-flow-gate.sh handles that)

set -euo pipefail

INPUT=$(cat)
FILE_PATH=$(echo "$INPUT" | jq -r '.tool_input.file_path // empty')

# Only gate backend/src and frontend/src edits
case "$FILE_PATH" in
  */backend/src/*|*/frontend/src/*) ;;
  *) exit 0 ;;
esac

# Only enforce for code files — allow config/mapping/templates without TDD
case "$FILE_PATH" in
  *.php|*.ts|*.tsx|*.js|*.jsx) ;;
  *) exit 0 ;;
esac

REPO="/home/user/mxo-track"
STATE_FILE="$REPO/.claude/session-state.json"

deny() {
  local reason="$1"
  echo "{\"hookSpecificOutput\":{\"hookEventName\":\"PreToolUse\",\"permissionDecision\":\"deny\",\"permissionDecisionReason\":\"$reason\"}}"
  exit 0
}

# ── Session state bypasses ──

# If no state file, let full-flow-gate.sh handle enforcement
if [ ! -f "$STATE_FILE" ]; then
  exit 0
fi

FLOW_TYPE=$(jq -r '.flow_type // "unknown"' "$STATE_FILE" 2>/dev/null || echo "unknown")

# Micro, light, and documentation flows skip TDD gate
case "$FLOW_TYPE" in
  micro|light|documentation) exit 0 ;;
esac

# Temporary TDD bypass for refactoring
TDD_BYPASS=$(jq -r '.tdd_bypass // false' "$STATE_FILE" 2>/dev/null || echo "false")
if [ "$TDD_BYPASS" = "true" ]; then
  exit 0
fi

# ── Check for test changes in working tree ──

cd "$REPO"

# Backend tests: unstaged + staged + untracked
BACKEND_TESTS=$(
  git diff --name-only -- 'backend/tests/' 2>/dev/null
  git diff --cached --name-only -- 'backend/tests/' 2>/dev/null
  git ls-files --others --exclude-standard -- 'backend/tests/' 2>/dev/null
)

# Frontend tests: unstaged + staged + untracked
FRONTEND_TESTS=$(
  git diff --name-only -- 'frontend/src/' 2>/dev/null | grep -E '\.(test|spec)\.' || true
  git diff --cached --name-only -- 'frontend/src/' 2>/dev/null | grep -E '\.(test|spec)\.' || true
  git ls-files --others --exclude-standard -- 'frontend/src/' 2>/dev/null | grep -E '\.(test|spec)\.' || true
)

if [ -n "$BACKEND_TESTS" ] || [ -n "$FRONTEND_TESTS" ]; then
  exit 0
fi

deny "TDD GATE: No test changes detected in working tree. Write a failing test first (Skill 7). Add/modify a test in backend/tests/ or frontend/src/**/*.{test,spec}.* before editing production code. For refactoring without behavior change: set tdd_bypass=true in .claude/session-state.json."
