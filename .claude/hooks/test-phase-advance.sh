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

# ── Validator-blocking tests ──

# Test 8: brainstorming → planning blocked without spec file
reset_state "full"
jq '.current_phase = "brainstorming" | .evidence = {
  "user_turns": 3, "alternatives_proposed": true, "user_approved": true,
  "spec_path": "/tmp/nonexistent-spec.md"
}' "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
echo "Test 8: brainstorming → planning blocked without spec file"
assert_fail "blocks when spec file missing" "$ADVANCE" "planning"

# Test 9: planning → implementation blocked without plan file
reset_state "full"
jq '.current_phase = "planning" | .evidence = {
  "plan_path": "/tmp/nonexistent-plan.md"
}' "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
echo "Test 9: planning → implementation blocked without plan file"
assert_fail "blocks when plan file missing" "$ADVANCE" "implementation"

# Test 10: retrospective → finalize blocked without lessons
TEST_LOG="/tmp/test-pa-log.md"
echo "## Implementation" > "$TEST_LOG"
echo "Did some stuff." >> "$TEST_LOG"
reset_state "full"
jq --arg log "$TEST_LOG" '.current_phase = "retrospective" | .evidence = {
  "execution_log_path": $log
}' "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
echo "Test 10: retrospective → finalize blocked without lessons section"
assert_fail "blocks without retrospective in log" "$ADVANCE" "finalize"

# ── Full walk with artifacts ──

# Test 11: Full sequence walk (with required artifacts)
TEST_SPEC="/tmp/test-pa-spec.md"
TEST_PLAN="/tmp/test-pa-plan.md"
TEST_LOG2="/tmp/test-pa-log2.md"

# Create minimal valid spec
cat > "$TEST_SPEC" << 'SPECEOF'
# Test Spec
## Problem
Test problem description for the spec that needs to be at least 500 bytes.

## Alternativa A
Option A description.

## Alternativa B
Option B description with trade-off analysis.

## Existing Functionality Inventory
| Element | Decision |
|---------|----------|
| Foo     | Keep     |

## Omission Decisions
| Element | Decision |
|---------|----------|
| Bar     | Not touched |

## Architecture
Some architecture description to reach the 500 byte minimum for the spec validator.
More content here to pad it out sufficiently for the test to pass the size check.
SPECEOF

# Create minimal valid plan (must be ≥300 bytes)
cat > "$TEST_PLAN" << 'PLANEOF'
# Test Plan

**Spec:** specs/test-spec.md
**Branch:** test-branch

## Phase 1 (v0)

### Task 1 — Crear something new
- Crear a new file with the required content
- Modificar the existing configuration to support the new feature
- Test that it compiles and works correctly

### Task 2 — Actualizar tests
- Actualizar existing test files to cover the new behavior
- Run all tests and verify they pass without regressions
- Commit after verification

## Phase 2 (Mature): N/A
PLANEOF

# Create minimal valid execution log with retrospective
cat > "$TEST_LOG2" << 'LOGEOF'
# Execution Log

## Implementation
Did some stuff.

## Lessons

- First lesson learned from the implementation process that was quite informative and educational overall
- Second lesson about testing that revealed important patterns in the codebase worth documenting for future
- Third lesson about workflow enforcement that helped identify architectural gaps in the hook system
LOGEOF

reset_state "full"
echo "Test 11: Full legal sequence walk (with artifacts)"
ALL_PASS=true
PHASES=("consult" "brainstorming" "planning" "implementation" "verification" "capture" "retrospective" "finalize")
for phase in "${PHASES[@]}"; do
  # Set required evidence before each gated transition
  case "$phase" in
    brainstorming)
      # consult validator (hardened AND): decisions_read AND logs_scanned
      jq '.evidence.decisions_read = true | .evidence.logs_scanned = true' "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
      ;;
    planning)
      # brainstorm validator: spec + approval + turns + alternatives
      jq --arg sp "$TEST_SPEC" '.evidence = (.evidence + {
        "user_turns": 3, "alternatives_proposed": true, "user_approved": true,
        "spec_path": $sp
      })' "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
      ;;
    implementation)
      # planning validator: plan file ≥300B with keywords
      jq --arg pp "$TEST_PLAN" '.evidence.plan_path = $pp' "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
      ;;
    verification)
      # implementation validator: plan exists (HARD), TDD (SOFT — exit 1 allowed)
      ;;
    capture)
      # verification validator: tests_passed + lint_clean
      jq '.evidence.tests_passed = true | .evidence.lint_clean = true' "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
      ;;
    retrospective)
      # capture validator: execution_log_path (SOFT — exit 1 allowed)
      jq --arg lp "$TEST_LOG2" '.evidence.execution_log_path = $lp' "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
      ;;
    finalize)
      # retrospective validator (hardened): retrospective_shown flag + lessons section
      jq '.evidence.retrospective_shown = true' "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
      ;;
  esac

  if ! "$ADVANCE" "$phase" > /dev/null 2>&1; then
    echo "  ❌ Failed to advance to $phase"
    FAIL=$((FAIL + 1))
    ALL_PASS=false
    break
  fi
