#!/usr/bin/env bash
# Test: pre-agent-check.sh — three gates
#   1. clean-repo gate (existing)
#   2. classify-validation gate (existing, warn-only via systemMessage)
#   3. Norms & Safeguards in agent prompt (new — Layer Agent)

set -euo pipefail

REPO="/home/user/mxo-track"
HOOK="$REPO/.claude/hooks/pre-agent-check.sh"

# shellcheck source=./lib/test-harness.sh
source "$REPO/.claude/hooks/lib/test-harness.sh"
init_harness

# Run hook with constructed JSON input. Returns combined output (so the test
# can grep deny / systemMessage / permission keywords). The hook always
# exits 0 (it emits JSON instructions instead of using exit codes).
run_hook() {
  local subagent_type="$1"
  local prompt="$2"
  echo "{\"tool_input\":{\"subagent_type\":\"$subagent_type\",\"prompt\":$(jq -Rs . <<<"$prompt")}}" \
    | bash "$HOOK" 2>&1
}

# Helper: did the hook return a deny decision (Gate 1 or Gate 3 block)?
has_deny() {
  local out="$1"
  echo "$out" | grep -q '"permissionDecision":"deny"'
}

# Test setup: repo state. Gate 1 fires when REPO has uncommitted changes.
# We can't reliably get the repo clean during tests (this very test exists
# in an active editing session), so we test Gate 1 by overriding REPO via
# a fixture path with controlled state, and skip clean-state gate tests
# that depend on the real repo. Pre-agent-check uses a hardcoded REPO=
# constant, so we patch via a wrapper that sources the hook with REPO
# overridden.

# Approach: create a fixture repo, patch the hook's REPO var to point there.
make_fixture_repo() {
  local dir="$1"
  local dirty="$2"  # "clean" or "dirty"
  mkdir -p "$dir"
  (
    cd "$dir"
    git init -q -b main 2>/dev/null
    git config user.email t@t
    git config user.name t
    git config commit.gpgsign false
    echo seed > seed.txt
    git add seed.txt
    git commit -q -m seed
    if [ "$dirty" = "dirty" ]; then
      echo modified > seed.txt  # uncommitted change
    fi
  )
}

# Run hook against a custom REPO (uses sed substitution — fragile but
# scoped; the hook's REPO var is on a single recognizable line).
run_hook_with_repo() {
  local repo_root="$1"
  local subagent_type="$2"
  local prompt="$3"
  local hook_copy="$TEST_TMPDIR/pre-agent-check.sh"
  sed "s|^REPO=.*|REPO=\"$repo_root\"|" "$HOOK" > "$hook_copy"
  chmod +x "$hook_copy"
  echo "{\"tool_input\":{\"subagent_type\":\"$subagent_type\",\"prompt\":$(jq -Rs . <<<"$prompt")}}" \
    | bash "$hook_copy" 2>&1
}

# ── A1: dirty repo + general-purpose → deny (Gate 1 regression) ──
DIRTY_REPO="$TEST_TMPDIR/dirty"
make_fixture_repo "$DIRTY_REPO" dirty
OUT=$(run_hook_with_repo "$DIRTY_REPO" "general-purpose" "## Norms
- The agent must verify output before reporting.
## Safeguards
| Risk | Mitigation |
| risk | mitigation |
")
has_deny "$OUT" && echo "  ✅ A1: dirty repo → deny" && PASS=$((PASS+1)) || { echo "  ❌ A1 — expected deny got: $OUT"; FAIL=$((FAIL+1)); }

# ── A2: clean repo + Explore → no deny (read-only exempt) ──
CLEAN_REPO="$TEST_TMPDIR/clean"
make_fixture_repo "$CLEAN_REPO" clean
OUT=$(run_hook_with_repo "$CLEAN_REPO" "Explore" "do anything, no norms required")
if has_deny "$OUT"; then
  echo "  ❌ A2 — Explore should be exempt, got deny: $OUT"
  FAIL=$((FAIL+1))
else
  echo "  ✅ A2: clean + Explore → no deny"
  PASS=$((PASS+1))
fi

# ── A3: clean repo + general-purpose + prompt without Norms → deny (Gate 3) ──
OUT=$(run_hook_with_repo "$CLEAN_REPO" "general-purpose" "Just do the task. No structured sections.")
has_deny "$OUT" && echo "  ✅ A3: missing Norms → deny" && PASS=$((PASS+1)) || { echo "  ❌ A3 — expected deny got: $OUT"; FAIL=$((FAIL+1)); }

# ── A4: clean + inline Norms+Safeguards → no deny ──
OUT=$(run_hook_with_repo "$CLEAN_REPO" "general-purpose" "## Operations
- Edit file X.

## Norms
- The agent must follow project conventions.
- Tests shall pass before reporting done.

## Safeguards
| Risk | Mitigation |
|------|------------|
| Drift in parallel waves | Touch only declared files |

## Verification
- Run lint.")
if has_deny "$OUT"; then
  echo "  ❌ A4 — valid inline should pass, got deny: $OUT"
  FAIL=$((FAIL+1))
else
  echo "  ✅ A4: inline Norms+Safeguards → no deny"
  PASS=$((PASS+1))
fi

# ── A5: clean + spec-reference Norms+Safeguards → no deny ──
OUT=$(run_hook_with_repo "$CLEAN_REPO" "general-purpose" "## Operations
- Edit X.

## Norms
See docs/superpowers/specs/2026-04-28-agent-prompt-validator-design.md § Norms for invariants.

## Safeguards
See docs/superpowers/specs/2026-04-28-agent-prompt-validator-design.md § Safeguards for risk-mitigation pairs.

## Verification
- Run lint.")
if has_deny "$OUT"; then
  echo "  ❌ A5 — valid spec-ref should pass, got deny: $OUT"
  FAIL=$((FAIL+1))
else
  echo "  ✅ A5: spec-reference Norms+Safeguards → no deny"
  PASS=$((PASS+1))
fi

# ── A6: clean + Norms heading but empty content → deny ──
OUT=$(run_hook_with_repo "$CLEAN_REPO" "general-purpose" "## Operations
- Edit X.

## Norms
This section describes things in passing prose.
The agent does some work efficiently.

## Safeguards
Things might fail sometimes.

## Verification
- Run lint.")
has_deny "$OUT" && echo "  ✅ A6: Norms without imperative or reference → deny" && PASS=$((PASS+1)) || { echo "  ❌ A6 — expected deny got: $OUT"; FAIL=$((FAIL+1)); }

echo
summary
