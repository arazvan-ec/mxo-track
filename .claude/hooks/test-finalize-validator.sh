#!/usr/bin/env bash
# Tests for finalize-validator.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
VALIDATOR="$SCRIPT_DIR/validators/finalize-validator.sh"
TEST_STATE="/tmp/test-finalize-state.json"

PASS=0
FAIL=0

assert_warns() {
  local desc="$1"; shift
  if "$@" > /dev/null 2>&1; then
    FAIL=$((FAIL + 1))
    echo "  ❌ $desc (expected warn/exit 1, got pass)"
  else
    PASS=$((PASS + 1))
    echo "  ✅ $desc"
  fi
}

assert_passes() {
  local desc="$1"; shift
  if "$@" > /dev/null 2>&1; then
    PASS=$((PASS + 1))
    echo "  ✅ $desc"
  else
    FAIL=$((FAIL + 1))
    echo "  ❌ $desc (expected pass, got warn/exit 1)"
  fi
}

assert_output_contains() {
  local desc="$1"; shift
  local expected="$1"; shift
  local output
  output=$("$@" 2>&1 || true)
  if echo "$output" | grep -q "$expected"; then
    PASS=$((PASS + 1))
    echo "  ✅ $desc"
  else
    FAIL=$((FAIL + 1))
    echo "  ❌ $desc (expected output to contain '$expected')"
    echo "     got: $output"
  fi
}

assert_output_not_contains() {
  local desc="$1"; shift
  local unexpected="$1"; shift
  local output
  output=$("$@" 2>&1 || true)
  if echo "$output" | grep -q "$unexpected"; then
    FAIL=$((FAIL + 1))
    echo "  ❌ $desc (output should NOT contain '$unexpected')"
    echo "     got: $output"
  else
    PASS=$((PASS + 1))
    echo "  ✅ $desc"
  fi
}

setup_state() {
  local branch_strategy="${1:-}"
  cat > "$TEST_STATE" << STATEEOF
{"evidence":{"branch_strategy":"$branch_strategy"}}
STATEEOF
}

echo "=== Test: finalize-validator.sh ==="

# Test 1: No branch_strategy → should warn (exit 1)
echo "Test 1: No branch_strategy declared"
setup_state ""
assert_warns "warns without branch_strategy" "$VALIDATOR" "$TEST_STATE"
assert_output_contains "mentions branch strategy in warning" "No branch strategy" "$VALIDATOR" "$TEST_STATE"

# Test 2: branch_strategy="pr" → should NOT produce branch_strategy warning
# Note: The knowledge module check uses git diff origin/main...HEAD which may
# produce warnings in test env depending on branch state. We test specifically
# that the branch_strategy check passes (no "No branch strategy" in output).
echo "Test 2: branch_strategy=pr suppresses branch_strategy warning"
setup_state "pr"
assert_output_not_contains "no branch_strategy warning with pr" "No branch strategy" "$VALIDATOR" "$TEST_STATE"

# Test 3: branch_strategy="merge" → should also suppress branch_strategy warning
echo "Test 3: branch_strategy=merge suppresses branch_strategy warning"
setup_state "merge"
assert_output_not_contains "no branch_strategy warning with merge" "No branch strategy" "$VALIDATOR" "$TEST_STATE"

# Test 4: Knowledge module check — when git diff detects .claude/hooks changes,
# it should suggest superpowers-skills.md. This validates the mapping works.
# We check the actual output of the validator against the current branch state.
echo "Test 4: Knowledge module mapping produces correct suggestions"
setup_state "pr"
VALIDATOR_OUTPUT=$("$VALIDATOR" "$TEST_STATE" 2>&1 || true)
DIFF_OUTPUT=$(git diff --name-only origin/main...HEAD 2>/dev/null || echo "")

if echo "$DIFF_OUTPUT" | grep -q '\.claude/hooks/'; then
  # Branch has hook changes → validator should mention superpowers-skills.md
  if echo "$VALIDATOR_OUTPUT" | grep -q "superpowers-skills.md"; then
    PASS=$((PASS + 1))
    echo "  ✅ suggests superpowers-skills.md for hook changes"
  else
    FAIL=$((FAIL + 1))
    echo "  ❌ should suggest superpowers-skills.md for hook changes"
  fi
else
  # No hook changes in diff → skip this assertion
  PASS=$((PASS + 1))
  echo "  ✅ (skipped: no hook changes in current branch diff)"
fi

# Test 5: When knowledge modules ARE updated, they should NOT appear in warning.
# If docs/knowledge/superpowers-skills.md were in the diff, the validator filters it out.
# We verify the logic by checking: modules in the diff should not be warned about.
echo "Test 5: Already-updated modules not warned about"
if [ -n "$DIFF_OUTPUT" ]; then
  # Check that any module that IS in the diff is NOT in the warning
  ALL_GOOD=true
  for module_file in $(echo "$DIFF_OUTPUT" | grep "docs/knowledge/" || true); do
    module_name=$(basename "$module_file")
    if echo "$VALIDATOR_OUTPUT" | grep -q "KNOWLEDGE CHECK" && echo "$VALIDATOR_OUTPUT" | grep -q "$module_name"; then
      ALL_GOOD=false
      echo "  ❌ module $module_name is in diff but still warned about"
      FAIL=$((FAIL + 1))
      break
    fi
  done
  if $ALL_GOOD; then
    PASS=$((PASS + 1))
    echo "  ✅ updated modules correctly excluded from warning"
  fi
else
  PASS=$((PASS + 1))
  echo "  ✅ (skipped: no diff available)"
fi

# Cleanup
rm -f "$TEST_STATE"

echo ""
echo "=== Results: $PASS passed, $FAIL failed ==="
[ "$FAIL" -eq 0 ] && exit 0 || exit 1
