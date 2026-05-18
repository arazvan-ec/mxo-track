#!/usr/bin/env bash
# Test suite for pattern-audit.sh gate-drift detection (P2 of 2026-05-18 harness improvements).
# Spec: docs/superpowers/specs/2026-05-18-pattern-audit-gate-drift-design.md
#
# Uses PATTERN_AUDIT_DECISION_LOG + PATTERN_AUDIT_BYPASS_WINDOW_DAYS +
# PATTERN_AUDIT_BYPASS_THRESHOLD env vars for isolation. Constructs fixture
# decision logs in-memory; does not require any committed fixture files.
set -uo pipefail

TMPDIR=$(mktemp -d)
trap 'rm -rf "$TMPDIR"' EXIT

# Shared isolation: empty logs dir so the existing tag-graduation detection
# emits nothing; we only test the new gate-drift detection in this suite.
LOGS_DIR="$TMPDIR/logs"
KNOWLEDGE_DIR="$TMPDIR/knowledge"
REGISTRY="$TMPDIR/_graduations.yaml"
VOCAB="$TMPDIR/_vocabulary.yaml"
mkdir -p "$LOGS_DIR" "$KNOWLEDGE_DIR"
cat > "$REGISTRY" <<'EOF'
tags: {}
patterns: {}
keyword_mappings: {}
EOF
cat > "$VOCAB" <<'EOF'
terms: {}
EOF

TESTS_RUN=0
TESTS_PASSED=0
TESTS_FAILED=0

# Compute dates relative to today so the 90-day window includes them.
TODAY=$(date -u +%Y-%m-%d)
D_RECENT_1=$(date -u -d "-5 days" +%Y-%m-%d 2>/dev/null || date -u -v-5d +%Y-%m-%d)
D_RECENT_2=$(date -u -d "-20 days" +%Y-%m-%d 2>/dev/null || date -u -v-20d +%Y-%m-%d)
D_RECENT_3=$(date -u -d "-60 days" +%Y-%m-%d 2>/dev/null || date -u -v-60d +%Y-%m-%d)
D_OLD=$(date -u -d "-200 days" +%Y-%m-%d 2>/dev/null || date -u -v-200d +%Y-%m-%d)

# Fixture 1: 3 entries of SKIP_PHASE_EXIT_GATE within window → should flag
LOG1="$TMPDIR/decision-log-3-bypasses.md"
cat > "$LOG1" <<EOF
# Decision Log

### [$D_RECENT_1] Bypass SKIP_PHASE_EXIT_GATE — case A
- **Problema:** X
- **Decisión:** Bypass SKIP_PHASE_EXIT_GATE=1.

### [$D_RECENT_2] Bypass SKIP_PHASE_EXIT_GATE — case B
- **Problema:** Y
- **Decisión:** SKIP_PHASE_EXIT_GATE=1 used.

### [$D_RECENT_3] Bypass SKIP_PHASE_EXIT_GATE — case C
- **Problema:** Z
- **Decisión:** SKIP_PHASE_EXIT_GATE=1.
EOF

# Fixture 2: 2 entries of SKIP_SYNC_GATE → should NOT flag (under threshold)
LOG2="$TMPDIR/decision-log-2-bypasses.md"
cat > "$LOG2" <<EOF
# Decision Log

### [$D_RECENT_1] Bypass SKIP_SYNC_GATE — only one
- **Decisión:** SKIP_SYNC_GATE=1.

### [$D_RECENT_2] Bypass SKIP_SYNC_GATE — another
- **Decisión:** SKIP_SYNC_GATE=1.
EOF

# Fixture 3: 3 entries but all OUT of window → should NOT flag
LOG3="$TMPDIR/decision-log-old.md"
cat > "$LOG3" <<EOF
# Decision Log

### [$D_OLD] old SKIP_OLD_GATE
- **Decisión:** SKIP_OLD_GATE=1.

### [$D_OLD] old SKIP_OLD_GATE
- **Decisión:** SKIP_OLD_GATE=1.

### [$D_OLD] old SKIP_OLD_GATE
- **Decisión:** SKIP_OLD_GATE=1.
EOF

# Fixture 4: multi-underscore gate name (regex edge case)
LOG4="$TMPDIR/decision-log-ddd.md"
cat > "$LOG4" <<EOF
# Decision Log

### [$D_RECENT_1] Bypass SKIP_DDD_BOUNDARY_GATE
- **Decisión:** SKIP_DDD_BOUNDARY_GATE=1.

### [$D_RECENT_2] Bypass SKIP_DDD_BOUNDARY_GATE
- **Decisión:** SKIP_DDD_BOUNDARY_GATE=1.

### [$D_RECENT_3] Bypass SKIP_DDD_BOUNDARY_GATE
- **Decisión:** SKIP_DDD_BOUNDARY_GATE=1.
EOF

PA="$(pwd)/.claude/hooks/pattern-audit.sh"
export CONSULT_LOGS_DIR="$LOGS_DIR"
export PATTERN_AUDIT_REGISTRY="$REGISTRY"
export PATTERN_AUDIT_KNOWLEDGE_DIR="$KNOWLEDGE_DIR"
export VOCAB_FILE="$VOCAB"
export EXEC_LOGS_DIR="$LOGS_DIR"

# Test 1: 3 bypasses of same gate → should flag with [TUNE] and [LEGITIMIZE]
export PATTERN_AUDIT_DECISION_LOG="$LOG1"
out=$("$PA" 2>&1)

