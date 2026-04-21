#!/usr/bin/env bash
# test-validate-graduations.sh — tests for validate-graduations.sh
set -uo pipefail

REPO_ROOT="$(cd "$(dirname "$(readlink -f "$0")")/.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/validate-graduations.sh"

PASS=0
FAIL=0

assert() {
  local desc="$1" expected="$2" actual="$3"
  if [ "$expected" = "$actual" ]; then
    echo "  ✓ $desc"
    PASS=$((PASS+1))
  else
    echo "  ✗ $desc (expected='$expected' actual='$actual')"
    FAIL=$((FAIL+1))
  fi
}

setup_valid_fixture() {
  FIX=$(mktemp -d)
  mkdir -p "$FIX/knowledge"
  cat > "$FIX/knowledge/a.md" <<'EOF'
# A
## S1
## S2 with stuff
EOF
  cat > "$FIX/knowledge/b.md" <<'EOF'
# B
EOF
  cat > "$FIX/registry.yaml" <<'EOF'
tags:
  one:
    module: a.md
    section: "S1"
  two:
    module: a.md
    section: "S2 with stuff"
  three:
    module: b.md
    section: "*"

patterns:
  pat1:
    module: a.md
    section: "S1"

keyword_mappings:
  foo: one
  bar: two
EOF
  export VALIDATE_GRADUATIONS_REGISTRY="$FIX/registry.yaml"
  export VALIDATE_GRADUATIONS_KNOWLEDGE_DIR="$FIX/knowledge"
}

setup_broken_fixture() {
  FIX=$(mktemp -d)
  mkdir -p "$FIX/knowledge"
  cat > "$FIX/knowledge/a.md" <<'EOF'
# A
## S1
EOF
  cat > "$FIX/registry.yaml" <<'EOF'
tags:
  missing-section:
    module: a.md
    section: "Nonexistent"
  missing-module:
    module: ghost.md
    section: "S1"

patterns: {}

keyword_mappings: {}
EOF
  export VALIDATE_GRADUATIONS_REGISTRY="$FIX/registry.yaml"
  export VALIDATE_GRADUATIONS_KNOWLEDGE_DIR="$FIX/knowledge"
}

teardown() { rm -rf "$FIX"; }

echo "=== validate-graduations.sh tests ==="

# T1: valid fixture → exit 0
setup_valid_fixture
out=$(bash "$SCRIPT" 2>&1); code=$?
assert "T1 valid registry → exit 0" "0" "$code"
echo "$out" | grep -q "valid"; code=$?
assert "T1b output says 'valid'" "0" "$code"
teardown

# T2: broken fixture → exit 1, reports both errors
setup_broken_fixture
out=$(bash "$SCRIPT" 2>&1); code=$?
assert "T2 broken registry → exit 1" "1" "$code"
echo "$out" | grep -q "missing-section"; code=$?
assert "T2b reports missing section" "0" "$code"
echo "$out" | grep -q "missing-module"; code=$?
assert "T2c reports missing module" "0" "$code"
teardown

# T3: missing registry file → exit 2
export VALIDATE_GRADUATIONS_REGISTRY="/nonexistent/registry.yaml"
out=$(bash "$SCRIPT" 2>&1); code=$?
assert "T3 missing registry → exit 2" "2" "$code"
unset VALIDATE_GRADUATIONS_REGISTRY

# T4: real-project registry must validate clean (smoke test, after curation)
#     NOTE: this is skipped in test-time since Wave 3 curation hasn't happened yet.
#     Run manually post-curation: bash scripts/validate-graduations.sh
#     Left intentionally as manual-only check for TDD determinism.

echo ""
echo "── Summary ──"
echo "PASS: $PASS"
echo "FAIL: $FAIL"
[ "$FAIL" -eq 0 ] && exit 0 || exit 1
