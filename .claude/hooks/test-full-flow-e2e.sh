#!/usr/bin/env bash
# End-to-end test: complete workflow flow consult → finalize
# Tests that phase-advance.sh + autodiscovery validators work together
# for a full 8-phase walk with real artifacts and evidence.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ADVANCE="$SCRIPT_DIR/phase-advance.sh"
REPO="/home/user/mxo-track"
STATE_FILE="$REPO/.claude/session-state.json"
BACKUP="/tmp/test-e2e-backup.json"

# Temp artifacts
TEST_SPEC="/tmp/test-e2e-spec.md"
TEST_PLAN="/tmp/test-e2e-plan.md"
TEST_LOG="/tmp/test-e2e-log.md"

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

assert_eq() {
  local desc="$1" expected="$2" actual="$3"
  if [ "$expected" = "$actual" ]; then
    PASS=$((PASS + 1))
    echo "  ✅ $desc"
  else
    FAIL=$((FAIL + 1))
    echo "  ❌ $desc (expected '$expected', got '$actual')"
  fi
}

cleanup() {
  cp "$BACKUP" "$STATE_FILE"
  rm -f "$BACKUP" "$TEST_SPEC" "$TEST_PLAN" "$TEST_LOG"
}
trap cleanup EXIT

reset_state() {
  jq '.flow_type = "full" | .current_phase = null | .phase_history = [] | .evidence = {}' \
    "$BACKUP" > "$STATE_FILE"
}

# ── Create temporary artifacts ──

cat > "$TEST_SPEC" << 'SPECEOF'
# Test Spec — E2E Full Flow

## Problema
We need to implement a new feature for the logistics tracking portal.
This is a test spec created for the end-to-end workflow validation.
The feature involves adding a new endpoint for route optimization data.

## Alternativa A — Direct Integration
Directly integrate with the optimization engine via synchronous API calls.
Trade-off: simpler but slower under load.

## Alternativa B — Event-Driven
Use Mercure events to push optimization results asynchronously.
Trade-off: more complex but scales better.

## Recommendation
Alternativa B is recommended for production workloads due to scalability.

## Existing Functionality Inventory
| Element | Decision | Justification |
|---------|----------|---------------|
| RouteOptimizer service | Keep | Core service, extend don't replace |
| MercurePublisher | Include | Reuse for event dispatch |
| OptimizationController | Transform | Add new endpoint |

## Omission Decisions
| Element | Decision | Justification |
|---------|----------|---------------|
| LegacyRouteCalc | Omit | Deprecated, replaced by RouteOptimizer |

## Architecture
The new endpoint will accept route parameters, dispatch an async optimization
job via Messenger, and publish results via Mercure when complete.
SPECEOF

cat > "$TEST_PLAN" << 'PLANEOF'
# Implementation Plan — E2E Full Flow

**Spec:** specs/test-e2e-spec.md
**Branch:** test-e2e-branch

## Phase 1 (v0)

### Task 1 — Create OptimizationRequest DTO
- Create a new DTO class for optimization request parameters
- Add validation constraints for required fields
- Write unit test for DTO validation

### Task 2 — Add async optimization handler
- Create Messenger handler for optimization jobs
- Implement basic optimization logic using RouteOptimizer service
- Write integration test for the handler

### Task 3 — Add API endpoint
- Create new controller action for /api/optimize
- Wire up request validation and Messenger dispatch
- Write functional test for the endpoint

### Task 4 — Mercure integration
- Publish optimization results via MercurePublisher
- Add SSE subscription topic for optimization updates
- Write test for Mercure event publishing

## Phase 2 (Mature): Refactor handler for batch optimization
PLANEOF

cat > "$TEST_LOG" << 'LOGEOF'
# Execution Log — E2E Full Flow Test

