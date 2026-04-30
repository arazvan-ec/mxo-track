#!/usr/bin/env bash
# test-ci-vocab-deprecation-check.sh — smoke for C-2.

set -uo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/ci-vocab-deprecation-check.sh"
VOCAB_FILE="$REPO_ROOT/docs/knowledge/_vocabulary.yaml"

pass=0; fail=0
assert() {
  local label="$1" actual="$2" expected="$3"
  if [ "$actual" = "$expected" ]; then
    echo "  ✅ $label"; pass=$((pass+1))
  else
    echo "  ❌ $label (got '$actual', expected '$expected')"; fail=$((fail+1))
  fi
}

TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT
cd "$TMP" || exit 1
git init -q -b main
git config user.email "t@t" && git config user.name "t"
git config commit.gpgsign false
mkdir -p backend/src docs/knowledge .claude/hooks/lib
cp "$VOCAB_FILE" docs/knowledge/_vocabulary.yaml
cp "$REPO_ROOT/.claude/hooks/lib/vocabulary-reader.sh" .claude/hooks/lib/
echo 'function clean() { return 1; }' > backend/src/foo.js
git add . && git commit -q -m base 2>/dev/null

# Diverge: introduce 'tour' on a new commit reachable from HEAD only.
echo 'var tour = 2;' >> backend/src/foo.js
git add backend/src/foo.js
git commit -q -m bad 2>/dev/null

echo "Test 1: HEAD diff introduces 'tour' → exit 1"
out1=$(bash "$SCRIPT" 2>&1); rc1=$?
assert "exit 1 on deprecated alias" "$rc1" "1"
case "$out1" in
  *"deprecated alias"*"tour"*"Route"*) echo "  ✅ ERROR message shape"; pass=$((pass+1)) ;;
  *) echo "  ❌ ERROR message missing: $out1"; fail=$((fail+1)) ;;
esac

# Reset to clean state: revert the bad commit.
echo "Test 2: revert bad commit → exit 0"
git reset --hard HEAD~1 -q
echo 'var fine = 3;' >> backend/src/foo.js
git add backend/src/foo.js
git commit -q -m clean 2>/dev/null
bash "$SCRIPT" >/dev/null 2>&1; rc2=$?
assert "exit 0 on clean diff" "$rc2" "0"

echo
echo "Total: $pass passed, $fail failed"
[ "$fail" -eq 0 ]
