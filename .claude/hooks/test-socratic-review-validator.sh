#!/usr/bin/env bash
# Test: socratic-review-validator.sh (refactored 2026-04-24)
#
# Reads `## Architectural Adversarial Review` section from a spec file.
# Exercises the contract: >=3 numbered Q/A entries, each >=30 chars,
# architectural keyword required when spec references critical paths.

set -euo pipefail

REPO="/home/user/mxo-track"
VALIDATOR="$REPO/.claude/hooks/validators/socratic-review-validator.sh"

# shellcheck source=./lib/test-harness.sh
source "$REPO/.claude/hooks/lib/test-harness.sh"
init_harness

# Helper: build a spec fixture and run the validator against it.
# $1 = scenario label, $2 = section body
run_with_section() {
  local label="$1"
  local section="$2"
  local spec_path="$TEST_TMPDIR/spec-${label}.md"
  cat > "$spec_path" <<EOF
# Spec $label

Touches backend/src/Domain/Route/Planner.php.

## Approaches

A vs B trade-off.

## Existing Functionality Inventory

- None.

## Prior Art Audit

| Path | Endorsed? |
|---|---|
| backend/src/Domain/Route/Planner.php | ❌ tech-debt |

## Architectural Adversarial Review

$section

## Omission Decisions

- None.

Padding padding padding padding padding padding padding padding padding padding.
EOF
  if bash "$VALIDATOR" "$spec_path" >/dev/null 2>&1; then
    echo "pass"
  else
    echo "block"
  fi
}

# Variant: spec that does NOT reference critical paths (architectural
# keyword requirement should NOT fire).
run_non_critical() {
  local label="$1"
  local section="$2"
  local spec_path="$TEST_TMPDIR/spec-${label}.md"
  cat > "$spec_path" <<EOF
# Spec $label

Touches backend/src/Infrastructure/Foo.php only.

## Approaches

A vs B.

## Existing Functionality Inventory

- None.

## Architectural Adversarial Review

$section

## Omission Decisions

- None.

Padding padding padding padding padding padding padding padding padding padding.
EOF
  if bash "$VALIDATOR" "$spec_path" >/dev/null 2>&1; then
    echo "pass"
  else
    echo "block"
  fi
}

echo "── socratic-review-validator (spec-based) ──"

# Test 1: no section present → block
EMPTY_SPEC_PATH="$TEST_TMPDIR/spec-empty.md"
cat > "$EMPTY_SPEC_PATH" <<'EOF'
# Spec

Touches backend/src/Domain/Route/X.php.

## Approaches
A vs B.

Padding padding padding padding padding padding padding padding padding padding.
EOF
if bash "$VALIDATOR" "$EMPTY_SPEC_PATH" >/dev/null 2>&1; then
  assert_eq "missing section → block" "block" "pass"
else
  assert_eq "missing section → block" "block" "block"
fi

# Test 2: two questions only → block
TWO_Q='1. **Q:** Does the DDD boundary hold under this refactor of the domain service?
   **A:** Yes because Infrastructure is isolated.

2. **Q:** Is the pattern consistent with endorsed Facade usage in the codebase?
   **A:** Yes.'
assert_eq "two questions (below floor) → block" "block" "$(run_with_section "two" "$TWO_Q")"

# Test 3: three short questions → block (each entry under 30 chars)
SHORT_Q='1. **Q:** Short? **A:** Yes.

2. **Q:** Too short? **A:** Y.

3. **Q:** Brief? **A:** K.'
assert_eq "three short entries → block" "block" "$(run_with_section "short" "$SHORT_Q")"

# Test 4: three long questions but NO architectural keyword AND spec
# references critical paths → block
NO_ARCH_Q='1. **Q:** Did we plan enough rollout time for the production push of this change?
   **A:** Yes we allocated three days in the schedule.

2. **Q:** Are we going to announce the change to stakeholders before shipping?
   **A:** An email will go out the day before the release.

3. **Q:** Is the documentation going to be ready in time for launch to the public?
   **A:** The writer committed to having it ready.'
assert_eq "three long no arch keyword, critical path → block" "block" "$(run_with_section "noarch" "$NO_ARCH_Q")"

# Test 5: three long questions, at least one with architectural keyword,
# critical path referenced → pass
WITH_ARCH_Q='1. **Q:** Does this refactor respect the DDD boundary between Domain and Infrastructure layers?
   **A:** Yes, the Repository interface stays in Domain.

2. **Q:** Does the new pattern follow the endorsed Facade approach documented in backend/CLAUDE.md?
   **A:** Yes.

3. **Q:** What tradeoff did we accept regarding coverage versus implementation simplicity?
   **A:** Accepted skipping functional tests for this iteration.'
assert_eq "three long with arch keyword, critical path → pass" "pass" "$(run_with_section "witharch" "$WITH_ARCH_Q")"

# Test 6 (extra): non-critical spec with 3 long non-arch questions → pass
# (keyword requirement only fires for critical-path specs)
assert_eq "three long no arch keyword, non-critical spec → pass" "pass" "$(run_non_critical "nonarch" "$NO_ARCH_Q")"

summary