## Implementation
- Task 1: Created OptimizationRequest DTO with validation (3 files)
- Task 2: Added async handler with RouteOptimizer integration (2 files)
- Task 3: Created /api/optimize endpoint with tests (4 files)
- Task 4: Integrated Mercure publishing for results (2 files)

## Verification
- PHPUnit: 612 tests, 0 failures
- Lint: clean
- TypeScript: no errors

## Lessons

- The RouteOptimizer service API was not well documented internally, which caused
  an initial misunderstanding about the expected input format. We should add PHPDoc
  blocks to all public methods in core services.
- Messenger async handling required careful configuration of the transport to avoid
  duplicate processing. The retry strategy needs explicit configuration per handler.
- Mercure topic naming should follow a consistent convention across the codebase.
  Currently some topics use camelCase and others use snake_case. We standardized on
  snake_case for this feature but the legacy topics remain inconsistent.
LOGEOF

echo "=== Test: Full Flow E2E (consult → finalize) ==="

# ── Test 1: Transitions fail WITHOUT evidence ──

echo ""
echo "── Phase 1: Transitions blocked without evidence ──"

# consult → brainstorming without decisions_read
reset_state
"$ADVANCE" consult > /dev/null 2>&1
echo "Test 1a: consult → brainstorming blocked without decisions_read"
assert_fail "blocks without decisions_read" "$ADVANCE" "brainstorming"

# brainstorming → planning without spec/approval
reset_state
jq '.current_phase = "consult" | .evidence.decisions_read = true' "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
"$ADVANCE" brainstorming > /dev/null 2>&1
echo "Test 1b: brainstorming → planning blocked without spec and approval"
assert_fail "blocks without spec and approval" "$ADVANCE" "planning"

# planning → implementation blocked without plan
reset_state
jq '.current_phase = "planning" | .phase_history = [{"phase":"consult","at":"t"},{"phase":"brainstorming","at":"t"},{"phase":"planning","at":"t"}]' "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
echo "Test 1c: planning → implementation blocked without plan"
assert_fail "blocks without plan file" "$ADVANCE" "implementation"

# verification → capture blocked without tests_passed/lint_clean
reset_state
jq '.current_phase = "verification" | .phase_history = [{"phase":"consult","at":"t"},{"phase":"brainstorming","at":"t"},{"phase":"planning","at":"t"},{"phase":"implementation","at":"t"},{"phase":"verification","at":"t"}]' "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
echo "Test 1d: verification → capture blocked without tests_passed"
assert_fail "blocks without tests_passed and lint_clean" "$ADVANCE" "capture"

# retrospective → finalize blocked without lessons in log
BARE_LOG="/tmp/test-e2e-bare-log.md"
echo -e "# Log\n## Implementation\nDid stuff." > "$BARE_LOG"
reset_state
jq --arg lp "$BARE_LOG" '.current_phase = "retrospective" | .evidence.execution_log_path = $lp | .phase_history = [{"phase":"consult","at":"t"},{"phase":"brainstorming","at":"t"},{"phase":"planning","at":"t"},{"phase":"implementation","at":"t"},{"phase":"verification","at":"t"},{"phase":"capture","at":"t"},{"phase":"retrospective","at":"t"}]' "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
echo "Test 1e: retrospective → finalize blocked without lessons section"
assert_fail "blocks without lessons in execution log" "$ADVANCE" "finalize"
rm -f "$BARE_LOG"

# ── Test 2: Complete walk WITH proper evidence ──

echo ""
echo "── Phase 2: Complete walk with evidence at each gate ──"

reset_state
ALL_PASS=true

# Phase 1: null → consult
echo "Step 1/8: null → consult"
if "$ADVANCE" consult > /dev/null 2>&1; then
  PASS=$((PASS + 1))
  echo "  ✅ advanced to consult"
else
  FAIL=$((FAIL + 1))
  echo "  ❌ failed to advance to consult"
  ALL_PASS=false
fi

