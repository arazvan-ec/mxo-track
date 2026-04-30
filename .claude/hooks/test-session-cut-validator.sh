#!/usr/bin/env bash
# test-session-cut-validator.sh — smoke for session-cut-validator (B3).

set -uo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
VALIDATOR="$REPO_ROOT/.claude/hooks/validators/session-cut-validator.sh"

pass=0; fail=0
assert_rc() {
  local label="$1" actual="$2" expected="$3"
  if [ "$actual" = "$expected" ]; then
    echo "  ✅ $label"; pass=$((pass+1))
  else
    echo "  ❌ $label (got rc=$actual, expected rc=$expected)"; fail=$((fail+1))
  fi
}

TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT
TODAY=$(date +%Y-%m-%d)
YESTERDAY=$(date -d "yesterday" +%Y-%m-%d 2>/dev/null || python3 -c "import datetime; print((datetime.date.today() - datetime.timedelta(days=1)).isoformat())")

mkstate() {
  local stamp_field="$1" stamp_val="$2"
  cat > "$TMP/state.json" <<JSON
{
  "session_date": "$TODAY",
  "evidence": {
    "$stamp_field": "$stamp_val"
  }
}
JSON
}

echo "Test 1: planning→implementation, plan_session_date=TODAY → block (rc=2)"
mkstate "plan_session_date" "$TODAY"
bash "$VALIDATOR" "planning-to-implementation" "$TMP/state.json" >/dev/null 2>&1; rc=$?
assert_rc "same-date plan → blocks" "$rc" "2"

echo "Test 2: planning→implementation, plan_session_date=YESTERDAY → pass (rc=0)"
mkstate "plan_session_date" "$YESTERDAY"
bash "$VALIDATOR" "planning-to-implementation" "$TMP/state.json" >/dev/null 2>&1; rc=$?
assert_rc "different-date plan → passes" "$rc" "0"

echo "Test 3: planning→implementation, plan_session_date empty → pass with WARN (rc=0)"
mkstate "plan_session_date" ""
bash "$VALIDATOR" "planning-to-implementation" "$TMP/state.json" >/dev/null 2>&1; rc=$?
assert_rc "empty stamp → passes" "$rc" "0"

echo "Test 4: retrospective→finalize, last_code_commit_session_date=TODAY → block"
mkstate "last_code_commit_session_date" "$TODAY"
bash "$VALIDATOR" "retrospective-to-finalize" "$TMP/state.json" >/dev/null 2>&1; rc=$?
assert_rc "same-date code commit → blocks" "$rc" "2"

echo "Test 5: SKIP_SESSION_CUT_GATE=1 bypass → pass with stderr notice"
mkstate "plan_session_date" "$TODAY"
out=$(SKIP_SESSION_CUT_GATE=1 bash "$VALIDATOR" "planning-to-implementation" "$TMP/state.json" 2>&1); rc=$?
assert_rc "SKIP bypass → passes" "$rc" "0"
case "$out" in
  *SKIP_SESSION_CUT_GATE*decision-log*|*SKIP_SESSION_CUT_GATE*)
    echo "  ✅ stderr notice present"; pass=$((pass+1)) ;;
  *)
    echo "  ❌ stderr notice missing: $out"; fail=$((fail+1)) ;;
esac

echo "Test 6: unknown transition label → no-op (rc=0)"
mkstate "plan_session_date" "$TODAY"
bash "$VALIDATOR" "consult-to-brainstorming" "$TMP/state.json" >/dev/null 2>&1; rc=$?
assert_rc "unknown transition → passes" "$rc" "0"

echo
echo "Total: $pass passed, $fail failed"
[ "$fail" -eq 0 ]
