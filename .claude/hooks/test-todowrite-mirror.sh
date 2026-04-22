#!/usr/bin/env bash
# Test suite for todowrite-mirror.sh:
#   - Single in_progress enforcement (exit 2 when >1)
#   - problems.current derivation from [prefix] of in_progress label
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "$(readlink -f "$0")")" && pwd)"
SCRIPT="$SCRIPT_DIR/todowrite-mirror.sh"

PASS=0
FAIL=0

assert() {
  local desc="$1" expected="$2" actual="$3"
  if [ "$expected" = "$actual" ]; then
    echo "  ✓ $desc"
    PASS=$((PASS+1))
  else
    echo "  ✗ $desc (expected='$expected' actual='$actual')"
    FAIL=$((FAIL+1))
  fi
}

# Helper: produce a minimal state file with given problems.labels
make_state() {
  local dest="$1"; shift
  local labels_json="$1"
  cat > "$dest" <<EOF
{
  "interaction_id": 1,
  "flow_type": "full",
  "current_phase": "implementation",
  "phase_history": [],
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
    "retrospective_shown": false,
    "root_cause_identified": false,
    "pattern_wide_search_done": false,
    "task_progress": {"current": 0, "total": 0, "label": null, "completed_labels": [], "task_index": []},
    "work_context": {"description": null, "problems": {"total": 2, "current": 0, "labels": $labels_json}, "wave": {"total": 0, "current": 0, "label": null, "labels": []}},
    "todo_progress": {"total": 0, "completed": 0, "in_progress_label": null, "items": []}
  },
  "deviation": {"active": false, "reason": null, "skipped_phases": [], "return_to_phase": null, "acknowledged_by_user": false}
}
EOF
}

# Override REPO path via environment so the hook points at our fixture state file.
# The hook reads STATE_FILE="$REPO/.claude/session-state.json", so we set up a
# fake REPO directory per test.
run_hook() {
  local input_json="$1"
  local state_file="$2"
  local tmp_repo
  tmp_repo=$(mktemp -d)
  mkdir -p "$tmp_repo/.claude"
  cp "$state_file" "$tmp_repo/.claude/session-state.json"
  # Inject tmp REPO by patching REPO=; use sed on a copy of the hook.
  local tmp_hook
  tmp_hook=$(mktemp)
  sed "s|^REPO=\".*\"|REPO=\"$tmp_repo\"|" "$SCRIPT" > "$tmp_hook"
  chmod +x "$tmp_hook"
  local output exit_code
  output=$(echo "$input_json" | "$tmp_hook" 2>&1) || exit_code=$?
  exit_code=${exit_code:-0}
  echo "$exit_code"
  cp "$tmp_repo/.claude/session-state.json" "$state_file.result"
  rm -rf "$tmp_repo" "$tmp_hook"
}

FIX=$(mktemp -d)
trap 'rm -rf "$FIX"' EXIT

echo "=== todowrite-mirror tests ==="

# T1: >1 in_progress → exit 2
make_state "$FIX/s1.json" '["Waves","Retro"]'
INPUT1='{"tool_input":{"todos":[
  {"content":"A","activeForm":"Aing","status":"in_progress"},
  {"content":"B","activeForm":"Bing","status":"in_progress"}
]}}'
RC=$(run_hook "$INPUT1" "$FIX/s1.json")
assert "T1: 2 in_progress → exit 2" "2" "$RC"

# T2: single in_progress with matching [Retro] prefix → problems.current=2
make_state "$FIX/s2.json" '["Terminar waves pendientes","Planificar mejoras retro"]'
INPUT2='{"tool_input":{"todos":[
  {"content":"Done task","activeForm":"Done","status":"completed"},
  {"content":"[Retro] Planificando","activeForm":"[Retro] Planificando","status":"in_progress"}
]}}'
RC=$(run_hook "$INPUT2" "$FIX/s2.json")
assert "T2: single in_progress → exit 0" "0" "$RC"
CUR=$(jq -r '.evidence.work_context.problems.current' "$FIX/s2.json.result")
assert "T2: problems.current=2 after [Retro] match" "2" "$CUR"