# Phase 2: consult → brainstorming (needs decisions_read)
echo "Step 2/8: consult → brainstorming"
jq '.evidence.decisions_read = true' "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
if "$ADVANCE" brainstorming > /dev/null 2>&1; then
  PASS=$((PASS + 1))
  echo "  ✅ advanced to brainstorming"
else
  FAIL=$((FAIL + 1))
  echo "  ❌ failed to advance to brainstorming"
  ALL_PASS=false
fi

# Phase 3: brainstorming → planning (needs user_turns, alternatives, approval, spec)
echo "Step 3/8: brainstorming → planning"
jq --arg sp "$TEST_SPEC" '.evidence += {
  "user_turns": 3,
  "alternatives_proposed": true,
  "user_approved": true,
  "spec_path": $sp
}' "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
if "$ADVANCE" planning > /dev/null 2>&1; then
  PASS=$((PASS + 1))
  echo "  ✅ advanced to planning"
else
  FAIL=$((FAIL + 1))
  echo "  ❌ failed to advance to planning"
  ALL_PASS=false
fi

# Phase 4: planning → implementation (needs plan file)
echo "Step 4/8: planning → implementation"
jq --arg pp "$TEST_PLAN" '.evidence.plan_path = $pp' "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
if "$ADVANCE" implementation > /dev/null 2>&1; then
  PASS=$((PASS + 1))
  echo "  ✅ advanced to implementation"
else
  FAIL=$((FAIL + 1))
  echo "  ❌ failed to advance to implementation"
  ALL_PASS=false
fi

# Phase 5: implementation → verification (plan must exist — already set)
echo "Step 5/8: implementation → verification"
if "$ADVANCE" verification > /dev/null 2>&1; then
  PASS=$((PASS + 1))
  echo "  ✅ advanced to verification"
else
  FAIL=$((FAIL + 1))
  echo "  ❌ failed to advance to verification"
  ALL_PASS=false
fi

# Phase 6: verification → capture (needs tests_passed + lint_clean)
echo "Step 6/8: verification → capture"
jq '.evidence.tests_passed = true | .evidence.lint_clean = true' "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
if "$ADVANCE" capture > /dev/null 2>&1; then
  PASS=$((PASS + 1))
  echo "  ✅ advanced to capture"
else
  FAIL=$((FAIL + 1))
  echo "  ❌ failed to advance to capture"
  ALL_PASS=false
fi

# Phase 7: capture → retrospective (needs execution_log_path)
echo "Step 7/8: capture → retrospective"
jq --arg lp "$TEST_LOG" '.evidence.execution_log_path = $lp' "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
if "$ADVANCE" retrospective > /dev/null 2>&1; then
  PASS=$((PASS + 1))
  echo "  ✅ advanced to retrospective"
else
  FAIL=$((FAIL + 1))
  echo "  ❌ failed to advance to retrospective"
  ALL_PASS=false
fi

# Phase 8: retrospective → finalize (needs lessons section in log)
echo "Step 8/8: retrospective → finalize"
if "$ADVANCE" finalize > /dev/null 2>&1; then
  PASS=$((PASS + 1))
  echo "  ✅ advanced to finalize"
else
  FAIL=$((FAIL + 1))
  echo "  ❌ failed to advance to finalize"
  ALL_PASS=false
fi

# ── Test 3: Verify final state ──

echo ""
echo "── Phase 3: Verify final state ──"

# current_phase should be finalize
FINAL_PHASE=$(jq -r '.current_phase' "$STATE_FILE")
echo "Test 3a: current_phase = finalize"
assert_eq "current_phase is finalize" "finalize" "$FINAL_PHASE"

# phase_history should have 8 entries
HISTORY_LEN=$(jq '.phase_history | length' "$STATE_FILE")
echo "Test 3b: phase_history has 8 entries"
assert_eq "phase_history length is 8" "8" "$HISTORY_LEN"

