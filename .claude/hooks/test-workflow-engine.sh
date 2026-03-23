#!/usr/bin/env bash
# End-to-end tests for the workflow verification system
#
# Tests: workflow-engine.sh + all validators
# Usage: bash .claude/hooks/test-workflow-engine.sh
# Returns: exit 0 if all pass, exit 1 on first failure

set -euo pipefail

REPO="/home/user/mxo-track"
ENGINE="$REPO/.claude/hooks/workflow-engine.sh"
STATE_FILE="$REPO/.claude/session-state.json"
SPEC_DIR="$REPO/docs/superpowers/specs"
PLANS_DIR="$REPO/docs/superpowers/plans"
EXEC_LOG_DIR="$REPO/docs/superpowers/execution-logs"
TODAY=$(date +%Y-%m-%d)

# Save originals
ORIG_STATE=""
[ -f "$STATE_FILE" ] && ORIG_STATE=$(cat "$STATE_FILE")

PASS=0
FAIL=0

cleanup() {
  # Restore originals
  if [ -n "$ORIG_STATE" ]; then
    echo "$ORIG_STATE" > "$STATE_FILE"
  fi
  # Remove test artifacts
  rm -f "$SPEC_DIR/test-workflow-spec.md"
  rm -f "$PLANS_DIR/${TODAY}-test-workflow.md"
  rm -f "$EXEC_LOG_DIR/${TODAY}-test-workflow.md"
  echo ""
  echo "==============================="
  echo "Results: $PASS passed, $FAIL failed"
  echo "==============================="
  [ "$FAIL" -eq 0 ] && exit 0 || exit 1
}
trap cleanup EXIT

# Helper: run engine with a fake Edit input
run_engine() {
  local file_path="$1"
  echo "{\"tool_name\":\"Edit\",\"tool_input\":{\"file_path\":\"$file_path\"}}" | bash "$ENGINE" 2>/dev/null || true
}

expect_deny() {
  local test_name="$1"
  local file_path="$2"
  local expected_msg="${3:-}"
  local result
  result=$(run_engine "$file_path")

  if echo "$result" | jq -r '.hookSpecificOutput.permissionDecision' 2>/dev/null | grep -q "deny"; then
    if [ -n "$expected_msg" ] && ! echo "$result" | grep -qiF "$expected_msg"; then
      echo "FAIL: $test_name — denied but wrong reason"
      echo "  Expected substring: $expected_msg"
      echo "  Got: $(echo "$result" | jq -r '.hookSpecificOutput.permissionDecisionReason' 2>/dev/null)"
      FAIL=$((FAIL + 1))
      return
    fi
    echo "PASS: $test_name"
    PASS=$((PASS + 1))
  else
    echo "FAIL: $test_name — expected deny but got allow"
    echo "  Output: $result"
    FAIL=$((FAIL + 1))
  fi
}

expect_allow() {
  local test_name="$1"
  local file_path="$2"
  local result
  result=$(run_engine "$file_path")

  # Empty result or systemMessage (warning) = allowed
  if [ -z "$result" ]; then
    echo "PASS: $test_name"
    PASS=$((PASS + 1))
    return
  fi

  if echo "$result" | jq -r '.hookSpecificOutput.permissionDecision' 2>/dev/null | grep -q "deny"; then
    echo "FAIL: $test_name — expected allow but got deny"
    echo "  Reason: $(echo "$result" | jq -r '.hookSpecificOutput.permissionDecisionReason' 2>/dev/null)"
    FAIL=$((FAIL + 1))
  else
    echo "PASS: $test_name (with warning)"
    PASS=$((PASS + 1))
  fi
}

expect_warn() {
  local test_name="$1"
  local file_path="$2"
  local expected_msg="${3:-}"
  local result
  result=$(run_engine "$file_path")

  if echo "$result" | jq -r '.systemMessage' 2>/dev/null | grep -q "WORKFLOW ENGINE"; then
    if [ -n "$expected_msg" ] && ! echo "$result" | grep -qiF "$expected_msg"; then
      echo "FAIL: $test_name — warned but wrong message"
      echo "  Expected substring: $expected_msg"
      echo "  Got: $(echo "$result" | jq -r '.systemMessage' 2>/dev/null)"
      FAIL=$((FAIL + 1))
      return
    fi
    echo "PASS: $test_name"
    PASS=$((PASS + 1))
  else
    echo "FAIL: $test_name — expected warning but got: $result"
    FAIL=$((FAIL + 1))
  fi
}

