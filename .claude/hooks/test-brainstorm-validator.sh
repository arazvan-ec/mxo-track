#!/usr/bin/env bash
# Test: brainstorm-validator.sh — parallel-conflict detection + Layers H & J
#
# Exercises the `→ files:` declaration parser for both correct conflict
# detection (regression guard) and tolerance of annotations that are not
# actually file paths (e.g., "(no file writes)", "(none)").
#
# Additionally exercises:
#   - Layer H — Prior Art Audit HARD gate when the spec references critical
#     contexts (Domain/Route, Domain/Shipment, Controller/Api/Admin/).
#   - Layer J was removed 2026-04-26; the brainstorm-validator no longer
#     runs the graduation soft-check.

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

# ─────────────────────────────────────────────────────────────────────────────
# Layer H — Prior Art Audit gate
# ─────────────────────────────────────────────────────────────────────────────
# Runs the validator against an H-scenario spec (no plan needed — H is keyed
# off the spec content) and inspects combined stdout+stderr for the H marker
# or for a clean pass. Returns "block-h" when H fires, "clean" otherwise.
run_h_scenario() {
  local spec_path="$1"
  local state_file="$TEST_TMPDIR/state-h.json"
  cat > "$state_file" <<EOF
{
  "evidence": {
    "user_turns": 3,
    "alternatives_proposed": true,
    "user_approved": true,
    "spec_path": "$spec_path"
  }
}
EOF
  local output
  output=$(bash "$VALIDATOR" "$state_file" 2>&1 || true)
  if echo "$output" | grep -qE '^- H:'; then
    echo "block-h"
  else
    echo "clean"
  fi
}

# ── Fixture H1: spec references Route, NO Prior Art Audit section → block ──
SPEC_H1="$TEST_TMPDIR/spec-h1.md"
cat > "$SPEC_H1" <<'EOF'
# Spec

Touches backend/src/Domain/Route/RouteOptimizer.php heavily.

## Approaches

Approach A vs Approach B. Trade-off discussed.

## Existing Functionality Inventory

- RouteOptimizer exists.

## Omission Decisions

- None.

Padding padding padding padding padding padding padding padding padding padding.
Padding padding padding padding padding padding padding padding padding padding.
Padding padding padding padding padding padding padding padding padding padding.
Padding padding padding padding padding padding padding padding padding padding.
Padding padding padding padding padding padding padding padding padding padding.
EOF

# ── Fixture H2: spec references Route AND has Prior Art Audit with ❌ tech-debt
#    AND has Architectural Adversarial Review (required by Layer C sub-invocation) ──
SPEC_H2="$TEST_TMPDIR/spec-h2.md"
cat > "$SPEC_H2" <<'EOF'
# Spec

Touches backend/src/Domain/Route/RouteOptimizer.php heavily.

## Approaches

Approach A vs Approach B. Trade-off discussed.

## Existing Functionality Inventory

- RouteOptimizer exists.

## Prior Art Audit

| Path | Endorsed? |
|---|---|
| backend/src/Domain/Route/RouteOptimizer.php | ❌ tech-debt |

## Architectural Adversarial Review

1. **Q:** Does this refactor respect the DDD boundary between Domain and Infrastructure layers?
   **A:** Yes, the repository interface stays on the Domain side.

2. **Q:** Does the new approach follow the endorsed Facade pattern documented in backend/CLAUDE.md?
   **A:** Yes, the Application service composes Repository calls consistently.

3. **Q:** What tradeoff did we accept on coverage versus simplicity for this iteration?
   **A:** We skipped functional tests for this pass and accepted unit-mock coverage only.

## Omission Decisions

- None.

Padding padding padding padding padding padding padding padding padding padding.
Padding padding padding padding padding padding padding padding padding padding.
Padding padding padding padding padding padding padding padding padding padding.
Padding padding padding padding padding padding padding padding padding padding.
EOF

# ── Fixture H3: spec does NOT reference critical paths → H baseline (no fire) ──
SPEC_H3="$TEST_TMPDIR/spec-h3.md"
cat > "$SPEC_H3" <<'EOF'
# Spec

Touches only backend/src/Infrastructure/Foo.php.

## Approaches

Approach A vs Approach B. Trade-off discussed.

## Existing Functionality Inventory

- None affected.

## Omission Decisions

- None.

