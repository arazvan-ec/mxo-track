#!/usr/bin/env bash
# Test suite for mark-verified.sh
set -uo pipefail

TMPDIR=$(mktemp -d)
trap "rm -rf $TMPDIR" EXIT

LOGS_DIR="$TMPDIR/logs"
mkdir -p "$LOGS_DIR"

TESTS_RUN=0
TESTS_PASSED=0
TESTS_FAILED=0

mkfx() {
  local name="$1" outcome="$2" verified="$3"
  cat > "$LOGS_DIR/$name" <<EOF
---
type: bugfix
tags: []
files_touched: []
patterns: []
outcome: $outcome
outcome_verified_at: $verified
regressions_later: []
pr_number: null
estimated_lines: null
actual_lines: null
duration_minutes: null
consulted_in_future: []
---
# Log — $name
EOF
}

MV="$(pwd)/scripts/mark-verified.sh"
export MARK_VERIFIED_LOGS_DIR="$LOGS_DIR"

check_test() {
  local desc="$1" expected_exit="$2" actual_exit="$3"
  TESTS_RUN=$((TESTS_RUN+1))
  if [ "$expected_exit" = "$actual_exit" ]; then
    TESTS_PASSED=$((TESTS_PASSED+1)); echo "  ✅ $desc (exit=$actual_exit)"
  else
    TESTS_FAILED=$((TESTS_FAILED+1)); echo "  ❌ $desc (expected exit $expected_exit, got $actual_exit)"
  fi
}

check_field() {
  local file="$1" field="$2" expected="$3" desc="$4"
  local got
  got=$(awk -v f="$field" '/^---[[:space:]]*$/{c++; if(c==2)exit; next} c==1 && $0 ~ "^"f":" {sub("^"f":[[:space:]]*",""); print; exit}' "$file")
  TESTS_RUN=$((TESTS_RUN+1))
  if [ "$got" = "$expected" ]; then
    TESTS_PASSED=$((TESTS_PASSED+1)); echo "  ✅ $desc ($field=$got)"
  else
    TESTS_FAILED=$((TESTS_FAILED+1)); echo "  ❌ $desc (expected $field=$expected, got $got)"
  fi
}

TODAY=$(date +%Y-%m-%d)

# Case 1: success + null verified → sets today
mkfx "case1.md" "success" "null"
"$MV" "case1.md" >/dev/null 2>&1; rc=$?
check_test "success+null gets verified" 0 $rc
check_field "$LOGS_DIR/case1.md" "outcome_verified_at" "$TODAY" "case1 updated to today"

# Case 2: already verified → skip
mkfx "case2.md" "success" "2026-01-01"
"$MV" "case2.md" >/dev/null 2>&1; rc=$?
check_test "already verified → skip (exit 1)" 1 $rc
check_field "$LOGS_DIR/case2.md" "outcome_verified_at" "2026-01-01" "case2 unchanged"

# Case 3: already verified + --force → overwrites
mkfx "case3.md" "success" "2026-01-01"
"$MV" "case3.md" --force >/dev/null 2>&1; rc=$?
check_test "already verified + --force works" 0 $rc
check_field "$LOGS_DIR/case3.md" "outcome_verified_at" "$TODAY" "case3 overwritten"

# Case 4: outcome != success → refuse
mkfx "case4.md" "reverted" "null"
"$MV" "case4.md" >/dev/null 2>&1; rc=$?
check_test "reverted outcome refused" 2 $rc

# Case 5: missing file
"$MV" "nonexistent.md" >/dev/null 2>&1; rc=$?
check_test "missing log → error" 2 $rc

# Case 6: missing arg
"$MV" >/dev/null 2>&1; rc=$?
check_test "no arg → usage error" 2 $rc

echo ""
echo "Results: $TESTS_RUN run · $TESTS_PASSED passed · $TESTS_FAILED failed"
[ $TESTS_FAILED -eq 0 ] && exit 0 || exit 1
