#!/usr/bin/env bash
# test-pre-commit-deprecated-alias.sh — smoke for C-1.

set -uo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
HOOK="$REPO_ROOT/.claude/hooks/pre-commit-deprecated-alias.sh"
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

# Build a temp git repo with a staged file using deprecated alias 'tour'.
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT
cd "$TMP" || exit 1
git init -q
mkdir -p backend/src
echo 'var tour = 1;' > backend/src/foo.js
git add backend/src/foo.js

# Symlink _vocabulary so the hook can locate the lib relative to repo root.
mkdir -p docs/knowledge .claude/hooks/lib
cp "$VOCAB_FILE" docs/knowledge/_vocabulary.yaml
cp "$REPO_ROOT/.claude/hooks/lib/vocabulary-reader.sh" .claude/hooks/lib/

echo "Test 1: default mode (WARN-only, exit 0)"
out1=$(bash "$HOOK" 2>&1); rc1=$?
assert "exit 0 default" "$rc1" "0"
case "$out1" in
  *"deprecated alias"*"tour"*"Route"*"backend/src/foo.js"*) echo "  ✅ WARN message shape"; pass=$((pass+1)) ;;
  *) echo "  ❌ WARN message missing or malformed: $out1"; fail=$((fail+1)) ;;
esac

echo "Test 2: STRICT=1 → exit 1"
STRICT=1 bash "$HOOK" >/dev/null 2>&1; rc2=$?
assert "STRICT exit 1" "$rc2" "1"

echo "Test 3: SKIP_VOCAB_PRECOMMIT=1 → exit 0, no warn"
out3=$(SKIP_VOCAB_PRECOMMIT=1 bash "$HOOK" 2>&1); rc3=$?
assert "SKIP exit 0" "$rc3" "0"
case "$out3" in
  *deprecated*) echo "  ❌ WARN not suppressed: $out3"; fail=$((fail+1)) ;;
  *) echo "  ✅ WARN suppressed"; pass=$((pass+1)) ;;
esac

echo
echo "Total: $pass passed, $fail failed"
[ "$fail" -eq 0 ]
