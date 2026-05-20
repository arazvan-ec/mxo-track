#!/usr/bin/env bash
# Tests for retrospective-validator.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
VALIDATOR="$SCRIPT_DIR/validators/retrospective-validator.sh"
TEST_STATE="/tmp/test-retro-state.json"
TEST_LOG="/tmp/test-retro-log.md"

PASS=0
FAIL=0

assert_blocks() {
  local desc="$1"; shift
  if "$@" > /dev/null 2>&1; then
    FAIL=$((FAIL + 1))
    echo "  ❌ $desc (expected block, got pass)"
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
    echo "  ❌ $desc (expected pass, got block)"
  fi
}

setup_state() {
  local log_path="${1:-}"
  local retro_shown="${2:-true}"
  local plan_path="${3:-}"
  cat > "$TEST_STATE" << STATEEOF
{"evidence":{"execution_log_path":"$log_path","retrospective_shown":$retro_shown,"plan_path":"$plan_path"}}
STATEEOF
}

echo "=== Test: retrospective-validator.sh ==="

# Test 1: No execution log path → block
echo "Test 1: No execution log path"
setup_state ""
assert_blocks "blocks without log path" "$VALIDATOR" "$TEST_STATE"

# Test 2: Log path set but file missing → block
echo "Test 2: Log path set but file missing"
rm -f "$TEST_LOG"
setup_state "$TEST_LOG"
assert_blocks "blocks when log file missing" "$VALIDATOR" "$TEST_STATE"

# Test 3: Log exists but no retrospective section → block
echo "Test 3: Log exists but no retrospective/lessons section"
cat > "$TEST_LOG" << 'LOGEOF'
# Execution Log

## Implementation
Did some stuff.

## Verification
Tests passed.
LOGEOF
setup_state "$TEST_LOG"
assert_blocks "blocks without retrospective section" "$VALIDATOR" "$TEST_STATE"

# Test 4: Log has Lessons header but empty content → block
echo "Test 4: Lessons header but content too short"
cat > "$TEST_LOG" << 'LOGEOF'
# Execution Log

## Lessons

Short.

## End
LOGEOF
setup_state "$TEST_LOG"
assert_blocks "blocks with short retrospective (<100 chars)" "$VALIDATOR" "$TEST_STATE"

# Test 5: Log has valid Lessons section → pass
echo "Test 5: Valid Lessons section with enough content"
cat > "$TEST_LOG" << 'LOGEOF'
# Execution Log

## Implementation
Did some stuff.

## Lessons

- Router layout routes respect the boundary between shared chrome and page-specific logic — DDD-style separation we should have applied earlier
- Workflow hooks need integration tests that simulate full session flows, not just unit tests per hook
- The phase-transition-controller string matching is architectural tech-debt — consider a different approach for future enforcement

Backlog candidates: 0 — no surfaced improvements this interaction

## End
LOGEOF
setup_state "$TEST_LOG"
assert_passes "passes with valid lessons section" "$VALIDATOR" "$TEST_STATE"

# Test 6: Log has Retrospectiva (Spanish) section → pass
echo "Test 6: Spanish 'Retrospectiva' header"
cat > "$TEST_LOG" << 'LOGEOF'
# Execution Log

## Retrospectiva

### Estimación vs realidad
La implementación fue rápida. Lo que NO anticipé fue el tiempo perdido luchando contra el coupling
entre los hooks y phase-advance. El workflow se bloqueó a sí mismo en 4 puntos distintos debido a una
architecture de gates demasiado estricta, requiriendo workarounds diversos para continuar.

Backlog candidates: 0 — no surfaced improvements this interaction

## End
LOGEOF
setup_state "$TEST_LOG"
assert_passes "passes with Spanish retrospectiva section" "$VALIDATOR" "$TEST_STATE"

# Test 7 (Layer I baseline post-removal): Lessons content >=100 chars
# without any architectural keyword should now PASS (the gate was removed
# 2026-04-26 — see /tmp/layer-i-analysis.md for the 4-test rationale).
echo "Test 7: Post-Layer-I-removal — neutral lessons content passes"
cat > "$TEST_LOG" << 'LOGEOF'
# Execution Log

## Lessons