done
if [ "$ALL_PASS" = true ]; then
  echo "  ✅ Full sequence completed (with validator gates)"
  PASS=$((PASS + 1))
fi

# Cleanup temp files
rm -f "$TEST_SPEC" "$TEST_PLAN" "$TEST_LOG" "$TEST_LOG2"

# Test 12: phase_history has timestamps
echo "Test 12: phase_history has timestamp format"
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

# ── Option 3-Enforced hardened gates (2026-04-22) ──

# Test 13: consult → brainstorming blocked when only decisions_read (was OR, now AND)
reset_state "full"
jq '.current_phase = "consult" | .evidence.decisions_read = true | .evidence.logs_scanned = false' \
  "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
echo "Test 13: consult → brainstorming blocked when only decisions_read=true (AND gate)"
assert_fail "blocks when logs_scanned=false" "$ADVANCE" "brainstorming"

# Test 14: consult → brainstorming blocked when only logs_scanned
reset_state "full"
jq '.current_phase = "consult" | .evidence.decisions_read = false | .evidence.logs_scanned = true' \
  "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
echo "Test 14: consult → brainstorming blocked when only logs_scanned=true (AND gate)"
assert_fail "blocks when decisions_read=false" "$ADVANCE" "brainstorming"

# Test 15: consult → brainstorming passes when BOTH true
reset_state "full"
jq '.current_phase = "consult" | .evidence.decisions_read = true | .evidence.logs_scanned = true' \
  "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
echo "Test 15: consult → brainstorming passes when both flags true"
assert_pass "passes when both evidence flags set" "$ADVANCE" "brainstorming"

# Test 16: capture → retrospective blocked (HARD gate, was SOFT)
reset_state "full"
jq '.current_phase = "capture" | .evidence.execution_log_path = null' \
  "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
echo "Test 16: capture → retrospective blocked without execution_log_path (HARD)"
assert_fail "blocks (HARD) when execution_log_path missing" "$ADVANCE" "retrospective"

# Test 17: retrospective → finalize blocked when retrospective_shown=false
TEST_LOG3="/tmp/test-pa-log3.md"
cat > "$TEST_LOG3" <<'LOGEOF'
# Execution Log

## Lessons
- A lesson long enough to pass the 100-char minimum check. This is to verify the retrospective_shown flag is what blocks, not the section content.
- Another lesson with enough content to be considered complete.
LOGEOF
reset_state "full"
jq --arg lp "$TEST_LOG3" '.current_phase = "retrospective" | .evidence = {
  "execution_log_path": $lp,
  "retrospective_shown": false
}' "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
echo "Test 17: retrospective → finalize blocked without retrospective_shown=true"
assert_fail "blocks when retrospective_shown=false" "$ADVANCE" "finalize"

# Test 18: retrospective → finalize passes with retrospective_shown=true
reset_state "full"
jq --arg lp "$TEST_LOG3" '.current_phase = "retrospective" | .evidence = {
  "execution_log_path": $lp,
  "retrospective_shown": true
}' "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
echo "Test 18: retrospective → finalize passes with retrospective_shown=true"
assert_pass "passes with retrospective_shown=true" "$ADVANCE" "finalize"

# Test 19: SKIP_PHASE_EXIT_GATE=1 bypass (capture with no log)
reset_state "full"
jq '.current_phase = "capture" | .evidence.execution_log_path = null' \
  "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
echo "Test 19: SKIP_PHASE_EXIT_GATE=1 bypass allows advance"
if SKIP_PHASE_EXIT_GATE=1 "$ADVANCE" "retrospective" > /dev/null 2>&1; then
  echo "  ✅ SKIP_PHASE_EXIT_GATE bypass works"
  PASS=$((PASS + 1))
else
  echo "  ❌ SKIP_PHASE_EXIT_GATE bypass failed"
  FAIL=$((FAIL + 1))
fi

rm -f "$TEST_LOG3"

# Restore original state
cp "$BACKUP" "$STATE_FILE"
rm -f "$BACKUP"

echo ""
echo "=== Results: $PASS passed, $FAIL failed ==="
[ "$FAIL" -eq 0 ] && exit 0 || exit 1
