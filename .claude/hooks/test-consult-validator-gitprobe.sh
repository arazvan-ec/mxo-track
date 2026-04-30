#!/usr/bin/env bash
# test-consult-validator-gitprobe.sh — smoke for consult-validator git-probe fallback.

set -uo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
VALIDATOR="$REPO_ROOT/.claude/hooks/validators/consult-validator.sh"

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

# Mirror the repo-root path expected by the validator.
mkdir -p .claude/hooks/lib .claude/hooks/validators docs/superpowers/specs
cp "$REPO_ROOT/.claude/hooks/lib/git-probe.sh" .claude/hooks/lib/
echo "# spec content" > docs/superpowers/specs/test.md
git add . && git commit -q -m seed 2>/dev/null

mkstate() {
  local d_read="$1" l_scan="$2" spec="$3"
  cat > "$TMP/state.json" <<JSON
{
  "evidence": {
    "spec_path": "$spec",
    "decisions_read": $d_read,
    "logs_scanned": $l_scan
  }
}
JSON
}

echo "Test 1: flags=false + spec committed-clean → validator passes (rc=0)"
mkstate "false" "false" "docs/superpowers/specs/test.md"
REPO="$TMP" bash "$VALIDATOR" "$TMP/state.json" >/dev/null 2>&1; rc=$?
assert_rc "git-probe fallback passes" "$rc" "0"

echo "Test 2: flags=false + no spec_path → validator blocks (rc=2)"
mkstate "false" "false" ""
REPO="$TMP" bash "$VALIDATOR" "$TMP/state.json" >/dev/null 2>&1; rc=$?
assert_rc "no spec → blocks" "$rc" "2"

echo "Test 3: flags=false + spec_path but file uncommitted → validator blocks"
echo "uncommitted" > docs/superpowers/specs/uncommitted.md
mkstate "false" "false" "docs/superpowers/specs/uncommitted.md"
REPO="$TMP" bash "$VALIDATOR" "$TMP/state.json" >/dev/null 2>&1; rc=$?
assert_rc "uncommitted spec → blocks" "$rc" "2"

echo "Test 4: flags=true (no fallback needed) → validator passes"
mkstate "true" "true" ""
REPO="$TMP" bash "$VALIDATOR" "$TMP/state.json" >/dev/null 2>&1; rc=$?
assert_rc "flags=true → passes" "$rc" "0"

echo "Test 5: flags=false + spec tracked but modified → blocks"
echo "modified" >> docs/superpowers/specs/test.md
mkstate "false" "false" "docs/superpowers/specs/test.md"
REPO="$TMP" bash "$VALIDATOR" "$TMP/state.json" >/dev/null 2>&1; rc=$?
assert_rc "modified spec → blocks" "$rc" "2"

echo
echo "Total: $pass passed, $fail failed"
[ "$fail" -eq 0 ]
