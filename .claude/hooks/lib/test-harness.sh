#!/usr/bin/env bash
# test-harness.sh — shared helpers for shell test scripts under .claude/hooks/
#
# Source me; do not execute directly. Call `init_harness` at the top to set
# up counters and a scratch tmpdir (auto-cleaned on exit). Use the `assert_*`
# helpers to record pass/fail. Call `summary` at the end to print results
# and exit non-zero on any failure.
#
# Usage:
#   source "$REPO/.claude/hooks/lib/test-harness.sh"
#   init_harness
#   assert_eq "name" "expected" "$actual"
#   summary
#
# Globals exposed:
#   PASS, FAIL           — counters
#   TEST_TMPDIR          — scratch dir (auto-cleaned on EXIT)

PASS=0
FAIL=0
TEST_TMPDIR=""

init_harness() {
  PASS=0
  FAIL=0
  TEST_TMPDIR=$(mktemp -d)
  trap '[ -n "$TEST_TMPDIR" ] && [ -d "$TEST_TMPDIR" ] && rm -rf "$TEST_TMPDIR"' EXIT
}

pass() {
  echo "  ✅ $1"
  PASS=$((PASS + 1))
}

fail() {
  local name="$1"
  local detail="${2:-}"
  if [ -n "$detail" ]; then
    echo "  ❌ $name — $detail"
  else
    echo "  ❌ $name"
  fi
  FAIL=$((FAIL + 1))
}

assert_eq() {
  local name="$1"
  local expected="$2"
  local actual="$3"
  if [ "$expected" = "$actual" ]; then
    pass "$name"
  else
    fail "$name" "expected='$expected' actual='$actual'"
  fi
}

assert_contains() {
  local name="$1"
  local needle="$2"
  shift 2
  for item in "$@"; do
    if [ "$item" = "$needle" ]; then
      pass "$name"
      return 0
    fi
  done
  fail "$name" "'$needle' not in ($*)"
}

assert_not_contains() {
  local name="$1"
  local needle="$2"
  shift 2
  for item in "$@"; do
    if [ "$item" = "$needle" ]; then
      fail "$name" "'$needle' unexpectedly present in ($*)"
      return 0
    fi
  done
  pass "$name"
}

summary() {
  echo
  echo "── Results ──"
  echo "  Passed: $PASS"
  echo "  Failed: $FAIL"
  [ "$FAIL" -eq 0 ]
}
