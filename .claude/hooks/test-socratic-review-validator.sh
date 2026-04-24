#!/usr/bin/env bash
# Test: socratic-review-validator.sh
#
# Exercises the contract: >=3 questions, each >=30 chars, architectural
# keyword required when critical paths are touched.

set -euo pipefail

REPO="/home/user/mxo-track"
VALIDATOR="$REPO/.claude/hooks/validators/socratic-review-validator.sh"

# shellcheck source=./lib/test-harness.sh
source "$REPO/.claude/hooks/lib/test-harness.sh"
init_harness

# Helper: run validator against a synthetic state with the given socratic_questions
# array. Returns "pass" or "block".
run_validator() {
  local questions_json="$1"
  local state_file="$TEST_TMPDIR/state.json"
  jq --argjson q "$questions_json" '{
    evidence: { socratic_questions: $q }
  }' <<<'{}' > "$state_file"
  if bash "$VALIDATOR" "$state_file" >/dev/null 2>&1; then
    echo "pass"
  else
    echo "block"
  fi
}

echo "── socratic-review-validator ──"

# Test 1: empty questions array → block
EMPTY='[]'
assert_eq "empty array → block" "block" "$(run_validator "$EMPTY")"

# Test 2: two questions (below floor of 3) → block
TWO='["Is the DDD boundary respected?", "Do tests validate architecture or only shape?"]'
assert_eq "two questions (below floor) → block" "block" "$(run_validator "$TWO")"

# Test 3: three short questions (<30 chars each) → block
SHORT='["Too short?", "Still short?", "Also brief?"]'
assert_eq "three short questions → block" "block" "$(run_validator "$SHORT")"

# Test 4: three long questions, no architectural keyword, critical paths touched → block
# (this test works because the test harness runs in the same repo and there ARE
#  critical-path changes pending on HEAD-vs-origin/main — the C layer itself
#  lives under .claude/hooks/, which the regex matches).
NO_ARCH='["Did we budget enough time for the rollout of this change to production?", "Are the five new layers going to confuse new contributors on boarding?", "Should we publish an announcement to the team about this change?"]'
assert_eq "three long, no arch keyword, critical paths → block" "block" "$(run_validator "$NO_ARCH")"

# Test 5: three long questions with at least one architectural keyword → pass
WITH_ARCH='["Did this refactor respect the DDD boundary between Domain and Infrastructure?", "Does the new validator follow the established pattern of other phase exit validators?", "What tradeoff did we accept on graduation registry coverage versus implementation simplicity?"]'
assert_eq "three long with arch keyword → pass" "pass" "$(run_validator "$WITH_ARCH")"

summary
