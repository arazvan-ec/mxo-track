#!/usr/bin/env bash
# Unit tests for auto-evidence.sh
set -euo pipefail

REPO="/home/user/mxo-track"
HOOK="$REPO/.claude/hooks/auto-evidence.sh"
STATE_FILE="$REPO/.claude/session-state.json"
BACKUP="$REPO/.claude/session-state.json.test-backup"

# Save original state
if [ -f "$STATE_FILE" ]; then
  cp "$STATE_FILE" "$BACKUP"
fi

PASSED=0
FAILED=0

assert_field() {
  local test_name="$1"
  local jq_path="$2"
  local expected="$3"
  local actual
  actual=$(jq -r "$jq_path" "$STATE_FILE" 2>/dev/null || echo "(error)")
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

write_state() {
  echo "$1" > "$STATE_FILE"
}

run_hook() {
  echo "$1" | $HOOK
}

BASE_STATE='{"flow_type":"full","current_phase":"consult","deviation":{"active":false},"evidence":{"decisions_read":false,"logs_scanned":false,"user_turns":0,"alternatives_proposed":false,"user_approved":false,"spec_path":null,"plan_path":null,"tests_written":0,"tests_passed":null,"lint_clean":null,"execution_log_path":null,"branch_strategy":null,"root_cause_identified":false,"pattern_wide_search_done":false,"task_progress":{"current":0,"total":0,"label":null,"completed_labels":[]}}}'

echo "=== Testing auto-evidence.sh ==="
echo ""

# --- Test 1: Read decisions log ---
echo "1. Read decisions/log.md → decisions_read = true"
write_state "$BASE_STATE"
run_hook '{"tool_name":"Read","tool_input":{"file_path":"/home/user/mxo-track/docs/decisions/log.md"},"tool_response":{}}'
assert_field "decisions_read set" ".evidence.decisions_read" "true"

# --- Test 2: Read execution log ---
echo "2. Read execution-logs/* → logs_scanned = true"
write_state "$BASE_STATE"
run_hook '{"tool_name":"Read","tool_input":{"file_path":"/home/user/mxo-track/docs/superpowers/execution-logs/2026-04-01-test.md"},"tool_response":{}}'
assert_field "logs_scanned set" ".evidence.logs_scanned" "true"

# --- Test 3: Write spec ---
echo "3. Write spec → spec_path set"
write_state "$BASE_STATE"
run_hook '{"tool_name":"Write","tool_input":{"file_path":"/home/user/mxo-track/docs/superpowers/specs/2026-04-01-test-design.md"},"tool_response":{}}'
assert_field "spec_path set" ".evidence.spec_path" "/home/user/mxo-track/docs/superpowers/specs/2026-04-01-test-design.md"

# --- Test 4: Edit spec ---
echo "4. Edit spec → spec_path set"
write_state "$BASE_STATE"
run_hook '{"tool_name":"Edit","tool_input":{"file_path":"/home/user/mxo-track/docs/superpowers/specs/2026-04-01-test-design.md"},"tool_response":{}}'
assert_field "spec_path set via Edit" ".evidence.spec_path" "/home/user/mxo-track/docs/superpowers/specs/2026-04-01-test-design.md"

# --- Test 5: Write plan ---
echo "5. Write plan → plan_path set"
write_state "$BASE_STATE"
run_hook '{"tool_name":"Write","tool_input":{"file_path":"/home/user/mxo-track/docs/superpowers/plans/2026-04-01-test.md"},"tool_response":{}}'
assert_field "plan_path set" ".evidence.plan_path" "/home/user/mxo-track/docs/superpowers/plans/2026-04-01-test.md"

# --- Test 6: Write plan in conversation/ → ignored ---
echo "6. Write plan in conversation/ → plan_path NOT set"
write_state "$BASE_STATE"
run_hook '{"tool_name":"Write","tool_input":{"file_path":"/home/user/mxo-track/docs/superpowers/plans/conversation/2026-04-01-test.md"},"tool_response":{}}'
assert_field "plan_path unchanged" ".evidence.plan_path" "null"

# --- Test 7: Write execution log ---
echo "7. Write execution-log → execution_log_path set"
write_state "$BASE_STATE"
run_hook '{"tool_name":"Write","tool_input":{"file_path":"/home/user/mxo-track/docs/superpowers/execution-logs/2026-04-01-test.md"},"tool_response":{}}'
assert_field "execution_log_path set" ".evidence.execution_log_path" "/home/user/mxo-track/docs/superpowers/execution-logs/2026-04-01-test.md"

# --- Test 8: Write test file → tests_written derived from git ---
# Migration (Wave 2, 2b): tests_written is now ground-truthed from git diff +
# untracked files, not a counter. Create a real untracked test file so git
# sees it; verify tests_written reflects the count; clean up.
echo "8. Write test file → tests_written derived from git (≥1)"
write_state "$BASE_STATE"
FAKE_TEST="$REPO/backend/tests/Unit/_AutoEvidenceFakeTest.php"
mkdir -p "$(dirname "$FAKE_TEST")"
cat > "$FAKE_TEST" <<'PHP'
<?php
// Temporary fixture for test-auto-evidence.sh — removed in cleanup.
class _AutoEvidenceFakeTest {}
PHP
run_hook "{\"tool_name\":\"Write\",\"tool_input\":{\"file_path\":\"$FAKE_TEST\"},\"tool_response\":{}}"
TW=$(jq -r '.evidence.tests_written' "$STATE_FILE")
if [ "$TW" -ge 1 ] 2>/dev/null; then
  echo "  ✅ tests_written ≥ 1 after creating a test file (got $TW)"
  PASSED=$((PASSED + 1))
else
  echo "  ❌ tests_written < 1 after creating a test file (got $TW)"
  FAILED=$((FAILED + 1))
fi

# --- Test 9: Write second test → tests_written reflects total (≥ previous) ---
echo "9. Write second test file → tests_written reflects git count (not a counter)"
TW_BEFORE="$TW"
FAKE_TEST2="$REPO/backend/tests/Unit/_AutoEvidenceFakeTwoTest.php"
cat > "$FAKE_TEST2" <<'PHP'
<?php
class _AutoEvidenceFakeTwoTest {}
PHP
run_hook "{\"tool_name\":\"Edit\",\"tool_input\":{\"file_path\":\"$FAKE_TEST2\"},\"tool_response\":{}}"
TW_AFTER=$(jq -r '.evidence.tests_written' "$STATE_FILE")
if [ "$TW_AFTER" -ge "$TW_BEFORE" ] 2>/dev/null && [ "$TW_AFTER" -ge 2 ] 2>/dev/null; then
  echo "  ✅ tests_written ≥ 2 (previous=$TW_BEFORE, after=$TW_AFTER, two fake files tracked)"
  PASSED=$((PASSED + 1))
else
  echo "  ❌ tests_written did not reflect second file (previous=$TW_BEFORE, after=$TW_AFTER)"
  FAILED=$((FAILED + 1))
fi
rm -f "$FAKE_TEST" "$FAKE_TEST2"

# --- Test 9b: Edit to src (non-test) does NOT trigger tests_written update ---
echo "9b. Write src file → tests_written unchanged (only test paths trigger)"
write_state "$BASE_STATE"
run_hook '{"tool_name":"Write","tool_input":{"file_path":"/home/user/mxo-track/backend/src/Controller/SomeController.php"},"tool_response":{}}'
assert_field "tests_written untouched by src write" ".evidence.tests_written" "0"

# --- Test 10: Bash phpunit success ---
echo "10. Bash phpunit success → tests_passed = true"
write_state "$BASE_STATE"
run_hook '{"tool_name":"Bash","tool_input":{"command":"php vendor/bin/phpunit"},"tool_response":{"exit_code":0}}'
assert_field "tests_passed true" ".evidence.tests_passed" "true"

# --- Test 11: Bash phpunit failure ---
echo "11. Bash phpunit failure → tests_passed = false"
write_state "$BASE_STATE"
run_hook '{"tool_name":"Bash","tool_input":{"command":"php vendor/bin/phpunit --filter SomeTest"},"tool_response":{"exit_code":1}}'
assert_field "tests_passed false" ".evidence.tests_passed" "false"

# --- Test 12: Bash lint success ---
echo "12. Bash make lint success → lint_clean = true"
write_state "$BASE_STATE"
run_hook '{"tool_name":"Bash","tool_input":{"command":"make lint"},"tool_response":{"exit_code":0}}'
assert_field "lint_clean true" ".evidence.lint_clean" "true"

# --- Test 13: Bash lint failure ---
echo "13. Bash make lint failure → lint_clean = false"
write_state "$BASE_STATE"
run_hook '{"tool_name":"Bash","tool_input":{"command":"make lint"},"tool_response":{"exit_code":1}}'
assert_field "lint_clean false" ".evidence.lint_clean" "false"

# --- Test 14: No flow → no updates ---
echo "14. No flow declared → no evidence updates"
write_state '{"flow_type":null,"current_phase":null,"deviation":{"active":false},"evidence":{"decisions_read":false}}'
run_hook '{"tool_name":"Read","tool_input":{"file_path":"/home/user/mxo-track/docs/decisions/log.md"},"tool_response":{}}'
assert_field "decisions_read unchanged" ".evidence.decisions_read" "false"

# --- Test 15: Unrelated Read → no updates ---
echo "15. Unrelated Read → no evidence updates"
write_state "$BASE_STATE"
run_hook '{"tool_name":"Read","tool_input":{"file_path":"/home/user/mxo-track/backend/src/Entity/Vehicle.php"},"tool_response":{}}'
assert_field "decisions_read still false" ".evidence.decisions_read" "false"
assert_field "logs_scanned still false" ".evidence.logs_scanned" "false"

# --- Test 16: Empty stdin → no crash ---
echo "16. Empty/no stdin → exits cleanly"
write_state "$BASE_STATE"
echo '{}' | $HOOK
assert_field "state unchanged" ".evidence.decisions_read" "false"

# --- Cleanup ---
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
