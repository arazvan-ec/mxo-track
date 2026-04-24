#!/usr/bin/env bash
# Test: brainstorm-validator.sh — parallel-conflict detection
#
# Exercises the `→ files:` declaration parser for both correct conflict
# detection (regression guard) and tolerance of annotations that are not
# actually file paths (e.g., "(no file writes)", "(none)").

set -euo pipefail

REPO="/home/user/mxo-track"
VALIDATOR="$REPO/.claude/hooks/validators/brainstorm-validator.sh"

# shellcheck source=./lib/test-harness.sh
source "$REPO/.claude/hooks/lib/test-harness.sh"
init_harness

# Minimal spec that satisfies all non-parallel checks (keyword, size, sections)
SPEC="$TEST_TMPDIR/spec.md"
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
  local state_file="$TEST_TMPDIR/state.json"
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

assert_conflict_outcome() {
  local name="$1"
  local expected="$2"  # "conflict" or "clean"
  local plan_path="$3"
  local actual
  if has_conflict "$plan_path"; then
    actual="conflict"
  else
    actual="clean"
  fi
  assert_eq "$name" "$expected" "$actual"
}

# ── Fixture 1: true conflict (regression guard) ──
PLAN1="$TEST_TMPDIR/plan1.md"
cat > "$PLAN1" <<'EOF'
# Plan

### [parallel] Wave 1
- **1a** · → files: a.ts
- **1b** · → files: a.ts
EOF

# ── Fixture 2: no conflict baseline (disjoint files) ──
PLAN2="$TEST_TMPDIR/plan2.md"
cat > "$PLAN2" <<'EOF'
# Plan

### [parallel] Wave 1
- **1a** · → files: a.ts
- **1b** · → files: b.ts
EOF

# ── Fixture 3: parenthesized annotation (bug fix) ──
PLAN3="$TEST_TMPDIR/plan3.md"
cat > "$PLAN3" <<'EOF'
# Plan

### [parallel] Wave 1
- **1a** · → files: (no file writes)
- **1b** · → files: (no file writes)
EOF

# ── Fixture 4: mixed — path + annotation in same payload ──
PLAN4="$TEST_TMPDIR/plan4.md"
cat > "$PLAN4" <<'EOF'
# Plan

### [parallel] Wave 1
- **1a** · → files: a.ts, (annotation)
- **1b** · → files: b.ts
EOF

# ── Fixture 5: parenthesized legitimate list — should still detect paths ──
PLAN5="$TEST_TMPDIR/plan5.md"
cat > "$PLAN5" <<'EOF'
# Plan

### [parallel] Wave 1
- **1a** · → files: (a.ts, b.ts)
- **1b** · → files: (c.ts, a.ts)
EOF

# ── Fixture 6: bare filenames without extension (Makefile sentinel) ──
PLAN6="$TEST_TMPDIR/plan6.md"
cat > "$PLAN6" <<'EOF'
# Plan

### [parallel] Wave 1
- **1a** · → files: Makefile
- **1b** · → files: Makefile
EOF

echo "── brainstorm-validator parallel-conflict parser ──"
assert_conflict_outcome "regression: real conflict on shared path → detected" "conflict" "$PLAN1"
assert_conflict_outcome "baseline: disjoint paths → no conflict" "clean" "$PLAN2"
assert_conflict_outcome "fix: two parenthesized annotations → no conflict" "clean" "$PLAN3"
assert_conflict_outcome "fix: path + annotation mix → annotation ignored" "clean" "$PLAN4"
assert_conflict_outcome "fix: parenthesized legitimate list → conflict detected on shared a.ts" "conflict" "$PLAN5"
assert_conflict_outcome "fix: bare Makefile repeated → conflict detected via sentinel" "conflict" "$PLAN6"

summary