TESTS_RUN=$((TESTS_RUN+1))
if echo "$out" | grep -q "SKIP_PHASE_EXIT_GATE"; then
  TESTS_PASSED=$((TESTS_PASSED+1)); echo "  ✅ 3 bypasses → gate name surfaced"
else
  TESTS_FAILED=$((TESTS_FAILED+1)); echo "  ❌ expected SKIP_PHASE_EXIT_GATE in output. Got: $out"
fi

TESTS_RUN=$((TESTS_RUN+1))
if echo "$out" | grep -q "\[TUNE\]"; then
  TESTS_PASSED=$((TESTS_PASSED+1)); echo "  ✅ [TUNE] option emitted"
else
  TESTS_FAILED=$((TESTS_FAILED+1)); echo "  ❌ expected [TUNE] in output. Got: $out"
fi

TESTS_RUN=$((TESTS_RUN+1))
if echo "$out" | grep -q "\[LEGITIMIZE\]"; then
  TESTS_PASSED=$((TESTS_PASSED+1)); echo "  ✅ [LEGITIMIZE] option emitted"
else
  TESTS_FAILED=$((TESTS_FAILED+1)); echo "  ❌ expected [LEGITIMIZE] in output. Got: $out"
fi

# Test 2: 2 bypasses → should NOT flag
export PATTERN_AUDIT_DECISION_LOG="$LOG2"
out=$("$PA" 2>&1)

TESTS_RUN=$((TESTS_RUN+1))
if echo "$out" | grep -q "SKIP_SYNC_GATE"; then
  TESTS_FAILED=$((TESTS_FAILED+1)); echo "  ❌ 2 bypasses should NOT flag. Got: $out"
else
  TESTS_PASSED=$((TESTS_PASSED+1)); echo "  ✅ 2 bypasses → not flagged (under threshold)"
fi

# Test 3: 3 bypasses but all old → should NOT flag (out of window)
export PATTERN_AUDIT_DECISION_LOG="$LOG3"
out=$("$PA" 2>&1)

TESTS_RUN=$((TESTS_RUN+1))
if echo "$out" | grep -q "SKIP_OLD_GATE"; then
  TESTS_FAILED=$((TESTS_FAILED+1)); echo "  ❌ old bypasses should be out of window. Got: $out"
else
  TESTS_PASSED=$((TESTS_PASSED+1)); echo "  ✅ out-of-window bypasses excluded"
fi

# Test 4: shrunken window → recent 3 entries fall outside
export PATTERN_AUDIT_DECISION_LOG="$LOG1"
export PATTERN_AUDIT_BYPASS_WINDOW_DAYS=2
out=$("$PA" 2>&1)
unset PATTERN_AUDIT_BYPASS_WINDOW_DAYS

TESTS_RUN=$((TESTS_RUN+1))
if echo "$out" | grep -q "SKIP_PHASE_EXIT_GATE"; then
  TESTS_FAILED=$((TESTS_FAILED+1)); echo "  ❌ with 2-day window, no entries should flag. Got: $out"
else
  TESTS_PASSED=$((TESTS_PASSED+1)); echo "  ✅ PATTERN_AUDIT_BYPASS_WINDOW_DAYS honored"
fi

# Test 5: multi-underscore gate name parses correctly
export PATTERN_AUDIT_DECISION_LOG="$LOG4"
out=$("$PA" 2>&1)

TESTS_RUN=$((TESTS_RUN+1))
if echo "$out" | grep -q "SKIP_DDD_BOUNDARY_GATE"; then
  TESTS_PASSED=$((TESTS_PASSED+1)); echo "  ✅ multi-underscore gate name parsed"
else
  TESTS_FAILED=$((TESTS_FAILED+1)); echo "  ❌ expected SKIP_DDD_BOUNDARY_GATE. Got: $out"
fi

# Test 6: configurable threshold
export PATTERN_AUDIT_DECISION_LOG="$LOG2"
export PATTERN_AUDIT_BYPASS_THRESHOLD=2
out=$("$PA" 2>&1)
unset PATTERN_AUDIT_BYPASS_THRESHOLD

TESTS_RUN=$((TESTS_RUN+1))
if echo "$out" | grep -q "SKIP_SYNC_GATE"; then
  TESTS_PASSED=$((TESTS_PASSED+1)); echo "  ✅ PATTERN_AUDIT_BYPASS_THRESHOLD honored (2 → flag)"
else
  TESTS_FAILED=$((TESTS_FAILED+1)); echo "  ❌ with threshold=2 expected SKIP_SYNC_GATE. Got: $out"
fi

# Test 7: exit code always 0 (advisory contract)
export PATTERN_AUDIT_DECISION_LOG="$LOG1"
"$PA" >/dev/null 2>&1
rc=$?
TESTS_RUN=$((TESTS_RUN+1))
if [ "$rc" -eq 0 ]; then
  TESTS_PASSED=$((TESTS_PASSED+1)); echo "  ✅ exit 0 preserved (advisory contract)"
else
  TESTS_FAILED=$((TESTS_FAILED+1)); echo "  ❌ expected exit 0, got $rc"
fi

echo ""
echo "Results: $TESTS_RUN run · $TESTS_PASSED passed · $TESTS_FAILED failed"
[ $TESTS_FAILED -eq 0 ] && exit 0 || exit 1
