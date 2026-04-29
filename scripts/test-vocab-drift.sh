#!/usr/bin/env bash
# test-vocab-drift.sh — smoke for C-3.

set -uo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/vocab-drift.sh"

pass=0; fail=0
assert_contains() {
  local label="$1" haystack="$2" needle="$3"
  case "$haystack" in
    *"$needle"*) echo "  ✅ $label"; pass=$((pass+1)) ;;
    *) echo "  ❌ $label (missing '$needle' in: $haystack)"; fail=$((fail+1)) ;;
  esac
}
assert_not_contains() {
  local label="$1" haystack="$2" needle="$3"
  case "$haystack" in
    *"$needle"*) echo "  ❌ $label (should NOT contain '$needle')"; fail=$((fail+1)) ;;
    *) echo "  ✅ $label"; pass=$((pass+1)) ;;
  esac
}
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

# Build a fake repo with vocab + target files.
mkdir -p "$TMP/repo/docs/knowledge" "$TMP/repo/src"
cd "$TMP/repo" || exit 1
git init -q
echo 'class Foo {}' > src/foo.php           # entry A: clean
echo 'class Other {}' > src/other.php       # entry C: name absent
# entry B: path missing (no file at src/missing.php)
# entry D: bounded_context TODO (uncurated, must be skipped)

cat > docs/knowledge/_vocabulary.yaml <<'YAML'
entries:
  - canonical: Foo
    aliases: []
    bounded_context: domain-x
    layer: domain
    authoritative_path: src/foo.php
  - canonical: BarMissing
    aliases: []
    bounded_context: domain-x
    layer: domain
    authoritative_path: src/missing.php
  - canonical: WrongName
    aliases: []
    bounded_context: domain-x
    layer: domain
    authoritative_path: src/other.php
  - canonical: Skip
    aliases: []
    bounded_context: TODO
    layer: domain
    authoritative_path: src/zzz_nonexistent.php
YAML

out=$(VOCAB_FILE="$TMP/repo/docs/knowledge/_vocabulary.yaml" bash "$SCRIPT" 2>&1); rc=$?

assert "exit 1 on drift" "$rc" "1"
assert_contains "MISSING_PATH for BarMissing" "$out" "MISSING_PATH"
assert_contains "row mentions BarMissing" "$out" "BarMissing"
assert_contains "NAME_DRIFT for WrongName" "$out" "NAME_DRIFT"
assert_contains "row mentions WrongName" "$out" "WrongName"
assert_not_contains "Foo (clean) absent from output" "$out" "Foo	"
assert_not_contains "Skip (TODO) absent from output" "$out" "Skip	"

echo
echo "Total: $pass passed, $fail failed"
[ "$fail" -eq 0 ]