Padding padding padding padding padding padding padding padding padding padding.
Padding padding padding padding padding padding padding padding padding padding.
Padding padding padding padding padding padding padding padding padding padding.
Padding padding padding padding padding padding padding padding padding padding.
Padding padding padding padding padding padding padding padding padding padding.
EOF

echo
echo "── brainstorm-validator Layer H (Prior Art Audit) ──"
assert_eq "H1: Route ref + no Prior Art Audit → H blocks"     "block-h" "$(run_h_scenario "$SPEC_H1")"
assert_eq "H2: Route ref + Prior Art Audit w/ tech-debt → pass" "clean"   "$(run_h_scenario "$SPEC_H2")"
assert_eq "H3: no critical paths referenced → H doesn't fire" "clean"   "$(run_h_scenario "$SPEC_H3")"

# Layer J cases REMOVED 2026-04-26 along with the layer itself
# (brainstorm-validator no longer runs the graduation soft-check).
# See /tmp/layer-j-analysis.md for the 4-test failure rationale.

# ─────────────────────────────────────────────────────────────────────────────
# Layer C (sub-invocation of socratic-review-validator) — called from
# brainstorm-validator when the spec references critical paths.
# ─────────────────────────────────────────────────────────────────────────────
# Helper: similar to run_h_scenario but looks for the C-layer error marker.
run_c_scenario() {
  local spec_path="$1"
  local state_file="$TEST_TMPDIR/state-c.json"
  cat > "$state_file" <<EOF
{
  "evidence": {
    "user_turns": 3,
    "alternatives_proposed": true,
    "user_approved": true,
    "spec_path": "$spec_path"
  }
}
EOF
  local output
  output=$(bash "$VALIDATOR" "$state_file" 2>&1 || true)
  if echo "$output" | grep -qE '^- C:'; then
    echo "block-c"
  else
    echo "clean"
  fi
}

# ── Fixture C1: critical path + Prior Art Audit + NO Architectural Adversarial Review → C blocks
SPEC_C1="$TEST_TMPDIR/spec-c1.md"
cat > "$SPEC_C1" <<'EOF'
# Spec

Touches backend/src/Domain/Route/Planner.php.

## Approaches
A vs B.

## Existing Functionality Inventory
- None.

## Prior Art Audit
| Path | Endorsed? |
|---|---|
| backend/src/Domain/Route/Planner.php | ❌ tech-debt |

## Omission Decisions
- None.

Padding padding padding padding padding padding padding padding padding padding.
Padding padding padding padding padding padding padding padding padding padding.
Padding padding padding padding padding padding padding padding padding padding.
EOF

# ── Fixture C2: critical path + Prior Art Audit + valid Architectural Adversarial Review → pass
SPEC_C2="$TEST_TMPDIR/spec-c2.md"
cat > "$SPEC_C2" <<'EOF'
# Spec

Touches backend/src/Domain/Route/Planner.php.

## Approaches
A vs B.

## Existing Functionality Inventory
- None.

## Prior Art Audit
| Path | Endorsed? |
|---|---|
| backend/src/Domain/Route/Planner.php | ❌ tech-debt |

## Architectural Adversarial Review

1. **Q:** Does this refactor respect the DDD boundary between Domain and Infrastructure?
   **A:** Yes, the Repository stays in Domain.

2. **Q:** Does the approach match the endorsed Facade pattern for this subsystem?
   **A:** Yes.

3. **Q:** What tradeoff did we accept on test coverage versus simplicity?
   **A:** Skipped functional tests for this iteration.

## Omission Decisions
- None.

Padding padding padding padding padding padding padding padding padding padding.
Padding padding padding padding padding padding padding padding padding padding.
Padding padding padding padding padding padding padding padding padding padding.
EOF

echo
echo "── brainstorm-validator Layer C (architectural review sub-invocation) ──"
assert_eq "C1: critical path + no Architectural Adversarial Review → C blocks" "block-c" "$(run_c_scenario "$SPEC_C1")"
assert_eq "C2: critical path + valid Architectural Adversarial Review → pass"  "clean"   "$(run_c_scenario "$SPEC_C2")"

