#!/usr/bin/env bash
# Test suite for link-regression.sh
set -uo pipefail

TMPDIR=$(mktemp -d)
trap 'rm -rf "$TMPDIR"' EXIT

LOGS_DIR="$TMPDIR/logs"
mkdir -p "$LOGS_DIR"

TESTS_RUN=0
TESTS_PASSED=0
TESTS_FAILED=0

mkfx() {
  local name="$1" regressions="$2"
  cat > "$LOGS_DIR/$name" <<EOF
---
type: feature
tags: []
files_touched: []
patterns: []
outcome: success
outcome_verified_at: null
regressions_later: $regressions
pr_number: null
estimated_lines: null
actual_lines: null
duration_minutes: null
consulted_in_future: []
---
# Log — $name
EOF
}

LR="$(pwd)/scripts/link-regression.sh"
export LINK_REGRESSION_LOGS_DIR="$LOGS_DIR"

check_exit() {
  local desc="$1" expected="$2" actual="$3"
  TESTS_RUN=$((TESTS_RUN+1))
  if [ "$expected" = "$actual" ]; then
    TESTS_PASSED=$((TESTS_PASSED+1)); echo "  ✅ $desc (exit=$actual)"
  else
    TESTS_FAILED=$((TESTS_FAILED+1)); echo "  ❌ $desc (expected $expected, got $actual)"
  fi
}

check_regressions() {
  local file="$1" expected_contains="$2" desc="$3"
  local got
  got=$(awk '/^---[[:space:]]*$/{c++; if(c==2)exit; next} c==1 && /^regressions_later:/{sub(/^regressions_later:[[:space:]]*/,""); print; exit}' "$file")
  TESTS_RUN=$((TESTS_RUN+1))
  if echo "$got" | grep -qF "$expected_contains"; then
    TESTS_PASSED=$((TESTS_PASSED+1)); echo "  ✅ $desc (got: $got)"
  else
    TESTS_FAILED=$((TESTS_FAILED+1)); echo "  ❌ $desc (expected to contain '$expected_contains', got: $got)"
  fi
}

# Case 1: empty regressions → adds new log
mkfx "old1.md" "[]"
"$LR" "new1.md" "old1.md" >/dev/null 2>&1; rc=$?
check_exit "empty list + new log adds" 0 $rc
check_regressions "$LOGS_DIR/old1.md" "new1.md" "old1 contains new1"

# Case 2: pre-existing entry → adds alongside
mkfx "old2.md" "[existing.md]"
"$LR" "new2.md" "old2.md" >/dev/null 2>&1; rc=$?
check_exit "existing list + new log appends" 0 $rc
check_regressions "$LOGS_DIR/old2.md" "existing.md" "old2 preserves existing.md"
check_regressions "$LOGS_DIR/old2.md" "new2.md" "old2 adds new2.md"

# Case 3: idempotence — adding same log twice is no-op
mkfx "old3.md" "[already.md]"
"$LR" "already.md" "old3.md" >/dev/null 2>&1; rc=$?
check_exit "idempotent — already present" 0 $rc
# Count occurrences: should only appear once
count=$(awk '/^---[[:space:]]*$/{c++; if(c==2)exit; next} c==1 && /^regressions_later:/{print; exit}' "$LOGS_DIR/old3.md" | grep -oF "already.md" | wc -l)
TESTS_RUN=$((TESTS_RUN+1))
if [ "$count" = "1" ]; then
  TESTS_PASSED=$((TESTS_PASSED+1)); echo "  ✅ already.md appears exactly once (idempotent)"
else
  TESTS_FAILED=$((TESTS_FAILED+1)); echo "  ❌ already.md appears $count times"
fi

# Case 4: missing old-log → error
"$LR" "new.md" "nonexistent.md" >/dev/null 2>&1; rc=$?
check_exit "missing old-log → error" 2 $rc

# Case 5: missing args → error
"$LR" >/dev/null 2>&1; rc=$?
check_exit "no args → usage error" 2 $rc

"$LR" "onlyone" >/dev/null 2>&1; rc=$?
check_exit "one arg → usage error" 2 $rc

echo ""
echo "Results: $TESTS_RUN run · $TESTS_PASSED passed · $TESTS_FAILED failed"
[ $TESTS_FAILED -eq 0 ] && exit 0 || exit 1