# T3: single in_progress with matching [Waves] prefix → problems.current=1
make_state "$FIX/s3.json" '["Terminar waves pendientes","Planificar mejoras retro"]'
INPUT3='{"tool_input":{"todos":[
  {"content":"[Waves] Escribiendo","activeForm":"[Waves] Escribiendo","status":"in_progress"}
]}}'
RC=$(run_hook "$INPUT3" "$FIX/s3.json")
assert "T3: single in_progress → exit 0" "0" "$RC"
CUR=$(jq -r '.evidence.work_context.problems.current' "$FIX/s3.json.result")
assert "T3: problems.current=1 after [Waves] match" "1" "$CUR"

# T4: single in_progress with non-matching prefix → current unchanged
make_state "$FIX/s4.json" '["Waves","Retro"]'
# Pre-set current=2 to verify it's not reset on non-match
jq '.evidence.work_context.problems.current = 2' "$FIX/s4.json" > "$FIX/s4.json.tmp" && mv "$FIX/s4.json.tmp" "$FIX/s4.json"
INPUT4='{"tool_input":{"todos":[
  {"content":"[NoMatch] Hello","activeForm":"[NoMatch] Hello","status":"in_progress"}
]}}'
RC=$(run_hook "$INPUT4" "$FIX/s4.json")
assert "T4: non-matching prefix → exit 0" "0" "$RC"
CUR=$(jq -r '.evidence.work_context.problems.current' "$FIX/s4.json.result")
assert "T4: problems.current unchanged (2) on non-match" "2" "$CUR"

# T5: in_progress without [prefix] → current unchanged
make_state "$FIX/s5.json" '["Waves","Retro"]'
jq '.evidence.work_context.problems.current = 1' "$FIX/s5.json" > "$FIX/s5.json.tmp" && mv "$FIX/s5.json.tmp" "$FIX/s5.json"
INPUT5='{"tool_input":{"todos":[
  {"content":"Plain task no prefix","activeForm":"Plain task no prefix","status":"in_progress"}
]}}'
RC=$(run_hook "$INPUT5" "$FIX/s5.json")
assert "T5: no [prefix] → exit 0" "0" "$RC"
CUR=$(jq -r '.evidence.work_context.problems.current' "$FIX/s5.json.result")
assert "T5: problems.current unchanged (1) when no bracket prefix" "1" "$CUR"

# T6: zero in_progress (all completed) → exit 0, no change
make_state "$FIX/s6.json" '["Waves","Retro"]'
INPUT6='{"tool_input":{"todos":[
  {"content":"A","activeForm":"A","status":"completed"},
  {"content":"B","activeForm":"B","status":"completed"}
]}}'
RC=$(run_hook "$INPUT6" "$FIX/s6.json")
assert "T6: 0 in_progress → exit 0" "0" "$RC"

# T7: todo_progress mirror is still populated (regression guard)
TOTAL=$(jq -r '.evidence.todo_progress.total' "$FIX/s6.json.result")
DONE=$(jq -r '.evidence.todo_progress.completed' "$FIX/s6.json.result")
assert "T7: todo_progress.total=2" "2" "$TOTAL"
assert "T7: todo_progress.completed=2" "2" "$DONE"

# T8: case-insensitive substring match (prefix [retro] lowercase → matches label)
make_state "$FIX/s8.json" '["Waves","Retro Improvements"]'
INPUT8='{"tool_input":{"todos":[
  {"content":"[retro] lowercase","activeForm":"[retro] lowercase","status":"in_progress"}
]}}'
RC=$(run_hook "$INPUT8" "$FIX/s8.json")
assert "T8: case-insensitive match → exit 0" "0" "$RC"
CUR=$(jq -r '.evidence.work_context.problems.current' "$FIX/s8.json.result")
assert "T8: problems.current=2 (case-insensitive)" "2" "$CUR"

echo ""
echo "── Summary ──"
echo "PASS: $PASS"
echo "FAIL: $FAIL"
[ "$FAIL" -eq 0 ] && exit 0 || exit 1