SRC_FILE="$REPO/backend/src/Controller/SomeController.php"
TEST_FILE="$REPO/backend/tests/SomeTest.php"
DOC_FILE="$REPO/docs/some-doc.md"
SPEC_FILE="$REPO/docs/superpowers/specs/test-workflow-spec.md"
PLAN_FILE="$REPO/docs/superpowers/plans/${TODAY}-test-workflow.md"

# =====================================================
# Setup helpers
# =====================================================
create_fresh_state() {
  cat > "$STATE_FILE" <<EOJSON
{
  "session_date": "$TODAY",
  "flow_type": null,
  "current_phase": null,
  "interaction_classification": null,
  "phase_history": [],
  "evidence": {
    "decisions_read": false,
    "logs_scanned": false,
    "user_turns": 0,
    "alternatives_proposed": false,
    "user_approved": false,
    "spec_path": null,
    "plan_path": null,
    "tests_written": 0,
    "tests_passed": null,
    "lint_clean": null,
    "execution_log_path": null,
    "branch_strategy": null
  },
  "deviation": {
    "active": false,
    "reason": null,
    "skipped_phases": [],
    "return_to_phase": null,
    "acknowledged_by_user": false
  }
}
EOJSON
}

set_flow() {
  local flow="$1"
  jq --arg f "$flow" '.flow_type = $f' "$STATE_FILE" > "$STATE_FILE.tmp" && mv "$STATE_FILE.tmp" "$STATE_FILE"
}

set_evidence() {
  local key="$1"
  local value="$2"
  jq --arg k "$key" --arg v "$value" '.evidence[$k] = ($v | if . == "true" then true elif . == "false" then false elif test("^[0-9]+$") then tonumber else . end)' "$STATE_FILE" > "$STATE_FILE.tmp" && mv "$STATE_FILE.tmp" "$STATE_FILE"
}

create_valid_spec() {
  cat > "$SPEC_DIR/test-workflow-spec.md" <<'EOF'
# Design Spec for Test Feature

## Problema
We need to solve X because the current implementation has performance issues.

## Approach A — Direct refactor
Trade-off: fast to implement but fragile under edge cases.

## Alternativa B — New service layer
Ventaja: Better scalability. Desventaja: More files.

## Opcion elegida
We chose Approach B because it scales better for future requirements
and allows proper unit testing without database dependencies.
This provides enough content to pass the 500 byte minimum threshold easily.

## Existing Functionality Inventory
| # | Element | Location | Description |
|---|---------|----------|-------------|
| 1 | SomeController | src/Controller/ | Handles X |

## Omission Decisions
| Element | Decision | Justification |
|---------|----------|---------------|
| SomeController | Transform | Refactor to use new service layer |
EOF
}

create_valid_plan() {
  cat > "$PLANS_DIR/${TODAY}-test-workflow.md" <<'EOF'
# Implementation Plan for Test Feature

## Task 1: Crear SomeController.php
### Step 1: Modificar File
- Archivo: SomeController.php
- Actualizar the controller to handle new endpoint

## Task 2: Crear unit tests
### Step 1: Write test file
- Archivo: SomeTest.php
- Verify endpoint returns correct response

This plan has enough content and structure to pass validation.
EOF
}

setup_full_flow_ready() {
  create_fresh_state
  set_flow "full-flow"
  set_evidence "decisions_read" "true"
  set_evidence "user_turns" "5"
  set_evidence "alternatives_proposed" "true"
  set_evidence "user_approved" "true"
  set_evidence "spec_path" "docs/superpowers/specs/test-workflow-spec.md"
  set_evidence "plan_path" "docs/superpowers/plans/${TODAY}-test-workflow.md"
  set_evidence "tests_written" "1"
  create_valid_spec
  create_valid_plan
}

# =====================================================
# TEST SUITE
# =====================================================

echo ""
echo "=== GATE 1: Flow type declaration ==="
echo ""

# No flow type → block src edits
create_fresh_state
expect_deny "1.1 No flow_type blocks src edits" "$SRC_FILE" "Declara el tipo de flujo"

# No flow type → allow doc edits
create_fresh_state
expect_allow "1.2 No flow_type allows doc edits" "$DOC_FILE"

# No state file → allow everything
rm -f "$STATE_FILE"
expect_allow "1.3 No state file allows edits" "$SRC_FILE"

