#!/usr/bin/env bash
# Phase Transition Controller — PostToolUse:Bash hook
#
# Detects and reverts unauthorized manipulations of session-state.json:
# 1. Direct writes to phase_history (must use phase-advance.sh)
# 2. Direct writes to user_approved (must come from user-prompt-state.sh)
#
# Runs BEFORE auto-evidence.sh so reverts happen before evidence detection.
# Non-blocking: always exits 0 (reverts silently + emits systemMessage).

set -euo pipefail

REPO="/home/user/mxo-track"
STATE_FILE="$REPO/.claude/session-state.json"
SNAPSHOT_FILE="/tmp/ptc-state-snapshot.json"

# Read hook input
INPUT=$(cat)
TOOL_NAME=$(echo "$INPUT" | jq -r '.tool_name // ""')
COMMAND=$(echo "$INPUT" | jq -r '.tool_input.command // ""')

# Only monitor Bash commands that touch session-state.json
if [ "$TOOL_NAME" != "Bash" ]; then
  exit 0
fi

# Skip if command doesn't reference session-state.json
if ! echo "$COMMAND" | grep -q 'session-state.json'; then
  exit 0
fi

# Skip if command IS phase-advance.sh (the sanctioned tool)
if echo "$COMMAND" | grep -q 'phase-advance.sh'; then
  exit 0
fi

# Skip if no state file
if [ ! -f "$STATE_FILE" ]; then
  exit 0
fi

# Skip if no snapshot (first run in session — take snapshot for next time)
if [ ! -f "$SNAPSHOT_FILE" ]; then
  cp "$STATE_FILE" "$SNAPSHOT_FILE"
  exit 0
fi

# Compare snapshot with current state
WARNINGS=""

# Check 1: phase_history manipulation
OLD_HISTORY=$(jq -c '.phase_history // []' "$SNAPSHOT_FILE" 2>/dev/null || echo "[]")
NEW_HISTORY=$(jq -c '.phase_history // []' "$STATE_FILE" 2>/dev/null || echo "[]")

if [ "$OLD_HISTORY" != "$NEW_HISTORY" ]; then
  OLD_LEN=$(echo "$OLD_HISTORY" | jq 'length')
  NEW_LEN=$(echo "$NEW_HISTORY" | jq 'length')

  # Allow append (growing) — phase-advance.sh does this
  # But detect rewrite (different content, not just longer)
  if [ "$NEW_LEN" -lt "$OLD_LEN" ]; then
    # Shrunk — definitely manipulation
    jq --argjson old "$OLD_HISTORY" '.phase_history = $old' "$STATE_FILE" > /tmp/ptc-fix.json && mv /tmp/ptc-fix.json "$STATE_FILE"
    WARNINGS="${WARNINGS}⚠ REVERT: phase_history fue reducido (de $OLD_LEN a $NEW_LEN entries). Revertido. Usa phase-advance.sh para transiciones legales. "
  elif [ "$NEW_LEN" -gt "$OLD_LEN" ]; then
    # Grew — check if old entries are preserved (append-only)
    OLD_PREFIX=$(echo "$OLD_HISTORY" | jq -c ".[0:$OLD_LEN]")
    NEW_PREFIX=$(echo "$NEW_HISTORY" | jq -c ".[0:$OLD_LEN]")
    if [ "$OLD_PREFIX" != "$NEW_PREFIX" ]; then
      # Old entries were modified — revert
      jq --argjson old "$OLD_HISTORY" '.phase_history = $old' "$STATE_FILE" > /tmp/ptc-fix.json && mv /tmp/ptc-fix.json "$STATE_FILE"
      WARNINGS="${WARNINGS}⚠ REVERT: phase_history fue reescrito (entries existentes modificados). Revertido. Usa phase-advance.sh. "
    else
      # Check new entries have timestamp format
      NEW_ENTRIES=$(echo "$NEW_HISTORY" | jq -c ".[$OLD_LEN:]")
      HAS_BAD_FORMAT=$(echo "$NEW_ENTRIES" | jq '[.[] | select(type == "string" or (.phase == null) or (.at == null))] | length')
      if [ "$HAS_BAD_FORMAT" -gt 0 ]; then
        # String entries (old format) or missing timestamps — revert
        jq --argjson old "$OLD_HISTORY" '.phase_history = $old' "$STATE_FILE" > /tmp/ptc-fix.json && mv /tmp/ptc-fix.json "$STATE_FILE"
        WARNINGS="${WARNINGS}⚠ REVERT: phase_history tiene entries sin timestamp (formato antiguo). Revertido. Usa phase-advance.sh. "
      fi
    fi
  fi
fi

# Check 2: user_approved set to true directly (not via user-prompt-state.sh)
OLD_APPROVED=$(jq -r '.evidence.user_approved // false' "$SNAPSHOT_FILE" 2>/dev/null || echo "false")
NEW_APPROVED=$(jq -r '.evidence.user_approved // false' "$STATE_FILE" 2>/dev/null || echo "false")

if [ "$OLD_APPROVED" = "false" ] && [ "$NEW_APPROVED" = "true" ]; then
  # user_approved flipped to true — was this via user-prompt-state.sh or direct manipulation?
  # Only flag if the command explicitly assigns user_approved to true (not just reads/introspects it).
  # The sanctioned path is user-prompt-state.sh which runs as a UserPromptSubmit hook (not a Bash command).
  if echo "$COMMAND" | grep -qE 'user_approved\s*=\s*true'; then
    jq '.evidence.user_approved = false' "$STATE_FILE" > /tmp/ptc-fix.json && mv /tmp/ptc-fix.json "$STATE_FILE"
    WARNINGS="${WARNINGS}⚠ REVERT: user_approved fue seteado directamente via jq. Solo el hook UserPromptSubmit puede aprobarlo (cuando el usuario da su aprobacion). "
  fi
fi

# Update snapshot for next comparison
cp "$STATE_FILE" "$SNAPSHOT_FILE"

# Emit warnings if any
if [ -n "$WARNINGS" ]; then
  # Escape for JSON
  ESCAPED=$(echo "$WARNINGS" | sed 's/"/\\"/g')
  echo "{\"systemMessage\":\"PHASE-TRANSITION-CONTROLLER: $ESCAPED\"}"
fi

exit 0