# ─────────────────────────────────────────────────────────────────────────────
# Layer K — Anti-Reduction gate
# Triggers when the spec contains reduction markers (MVP, "minimum viable",
# "fase 1", "v0", etc.) outside fenced code blocks. Requires a
# `## Maximal Version Considered` section with 4 bullets including an
# "Independent superiority" bullet that is NOT solely cost-language.
# ─────────────────────────────────────────────────────────────────────────────
run_k_scenario() {
  local spec_path="$1"
  local state_file="$TEST_TMPDIR/state-k.json"
  cat > "$state_file" <<EOF
{
  "evidence": {
    "user_turns": 3,
    "alternatives_proposed": true,
    "user_approved": true,
    "spec_path": "$spec_path"
  }
}
EOF
  local output
  output=$(bash "$VALIDATOR" "$state_file" 2>&1 || true)
  if echo "$output" | grep -qE '^- K:'; then
    echo "block-k"
  else
    echo "clean"
  fi
}

# ── Fixture K1: spec without any reduction markers → no Layer K activity ──
SPEC_K1="$TEST_TMPDIR/spec-k1.md"
cat > "$SPEC_K1" <<'EOF'
# Spec

## Approaches
Approach A vs Approach B. Trade-off discussed.

## Existing Functionality Inventory
- None affected.

## Omission Decisions
- None.

Padding padding padding padding padding padding padding padding padding padding.
Padding padding padding padding padding padding padding padding padding padding.
Padding padding padding padding padding padding padding padding padding padding.
Padding padding padding padding padding padding padding padding padding padding.
EOF

# ── Fixture K2: spec uses "MVP" marker but lacks Maximal Version Considered → K blocks ──
SPEC_K2="$TEST_TMPDIR/spec-k2.md"
cat > "$SPEC_K2" <<'EOF'
# Spec

## Approaches
We will start with an MVP and grow on demand.

## Existing Functionality Inventory
- None affected.

## Omission Decisions
- None.

Padding padding padding padding padding padding padding padding padding padding.
Padding padding padding padding padding padding padding padding padding padding.
Padding padding padding padding padding padding padding padding padding padding.
Padding padding padding padding padding padding padding padding padding padding.
EOF

# ── Fixture K3: marker + section + cost-only superiority bullet → K blocks ──
SPEC_K3="$TEST_TMPDIR/spec-k3.md"
cat > "$SPEC_K3" <<'EOF'
# Spec

## Approaches
We will start with an MVP.

## Maximal Version Considered
- Maximal version: full 400-entry bootstrap with 10 integrations.
- Why not chosen: Test 3 fails on cost.
- Proposed (reduced) version: empty start, on-demand graduation.
- Independent superiority: la versión propuesta es más barata, usa menos tokens y tiene menos líneas.

## Existing Functionality Inventory
- None.

## Omission Decisions
- None.

Padding padding padding padding padding padding padding padding padding padding.
Padding padding padding padding padding padding padding padding padding padding.
Padding padding padding padding padding padding padding padding padding padding.
EOF

# ── Fixture K4: marker + section + valid non-cost superiority bullet → pass ──
SPEC_K4="$TEST_TMPDIR/spec-k4.md"
cat > "$SPEC_K4" <<'EOF'
# Spec

## Approaches
We will start with an MVP.

## Maximal Version Considered
- Maximal version: full 400-entry bootstrap with 10 integrations.
- Why not chosen: Test 3 fails — automated extraction produces noise above signal, ~80% of entries become formulaic and unread.
- Proposed (reduced) version: empty start, on-demand graduation.
- Independent superiority: la graduación bajo demanda solo registra términos que causaron confusión real (3+ ocurrencias en logs), garantizando que cada entrada documente un drift verificado en lugar de ruido especulativo. Mismo patrón que `_graduations.yaml`.

## Existing Functionality Inventory
- None.

## Omission Decisions
- None.

Padding padding padding padding padding padding padding padding padding padding.
Padding padding padding padding padding padding padding padding padding padding.
Padding padding padding padding padding padding padding padding padding padding.
EOF

echo
echo "── brainstorm-validator Layer K (anti-reduction) ──"
assert_eq "K1: no reduction markers → K does not fire"                            "clean"   "$(run_k_scenario "$SPEC_K1")"
assert_eq "K2: MVP marker without Maximal Version Considered section → K blocks"  "block-k" "$(run_k_scenario "$SPEC_K2")"
assert_eq "K3: marker + section + cost-only superiority bullet → K blocks"        "block-k" "$(run_k_scenario "$SPEC_K3")"
assert_eq "K4: marker + section + non-cost superiority bullet → pass"             "clean"   "$(run_k_scenario "$SPEC_K4")"

summary
