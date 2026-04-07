#!/usr/bin/env bash
# Integration tests for the 5 enforcement layers
set -euo pipefail

REPO="/home/user/mxo-track"
STATE_FILE="$REPO/.claude/session-state.json"
ADVANCE="$REPO/.claude/hooks/phase-advance.sh"
CONTROLLER="$REPO/.claude/hooks/phase-transition-controller.sh"
SNAPSHOT="/tmp/ptc-state-snapshot.json"
BACKUP="/tmp/test-el-backup.json"
BACKUP_SNAPSHOT="/tmp/test-el-snapshot-backup.json"

# Save originals
cp "$STATE_FILE" "$BACKUP"
[ -f "$SNAPSHOT" ] && cp "$SNAPSHOT" "$BACKUP_SNAPSHOT" || true

PASS=0
FAIL=0

assert_eq() {
  local desc="$1" expected="$2" actual="$3"
  if [ "$expected" = "$actual" ]; then
    PASS=$((PASS + 1))
    echo "  ✅ $desc"
  else
    FAIL=$((FAIL + 1))
    echo "  ❌ $desc (expected: $expected, got: $actual)"
  fi
}

reset_full_flow() {
  cat > "$STATE_FILE" << 'HEREDOC'
{
  "flow_type": "full",
  "current_phase": null,
  "phase_history": [],
  "interaction_id": 1,
  "evidence": {
    "interaction_id": 1,
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
    "branch_strategy": null,
    "root_cause_identified": false,
    "pattern_wide_search_done": false,
    "task_progress": {"current": 0, "total": 0, "label": null, "completed_labels": []}
  },
  "deviation": {"active": false}
}
HEREDOC
  cp "$STATE_FILE" "$SNAPSHOT"
}

run_controller_with() {
  local cmd="$1"
  jq -n --arg cmd "$cmd" '{"tool_name": "Bash", "tool_input": {"command": $cmd}}' | "$CONTROLLER" 2>/dev/null || true
}

echo "═══════════════════════════════════════════"
echo "Integration Tests — 5 Enforcement Layers"
echo "═══════════════════════════════════════════"

# ── LAYER 1: Phase Transition Controller ──
echo ""
echo "── Layer 1: Phase Transition Controller ──"

echo "Test 1.1: Legal full walk via phase-advance (with artifacts)"
reset_full_flow

# Create artifacts needed by validators
TEST_SPEC="/tmp/test-el-spec.md"
TEST_PLAN="/tmp/test-el-plan.md"
TEST_LOG="/tmp/test-el-log.md"

cat > "$TEST_SPEC" << 'SPECEOF'
# Spec
## Problem
Description of the problem that needs solving in this test scenario.
## Alternativa A
First option with trade-offs.
## Alternativa B
Second option with different trade-offs and analysis.
## Existing Functionality Inventory
| Element | Decision |
|---------|----------|
| Foo     | Keep     |
## Omission Decisions
| Element | Decision |
|---------|----------|
| Bar     | Not touched |
## Architecture
Architecture description padding to reach 500 bytes minimum for the validator.
More content for padding purposes to ensure the size check passes correctly in tests.
SPECEOF

cat > "$TEST_PLAN" << 'PLANEOF'
# Plan — Test Plan for Enforcement Layers

**Spec:** specs/test-spec.md
**Branch:** test-branch

## Phase 1 (v0)

### Task 1 — Crear the new component
- Crear a new file with the required content and structure
- Modificar the existing configuration to support the new feature properly
- Actualizar existing tests to cover the new behavior and edge cases
- Verify TypeScript compiles cleanly after changes

### Task 2 — Verificar integration
- Run all tests and verify they pass without regressions
- Check lint rules are satisfied across all modified files
- Commit after all verification steps pass successfully

## Phase 2 (Mature): N/A
PLANEOF

cat > "$TEST_LOG" << 'LOGEOF'
# Execution Log
## Implementation
Did some stuff.
## Lessons
- First lesson about the implementation that was educational and informative for the team overall
- Second lesson about workflow that revealed gaps worth documenting for future reference and improvement
- Third lesson about testing patterns discovered during this implementation cycle that we should remember
LOGEOF

