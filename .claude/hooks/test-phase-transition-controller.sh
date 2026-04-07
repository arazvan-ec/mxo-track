#!/usr/bin/env bash
# Tests for phase-transition-controller.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONTROLLER="$SCRIPT_DIR/phase-transition-controller.sh"
REPO="/home/user/mxo-track"
STATE_FILE="$REPO/.claude/session-state.json"
SNAPSHOT="/tmp/ptc-state-snapshot.json"
BACKUP="/tmp/test-ptc-backup.json"
BACKUP_SNAPSHOT="/tmp/test-ptc-snapshot-backup.json"

# Save originals
cp "$STATE_FILE" "$BACKUP"
[ -f "$SNAPSHOT" ] && cp "$SNAPSHOT" "$BACKUP_SNAPSHOT" || true

PASS=0
FAIL=0

reset_state() {
  cat > "$STATE_FILE" << 'HEREDOC'
{
  "flow_type": "full",
  "current_phase": "brainstorming",
  "phase_history": [{"phase": "consult", "at": "2026-04-07T10:00:00Z"}],
  "evidence": {"user_approved": false}
}
HEREDOC
  cp "$STATE_FILE" "$SNAPSHOT"
}

run_controller() {
  local cmd="${1:-jq . session-state.json}"
  # Use jq to safely build JSON (avoids nested quote issues)
  jq -n --arg tn "Bash" --arg cmd "$cmd" \
    '{"tool_name": $tn, "tool_input": {"command": $cmd}}' | "$CONTROLLER" 2>/dev/null || true
}

echo "=== Test: phase-transition-controller.sh ==="

# Test 1: Non-Bash tool → no action
echo "Test 1: Non-Bash tool ignored"
reset_state
echo '{"tool_name": "Read", "tool_input": {"file_path": "foo"}}' | "$CONTROLLER" > /dev/null 2>&1
PASS=$((PASS + 1))
echo "  ✅ Non-Bash tool passes through"

# Test 2: Bash without session-state → no action
echo "Test 2: Bash without session-state reference"
reset_state
RESULT=$(echo '{"tool_name": "Bash", "tool_input": {"command": "ls -la"}}' | "$CONTROLLER" 2>/dev/null || true)
if [ -z "$RESULT" ]; then
  echo "  ✅ Unrelated Bash command ignored"
  PASS=$((PASS + 1))
else
  echo "  ❌ Should have ignored unrelated command"
  FAIL=$((FAIL + 1))
fi

# Test 3: Direct phase_history rewrite with strings → revert
echo "Test 3: Direct phase_history rewrite (string format)"
reset_state
# Simulate: Claude writes phase_history with old string format
jq '.phase_history = ["consult", "brainstorming", "planning"]' "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
RESULT=$(run_controller "jq '.phase_history = [...]' .claude/session-state.json")
REVERTED_HISTORY=$(jq -c '.phase_history' "$STATE_FILE")
ORIGINAL='[{"phase":"consult","at":"2026-04-07T10:00:00Z"}]'
if [ "$REVERTED_HISTORY" = "$ORIGINAL" ]; then
  echo "  ✅ phase_history reverted to original"
  PASS=$((PASS + 1))
else
  echo "  ❌ phase_history not reverted: $REVERTED_HISTORY"
  FAIL=$((FAIL + 1))
fi

# Test 4: phase_history shrunk → revert
echo "Test 4: phase_history shrunk"
reset_state
jq '.phase_history = []' "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
run_controller "jq '.phase_history = []' .claude/session-state.json"
LEN=$(jq '.phase_history | length' "$STATE_FILE")
if [ "$LEN" -eq 1 ]; then
  echo "  ✅ Shrunk phase_history reverted (len=$LEN)"
  PASS=$((PASS + 1))
else
  echo "  ❌ Should have reverted (len=$LEN, expected 1)"
  FAIL=$((FAIL + 1))
fi

# Test 5: Legal append with timestamps → allowed
echo "Test 5: Legal append with timestamp format"
reset_state
jq '.phase_history += [{"phase": "brainstorming", "at": "2026-04-07T10:05:00Z"}]' "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
RESULT=$(run_controller "jq '.phase_history += [...]' .claude/session-state.json")
LEN=$(jq '.phase_history | length' "$STATE_FILE")
if [ "$LEN" -eq 2 ]; then
  echo "  ✅ Legal append preserved (len=$LEN)"
  PASS=$((PASS + 1))
else
  echo "  ❌ Legal append was reverted (len=$LEN, expected 2)"
  FAIL=$((FAIL + 1))
fi

# Test 6: Direct user_approved = true → revert
echo "Test 6: Direct user_approved = true"
reset_state
jq '.evidence.user_approved = true' "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
RESULT=$(run_controller "jq '.evidence.user_approved = true' .claude/session-state.json")
APPROVED=$(jq -r '.evidence.user_approved' "$STATE_FILE")
if [ "$APPROVED" = "false" ]; then
  echo "  ✅ user_approved reverted to false"
  PASS=$((PASS + 1))
else
  echo "  ❌ user_approved not reverted: $APPROVED"
  FAIL=$((FAIL + 1))
fi

# Test 7: phase-advance.sh command → not intercepted
echo "Test 7: phase-advance.sh is exempt"
reset_state
RESULT=$(echo '{"tool_name": "Bash", "tool_input": {"command": ".claude/hooks/phase-advance.sh brainstorming"}}' | "$CONTROLLER" 2>/dev/null || true)
if [ -z "$RESULT" ]; then
  echo "  ✅ phase-advance.sh command exempted"
  PASS=$((PASS + 1))
else
  echo "  ❌ phase-advance.sh should be exempt"
  FAIL=$((FAIL + 1))
fi

# Restore originals
cp "$BACKUP" "$STATE_FILE"
[ -f "$BACKUP_SNAPSHOT" ] && cp "$BACKUP_SNAPSHOT" "$SNAPSHOT" || rm -f "$SNAPSHOT"
rm -f "$BACKUP" "$BACKUP_SNAPSHOT"

echo ""
echo "=== Results: $PASS passed, $FAIL failed ==="
[ "$FAIL" -eq 0 ] && exit 0 || exit 1
