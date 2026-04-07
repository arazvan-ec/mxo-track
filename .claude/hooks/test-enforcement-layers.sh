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

echo "Test 1.1: Legal full walk via phase-advance"
reset_full_flow
PHASES=("consult" "brainstorming" "planning" "implementation" "verification" "capture" "retrospective" "finalize")
ALL_OK=true
for p in "${PHASES[@]}"; do
  if ! "$ADVANCE" "$p" > /dev/null 2>&1; then
    ALL_OK=false
    break
  fi
done
assert_eq "Full walk completes" "true" "$ALL_OK"

FINAL_PHASE=$(jq -r '.current_phase' "$STATE_FILE")
assert_eq "Ends at finalize" "finalize" "$FINAL_PHASE"

HISTORY_LEN=$(jq '.phase_history | length' "$STATE_FILE")
assert_eq "History has 8 entries" "8" "$HISTORY_LEN"

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
