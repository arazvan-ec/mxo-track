#!/usr/bin/env bash
# test-pre-push-gate-upstream-diff.sh — smoke for A1.
#
# Validates that pre-push-gate.sh's has_protected_changes() evaluates
# the unpushed-commits diff (`@{upstream}...HEAD`) and falls back to
# `origin/main...HEAD` only when no upstream exists.
#
# Origin: 2026-04-29 cross-session resume hardening v2 (A1).

set -uo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
# pre-push-gate.sh sources classify-file lib via REPO env; emulate by
# extracting just the function under test into a sandbox.

pass=0; fail=0
assert() {
  local label="$1" actual="$2" expected="$3"
  if [ "$actual" = "$expected" ]; then
    echo "  ✅ $label"; pass=$((pass+1))
  else
    echo "  ❌ $label (got '$actual', expected '$expected')"; fail=$((fail+1))
  fi
}

# Build a self-contained probe script that sources the real lib and
# invokes has_protected_changes() in the temp repo's CWD.
PROBE=$(mktemp)
trap 'rm -f "$PROBE"' EXIT
cat > "$PROBE" <<PROBE_EOF
#!/usr/bin/env bash
set -uo pipefail
REPO="\$(pwd)"
# shellcheck disable=SC1091
source "$REPO_ROOT/.claude/hooks/lib/classify-file.sh"

has_protected_changes() {
  local changed_files diff_range
  if (cd "\$REPO" && git rev-parse --verify --quiet '@{upstream}' >/dev/null 2>&1); then
    diff_range='@{upstream}...HEAD'
  else
    diff_range='origin/main...HEAD'
  fi
  changed_files=\$(cd "\$REPO" && git diff --name-only "\$diff_range" 2>/dev/null || echo "")
  [ -z "\$changed_files" ] && return 1
  while IFS= read -r file; do
    local file_class
    file_class=\$(classify_file "\$REPO/\$file")
    if [ "\$file_class" = "code" ] || [ "\$file_class" = "test" ]; then
      return 0
    fi
  done <<< "\$changed_files"
  return 1
}

has_protected_changes
echo \$?
PROBE_EOF
chmod +x "$PROBE"

# ── Setup: simulate a remote repo + a feature branch with upstream ──
TMP=$(mktemp -d)
trap 'rm -rf "$TMP" "$PROBE"' EXIT

REMOTE="$TMP/remote.git"
LOCAL="$TMP/local"
git init --bare -q -b main "$REMOTE"

git init -q -b main "$LOCAL"
cd "$LOCAL" || exit 1
git config user.email "t@t" && git config user.name "t"
git config commit.gpgsign false
git remote add origin "$REMOTE"

# Seed main with code (simulates the branch ancestry having protected files).
mkdir -p backend/src
echo 'class Seed {}' > backend/src/seed.php
git add backend/src/seed.php
git commit -q -m "seed: protected code on main"
git push -q origin main

# Branch off; push a code commit so upstream is set with code in ancestry.
git checkout -q -b feature
echo 'class Feature {}' > backend/src/feature.php
git add backend/src/feature.php
git commit -q -m "feat: feature code committed"
git push -q -u origin feature

# Now add an unpushed DOC-ONLY commit on the feature branch.
mkdir -p docs/superpowers/specs
echo "# spec" > docs/superpowers/specs/test-spec.md
git add docs/superpowers/specs/test-spec.md
git commit -q -m "docs: spec checkpoint"

echo "Test 1: unpushed commits are doc-only → has_protected_changes=1 (false)"
rc=$(bash "$PROBE" | tail -1)
assert "doc-only unpushed → returns 1" "$rc" "1"

# Add an unpushed CODE commit on top.
echo 'class Hot {}' > backend/src/hot.php
git add backend/src/hot.php
git commit -q -m "feat: unpushed code"

echo "Test 2: unpushed commits include code → has_protected_changes=0 (true)"
rc=$(bash "$PROBE" | tail -1)
assert "code-unpushed → returns 0" "$rc" "0"

# Reset to upstream-aligned (no unpushed commits at all).
git reset --hard "@{upstream}" -q

echo "Test 3: branch fully aligned with upstream → has_protected_changes=1 (false)"
rc=$(bash "$PROBE" | tail -1)
assert "no unpushed → returns 1" "$rc" "1"

# Negative: no upstream configured → fallback to origin/main...HEAD.
git checkout -q -b orphan-branch
echo 'class Orphan {}' > backend/src/orphan.php
git add backend/src/orphan.php
git commit -q -m "feat: orphan code"
# Sanity: orphan-branch has no upstream.
if git rev-parse --verify --quiet '@{upstream}' >/dev/null 2>&1; then
  echo "  ⚠ orphan-branch unexpectedly has upstream; skipping fallback test"
else
  echo "Test 4: no upstream → fallback to origin/main...HEAD"
  rc=$(bash "$PROBE" | tail -1)
  assert "no-upstream fallback → returns 0 (sees code)" "$rc" "0"
fi

echo
echo "Total: $pass passed, $fail failed"
[ "$fail" -eq 0 ]
