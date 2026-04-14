#!/usr/bin/env bash
# Preflight check: validates all process requirements before push.
#
# Usage: make preflight (from repo root)
#
# Checks:
# 1. PHP lint — no syntax errors in backend/src/
# 2. Unit tests — no new failures
# 3. Manifest freshness — generated today
# 4. Execution log — exists if there are feat:/fix: commits today
# 5. Session state — flow declared if src/ was modified today
#
# Exit code: 0 if all pass, 1 if any fail.

set -euo pipefail

REPO="/home/user/mxo-track"
TODAY=$(date +%Y-%m-%d)
PASS=0
FAIL=0

check() {
  local name="$1"
  local result="$2"
  if [ "$result" = "0" ]; then
    echo "  ✓ $name"
    PASS=$((PASS + 1))
  else
    echo "  ✗ $name"
    FAIL=$((FAIL + 1))
  fi
}

echo "═══ Preflight Checks ($TODAY) ═══"
echo ""

# ── 1. PHP Lint ──
echo "▸ PHP Lint"
LINT_ERRORS=$(find "$REPO/backend/src" -name '*.php' -print0 | xargs -0 -n1 php -l 2>&1 | grep -c "Parse error" || true)
if [ "$LINT_ERRORS" = "0" ]; then
  check "No syntax errors" "0"
else
  check "Found $LINT_ERRORS syntax errors" "1"
fi

# ── 1b. Frontend deps ──
echo "▸ Frontend deps"
if [ -d "$REPO/frontend/node_modules" ]; then
  check "frontend/node_modules present" "0"
else
  check "frontend/node_modules missing — run: cd frontend && npm install" "1"
fi

# ── 1c. Backend deps ──
echo "▸ Backend deps"
if [ -d "$REPO/backend/vendor" ]; then
  check "backend/vendor present" "0"
else
  check "backend/vendor missing — run: cd backend && composer install" "1"
fi

# ── 2. Unit Tests ──
echo "▸ Unit Tests"
if [ -f "$REPO/backend/vendor/bin/phpunit" ]; then
  TEST_OUTPUT=$(cd "$REPO/backend" && php vendor/bin/phpunit --testsuite Unit 2>&1 || true)
  TEST_FAILURES=$(echo "$TEST_OUTPUT" | grep -oP 'Failures: \K\d+' || echo "0")
  TEST_ERRORS=$(echo "$TEST_OUTPUT" | grep -oP 'Errors: \K\d+' || echo "0")
  TOTAL_ISSUES=$((TEST_FAILURES + TEST_ERRORS))

  # Compare against known baseline (pre-existing failures)
  BASELINE_FILE="$REPO/.claude/test-baseline.txt"
  if [ -f "$BASELINE_FILE" ]; then
    BASELINE=$(cat "$BASELINE_FILE")
    if [ "$TOTAL_ISSUES" -le "$BASELINE" ]; then
      check "Tests: $TOTAL_ISSUES issues (baseline: $BASELINE)" "0"
    else
      check "Tests: $TOTAL_ISSUES issues — NEW failures (baseline: $BASELINE)" "1"
    fi
  else
    # No baseline — just report
    check "Tests: $TOTAL_ISSUES issues (no baseline set — run: echo $TOTAL_ISSUES > .claude/test-baseline.txt)" "0"
  fi
else
  check "PHPUnit not installed — run composer install" "1"
fi

# ── 3. Manifest Freshness ──
echo "▸ Manifest"
MANIFEST="$REPO/docs/codebase-manifest.md"
if [ -f "$MANIFEST" ]; then
  MANIFEST_DATE=$(grep -oP '\*\*Generated:\*\*\s*\K\d{4}-\d{2}-\d{2}' "$MANIFEST" | tail -1 || echo "unknown")
  # Fallback: try without bold markdown
  if [ "$MANIFEST_DATE" = "unknown" ]; then
    MANIFEST_DATE=$(grep -oP 'Generated:\s*\K\d{4}-\d{2}-\d{2}' "$MANIFEST" | tail -1 || echo "unknown")
  fi
  if [ "$MANIFEST_DATE" = "$TODAY" ]; then
    check "Manifest generated today ($MANIFEST_DATE)" "0"
  else
    check "Manifest stale ($MANIFEST_DATE) — run: make manifest" "1"
  fi
else
  check "Manifest missing — run: make manifest" "1"
fi

# ── 4. Execution Log ──
echo "▸ Execution Log"
# Check if there are feat: or fix: commits today
FEAT_FIX_COMMITS=$(cd "$REPO" && git log --oneline --since="$TODAY 00:00" --grep="^feat:" --grep="^fix:" --all-match 2>/dev/null | wc -l || echo "0")
# Also check individually
FEAT_COMMITS=$(cd "$REPO" && git log --oneline --since="$TODAY 00:00" --grep="^feat:" 2>/dev/null | wc -l || echo "0")
FIX_COMMITS=$(cd "$REPO" && git log --oneline --since="$TODAY 00:00" --grep="^fix:" 2>/dev/null | wc -l || echo "0")
CODE_COMMITS=$((FEAT_COMMITS + FIX_COMMITS))

if [ "$CODE_COMMITS" -gt 0 ]; then
  EXEC_LOG_EXISTS=$(ls "$REPO/docs/superpowers/execution-logs/${TODAY}-"*.md 2>/dev/null | wc -l || echo "0")
  if [ "$EXEC_LOG_EXISTS" -gt 0 ]; then
    check "Execution log exists ($EXEC_LOG_EXISTS logs for $CODE_COMMITS code commits)" "0"
  else
    check "No execution log for today — $CODE_COMMITS feat:/fix: commits need documentation" "1"
  fi
else
  check "No feat:/fix: commits today — execution log not required" "0"
fi

# ── 5. Session State ──
echo "▸ Session State"
STATE_FILE="$REPO/.claude/session-state.json"
if [ -f "$STATE_FILE" ]; then
  FLOW_DECLARED=$(jq -r '.flow_declared // false' "$STATE_FILE" 2>/dev/null || echo "false")
  FLOW_TYPE=$(jq -r '.flow_type // "none"' "$STATE_FILE" 2>/dev/null || echo "none")
  if [ "$FLOW_DECLARED" = "true" ]; then
    check "Flow declared: $FLOW_TYPE" "0"
  else
    check "Flow not declared in session-state.json" "1"
  fi
else
  check "No session-state.json — SessionStart hook may not have run" "1"
fi

# ── Summary ──
echo ""
echo "═══ Results: $PASS passed, $FAIL failed ═══"

if [ "$FAIL" -gt 0 ]; then
  echo ""
  echo "Fix the failures above before pushing."
  exit 1
fi

exit 0
