#!/usr/bin/env bash
# Tests for phase-advance.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ADVANCE="$SCRIPT_DIR/phase-advance.sh"
REPO="/home/user/mxo-track"
STATE_FILE="$REPO/.claude/session-state.json"
BACKUP="/tmp/test-pa-backup.json"

# Save original state
cp "$STATE_FILE" "$BACKUP"

PASS=0
FAIL=0

assert_pass() {
  local desc="$1"; shift
  if "$@" > /dev/null 2>&1; then
    PASS=$((PASS + 1))
    echo "  ✅ $desc"
  else
    FAIL=$((FAIL + 1))
    echo "  ❌ $desc (expected pass, got fail)"
  fi
}

assert_fail() {
  local desc="$1"; shift
  if "$@" > /dev/null 2>&1; then
    FAIL=$((FAIL + 1))
    echo "  ❌ $desc (expected fail, got pass)"
  else
    PASS=$((PASS + 1))
    echo "  ✅ $desc"
  fi
}

reset_state() {
  jq --arg ft "${1:-full}" '.flow_type = $ft | .current_phase = null | .phase_history = []' \
    "$BACKUP" > "$STATE_FILE"
}

echo "=== Test: phase-advance.sh ==="

# Test 1: No args → fail
echo "Test 1: No arguments"
assert_fail "should fail without args" "$ADVANCE"

# Test 2: Invalid phase → fail
reset_state "full"
echo "Test 2: Invalid phase name"
assert_fail "should fail with invalid phase" "$ADVANCE" "invalid_phase"

# Test 3: From null → consult (legal)
reset_state "full"
echo "Test 3: null → consult (legal)"
assert_pass "should advance to consult" "$ADVANCE" "consult"
PHASE=$(jq -r '.current_phase' "$STATE_FILE")
if [ "$PHASE" = "consult" ]; then
  echo "  ✅ current_phase = consult"
  PASS=$((PASS + 1))
else
  echo "  ❌ current_phase = $PHASE (expected consult)"
  FAIL=$((FAIL + 1))
fi

# Test 4: From null → brainstorming (illegal skip)
reset_state "full"
echo "Test 4: null → brainstorming (illegal skip)"
assert_fail "should reject skipping to brainstorming" "$ADVANCE" "brainstorming"

# Test 5: consult → brainstorming (legal)
reset_state "full"
jq '.current_phase = "consult"' "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
echo "Test 5: consult → brainstorming (legal)"
assert_pass "should advance to brainstorming" "$ADVANCE" "brainstorming"

# Test 6: consult → planning (illegal skip)
reset_state "full"
jq '.current_phase = "consult"' "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
echo "Test 6: consult → planning (illegal skip)"
assert_fail "should reject skipping to planning" "$ADVANCE" "planning"

# Test 7: brainstorming → consult (illegal backward)
reset_state "full"
jq '.current_phase = "brainstorming"' "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
echo "Test 7: brainstorming → consult (illegal backward)"
assert_fail "should reject going backwards" "$ADVANCE" "consult"

# Test 8: Full sequence walk
reset_state "full"
echo "Test 8: Full legal sequence walk"
PHASES=("consult" "brainstorming" "planning" "implementation" "verification" "capture" "retrospective" "finalize")
ALL_PASS=true
for phase in "${PHASES[@]}"; do
  if ! "$ADVANCE" "$phase" > /dev/null 2>&1; then
    echo "  ❌ Failed to advance to $phase"
    FAIL=$((FAIL + 1))
    ALL_PASS=false
    break
  fi
done
if [ "$ALL_PASS" = true ]; then
  echo "  ✅ Full sequence completed"
  PASS=$((PASS + 1))
fi

# Test 9: phase_history has timestamps
echo "Test 9: phase_history has timestamp format"
FIRST_ENTRY=$(jq '.phase_history[0]' "$STATE_FILE")
HAS_PHASE=$(echo "$FIRST_ENTRY" | jq -r '.phase // "MISSING"')
HAS_AT=$(echo "$FIRST_ENTRY" | jq -r '.at // "MISSING"')
if [ "$HAS_PHASE" != "MISSING" ] && [ "$HAS_AT" != "MISSING" ]; then
  echo "  ✅ phase_history entries have {phase, at} format"
  PASS=$((PASS + 1))
else
  echo "  ❌ phase_history entries missing phase or at: $FIRST_ENTRY"
  FAIL=$((FAIL + 1))
fi

# Test 10: phase_history length = 8 after full walk
HISTORY_LEN=$(jq '.phase_history | length' "$STATE_FILE")
if [ "$HISTORY_LEN" -eq 8 ]; then
  echo "  ✅ phase_history length = 8"
  PASS=$((PASS + 1))
else
  echo "  ❌ phase_history length = $HISTORY_LEN (expected 8)"
  FAIL=$((FAIL + 1))
fi

# Restore original state
cp "$BACKUP" "$STATE_FILE"
rm -f "$BACKUP"

echo ""
echo "=== Results: $PASS passed, $FAIL failed ==="
[ "$FAIL" -eq 0 ] && exit 0 || exit 1
