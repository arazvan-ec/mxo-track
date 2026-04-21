#!/usr/bin/env bash
# Test suite for consult.sh
# Runs against synthetic fixtures in a temp dir (isolated from real execution-logs).

set -uo pipefail

TESTS_RUN=0
TESTS_PASSED=0
TESTS_FAILED=0
FAILED_NAMES=()

TMPDIR=$(mktemp -d)
trap 'rm -rf "$TMPDIR"' EXIT

LOGS_DIR="$TMPDIR/logs"
mkdir -p "$LOGS_DIR"

# ── Fixtures ──

cat > "$LOGS_DIR/2026-01-01-alpha-fix.md" <<'EOF'
---
type: bugfix
tags: [css, alpha]
files_touched: [src/alpha.css]
patterns: [tailwind-override]
outcome: success
outcome_verified_at: 2026-01-05
regressions_later: []
pr_number: 100
estimated_lines: 10
actual_lines: 12
duration_minutes: 30
consulted_in_future: []
---
# Execution Log — 2026-01-01 — Alpha fix
Body
EOF

cat > "$LOGS_DIR/2026-01-02-beta-feature.md" <<'EOF'
---
type: feature
tags: [widget, beta]
files_touched: [src/beta.tsx, src/shared.ts]
patterns: []
outcome: success
outcome_verified_at: null
regressions_later: []
pr_number: 101
estimated_lines: null
actual_lines: null
duration_minutes: null
consulted_in_future: []
---
# Execution Log — 2026-01-02 — Beta feature
EOF

cat > "$LOGS_DIR/2026-01-03-gamma-refactor.md" <<'EOF'
---
type: refactor
tags: [widget, css]
files_touched: [src/alpha.css, src/shared.ts]
patterns: [tailwind-override]
outcome: null
outcome_verified_at: null
regressions_later: []
pr_number: null
estimated_lines: null
actual_lines: null
duration_minutes: null
consulted_in_future: []
---
# Execution Log — 2026-01-03 — Gamma refactor
EOF

cat > "$LOGS_DIR/2026-01-04-untagged-no-fm.md" <<'EOF'
# Execution Log — 2026-01-04 — Untagged log without frontmatter
Body only
EOF

cat > "$LOGS_DIR/2026-01-05-delta-reverted.md" <<'EOF'
---
type: feature
tags: [widget]
files_touched: [src/delta.tsx]
patterns: []
outcome: reverted
outcome_verified_at: 2026-01-10
regressions_later: [2026-01-11-delta-rework.md]
pr_number: 102
estimated_lines: null
actual_lines: null
duration_minutes: null
consulted_in_future: []
---
# Execution Log — 2026-01-05 — Delta reverted
EOF

CONSULT="$(dirname "$(readlink -f "$0")")/consult.sh"
export CONSULT_LOGS_DIR="$LOGS_DIR"

# ── Helpers ──

assert_contains() {
  local output="$1" expected="$2" test_name="$3"
  TESTS_RUN=$((TESTS_RUN+1))
  if echo "$output" | grep -qF "$expected"; then
    TESTS_PASSED=$((TESTS_PASSED+1))
    printf "  ✅ %s\n" "$test_name"
  else
    TESTS_FAILED=$((TESTS_FAILED+1))
    FAILED_NAMES+=("$test_name")
    printf "  ❌ %s\n     Expected substring: %s\n     Got: %s\n" "$test_name" "$expected" "$output"
  fi
}

assert_not_contains() {
  local output="$1" unexpected="$2" test_name="$3"
  TESTS_RUN=$((TESTS_RUN+1))
  if echo "$output" | grep -qF "$unexpected"; then
    TESTS_FAILED=$((TESTS_FAILED+1))
    FAILED_NAMES+=("$test_name")
    printf "  ❌ %s\n     Unexpected substring: %s\n     Got: %s\n" "$test_name" "$unexpected" "$output"
  else
    TESTS_PASSED=$((TESTS_PASSED+1))
    printf "  ✅ %s\n" "$test_name"
  fi
}

assert_exit() {
  local expected="$1" actual="$2" test_name="$3"
  TESTS_RUN=$((TESTS_RUN+1))
  if [ "$expected" = "$actual" ]; then
    TESTS_PASSED=$((TESTS_PASSED+1))
    printf "  ✅ %s (exit=%s)\n" "$test_name" "$actual"
  else
    TESTS_FAILED=$((TESTS_FAILED+1))
    FAILED_NAMES+=("$test_name")
    printf "  ❌ %s — expected exit %s, got %s\n" "$test_name" "$expected" "$actual"
  fi
}

# ── Tests ──

echo "── tag <tag> ──"
out=$("$CONSULT" tag css); rc=$?
assert_contains "$out" "alpha-fix" "tag=css includes alpha-fix"
assert_contains "$out" "gamma-refactor" "tag=css includes gamma-refactor"
assert_not_contains "$out" "beta-feature" "tag=css excludes beta-feature"
assert_exit 0 $rc "tag with results exit 0"

