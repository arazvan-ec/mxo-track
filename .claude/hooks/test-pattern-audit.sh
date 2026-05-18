#!/usr/bin/env bash
# Test suite for pattern-audit.sh
# Uses CONSULT_LOGS_DIR + PATTERN_AUDIT_REGISTRY + PATTERN_AUDIT_KNOWLEDGE_DIR
# env vars for isolation.
set -uo pipefail

TMPDIR=$(mktemp -d)
trap 'rm -rf "$TMPDIR"' EXIT

LOGS_DIR="$TMPDIR/logs"
KNOWLEDGE_DIR="$TMPDIR/knowledge"
REGISTRY="$TMPDIR/_graduations.yaml"
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

# 3 logs with "foo" (ungraduated) → should be candidate
mkfx "2026-01-01-one.md" "foo"
mkfx "2026-01-02-two.md" "foo"
mkfx "2026-01-03-three.md" "foo"
# 3 logs with "bar" (graduated via registry) → should NOT be candidate
mkfx "2026-01-04-four.md" "bar"
mkfx "2026-01-05-five.md" "bar"
mkfx "2026-01-06-six.md" "bar"

# Registry: bar is graduated; foo is not.
cat > "$REGISTRY" <<'EOF'
tags:
  bar:
    module: bar-patterns.md
    section: "Bar Pattern"

patterns: {}

keyword_mappings: {}
EOF

# Knowledge dir contains a module where "foo" appears as substring
# (for suggestion heuristic — NOT graduation)
cat > "$KNOWLEDGE_DIR/foo-patterns.md" <<'EOF'
# Foo Patterns
Discussion of foo conventions.
EOF
cat > "$KNOWLEDGE_DIR/bar-patterns.md" <<'EOF'
# Bar Patterns
## Bar Pattern
Content.
EOF

PA="$(pwd)/.claude/hooks/pattern-audit.sh"
export CONSULT_LOGS_DIR="$LOGS_DIR"
export PATTERN_AUDIT_REGISTRY="$REGISTRY"
export PATTERN_AUDIT_KNOWLEDGE_DIR="$KNOWLEDGE_DIR"
# Isolate gate-drift detection (added 2026-05-18) so this suite tests only
# the graduation-candidate path. Point at a non-existent decision log so
# the new section short-circuits cleanly.
export PATTERN_AUDIT_DECISION_LOG="$TMPDIR/no-such-decision-log.md"

out=$("$PA" 2>&1)

TESTS_RUN=$((TESTS_RUN+1))
if echo "$out" | grep -q "foo"; then
  TESTS_PASSED=$((TESTS_PASSED+1)); echo "  ✅ foo (ungraduated) appears as candidate"
else
  TESTS_FAILED=$((TESTS_FAILED+1)); echo "  ❌ foo should appear. Got: $out"
fi

TESTS_RUN=$((TESTS_RUN+1))
if echo "$out" | grep -q "^  • bar"; then
  TESTS_FAILED=$((TESTS_FAILED+1)); echo "  ❌ bar (graduated via registry) should NOT appear. Got: $out"
else
  TESTS_PASSED=$((TESTS_PASSED+1)); echo "  ✅ bar (graduated) excluded from candidates"
fi

TESTS_RUN=$((TESTS_RUN+1))
if echo "$out" | grep -q "3 logs"; then
  TESTS_PASSED=$((TESTS_PASSED+1)); echo "  ✅ count reported correctly"
else
  TESTS_FAILED=$((TESTS_FAILED+1)); echo "  ❌ count not in output: $out"
fi

TESTS_RUN=$((TESTS_RUN+1))
if echo "$out" | grep -q "graduate.sh foo"; then
  TESTS_PASSED=$((TESTS_PASSED+1)); echo "  ✅ output suggests graduate.sh command"
else
  TESTS_FAILED=$((TESTS_FAILED+1)); echo "  ❌ expected graduate.sh in output: $out"
fi

TESTS_RUN=$((TESTS_RUN+1))
if echo "$out" | grep -q "foo-patterns.md"; then
  TESTS_PASSED=$((TESTS_PASSED+1)); echo "  ✅ suggestion points to heuristic-matched module"
else
  TESTS_FAILED=$((TESTS_FAILED+1)); echo "  ❌ expected foo-patterns.md in output: $out"
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

# Registry missing → silent (degraded gracefully)
rm -f "$REGISTRY"
mkfx "2026-01-01-one.md" "foo"
mkfx "2026-01-02-two.md" "foo"
mkfx "2026-01-03-three.md" "foo"
out=$("$PA" 2>&1)
TESTS_RUN=$((TESTS_RUN+1))
if echo "$out" | grep -q "foo"; then
  TESTS_PASSED=$((TESTS_PASSED+1)); echo "  ✅ with no registry, all ≥3 tags become candidates"
else
  TESTS_FAILED=$((TESTS_FAILED+1)); echo "  ❌ expected foo even without registry: $out"
fi

echo ""
echo "Results: $TESTS_RUN run · $TESTS_PASSED passed · $TESTS_FAILED failed"
[ $TESTS_FAILED -eq 0 ] && exit 0 || exit 1
