#!/usr/bin/env bash
# Test: phase-advance.sh — entry from null phase for each flow_type
#
# Exercises the null→first-phase transition to verify that each flow's first
# legal phase is accepted (and non-first phases rejected).

set -euo pipefail

REPO="/home/user/mxo-track"
SCRIPT="$REPO/.claude/hooks/phase-advance.sh"
TMPDIR=$(mktemp -d)
trap 'rm -rf "$TMPDIR"' EXIT

PASS=0
FAIL=0

# Helper: run phase-advance in a fresh temp state file.
# Args: $1=flow_type, $2=next_phase
# Writes state to $TMPDIR/state.json, returns script exit code, echoes
# "<exit>|<resulting_current_phase>" on stdout.
run_advance() {
  local flow="$1"
  local next="$2"
  local state_file="$TMPDIR/state.json"
  cat > "$state_file" <<EOF
{
  "flow_type": "$flow",
  "current_phase": null,
  "phase_history": []
}
EOF
  local exit_code=0
  STATE_FILE="$state_file" bash "$SCRIPT" "$next" >/dev/null 2>&1 || exit_code=$?
  local after
  after=$(jq -r '.current_phase // "null"' "$state_file" 2>/dev/null || echo "null")
  echo "$exit_code|$after"
}

assert_entry() {
  local name="$1"
  local flow="$2"
  local next="$3"
  local expect_exit="$4"       # 0 or 1
  local expect_phase="$5"      # resulting current_phase (or "null" if unchanged)
  local result; result=$(run_advance "$flow" "$next")
  local actual_exit="${result%%|*}"
  local actual_phase="${result##*|}"
  if [ "$actual_exit" = "$expect_exit" ] && [ "$actual_phase" = "$expect_phase" ]; then
    echo "  ✅ $name"
    PASS=$((PASS + 1))
  else
    echo "  ❌ $name"
    echo "     expected: exit=$expect_exit phase=$expect_phase"
    echo "     actual:   exit=$actual_exit phase=$actual_phase"
    FAIL=$((FAIL + 1))
  fi
}

echo "── phase-advance entry transitions ──"
assert_entry "full baseline: null → consult accepted"            "full"  "consult"        "0" "consult"
assert_entry "full rejects: null → root_cause denied"            "full"  "root_cause"     "1" "null"
assert_entry "debug fix: null → root_cause accepted"             "debug" "root_cause"     "0" "root_cause"
assert_entry "debug rejects: null → consult denied"              "debug" "consult"        "1" "null"
assert_entry "agent fix: null → implementation accepted"         "agent" "implementation" "0" "implementation"

echo
echo "── Results ──"
echo "  Passed: $PASS"
echo "  Failed: $FAIL"
[ "$FAIL" -eq 0 ]
