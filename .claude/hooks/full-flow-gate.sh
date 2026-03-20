#!/usr/bin/env bash
# Full-flow gate hook: blocks Edit/Write to source code unless
# the full workflow has been followed.
#
# Checks (sequential — first failure stops):
# 1. session-state.json exists and flow_declared is true
# 2. For full-flow: learning_loop_done is true
# 3. For full-flow or debug-flow: brainstorm_done is true (full) or learning_loop_done (debug)
# 4. .claude/active-spec file exists (set during brainstorming)
# 5. The spec file it references actually exists
# 6. A plan file for today exists in docs/superpowers/plans/
#
# Bypasses:
# - Files outside frontend/src and backend/src (docs, tests, config, migrations, etc.)
# - flow_type "micro" or "light" skip checks 4-6 (no spec/plan needed)
#
# To pass this gate:
#   1. Initialize session state: write flow_type + flow_declared to .claude/session-state.json
#   2. For full-flow: mark learning_loop_done and brainstorm_done
#   3. Brainstorm (Skill 2) -> write spec
#   4. echo "docs/superpowers/specs/YYYY-MM-DD-slug-design.md" > .claude/active-spec
#   5. Plan (Skill 3) -> write plan in docs/superpowers/plans/
#   6. Now you can edit source code

set -euo pipefail

INPUT=$(cat)
FILE_PATH=$(echo "$INPUT" | jq -r '.tool_input.file_path // empty')

# Only gate frontend/src and backend/src edits
case "$FILE_PATH" in
  */frontend/src/*|*/backend/src/*) ;;
  *) exit 0 ;;
esac

REPO="/home/user/mxo-track"
STATE_FILE="$REPO/.claude/session-state.json"
ACTIVE_SPEC_FILE="$REPO/.claude/active-spec"
TODAY=$(date +%Y-%m-%d)
PLANS_DIR="$REPO/docs/superpowers/plans"

deny() {
  local reason="$1"
  echo "{\"hookSpecificOutput\":{\"hookEventName\":\"PreToolUse\",\"permissionDecision\":\"deny\",\"permissionDecisionReason\":\"$reason\"}}"
  exit 0
}

# ── Check 1: session-state.json exists and flow is declared ──

if [ ! -f "$STATE_FILE" ]; then
  deny "FULL-FLOW GATE: No session state found. Classify the interaction first: write flow_type and set flow_declared=true in .claude/session-state.json. See CLAUDE.md 'Flujo Obligatorio para Toda Interaccion'."
fi

FLOW_DECLARED=$(jq -r '.flow_declared // false' "$STATE_FILE" 2>/dev/null || echo "false")
if [ "$FLOW_DECLARED" != "true" ]; then
  deny "FULL-FLOW GATE: Flow not declared. Before editing source code, classify the interaction type (micro/light/debug/full) and set flow_declared=true in .claude/session-state.json."
fi

FLOW_TYPE=$(jq -r '.flow_type // "unknown"' "$STATE_FILE" 2>/dev/null || echo "unknown")

# ── Check 2: For full-flow — learning loop must be done ──

if [ "$FLOW_TYPE" = "full" ]; then
  LEARNING_LOOP=$(jq -r '.learning_loop_done // false' "$STATE_FILE" 2>/dev/null || echo "false")
  if [ "$LEARNING_LOOP" != "true" ]; then
    deny "FULL-FLOW GATE: Learning Loop not done. Before brainstorming, read docs/decisions/log.md and scan recent execution-logs and retrospectives. Then set learning_loop_done=true in .claude/session-state.json."
  fi
fi

# ── Check 3: For full-flow — brainstorming must be done ──

if [ "$FLOW_TYPE" = "full" ]; then
  BRAINSTORM=$(jq -r '.brainstorm_done // false' "$STATE_FILE" 2>/dev/null || echo "false")
  if [ "$BRAINSTORM" != "true" ]; then
    deny "FULL-FLOW GATE: Brainstorming not done. Invoke Skill 2 (brainstorming), propose 2-3 approaches, get user approval, write spec. Then set brainstorm_done=true in .claude/session-state.json."
  fi
fi

# ── For micro/light flows — no spec/plan needed ──

if [ "$FLOW_TYPE" = "micro" ] || [ "$FLOW_TYPE" = "light" ]; then
  exit 0
fi

# ── Check 4: active-spec file exists ──

if [ ! -f "$ACTIVE_SPEC_FILE" ]; then
  deny "FULL-FLOW GATE: No active spec registered. Write spec (Skill 2) then: echo 'docs/superpowers/specs/YYYY-MM-DD-slug-design.md' > .claude/active-spec"
fi

# ── Check 5: the referenced spec file exists ──

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

# ── Check 6: a plan file for today exists ──

if ! ls "$PLANS_DIR"/${TODAY}-*.md 1>/dev/null 2>&1; then
  deny "FULL-FLOW GATE: Spec registered but no plan found for today ($TODAY) in docs/superpowers/plans/. Write a plan (Skill 3) before implementing."
fi

# All checks passed
exit 0