PHASES=("consult" "brainstorming" "planning" "implementation" "verification" "capture" "retrospective" "finalize")
ALL_OK=true
for p in "${PHASES[@]}"; do
  case "$p" in
    planning) jq --arg sp "$TEST_SPEC" '.evidence.user_turns = 3 | .evidence.alternatives_proposed = true | .evidence.user_approved = true | .evidence.spec_path = $sp' "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE" ;;
    implementation) jq --arg pp "$TEST_PLAN" '.evidence.plan_path = $pp' "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE" ;;
    finalize) jq --arg lp "$TEST_LOG" '.evidence.execution_log_path = $lp' "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE" ;;
  esac
  if ! "$ADVANCE" "$p" > /dev/null 2>&1; then
    echo "  ❌ Failed at $p"
    ALL_OK=false
    break
  fi
done
assert_eq "Full walk completes" "true" "$ALL_OK"

FINAL_PHASE=$(jq -r '.current_phase' "$STATE_FILE")
assert_eq "Ends at finalize" "finalize" "$FINAL_PHASE"

HISTORY_LEN=$(jq '.phase_history | length' "$STATE_FILE")
assert_eq "History has 8 entries" "8" "$HISTORY_LEN"

rm -f "$TEST_SPEC" "$TEST_PLAN" "$TEST_LOG"

echo ""
echo "Test 1.2: Fabricated phase_history is reverted"
reset_full_flow
"$ADVANCE" consult > /dev/null 2>&1
cp "$STATE_FILE" "$SNAPSHOT"
# Fabricate history
jq '.phase_history = ["consult","brainstorming","planning","implementation","verification","capture","retrospective","finalize"]' \
  "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
run_controller_with "jq '.phase_history = [...]' .claude/session-state.json"
REVERTED_LEN=$(jq '.phase_history | length' "$STATE_FILE")
assert_eq "Fabricated history reverted to 1" "1" "$REVERTED_LEN"

echo ""
echo "Test 1.3: Skip phase is rejected"
reset_full_flow
"$ADVANCE" consult > /dev/null 2>&1
RESULT=$("$ADVANCE" planning 2>&1 || true)
assert_eq "Skip rejected" "true" "$(echo "$RESULT" | grep -q 'Cannot skip' && echo true || echo false)"

# ── LAYER 2: Prerequisite Gates ──
echo ""
echo "── Layer 2: Prerequisite Gates (validators) ──"

echo "Test 2.1: Consult validator blocks without evidence"
reset_full_flow
set +e
"$REPO/.claude/hooks/validators/consult-validator.sh" "$STATE_FILE" > /dev/null 2>&1
EXIT_CODE=$?
set -e
assert_eq "Consult blocks (exit 2)" "2" "$EXIT_CODE"

echo "Test 2.2: Consult validator passes with evidence"
jq '.evidence.decisions_read = true' "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
set +e
"$REPO/.claude/hooks/validators/consult-validator.sh" "$STATE_FILE" > /dev/null 2>&1
EXIT_CODE=$?
set -e
assert_eq "Consult passes (exit 0)" "0" "$EXIT_CODE"

echo "Test 2.3: Brainstorm validator blocks without approval"
reset_full_flow
jq '.evidence.decisions_read = true | .evidence.user_turns = 2 | .evidence.alternatives_proposed = true' \
  "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
set +e
"$REPO/.claude/hooks/validators/brainstorm-validator.sh" "$STATE_FILE" > /dev/null 2>&1
EXIT_CODE=$?
set -e
assert_eq "Brainstorm blocks without approval (exit 2)" "2" "$EXIT_CODE"

echo "Test 2.4: Planning validator blocks without plan file"
reset_full_flow
jq '.evidence.plan_path = "nonexistent.md"' "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
set +e
"$REPO/.claude/hooks/validators/planning-validator.sh" "$STATE_FILE" > /dev/null 2>&1
EXIT_CODE=$?
set -e
assert_eq "Planning blocks without plan (exit 2)" "2" "$EXIT_CODE"

# ── LAYER 3: User Approval ──
echo ""
echo "── Layer 3: User Approval Detection ──"

echo "Test 3.1: Direct user_approved write is reverted"
reset_full_flow
"$ADVANCE" consult > /dev/null 2>&1
cp "$STATE_FILE" "$SNAPSHOT"
jq '.evidence.user_approved = true' "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
run_controller_with "jq '.evidence.user_approved = true' .claude/session-state.json"
APPROVED=$(jq -r '.evidence.user_approved' "$STATE_FILE")
assert_eq "Direct user_approved reverted" "false" "$APPROVED"

