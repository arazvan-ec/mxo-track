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
  cat > "$TEST_STATE" << STATEEOF
{"evidence":{"execution_log_path":"$log_path","retrospective_shown":$retro_shown}}
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

- Router layout routes are the right pattern for shared chrome — should have been done when DualMenuShell was created
- Workflow hooks need integration tests that simulate full session flows, not just unit tests per hook
- The phase-transition-controller string matching is inherently fragile — consider a different approach for future enforcement

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
La implementación fue rápida. Lo que NO anticipé fue el tiempo perdido luchando contra los hooks.
El workflow se bloqueó a sí mismo en 4 puntos distintos, requiriendo workarounds diversos para continuar.

## End
LOGEOF
setup_state "$TEST_LOG"
assert_passes "passes with Spanish retrospectiva section" "$VALIDATOR" "$TEST_STATE"

# Cleanup
rm -f "$TEST_STATE" "$TEST_LOG"

echo ""
echo "=== Results: $PASS passed, $FAIL failed ==="
[ "$FAIL" -eq 0 ] && exit 0 || exit 1