echo ""
echo "=== GATE 2: Flow type bypasses ==="
echo ""

# micro-flow skips all validation
create_fresh_state
set_flow "micro-flow"
expect_allow "2.1 micro-flow skips validation" "$SRC_FILE"

# light-flow skips all validation
create_fresh_state
set_flow "light-flow"
expect_allow "2.2 light-flow skips validation" "$SRC_FILE"

# explore-flow skips all validation
create_fresh_state
set_flow "explore-flow"
expect_allow "2.3 explore-flow skips validation" "$SRC_FILE"

echo ""
echo "=== GATE 3: Deviation warning ==="
echo ""

create_fresh_state
set_flow "debug-flow"
jq '.deviation.active = true | .deviation.return_to_phase = "capture"' "$STATE_FILE" > "$STATE_FILE.tmp" && mv "$STATE_FILE.tmp" "$STATE_FILE"
expect_warn "3.1 Deviation active emits warning" "$SRC_FILE" "Deviation activa"

echo ""
echo "=== BRAINSTORM VALIDATOR ==="
echo ""

# Missing user_turns
create_fresh_state
set_flow "full-flow"
set_evidence "decisions_read" "true"
set_evidence "user_turns" "1"
set_evidence "alternatives_proposed" "true"
set_evidence "user_approved" "true"
create_valid_spec
set_evidence "spec_path" "docs/superpowers/specs/test-workflow-spec.md"
create_valid_plan
set_evidence "plan_path" "docs/superpowers/plans/${TODAY}-test-workflow.md"
set_evidence "tests_written" "1"
expect_deny "4.1 Brainstorm: insufficient turns" "$SRC_FILE" "turnos"

# Missing alternatives
create_fresh_state
set_flow "full-flow"
set_evidence "decisions_read" "true"
set_evidence "user_turns" "5"
set_evidence "alternatives_proposed" "false"
set_evidence "user_approved" "true"
create_valid_spec
set_evidence "spec_path" "docs/superpowers/specs/test-workflow-spec.md"
create_valid_plan
set_evidence "plan_path" "docs/superpowers/plans/${TODAY}-test-workflow.md"
set_evidence "tests_written" "1"
expect_deny "4.2 Brainstorm: no alternatives" "$SRC_FILE" "alternativas"

# Spec too small
setup_full_flow_ready
echo "tiny" > "$SPEC_DIR/test-workflow-spec.md"
expect_deny "4.3 Brainstorm: spec too small" "$SRC_FILE" "pequeno"

# All brainstorm checks pass
setup_full_flow_ready
expect_allow "4.4 Brainstorm: all checks pass" "$SRC_FILE"

echo ""
echo "=== PLANNING VALIDATOR ==="
echo ""

# No plan
create_fresh_state
set_flow "full-flow"
set_evidence "decisions_read" "true"
set_evidence "user_turns" "5"
set_evidence "alternatives_proposed" "true"
set_evidence "user_approved" "true"
create_valid_spec
set_evidence "spec_path" "docs/superpowers/specs/test-workflow-spec.md"
set_evidence "plan_path" ""
set_evidence "tests_written" "1"
expect_deny "5.1 Planning: no plan" "$SRC_FILE" "plan"

# Plan too small
setup_full_flow_ready
echo "tiny plan" > "$PLANS_DIR/${TODAY}-test-workflow.md"
expect_deny "5.2 Planning: plan too small" "$SRC_FILE" "pequeno"

echo ""
echo "=== IMPLEMENTATION VALIDATOR (TDD) ==="
echo ""

# No tests written → warning (soft gate), still allowed
setup_full_flow_ready
set_evidence "tests_written" "0"
expect_deny "6.1 TDD hard gate: blocks without tests" "$SRC_FILE" "TDD"

# Contradiction: tests_passed=true with tests_written=0
setup_full_flow_ready
set_evidence "tests_passed" "true"
set_evidence "tests_written" "0"
expect_deny "6.2 Contradiction: tests_passed=true but tests_written=0" "$SRC_FILE" "Contradiccion"

echo ""
echo "=== FULL FLOW END-TO-END ==="
echo ""

# Full flow with all evidence → allow
setup_full_flow_ready
expect_allow "7.1 Full flow: all gates pass" "$SRC_FILE"

# debug-flow skips brainstorm/planning validators
create_fresh_state
set_flow "debug-flow"
expect_allow "7.2 debug-flow: skips validators for src" "$SRC_FILE"