out=$("$CONSULT" tag nonexistent) || rc=$?
assert_exit 1 $rc "tag with no results exit 1"

echo "── file <path> ──"
out=$("$CONSULT" file src/alpha.css); rc=$?
assert_contains "$out" "alpha-fix" "file=src/alpha.css includes alpha-fix"
assert_contains "$out" "gamma-refactor" "file=src/alpha.css includes gamma-refactor"
assert_not_contains "$out" "beta-feature" "file=src/alpha.css excludes beta-feature"
assert_exit 0 $rc "file with results exit 0"

echo "── file-glob <pattern> ──"
out=$("$CONSULT" file-glob 'src/*.tsx'); rc=$?
assert_contains "$out" "beta-feature" "file-glob *.tsx includes beta-feature"
assert_contains "$out" "delta-reverted" "file-glob *.tsx includes delta-reverted"
assert_not_contains "$out" "alpha-fix" "file-glob *.tsx excludes alpha-fix (.css)"

echo "── pattern <name> ──"
out=$("$CONSULT" pattern tailwind-override); rc=$?
assert_contains "$out" "alpha-fix" "pattern=tailwind-override includes alpha-fix"
assert_contains "$out" "gamma-refactor" "pattern=tailwind-override includes gamma-refactor"
assert_not_contains "$out" "beta-feature" "pattern=tailwind-override excludes beta-feature"

echo "── type <type> ──"
out=$("$CONSULT" type bugfix); rc=$?
assert_contains "$out" "alpha-fix" "type=bugfix includes alpha-fix"
assert_not_contains "$out" "beta-feature" "type=bugfix excludes beta-feature"

out=$("$CONSULT" type feature); rc=$?
assert_contains "$out" "beta-feature" "type=feature includes beta-feature"
assert_contains "$out" "delta-reverted" "type=feature includes delta-reverted"

echo "── recent [N] ──"
out=$("$CONSULT" recent 2); rc=$?
assert_contains "$out" "delta-reverted" "recent 2 includes newest"
assert_contains "$out" "gamma-refactor" "recent 2 includes second-newest (skipping no-fm)"
assert_not_contains "$out" "alpha-fix" "recent 2 excludes oldest"

echo "── by-outcome <outcome> ──"
out=$("$CONSULT" by-outcome success); rc=$?
assert_contains "$out" "alpha-fix" "by-outcome=success includes alpha-fix"
assert_contains "$out" "beta-feature" "by-outcome=success includes beta-feature"
assert_not_contains "$out" "gamma-refactor" "by-outcome=success excludes gamma-refactor (null)"
assert_not_contains "$out" "delta-reverted" "by-outcome=success excludes delta-reverted"

out=$("$CONSULT" by-outcome reverted); rc=$?
assert_contains "$out" "delta-reverted" "by-outcome=reverted includes delta-reverted"

echo "── stats ──"
out=$("$CONSULT" stats); rc=$?
assert_contains "$out" "widget" "stats lists widget tag"
assert_contains "$out" "css" "stats lists css tag"
assert_contains "$out" "PATTERN" "stats flags 3+ with PATTERN marker"

echo "── show <filename> ──"
out=$("$CONSULT" show 2026-01-01-alpha-fix.md); rc=$?
assert_contains "$out" "type: bugfix" "show prints frontmatter type"
assert_contains "$out" "tags:" "show prints tags field"
assert_not_contains "$out" "Body" "show does not print body"

echo "── unverified ──"
out=$("$CONSULT" unverified); rc=$?
assert_contains "$out" "beta-feature" "unverified includes success+null verified_at"
assert_not_contains "$out" "alpha-fix" "unverified excludes alpha-fix (has verified_at)"

echo "── invalid subcommand ──"
out=$("$CONSULT" bogus-command 2>&1) || rc=$?
assert_exit 2 $rc "invalid subcommand exit 2"

echo "── --quiet flag ──"
out=$("$CONSULT" --quiet tag css); rc=$?
assert_contains "$out" "alpha-fix" "--quiet still returns results"
assert_not_contains "$out" "===" "--quiet suppresses headers"

echo "── no frontmatter handling ──"
out=$("$CONSULT" type process 2>/dev/null || true)
assert_not_contains "$out" "untagged-no-fm" "log without frontmatter is skipped silently"

# ── Summary ──
echo ""
echo "──────────────────────────────────"
printf "Results: %d run · %d passed · %d failed\n" "$TESTS_RUN" "$TESTS_PASSED" "$TESTS_FAILED"
if [ $TESTS_FAILED -gt 0 ]; then
  echo "Failed tests:"
  for name in "${FAILED_NAMES[@]}"; do echo "  - $name"; done
  exit 1
fi
echo "All tests passed."
exit 0
