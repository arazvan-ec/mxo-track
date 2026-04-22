#!/usr/bin/env bash
# Test: brainstorm-validator.sh — parallel-conflict detection
#
# Exercises the `→ files:` declaration parser for both correct conflict
# detection (regression guard) and tolerance of annotations that are not
# actually file paths (e.g., "(no file writes)", "(none)").

set -euo pipefail

REPO="/home/user/mxo-track"
VALIDATOR="$REPO/.claude/hooks/validators/brainstorm-validator.sh"
TMPDIR=$(mktemp -d)
trap 'rm -rf "$TMPDIR"' EXIT

PASS=0
FAIL=0

# Minimal spec that satisfies all non-parallel checks (keyword, size, sections)
SPEC="$TMPDIR/spec.md"
cat > "$SPEC" <<'EOF'
# Spec

## Approaches

Approach A vs Approach B. Trade-off discussed.

## Existing Functionality Inventory

- None affected.

## Omission Decisions

- No omissions — all inventory items addressed.

Padding padding padding padding padding padding padding padding padding.
Padding padding padding padding padding padding padding padding padding.
Padding padding padding padding padding padding padding padding padding.
Padding padding padding padding padding padding padding padding padding.
Padding padding padding padding padding padding padding padding padding.
Padding padding padding padding padding padding padding padding padding.
EOF

# Helper: run validator against a plan fixture; return 0 if CONFLICTO PARALELO
# is reported, 1 otherwise. Other validator output (warnings, unrelated errors)
# is not considered.
has_conflict() {
  local plan_path="$1"
  local state_file="$TMPDIR/state.json"
  cat > "$state_file" <<EOF
{
  "evidence": {
    "user_turns": 3,
    "alternatives_proposed": true,
    "user_approved": true,
    "spec_path": "$SPEC",
    "plan_path": "$plan_path"
  }
}
EOF
  local output
  output=$(bash "$VALIDATOR" "$state_file" 2>&1 || true)
  echo "$output" | grep -q "CONFLICTO PARALELO"
}

assert() {
  local name="$1"
  local expected="$2"  # "conflict" or "clean"
  local plan_path="$3"
  if has_conflict "$plan_path"; then
    actual="conflict"
  else
    actual="clean"
  fi
  if [ "$actual" = "$expected" ]; then
    echo "  ✅ $name"
    PASS=$((PASS + 1))
  else
    echo "  ❌ $name — expected=$expected actual=$actual"
    FAIL=$((FAIL + 1))
  fi
}

# ── Fixture 1: true conflict (regression guard) ──
PLAN1="$TMPDIR/plan1.md"
cat > "$PLAN1" <<'EOF'
# Plan

### [parallel] Wave 1
- **1a** · → files: a.ts
- **1b** · → files: a.ts
EOF

# ── Fixture 2: no conflict baseline (disjoint files) ──
PLAN2="$TMPDIR/plan2.md"
cat > "$PLAN2" <<'EOF'
# Plan

### [parallel] Wave 1
- **1a** · → files: a.ts
- **1b** · → files: b.ts
EOF

# ── Fixture 3: parenthesized annotation (bug fix) ──
PLAN3="$TMPDIR/plan3.md"
cat > "$PLAN3" <<'EOF'
# Plan

### [parallel] Wave 1
- **1a** · → files: (no file writes)
- **1b** · → files: (no file writes)
EOF

# ── Fixture 4: mixed — path + annotation in same payload ──
PLAN4="$TMPDIR/plan4.md"
cat > "$PLAN4" <<'EOF'
# Plan

### [parallel] Wave 1
- **1a** · → files: a.ts, (annotation)
- **1b** · → files: b.ts
EOF

echo "── brainstorm-validator parallel-conflict parser ──"
assert "regression: real conflict on shared path → detected" "conflict" "$PLAN1"
assert "baseline: disjoint paths → no conflict" "clean" "$PLAN2"
assert "fix: two parenthesized annotations → no conflict" "clean" "$PLAN3"
assert "fix: path + annotation mix → annotation ignored" "clean" "$PLAN4"

echo
echo "── Results ──"
echo "  Passed: $PASS"
echo "  Failed: $FAIL"
[ "$FAIL" -eq 0 ]