# ── LAYER 5: Cross-validation ──
echo ""
echo "── Layer 5: Cross-validation (pre-push) ──"

echo "Test 5.1: String-format phase_history detected"
reset_full_flow
jq '.phase_history = ["consult", "brainstorming"]' "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
HISTORY_FORMAT=$(jq '[.phase_history // [] | .[] | select(type == "string")] | length' "$STATE_FILE")
assert_eq "Detects string-format entries" "2" "$HISTORY_FORMAT"

echo "Test 5.2: Timestamp-format passes"
reset_full_flow
"$ADVANCE" consult > /dev/null 2>&1
HISTORY_FORMAT=$(jq '[.phase_history // [] | .[] | select(type == "string")] | length' "$STATE_FILE")
assert_eq "Timestamp format has 0 string entries" "0" "$HISTORY_FORMAT"

# ── LAYER 2 (continued): New validator scenarios ──
echo ""
echo "── Layer 2 (continued): New validator scenarios ──"

echo "Test 2.5: Brainstorm blocks when spec_path set but file missing"
reset_full_flow
jq '.evidence.user_turns = 3 | .evidence.alternatives_proposed = true | .evidence.user_approved = true | .evidence.spec_path = "/tmp/nonexistent-spec.md"' \
  "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
set +e
"$REPO/.claude/hooks/validators/brainstorm-validator.sh" "$STATE_FILE" > /dev/null 2>&1
EXIT_CODE=$?
set -e
assert_eq "Brainstorm blocks with missing spec file (exit 2)" "2" "$EXIT_CODE"

echo "Test 2.6: Planning validator accepts 'Tarea' keyword"
TEST_TAREA_PLAN="/tmp/test-el-tarea-plan.md"
cat > "$TEST_TAREA_PLAN" << 'PLANEOF'
# Plan de implementacion

## Fase 1

### Tarea 1 — Implementar el widget
- Desarrollar el componente React
- Conectar con el backend API
- Agregar tests unitarios

### Tarea 2 — Verificar integracion
- Probar flujo completo
- Revisar estilos responsive
This plan needs 300 bytes so padding it here to reach the minimum.
PLANEOF
jq --arg pp "$TEST_TAREA_PLAN" '.evidence.plan_path = $pp' "$STATE_FILE" > /tmp/t.json && mv /tmp/t.json "$STATE_FILE"
set +e
"$REPO/.claude/hooks/validators/planning-validator.sh" "$STATE_FILE" > /dev/null 2>&1
EXIT_CODE=$?
set -e
assert_eq "Planning accepts Tarea keyword (exit 0)" "0" "$EXIT_CODE"
rm -f "$TEST_TAREA_PLAN"

echo "Test 2.7: Retrospective validator blocks without lessons"
TEST_NOLESSON_LOG="/tmp/test-el-nolesson.md"
echo -e "# Log\n## Implementation\nDid stuff." > "$TEST_NOLESSON_LOG"
cat > /tmp/test-el-retro-state.json << STATEEOF
{"evidence":{"execution_log_path":"$TEST_NOLESSON_LOG"}}
STATEEOF
set +e
"$REPO/.claude/hooks/validators/retrospective-validator.sh" /tmp/test-el-retro-state.json > /dev/null 2>&1
EXIT_CODE=$?
set -e
assert_eq "Retrospective blocks without lessons (exit 2)" "2" "$EXIT_CODE"
rm -f "$TEST_NOLESSON_LOG" /tmp/test-el-retro-state.json

# ── Summary ──
echo ""

# Restore originals
cp "$BACKUP" "$STATE_FILE"
[ -f "$BACKUP_SNAPSHOT" ] && cp "$BACKUP_SNAPSHOT" "$SNAPSHOT" || rm -f "$SNAPSHOT"
rm -f "$BACKUP" "$BACKUP_SNAPSHOT"

echo "═══════════════════════════════════════════"
echo "Results: $PASS passed, $FAIL failed"
echo "═══════════════════════════════════════════"
[ "$FAIL" -eq 0 ] && exit 0 || exit 1