- Nice feature landed on schedule.
- Happy with the user experience — the sparkline looks great in the expanded card.
- Should plan more time for the final polish pass next sprint.
- The release announcement got good feedback from stakeholders.

Backlog candidates: 0 — no surfaced improvements this interaction

## End
LOGEOF
setup_state "$TEST_LOG" "true"
assert_passes "neutral lessons (no arch keyword) passes after Layer I removal" "$VALIDATOR" "$TEST_STATE"

# ── P2 2026-05-20: Backlog candidates analysis (HARD) ──
# Spec: docs/superpowers/specs/2026-05-20-retrospective-backlog-candidates-design.md

echo "Test A: Retro without backlog-candidates section → block"
cat > "$TEST_LOG" << 'LOGEOF'
# Execution Log

## Lessons

- Some content here that meets the 100-char minimum requirement for retrospective sections,
  ensuring this test isolates the new backlog-candidates check from the existing length gate.

## End
LOGEOF
setup_state "$TEST_LOG" "true"
assert_blocks "blocks when no Backlog candidates section and no '0 — no surfaced' line" "$VALIDATOR" "$TEST_STATE"

echo "Test B: Retro with candidates section + bullets but docs/backlog.md not modified → block"
cat > "$TEST_LOG" << 'LOGEOF'
# Execution Log

## Lessons

- Some content here that meets the 100-char minimum requirement for retrospective sections,
  ensuring this test isolates the new backlog-candidates check from the existing length gate.

## Backlog candidates

- Approval regex extension — 4th occurrence of "approval not detected" pattern.
- Verification validator skipped acceptance — 5th occurrence in sandbox without shellcheck.

## End
LOGEOF
# Setup with plan_path pointing to a file that exists on disk so git-refs returns
# a base; but docs/backlog.md should NOT be in the diff. We point to a temp plan
# path that isn't a real plan — fallback origin/main is used.
setup_state "$TEST_LOG" "true" ""
# Make sure docs/backlog.md is not staged/modified in working tree (we'll check
# in real repo; if backlog.md IS modified, this test is non-deterministic, so
# we stash it ad-hoc for this assertion only via a sentinel git probe).
# Simplification: just assert the validator runs the check by inspecting STDERR
# when backlog is not in diff range. In live invocation it may pass if backlog
# happens to be already modified — accept either outcome here, but assert the
# section detection works.
# Conservative: assert blocks IF backlog clean, else pass IF backlog modified.
if git status --porcelain docs/backlog.md 2>/dev/null | grep -q .; then
  assert_passes "Test B: backlog already modified — section + bullets pass" "$VALIDATOR" "$TEST_STATE"
else
  assert_blocks "Test B: bullets present but docs/backlog.md not in diff → block" "$VALIDATOR" "$TEST_STATE"
fi

echo "Test C: Retro with '0 candidates' literal line → pass (no backlog modification needed)"
cat > "$TEST_LOG" << 'LOGEOF'
# Execution Log

## Lessons

- Some content here that meets the 100-char minimum requirement for retrospective sections,
  ensuring this test isolates the new backlog-candidates check from the existing length gate.

Backlog candidates: 0 — no surfaced improvements this interaction

## End
LOGEOF
setup_state "$TEST_LOG" "true"
assert_passes "passes with literal '0 — no surfaced improvements' line" "$VALIDATOR" "$TEST_STATE"

echo "Test D: Retro with both candidates section bullets AND zero-line → pass (defensive)"
cat > "$TEST_LOG" << 'LOGEOF'
# Execution Log

## Lessons

- Some content here that meets the 100-char minimum requirement for retrospective sections,
  ensuring this test isolates the new backlog-candidates check from the existing length gate.

## Backlog candidates

- Real candidate from this interaction.

Backlog candidates: 0 — no surfaced improvements this interaction

## End
LOGEOF
setup_state "$TEST_LOG" "true"
# The "0 — no surfaced" line takes precedence as sentinel, so backlog modification
# is not required. This is defensive — both signals say "no issue".
assert_passes "passes when zero-line present (defensive even with bullets)" "$VALIDATOR" "$TEST_STATE"

# Cleanup
rm -f "$TEST_STATE" "$TEST_LOG"

echo ""
echo "=== Results: $PASS passed, $FAIL failed ==="
[ "$FAIL" -eq 0 ] && exit 0 || exit 1