# "full" (without -flow suffix) also works — CLAUDE.md uses this form
echo ""
echo "=== FLOW_TYPE VALUE COMPATIBILITY ==="
echo ""

# "full" triggers same gates as "full-flow" — blocked at brainstorm first
create_fresh_state
set_flow "full"
set_evidence "decisions_read" "true"
expect_deny "7.3 'full' without -flow: blocks src without brainstorm" "$SRC_FILE" "Brainstorming"

# "full" with all evidence passes
setup_full_flow_ready
jq '.flow_type = "full"' "$STATE_FILE" > "$STATE_FILE.tmp" && mv "$STATE_FILE.tmp" "$STATE_FILE"
expect_allow "7.4 'full' without -flow: passes with all evidence" "$SRC_FILE"

# "micro" (without -flow) skips validation
create_fresh_state
set_flow "micro"
expect_allow "7.5 'micro' without -flow: skips validation" "$SRC_FILE"

# "debug" (without -flow) skips validation
create_fresh_state
set_flow "debug"
expect_allow "7.6 'debug' without -flow: skips validation" "$SRC_FILE"

echo ""
echo "=== SCOPE-CHANGE DETECTION ==="
echo ""

# Matching interaction_ids → allow
setup_full_flow_ready
jq '.interaction_id = 1 | .evidence.interaction_id = 1' "$STATE_FILE" > "$STATE_FILE.tmp" && mv "$STATE_FILE.tmp" "$STATE_FILE"
expect_allow "7.7 Matching interaction_ids: allow" "$SRC_FILE"

# Mismatched interaction_ids → block src edits
setup_full_flow_ready
jq '.interaction_id = 2 | .evidence.interaction_id = 1' "$STATE_FILE" > "$STATE_FILE.tmp" && mv "$STATE_FILE.tmp" "$STATE_FILE"
expect_deny "7.8 Mismatched interaction_ids: block src" "$SRC_FILE" "Scope change"

# Mismatched interaction_ids → allow doc edits (not gated)
setup_full_flow_ready
jq '.interaction_id = 2 | .evidence.interaction_id = 1' "$STATE_FILE" > "$STATE_FILE.tmp" && mv "$STATE_FILE.tmp" "$STATE_FILE"
expect_allow "7.9 Mismatched interaction_ids: allow docs" "$DOC_FILE"

echo ""
echo "=== WORKFLOW STATUS GENERATION ==="
echo ""

# Test workflow-status.sh generates output
setup_full_flow_ready
jq '.current_phase = "implementation"' "$STATE_FILE" > "$STATE_FILE.tmp" && mv "$STATE_FILE.tmp" "$STATE_FILE"
bash "$REPO/.claude/hooks/workflow-status.sh" 2>/dev/null
if [ -f "$REPO/.claude/workflow-status.md" ] && grep -q "implementation" "$REPO/.claude/workflow-status.md"; then
  echo "PASS: 8.1 workflow-status.sh generates valid output"
  PASS=$((PASS + 1))
else
  echo "FAIL: 8.1 workflow-status.sh did not generate expected output"
  FAIL=$((FAIL + 1))
fi
rm -f "$REPO/.claude/workflow-status.md"

echo ""
echo "=== SESSION-START ==="
echo ""

# session-start creates valid state
rm -f "$STATE_FILE"
bash "$REPO/.claude/hooks/session-start.sh" 2>/dev/null
if [ -f "$STATE_FILE" ] && jq 'has("evidence")' "$STATE_FILE" 2>/dev/null | grep -q "true"; then
  echo "PASS: 9.1 session-start creates new state model"
  PASS=$((PASS + 1))
else
  echo "FAIL: 9.1 session-start did not create expected state model"
  FAIL=$((FAIL + 1))
fi

# session-start preserves state on same day
jq '.flow_type = "full-flow"' "$STATE_FILE" > "$STATE_FILE.tmp" && mv "$STATE_FILE.tmp" "$STATE_FILE"
bash "$REPO/.claude/hooks/session-start.sh" 2>/dev/null
PRESERVED_FLOW=$(jq -r '.flow_type' "$STATE_FILE")
if [ "$PRESERVED_FLOW" = "full-flow" ]; then
  echo "PASS: 9.2 session-start preserves state on same day"
  PASS=$((PASS + 1))
else
  echo "FAIL: 9.2 session-start did not preserve state (got: $PRESERVED_FLOW)"
  FAIL=$((FAIL + 1))
fi
