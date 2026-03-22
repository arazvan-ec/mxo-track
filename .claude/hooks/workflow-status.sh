#!/usr/bin/env bash
# Generates .claude/workflow-status.md from session-state.json
# Non-blocking — used for visibility, not enforcement.

set -euo pipefail

REPO="/home/user/mxo-track"
STATE_FILE="$REPO/.claude/session-state.json"
OUTPUT="$REPO/.claude/workflow-status.md"

if [ ! -f "$STATE_FILE" ]; then
  echo "No session state found." > "$OUTPUT"
  exit 0
fi

FLOW_TYPE=$(jq -r '.flow_type // "not set"' "$STATE_FILE")
CURRENT_PHASE=$(jq -r '.current_phase // "not set"' "$STATE_FILE")
DEV_ACTIVE=$(jq -r '.deviation.active // false' "$STATE_FILE")
DEV_REASON=$(jq -r '.deviation.reason // "none"' "$STATE_FILE")

# Phase list for full-flow
PHASES=("consult" "brainstorming" "planning" "implementation" "verification" "capture" "retrospective" "finalize")

# Generate header
cat > "$OUTPUT" << HEADER
# Workflow Status
Generated: $(date -u +%Y-%m-%dT%H:%M:%SZ)

## Current Session
- **Flow type:** $FLOW_TYPE
- **Current phase:** $CURRENT_PHASE
- **Deviation:** $([ "$DEV_ACTIVE" = "true" ] && echo "ACTIVE — $DEV_REASON" || echo "none")

## Phase Progress
| Phase | Status | Evidence |
|-------|--------|----------|
HEADER

# Generate phase rows
for phase in "${PHASES[@]}"; do
  # Check if phase exists in phase_history
  STATUS=$(jq -r --arg p "$phase" '
    (.phase_history // [])[] | select(.phase == $p) | .status // empty
  ' "$STATE_FILE" 2>/dev/null || echo "")

  EVIDENCE=$(jq -r --arg p "$phase" '
    (.phase_history // [])[] | select(.phase == $p) | .evidence // empty
  ' "$STATE_FILE" 2>/dev/null || echo "")

  if [ "$phase" = "$CURRENT_PHASE" ]; then
    echo "| $phase | 🔄 active | ${EVIDENCE:-—} |" >> "$OUTPUT"
  elif [ -n "$STATUS" ] && [ "$STATUS" != "" ]; then
    echo "| $phase | ✅ done | ${EVIDENCE:-—} |" >> "$OUTPUT"
  else
    echo "| $phase | ⬚ pending | — |" >> "$OUTPUT"
  fi
done

# Deviation history
echo "" >> "$OUTPUT"
echo "## Recent Deviations" >> "$OUTPUT"
if [ "$DEV_ACTIVE" = "true" ]; then
  echo "- **ACTIVE:** $DEV_REASON" >> "$OUTPUT"
else
  echo "(none this session)" >> "$OUTPUT"
fi

# Hooks health
echo "" >> "$OUTPUT"
echo "## Hooks Health" >> "$OUTPUT"
for f in workflow-engine.sh post-commit-validator.sh post-push-validator.sh workflow-status.sh; do
  if [ -x "$REPO/.claude/hooks/$f" ]; then
    echo "- $f: ✅ active" >> "$OUTPUT"
  else
    echo "- $f: ❌ missing" >> "$OUTPUT"
  fi
done

exit 0
