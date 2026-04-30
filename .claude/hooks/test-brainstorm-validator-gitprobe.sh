#!/usr/bin/env bash
# test-brainstorm-validator-gitprobe.sh — smoke for brainstorm-validator git-probe fallback.
# Critical: user_approved=false must STILL block even when spec is committed.

set -uo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"

pass=0; fail=0
assert_rc() {
  local label="$1" actual="$2" expected="$3"
  if [ "$actual" = "$expected" ]; then
    echo "  ✅ $label"; pass=$((pass+1))
  else
    echo "  ❌ $label (got rc=$actual, expected rc=$expected)"; fail=$((fail+1))
  fi
}

# brainstorm-validator returns 2=block, 1=soft warn, 0=pass-clean.
# Smoke contract: "validator does not block" = rc != 2.
assert_not_block() {
  local label="$1" rc="$2"
  if [ "$rc" != "2" ]; then
    echo "  ✅ $label (rc=$rc, non-blocking)"; pass=$((pass+1))
  else
    echo "  ❌ $label (got rc=$rc — BLOCKED, expected non-blocking)"; fail=$((fail+1))
  fi
}

TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT
cd "$TMP" || exit 1
git init -q
git config user.email t@t && git config user.name t
git config commit.gpgsign false

# Mirror repo-root paths expected by the validator + libs.
mkdir -p .claude/hooks/lib .claude/hooks/validators docs/superpowers/specs docs/knowledge
cp "$REPO_ROOT/.claude/hooks/lib/git-probe.sh" .claude/hooks/lib/
cp "$REPO_ROOT/.claude/hooks/lib/section-validator.sh" .claude/hooks/lib/ 2>/dev/null || true
cp "$REPO_ROOT/.claude/hooks/lib/ddd-boundaries.sh" .claude/hooks/lib/ 2>/dev/null || true
cp "$REPO_ROOT/.claude/hooks/lib/files-decl-parser.sh" .claude/hooks/lib/ 2>/dev/null || true
cp "$REPO_ROOT/.claude/hooks/validators/brainstorm-validator.sh" .claude/hooks/validators/
cp "$REPO_ROOT/.claude/hooks/validators/socratic-review-validator.sh" .claude/hooks/validators/ 2>/dev/null || true
[ -f "$REPO_ROOT/docs/knowledge/_ddd-boundaries.yaml" ] && cp "$REPO_ROOT/docs/knowledge/_ddd-boundaries.yaml" docs/knowledge/
VALIDATOR="$TMP/.claude/hooks/validators/brainstorm-validator.sh"

cat > docs/superpowers/specs/full-spec.md <<'SPEC'
# Spec — full version test fixture

## Problem
Some recurring problem that motivated this work; padded so the file
reaches the minimum 500 bytes the validator enforces.

## Approach Chosen
Stuff happens. The chosen approach has tradeoffs and alternatives.

## Alternatives Rejected
**A. First alternative.** Description with a Trade-off rationale.
**B. Second alternative.** Another path with a Ventaja and Desventaja
discussion that satisfies the keyword check.

## Existing Functionality Inventory
| Element | Decision |
|---------|----------|
| None | new |

## Omission Decisions
None — all inventory items addressed.

## Norms
- The change must preserve invariants and shall never break callers.

## Safeguards
| Risk | Mitigation |
|------|------------|
| Some risk | Some mitigation |
| Another risk | Another mitigation |
SPEC
git add . && git commit -q -m seed 2>/dev/null

mkstate() {
  local alt="$1" approved="$2" spec="$3" turns="${4:-1}"
  cat > "$TMP/state.json" <<JSON
{
  "current_phase": "brainstorming",
  "evidence": {
    "spec_path": "$spec",
    "user_turns": $turns,
    "alternatives_proposed": $alt,
    "user_approved": $approved
  }
}
JSON
}

echo "Test 1: alt=false + approved=true + committed spec with sections → validator does not block"
mkstate "false" "true" "docs/superpowers/specs/full-spec.md"
REPO="$TMP" bash "$VALIDATOR" "$TMP/state.json" >/dev/null 2>&1; rc=$?
assert_not_block "git-probe fallback non-blocking" "$rc"

echo "Test 2: alt=false + approved=FALSE + committed spec → validator BLOCKS (user_approved must remain mandatory)"
mkstate "false" "false" "docs/superpowers/specs/full-spec.md"
REPO="$TMP" bash "$VALIDATOR" "$TMP/state.json" >/dev/null 2>&1; rc=$?
assert_rc "approved=false still blocks" "$rc" "2"

echo "Test 3: alt=false + approved=true + spec MISSING required sections → blocks (probe doesn't paper over content gaps)"
cat > docs/superpowers/specs/skinny.md <<'SPEC'
# Spec — skinny version with enough size to clear the 500-byte minimum
# but missing the required Norms / Safeguards / Alternatives sections
# that the git-probe expects. Padding included.

## Approach Chosen
Stuff happens here. Trade-off considered. Problema descrito.
SPEC
git add . && git commit -q -m skinny 2>/dev/null
mkstate "false" "true" "docs/superpowers/specs/skinny.md"
REPO="$TMP" bash "$VALIDATOR" "$TMP/state.json" >/dev/null 2>&1; rc=$?
assert_rc "skinny spec → blocks" "$rc" "2"

echo "Test 4: alt=true (no probe needed) + approved=true → does not block"
mkstate "true" "true" "docs/superpowers/specs/full-spec.md"
REPO="$TMP" bash "$VALIDATOR" "$TMP/state.json" >/dev/null 2>&1; rc=$?
assert_not_block "alt=true non-blocking" "$rc"

echo
echo "Total: $pass passed, $fail failed"
[ "$fail" -eq 0 ]