# Verify phase_history contains correct phases in order
echo "Test 3c: phase_history phases are in correct order"
EXPECTED_HISTORY='["null","consult","brainstorming","planning","implementation","verification","capture","retrospective"]'
ACTUAL_HISTORY=$(jq -c '[.phase_history[].phase]' "$STATE_FILE")
assert_eq "phase_history order is correct" "$EXPECTED_HISTORY" "$ACTUAL_HISTORY"

# Verify timestamps exist on all entries
echo "Test 3d: all phase_history entries have timestamps"
NULL_TIMESTAMPS=$(jq '[.phase_history[].at] | map(select(. == null or . == "")) | length' "$STATE_FILE")
assert_eq "no null timestamps" "0" "$NULL_TIMESTAMPS"

# ── Test 4: Verify artifacts have correct content ──

echo ""
echo "── Phase 4: Verify artifact content requirements ──"

# Spec is ≥500 bytes
SPEC_SIZE=$(wc -c < "$TEST_SPEC")
echo "Test 4a: spec is ≥500 bytes (actual: $SPEC_SIZE)"
if [ "$SPEC_SIZE" -ge 500 ]; then
  PASS=$((PASS + 1))
  echo "  ✅ spec size OK"
else
  FAIL=$((FAIL + 1))
  echo "  ❌ spec too small"
fi

# Spec contains required keywords
echo "Test 4b: spec contains 'Alternativa'"
if grep -q "Alternativa" "$TEST_SPEC"; then
  PASS=$((PASS + 1))
  echo "  ✅ spec has Alternativa"
else
  FAIL=$((FAIL + 1))
  echo "  ❌ spec missing Alternativa"
fi

echo "Test 4c: spec contains '## Existing Functionality Inventory'"
if grep -q "## Existing Functionality Inventory" "$TEST_SPEC"; then
  PASS=$((PASS + 1))
  echo "  ✅ spec has Existing Functionality Inventory"
else
  FAIL=$((FAIL + 1))
  echo "  ❌ spec missing Existing Functionality Inventory"
fi

echo "Test 4d: spec contains '## Omission Decisions'"
if grep -q "## Omission Decisions" "$TEST_SPEC"; then
  PASS=$((PASS + 1))
  echo "  ✅ spec has Omission Decisions"
else
  FAIL=$((FAIL + 1))
  echo "  ❌ spec missing Omission Decisions"
fi

# Plan is ≥300 bytes
PLAN_SIZE=$(wc -c < "$TEST_PLAN")
echo "Test 4e: plan is ≥300 bytes (actual: $PLAN_SIZE)"
if [ "$PLAN_SIZE" -ge 300 ]; then
  PASS=$((PASS + 1))
  echo "  ✅ plan size OK"
else
  FAIL=$((FAIL + 1))
  echo "  ❌ plan too small"
fi

# Plan contains "Task"
echo "Test 4f: plan contains 'Task'"
if grep -q "Task" "$TEST_PLAN"; then
  PASS=$((PASS + 1))
  echo "  ✅ plan has Task"
else
  FAIL=$((FAIL + 1))
  echo "  ❌ plan missing Task"
fi

# Execution log contains "## Lessons" with ≥100 chars
echo "Test 4g: execution log has '## Lessons' with ≥100 chars"
LESSONS_CONTENT=$(sed -n '/^## Lessons/,/^## /p' "$TEST_LOG" | tail -n +2 | head -n -1)
LESSONS_SIZE=${#LESSONS_CONTENT}
if [ "$LESSONS_SIZE" -ge 100 ]; then
  PASS=$((PASS + 1))
  echo "  ✅ lessons section has $LESSONS_SIZE chars"
else
  FAIL=$((FAIL + 1))
  echo "  ❌ lessons section too short ($LESSONS_SIZE chars, need ≥100)"
fi

# ── Summary ──

echo ""
echo "=== Results: $PASS passed, $FAIL failed ==="
[ "$FAIL" -eq 0 ] && exit 0 || exit 1
