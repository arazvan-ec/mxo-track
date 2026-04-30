#!/usr/bin/env bash
# test-git-probe.sh — smoke for lib/git-probe.sh.

set -uo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
# shellcheck disable=SC1091
source "$REPO_ROOT/.claude/hooks/lib/git-probe.sh"

pass=0; fail=0
assert_rc() {
  local label="$1" actual="$2" expected="$3"
  if [ "$actual" = "$expected" ]; then
    echo "  ✅ $label"; pass=$((pass+1))
  else
    echo "  ❌ $label (got rc=$actual, expected rc=$expected)"; fail=$((fail+1))
  fi
}

TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT
cd "$TMP" || exit 1
git init -q
git config user.email t@t && git config user.name t
git config commit.gpgsign false

mkdir -p docs
echo "# clean" > docs/clean.md
git add docs/clean.md
git commit -q -m "seed clean" 2>/dev/null || true

echo "Test 1: tracked + clean → rc=0"
is_path_committed_clean "$TMP" "docs/clean.md"; rc=$?
assert_rc "clean tracked file" "$rc" "0"

# Modify the file
echo "modified" >> docs/clean.md
echo "Test 2: tracked + modified → rc=1"
is_path_committed_clean "$TMP" "docs/clean.md"; rc=$?
assert_rc "modified tracked file" "$rc" "1"

# Untracked
git checkout -q -- docs/clean.md
echo "untracked content" > docs/untracked.md
echo "Test 3: untracked → rc=1"
is_path_committed_clean "$TMP" "docs/untracked.md"; rc=$?
assert_rc "untracked file" "$rc" "1"

echo "Test 4: nonexistent → rc=1"
is_path_committed_clean "$TMP" "docs/nope.md"; rc=$?
assert_rc "nonexistent file" "$rc" "1"

echo "Test 5: empty path → rc=1"
is_path_committed_clean "$TMP" ""; rc=$?
assert_rc "empty path" "$rc" "1"

# is_spec_committed_clean — needs a state file with evidence.spec_path
STATE="$TMP/state.json"
printf '{"evidence":{"spec_path":"docs/clean.md"}}' > "$STATE"
echo "Test 6: is_spec_committed_clean with clean spec → rc=0"
is_spec_committed_clean "$TMP" "$STATE"; rc=$?
assert_rc "spec_path clean" "$rc" "0"

printf '{"evidence":{"spec_path":""}}' > "$STATE"
echo "Test 7: is_spec_committed_clean with empty spec_path → rc=1"
is_spec_committed_clean "$TMP" "$STATE"; rc=$?
assert_rc "spec_path empty" "$rc" "1"

echo
echo "Total: $pass passed, $fail failed"
[ "$fail" -eq 0 ]
