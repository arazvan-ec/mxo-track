#!/usr/bin/env bash
# test-vocab-rename.sh — smoke for C-4.

set -uo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/vocab-rename.sh"

pass=0; fail=0
assert() {
  local label="$1" actual="$2" expected="$3"
  if [ "$actual" = "$expected" ]; then
    echo "  ✅ $label"; pass=$((pass+1))
  else
    echo "  ❌ $label (got '$actual', expected '$expected')"; fail=$((fail+1))
  fi
}
assert_contains() {
  local label="$1" file="$2" needle="$3"
  if grep -q -F -- "$needle" "$file"; then
    echo "  ✅ $label"; pass=$((pass+1))
  else
    echo "  ❌ $label (missing '$needle' in $file)"; fail=$((fail+1))
  fi
}
assert_not_contains() {
  local label="$1" file="$2" needle="$3"
  if grep -q -F -- "$needle" "$file"; then
    echo "  ❌ $label (should not contain '$needle')"; fail=$((fail+1))
  else
    echo "  ✅ $label"; pass=$((pass+1))
  fi
}

TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT

mkdir -p "$TMP/repo/docs/knowledge" "$TMP/repo/src"
cd "$TMP/repo" || exit 1
git init -q

cat > docs/knowledge/_vocabulary.yaml <<'YAML'
entries:
  - canonical: Foo
    aliases: []
    bounded_context: domain-x
    layer: domain
    authoritative_path: src/foo.php
  - canonical: Bar
    aliases:
      - {term: "barra", lang: "es", surface: "user"}
    bounded_context: domain-x
    layer: domain
    authoritative_path: src/bar.php
YAML
echo 'class Foo {}' > src/foo.php
echo 'class Bar {}' > src/bar.php
VFILE="$TMP/repo/docs/knowledge/_vocabulary.yaml"

echo "Test 1: rename Foo → Baz (success, alias added)"
VOCAB_FILE="$VFILE" bash "$SCRIPT" Foo Baz >/dev/null 2>&1; rc1=$?
assert "exit 0" "$rc1" "0"
assert_contains "canonical Baz present" "$VFILE" "  - canonical: Baz"
assert_not_contains "canonical Foo absent (top-level)" "$VFILE" "  - canonical: Foo"
assert_contains "Foo alias inserted" "$VFILE" 'term: "Foo", lang: "en", surface: "deprecated"'

echo "Test 2: re-run rename Foo → Baz (idempotency, exit 2)"
VOCAB_FILE="$VFILE" bash "$SCRIPT" Foo Baz >/dev/null 2>&1; rc2=$?
assert "exit 2 on missing old" "$rc2" "2"

echo "Test 3: rename Bar → Baz (Baz already canonical, exit 2)"
VOCAB_FILE="$VFILE" bash "$SCRIPT" Bar Baz >/dev/null 2>&1; rc3=$?
assert "exit 2 on existing new" "$rc3" "2"

echo "Test 4: rename Bar → Quux with non-existent path (exit 2)"
VOCAB_FILE="$VFILE" bash "$SCRIPT" Bar Quux /nonexistent/file.php >/dev/null 2>&1; rc4=$?
assert "exit 2 on missing path" "$rc4" "2"

echo "Test 5: rename Bar → Quux with valid path"
echo 'class Quux {}' > src/quux.php
VOCAB_FILE="$VFILE" bash "$SCRIPT" Bar Quux src/quux.php >/dev/null 2>&1; rc5=$?
assert "exit 0" "$rc5" "0"
assert_contains "authoritative_path updated" "$VFILE" "authoritative_path: src/quux.php"
assert_contains "Bar alias inserted" "$VFILE" 'term: "Bar", lang: "en", surface: "deprecated"'

echo
echo "Total: $pass passed, $fail failed"
[ "$fail" -eq 0 ]
