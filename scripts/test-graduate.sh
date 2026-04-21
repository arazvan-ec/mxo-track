#!/usr/bin/env bash
# test-graduate.sh — tests for scripts/graduate.sh
set -uo pipefail

REPO_ROOT="$(cd "$(dirname "$(readlink -f "$0")")/.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/graduate.sh"

PASS=0
FAIL=0

setup_fixture() {
  FIX_DIR=$(mktemp -d)
  KNOW_DIR="$FIX_DIR/knowledge"
  mkdir -p "$KNOW_DIR"

  cat > "$FIX_DIR/registry.yaml" <<'EOF'
tags:
  glass-overlay:
    module: ui-layout-contracts.md
    section: "Positioning Hierarchy"

patterns:
  workflow-script-conventions:
    module: skills.md
    section: "Workflow Script Conventions"

keyword_mappings:
  glass: glass-overlay
EOF

  cat > "$KNOW_DIR/ui-layout-contracts.md" <<'EOF'
# UI Layout Contracts

## Positioning Hierarchy
Content.

## Other Section
More content.
EOF

  cat > "$KNOW_DIR/route-optimization.md" <<'EOF'
# Route Optimization

## Providers
Content.
EOF

  cat > "$KNOW_DIR/skills.md" <<'EOF'
# Skills

## Workflow Script Conventions
Content.
EOF

  # Fake consult.sh that always returns count=5 for anything
  CONSULT="$FIX_DIR/consult.sh"
  cat > "$CONSULT" <<'EOF'
#!/usr/bin/env bash
if [ "$1" = "stats" ]; then
  cat <<TXT
  foo                            : 5 logs ⚠ PATTERN (≥3)
  bar                            : 5 logs ⚠ PATTERN (≥3)
  existing-tag                   : 5 logs ⚠ PATTERN (≥3)
  new-tag                        : 5 logs ⚠ PATTERN (≥3)
  low-count-tag                  : 1 logs
  star-tag                       : 5 logs ⚠ PATTERN (≥3)
  new-pattern                    : 5 logs ⚠ PATTERN (≥3)
  glass-overlay                  : 6 logs ⚠ PATTERN (≥3)
TXT
fi
EOF
  chmod +x "$CONSULT"

  export GRADUATE_REGISTRY="$FIX_DIR/registry.yaml"
  export GRADUATE_KNOWLEDGE_DIR="$KNOW_DIR"
  export GRADUATE_CONSULT="$CONSULT"
}

teardown() {
  rm -rf "$FIX_DIR"
}

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

run() {
  "$SCRIPT" "$@" 2>&1
}

echo "=== graduate.sh tests ==="

setup_fixture

# T1: missing name
out=$(run --module=x.md --section=Y 2>&1); code=$?
assert "T1 missing name → exit 2" "2" "$code"

# T2: missing module
out=$(run foo --section=Y 2>&1); code=$?
assert "T2 missing module → exit 2" "2" "$code"

# T3: missing section
out=$(run foo --module=x.md 2>&1); code=$?
assert "T3 missing section → exit 2" "2" "$code"

# T4: module not found
out=$(run foo --module=nonexistent.md --section=X 2>&1); code=$?
assert "T4 module not found → exit 2" "2" "$code"

# T5: section not found
out=$(run foo --module=ui-layout-contracts.md --section="Nonexistent" 2>&1); code=$?
assert "T5 section not found → exit 2" "2" "$code"

# T6: low count without --force
out=$(run low-count-tag --module=ui-layout-contracts.md --section="Positioning Hierarchy" 2>&1); code=$?
assert "T6 low count → exit 2" "2" "$code"

# T7: low count with --force → should proceed (but may skip if already present in T8's setup)
out=$(run low-count-tag --module=ui-layout-contracts.md --section="Positioning Hierarchy" --force 2>&1); code=$?
assert "T7 low count --force → exit 0" "0" "$code"

# T8: idempotent — re-graduate same tag
out=$(run low-count-tag --module=ui-layout-contracts.md --section="Positioning Hierarchy" --force 2>&1); code=$?
assert "T8 re-graduate → exit 1 (skip)" "1" "$code"

# T9: already in tags (glass-overlay was in fixture)
out=$(run glass-overlay --module=ui-layout-contracts.md --section="Positioning Hierarchy" 2>&1); code=$?
assert "T9 pre-existing in fixture → exit 1" "1" "$code"

# T10: new tag success
out=$(run new-tag --module=ui-layout-contracts.md --section="Other Section" 2>&1); code=$?
assert "T10 new tag → exit 0" "0" "$code"
grep -q "^  new-tag:$" "$GRADUATE_REGISTRY"; code=$?
assert "T10b entry written" "0" "$code"

# T11: star section (*)
out=$(run star-tag --module=route-optimization.md --section="*" 2>&1); code=$?
assert "T11 star section → exit 0" "0" "$code"
grep -q 'section: "\*"' "$GRADUATE_REGISTRY"; code=$?
assert "T11b star section written" "0" "$code"

# T12: --pattern writes under patterns:
out=$(run new-pattern --module=skills.md --section="Workflow Script Conventions" --pattern 2>&1); code=$?
assert "T12 --pattern → exit 0" "0" "$code"
# Verify it went into patterns: (should appear after 'patterns:' line, before 'keyword_mappings:')
added_to_patterns=$(awk '
  /^patterns:$/ { section="patterns"; next }
  /^keyword_mappings:$/ { section="km"; next }
  section=="patterns" && /^  new-pattern:$/ { print "yes"; exit }
' "$GRADUATE_REGISTRY")
assert "T12b pattern went under patterns:" "yes" "$added_to_patterns"

# T13: verify registry still parses as well-formed YAML (key format)
# Simple check: every "  X:" under tags/patterns has "    module:" and "    section:"
malformed=$(awk '
  /^tags:$/ { s="t"; next }
  /^patterns:$/ { s="p"; next }
  /^keyword_mappings:$/ { s="k"; next }
  (s=="t" || s=="p") && /^  [a-z]/ && /:$/ {
    name=$1
    getline nxt
    getline nxt2
    if (nxt !~ /^    module:/ || nxt2 !~ /^    section:/) { print name }
  }
' "$GRADUATE_REGISTRY")
assert "T13 registry well-formed (no malformed entries)" "" "$malformed"

teardown

echo ""
echo "── Summary ──"
echo "PASS: $PASS"
echo "FAIL: $FAIL"
[ "$FAIL" -eq 0 ] && exit 0 || exit 1
