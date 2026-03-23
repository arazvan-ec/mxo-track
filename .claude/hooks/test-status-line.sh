#!/usr/bin/env bash
# Unit tests for workflow-status-line.sh
set -euo pipefail

REPO="/home/user/mxo-track"
HOOK="$REPO/.claude/hooks/workflow-status-line.sh"
STATE_FILE="$REPO/.claude/session-state.json"
OUTPUT="$REPO/.claude/workflow-status-line.txt"
BACKUP="$REPO/.claude/session-state.json.test-backup"

# Save original state
if [ -f "$STATE_FILE" ]; then
  cp "$STATE_FILE" "$BACKUP"
fi

PASSED=0
FAILED=0

assert_output() {
  local test_name="$1"
  local expected="$2"
  local actual
  actual=$(cat "$OUTPUT" 2>/dev/null || echo "(no output)")
  if [ "$actual" = "$expected" ]; then
    echo "  ✅ $test_name"
    PASSED=$((PASSED + 1))
  else
    echo "  ❌ $test_name"
    echo "     Expected: $expected"
    echo "     Got:      $actual"
    FAILED=$((FAILED + 1))
  fi
}

assert_contains() {
  local test_name="$1"
  local expected="$2"
  local actual
  actual=$(cat "$OUTPUT" 2>/dev/null || echo "(no output)")
  if echo "$actual" | grep -qF "$expected"; then
    echo "  ✅ $test_name"
    PASSED=$((PASSED + 1))
  else
    echo "  ❌ $test_name"
    echo "     Expected to contain: $expected"
    echo "     Got: $actual"
    FAILED=$((FAILED + 1))
  fi
}

write_state() {
  echo "$1" > "$STATE_FILE"
}

echo "=== Testing workflow-status-line.sh ==="
echo ""

# --- Test 1: Missing state file ---
echo "1. Missing state file"
rm -f "$STATE_FILE"
$HOOK
assert_output "fallback when no state file" "📍 status unavailable"

# --- Test 2: Null flow ---
echo "2. Null flow type"
write_state '{"flow_type": null, "current_phase": null, "deviation": {"active": false}}'
$HOOK
assert_output "no flow declared" "📍 no flow declared"

# --- Test 3: Micro flow ---
echo "3. Micro flow"
write_state '{"flow_type": "micro", "current_phase": null, "deviation": {"active": false}}'
$HOOK
assert_output "micro flow label" "📍 micro | Responder"

# --- Test 4: Light flow ---
echo "4. Light flow"
write_state '{"flow_type": "light", "current_phase": null, "deviation": {"active": false}}'
$HOOK
assert_output "light flow label" "📍 light | Documentar"

# --- Test 5: Explore flow ---
echo "5. Explore flow"
write_state '{"flow_type": "explore", "current_phase": null, "deviation": {"active": false}}'
$HOOK
assert_output "explore flow label" "📍 explore | Investigar"

# --- Test 6: Full flow - consult ---
echo "6. Full flow - consult phase"
write_state '{"flow_type": "full", "current_phase": "consult", "deviation": {"active": false}, "evidence": {}}'
$HOOK
assert_contains "consult is phase 1/8" "Consult (1/8)"
assert_contains "consult is active" "🔄 consult"
assert_contains "pending includes brainstorming" "Pendiente: brainstorming"

# --- Test 7: Full flow - brainstorming ---
echo "7. Full flow - brainstorming phase"
write_state '{"flow_type": "full", "current_phase": "brainstorming", "deviation": {"active": false}, "evidence": {}}'
$HOOK
assert_contains "brainstorming is phase 2/8" "Brainstorming (2/8)"
assert_contains "consult completed" "✅ consult"
assert_contains "brainstorming active" "🔄 brainstorming"

# --- Test 8: Full flow - implementation ---
echo "8. Full flow - implementation phase"
write_state '{"flow_type": "full", "current_phase": "implementation", "deviation": {"active": false}, "evidence": {}}'
$HOOK
assert_contains "implementation is phase 4/8" "Implementation (4/8)"
assert_contains "3 prior phases completed" "✅ consult"
assert_contains "planning completed" "✅ planning"
assert_contains "pending has verification" "Pendiente: verification"

# --- Test 9: Full flow - finalize ---
echo "9. Full flow - finalize phase"
write_state '{"flow_type": "full", "current_phase": "finalize", "deviation": {"active": false}, "evidence": {}}'
$HOOK
assert_contains "finalize is phase 8/8" "Finalize (8/8)"
assert_contains "retrospective completed" "✅ retrospective"

# --- Test 10: Debug flow - consult ---
echo "10. Debug flow - consult phase"
write_state '{"flow_type": "debug", "current_phase": "consult", "deviation": {"active": false}, "evidence": {"root_cause_identified": false, "pattern_wide_search_done": false}}'
$HOOK
assert_contains "debug consult is 1/4" "Consult (1/4)"
assert_contains "pending has root_cause" "Pendiente: root_cause"

# --- Test 11: Debug flow - root_cause phase ---
echo "11. Debug flow - root_cause phase"
write_state '{"flow_type": "debug", "current_phase": "consult", "deviation": {"active": false}, "evidence": {"decisions_read": true, "root_cause_identified": false, "pattern_wide_search_done": false}}'
# When consult is done but root_cause not identified, should be at root_cause
$HOOK
# current_phase is consult, root_cause not identified → still at consult
assert_contains "debug at consult" "Consult (1/4)"

# --- Test 12: Debug flow - after root cause identified ---
echo "12. Debug flow - root cause identified"
write_state '{"flow_type": "debug", "current_phase": "consult", "deviation": {"active": false}, "evidence": {"root_cause_identified": true, "pattern_wide_search_done": false}}'
$HOOK
assert_contains "pattern_search phase" "Pattern_search (3/4)"
assert_contains "consult and root_cause done" "✅ root_cause"

# --- Test 13: Debug flow - after pattern search done ---
echo "13. Debug flow - pattern search done"
write_state '{"flow_type": "debug", "current_phase": "implementation", "deviation": {"active": false}, "evidence": {"root_cause_identified": true, "pattern_wide_search_done": true}}'
$HOOK
assert_contains "fix phase" "Fix (4/4)"
assert_contains "pattern_search completed" "✅ pattern_search"

# --- Test 14: Deviation active ---
echo "14. Deviation active"
write_state '{"flow_type": "full", "current_phase": "implementation", "deviation": {"active": true, "reason": "hotfix"}}'
$HOOK
assert_contains "deviation warning" "⚠ DESVÍO"

# --- Test 15: Deviation on simple flow ---
echo "15. Deviation on micro flow"
write_state '{"flow_type": "micro", "current_phase": null, "deviation": {"active": true, "reason": "urgent"}}'
$HOOK
assert_output "micro with deviation" "📍 micro | Responder | ⚠ DESVÍO"

# --- Test 16: Null flow with deviation ---
echo "16. Null flow with deviation"
write_state '{"flow_type": null, "current_phase": null, "deviation": {"active": true, "reason": "test"}}'
$HOOK
assert_output "no flow with deviation" "📍 no flow declared | ⚠ DESVÍO"

# --- Cleanup ---
rm -f "$OUTPUT"
if [ -f "$BACKUP" ]; then
  mv "$BACKUP" "$STATE_FILE"
else
  rm -f "$STATE_FILE"
fi

echo ""
echo "=== Results: $PASSED passed, $FAILED failed ==="

if [ "$FAILED" -gt 0 ]; then
  exit 1
fi
exit 0
