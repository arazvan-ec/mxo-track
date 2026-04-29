#!/usr/bin/env bash
# Test: sync-validator.sh — plan↔diff drift detection
#
# Constructs a temporary git repo per test case to produce predictable diffs,
# writes a fixture plan with `→ files:` declarations, and asserts the
# validator's exit code and drift output.

set -euo pipefail

REPO="/home/user/mxo-track"
VALIDATOR="$REPO/.claude/hooks/validators/sync-validator.sh"

# shellcheck source=./lib/test-harness.sh
source "$REPO/.claude/hooks/lib/test-harness.sh"
init_harness

# Build a fresh git repo inside TEST_TMPDIR with two branches: main (baseline)
# and feature (with the touched files committed). The validator will be invoked
# with REPO_ROOT pointed at this fixture repo so `git diff origin/main...HEAD`
# inside it produces the expected diff.
build_fixture_repo() {
  local repo_dir="$1"
  shift
  local touched_files=("$@")

  mkdir -p "$repo_dir"
  (
    cd "$repo_dir"
    git init -q -b main 2>/dev/null
    git config user.email test@test
    git config user.name test
    git config commit.gpgsign false
    git config tag.gpgsign false
    echo seed > seed.txt
    git add seed.txt
    git commit -q -m seed
    # Simulate origin/main by creating a remote-tracking ref pointing at HEAD
    git update-ref refs/remotes/origin/main HEAD
    git checkout -q -b feature
    for f in "${touched_files[@]}"; do
      mkdir -p "$(dirname "$f")"
      echo content > "$f"
      git add "$f"
    done
    if [ "${#touched_files[@]}" -gt 0 ]; then
      git commit -q -m feat
    fi
  )
}

# Run the validator against a fixture state file pointing at a fixture plan,
# inside a fixture repo. Returns exit code via $? and prints output.
run_sync() {
  local plan_path="$1"
  local repo_root="$2"
  local state_file="$TEST_TMPDIR/state.json"
  cat > "$state_file" <<EOF
{
  "evidence": {
    "plan_path": "$plan_path"
  }
}
EOF
  SYNC_REPO_ROOT="$repo_root" bash "$VALIDATOR" "$state_file" 2>&1
  return $?
}

# ── TC-Y1: plan declares [a.php, b.php], diff = [a.php, b.php] → pass ──
PLAN1="$TEST_TMPDIR/plan1.md"
cat > "$PLAN1" <<'EOF'
# Plan
- **1a** · → files: a.php, b.php
EOF
REPO1="$TEST_TMPDIR/repo1"
build_fixture_repo "$REPO1" a.php b.php
OUT=$(run_sync "$PLAN1" "$REPO1" || true)
EXIT=$(SYNC_REPO_ROOT="$REPO1" bash "$VALIDATOR" "$TEST_TMPDIR/state.json" >/dev/null 2>&1 && echo 0 || echo $?)
assert_eq "Y1: plan==diff → pass (exit 0)" "0" "$EXIT"

# ── TC-Y2: plan declares [a.php], diff = [a.php, c.php] → block ──
PLAN2="$TEST_TMPDIR/plan2.md"
cat > "$PLAN2" <<'EOF'
# Plan
- **1a** · → files: a.php
EOF
REPO2="$TEST_TMPDIR/repo2"
build_fixture_repo "$REPO2" a.php c.php
EXIT=$(SYNC_REPO_ROOT="$REPO2" bash "$VALIDATOR" <(cat <<EOF
{"evidence":{"plan_path":"$PLAN2"}}
EOF
) >/dev/null 2>&1 && echo 0 || echo $?)
assert_eq "Y2: undeclared file in diff → block (exit 2)" "2" "$EXIT"

OUT=$(SYNC_REPO_ROOT="$REPO2" bash "$VALIDATOR" <(cat <<EOF
{"evidence":{"plan_path":"$PLAN2"}}
EOF
) 2>&1 || true)
echo "$OUT" | grep -q "c.php" && pass "Y2: drift output mentions c.php" || fail "Y2: drift output should mention c.php"

# ── TC-Y3: diff includes only workflow artifacts → pass ──
PLAN3="$TEST_TMPDIR/plan3.md"
cat > "$PLAN3" <<'EOF'
# Plan
- **1a** · → files: (no file writes)
EOF
REPO3="$TEST_TMPDIR/repo3"
build_fixture_repo "$REPO3" \
  docs/superpowers/specs/x.md \
  docs/superpowers/plans/x.md \
  docs/codebase-manifest.md
EXIT=$(SYNC_REPO_ROOT="$REPO3" bash "$VALIDATOR" <(cat <<EOF
{"evidence":{"plan_path":"$PLAN3"}}
EOF
) >/dev/null 2>&1 && echo 0 || echo $?)
assert_eq "Y3: only workflow artifacts in diff → pass" "0" "$EXIT"

# ── TC-Y4: plan declares [a.php], diff = [a.php, docs/superpowers/specs/x.md] → pass ──
PLAN4="$TEST_TMPDIR/plan4.md"
cat > "$PLAN4" <<'EOF'
# Plan
- **1a** · → files: a.php
EOF
REPO4="$TEST_TMPDIR/repo4"
build_fixture_repo "$REPO4" a.php docs/superpowers/specs/x.md
EXIT=$(SYNC_REPO_ROOT="$REPO4" bash "$VALIDATOR" <(cat <<EOF
{"evidence":{"plan_path":"$PLAN4"}}
EOF
) >/dev/null 2>&1 && echo 0 || echo $?)
assert_eq "Y4: declared file + workflow artifact → pass" "0" "$EXIT"

# ── TC-Y5: parenthesized payload `(a.php, b.php)` → pass when diff matches ──
PLAN5="$TEST_TMPDIR/plan5.md"
cat > "$PLAN5" <<'EOF'
# Plan
- **1a** · → files: (a.php, b.php)
EOF
REPO5="$TEST_TMPDIR/repo5"
build_fixture_repo "$REPO5" a.php b.php
EXIT=$(SYNC_REPO_ROOT="$REPO5" bash "$VALIDATOR" <(cat <<EOF
{"evidence":{"plan_path":"$PLAN5"}}
EOF
) >/dev/null 2>&1 && echo 0 || echo $?)
assert_eq "Y5: parenthesized payload → pass" "0" "$EXIT"

echo
summary
