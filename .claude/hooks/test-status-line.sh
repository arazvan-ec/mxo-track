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
assert_contains "no flow declared" "📍 no flow declared"

# --- Test 3: Micro flow ---
echo "3. Micro flow"
write_state '{"flow_type": "micro", "current_phase": null, "deviation": {"active": false}}'
$HOOK
assert_contains "micro flow label" "📍 micro | Responder"

# --- Test 4: Light flow ---
echo "4. Light flow"
write_state '{"flow_type": "light", "current_phase": null, "deviation": {"active": false}}'
$HOOK
assert_contains "light flow label" "📍 light | Documentar"

# --- Test 5: Explore flow ---
echo "5. Explore flow"
write_state '{"flow_type": "explore", "current_phase": null, "deviation": {"active": false}}'
$HOOK
assert_contains "explore flow label" "📍 explore | Investigar"

# --- Test 6: Full flow - consult ---
echo "6. Full flow - consult phase"
write_state '{"flow_type": "full", "current_phase": "consult", "deviation": {"active": false}, "evidence": {}}'
$HOOK
assert_contains "consult is phase 1/8" "Consult (1/8)"
assert_contains "consult is active (emoji bar)" "🔄⬚⬚⬚⬚⬚⬚⬚"
assert_contains "evidence on line 2" "Evidence: decisions_read=N"
assert_contains "next action on line 3" "Next: read decisions/logs"

# --- Test 7: Full flow - brainstorming ---
echo "7. Full flow - brainstorming phase"
write_state '{"flow_type": "full", "current_phase": "brainstorming", "deviation": {"active": false}, "evidence": {}}'
$HOOK
assert_contains "brainstorming is phase 2/8" "Brainstorming (2/8)"
assert_contains "consult completed" "✅ consult"
assert_contains "brainstorming active (emoji bar)" "✅🔄⬚⬚⬚⬚⬚⬚"

# --- Test 8: Full flow - implementation ---
echo "8. Full flow - implementation phase"
write_state '{"flow_type": "full", "current_phase": "implementation", "deviation": {"active": false}, "evidence": {}}'
$HOOK
assert_contains "implementation is phase 4/8" "Implementation (4/8)"
assert_contains "3 prior phases completed" "✅ consult"
assert_contains "planning completed" "✅ planning"
assert_contains "expanded: history on line 3" "✅ consult"

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
assert_contains "debug bar shows active" "🔄⬚⬚⬚"

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
assert_contains "micro with deviation" "⚠ DESVÍO"

# --- Test 16: Null flow with deviation ---
echo "16. Null flow with deviation"
write_state '{"flow_type": null, "current_phase": null, "deviation": {"active": true, "reason": "test"}}'
$HOOK
assert_contains "no flow with deviation" "📍 no flow declared"
assert_contains "no flow deviation suffix" "⚠ DESVÍO"
assert_contains "no flow has branch" "🔀"

# --- Test 17: Branch name shown ---
echo "17. Branch name in status line"
write_state '{"flow_type": "full", "current_phase": "consult", "deviation": {"active": false}, "evidence": {}}'
$HOOK
assert_contains "branch shown in full flow" "🔀"

# --- Test 18: Evidence line in debug ---
echo "18. Debug evidence line"
write_state '{"flow_type": "debug", "current_phase": "consult", "deviation": {"active": false}, "evidence": {"root_cause_identified": false, "pattern_wide_search_done": false}}'
$HOOK
assert_contains "debug has evidence line" "Evidence: decisions=N root_cause=N"
assert_contains "debug has next line" "Next: read decisions/logs"

# --- Test 19: Full flow evidence varies by phase ---
echo "19. Brainstorming evidence fields"
write_state '{"flow_type": "full", "current_phase": "brainstorming", "deviation": {"active": false}, "evidence": {"user_turns": 2, "alternatives_proposed": true, "user_approved": false}}'
$HOOK
assert_contains "brainstorm evidence shows turns" "user_turns=2"
assert_contains "brainstorm evidence shows alternatives" "alternatives=Y"
assert_contains "brainstorm evidence shows approved" "approved=N"
assert_contains "brainstorm next action" "Next: get approval"

# --- Test 20: Implementation with task progress ---
echo "20. Implementation with task progress"
write_state '{"flow_type": "full", "current_phase": "implementation", "deviation": {"active": false}, "evidence": {"plan_path": "plans/test.md", "tests_written": 3, "task_progress": {"current": 2, "total": 5, "label": "Add toggle", "completed_labels": ["Init"]}}}'
$HOOK
assert_contains "impl evidence has task" "task=2/5"
assert_contains "impl next has task label" "Next: task 2/5: Add toggle"
assert_contains "impl task bar on line 1" "t2/5: Add toggle"

# --- Test 21: Simple flows show branch ---
echo "21. Simple flows have branch"
write_state '{"flow_type": "explore", "current_phase": null, "deviation": {"active": false}}'
$HOOK
assert_contains "explore has branch" "🔀"

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
