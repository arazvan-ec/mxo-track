#!/usr/bin/env bash
# Test suite for pattern-audit.sh
# Uses CONSULT_LOGS_DIR and PATTERN_AUDIT_KNOWLEDGE_DIR env vars for isolation.
set -uo pipefail

TMPDIR=$(mktemp -d)
trap "rm -rf $TMPDIR" EXIT

LOGS_DIR="$TMPDIR/logs"
KNOWLEDGE_DIR="$TMPDIR/knowledge"
mkdir -p "$LOGS_DIR" "$KNOWLEDGE_DIR"

TESTS_RUN=0
TESTS_PASSED=0
TESTS_FAILED=0

mkfx() {
  local name="$1" tag="$2"
  cat > "$LOGS_DIR/$name" <<EOF
---
type: bugfix
tags: [$tag]
files_touched: []
patterns: []
outcome: success
outcome_verified_at: null
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

# 3 logs with "foo" tag → candidate
mkfx "2026-01-01-one.md" "foo"
mkfx "2026-01-02-two.md" "foo"
mkfx "2026-01-03-three.md" "foo"
# 3 logs with "bar" tag → graduated (in knowledge)
mkfx "2026-01-04-four.md" "bar"
mkfx "2026-01-05-five.md" "bar"
mkfx "2026-01-06-six.md" "bar"
echo "Discussion of bar pattern." > "$KNOWLEDGE_DIR/barpatterns.md"

PA="$(pwd)/.claude/hooks/pattern-audit.sh"
export CONSULT_LOGS_DIR="$LOGS_DIR"
export PATTERN_AUDIT_KNOWLEDGE_DIR="$KNOWLEDGE_DIR"

out=$("$PA" 2>&1)

TESTS_RUN=$((TESTS_RUN+1))
if echo "$out" | grep -q "foo"; then
  TESTS_PASSED=$((TESTS_PASSED+1)); echo "  ✅ foo (not graduated) appears in candidates"
else
  TESTS_FAILED=$((TESTS_FAILED+1)); echo "  ❌ foo should appear. Got: $out"
fi

TESTS_RUN=$((TESTS_RUN+1))
if echo "$out" | grep -q "bar"; then
  TESTS_FAILED=$((TESTS_FAILED+1)); echo "  ❌ bar (graduated) should NOT appear. Got: $out"
else
  TESTS_PASSED=$((TESTS_PASSED+1)); echo "  ✅ bar (graduated) excluded from candidates"
fi

TESTS_RUN=$((TESTS_RUN+1))
if echo "$out" | grep -q "3 logs"; then
  TESTS_PASSED=$((TESTS_PASSED+1)); echo "  ✅ count reported correctly"
else
  TESTS_FAILED=$((TESTS_FAILED+1)); echo "  ❌ count not in output: $out"
fi

# Empty corpus — should exit silent
rm -f "$LOGS_DIR"/*.md
out=$("$PA" 2>&1)
TESTS_RUN=$((TESTS_RUN+1))
if [ -z "$out" ]; then
  TESTS_PASSED=$((TESTS_PASSED+1)); echo "  ✅ silent when no candidates"
else
  TESTS_FAILED=$((TESTS_FAILED+1)); echo "  ❌ expected silent, got: $out"
fi

echo ""
echo "Results: $TESTS_RUN run · $TESTS_PASSED passed · $TESTS_FAILED failed"
[ $TESTS_FAILED -eq 0 ] && exit 0 || exit 1
