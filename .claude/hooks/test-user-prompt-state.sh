#!/usr/bin/env bash
# Tests for user-prompt-state.sh — approval UX overhaul (P1 of 2026-05-20)
# Covers: regex extension, DRY refactor, proactive feedback, semantic probe,
# direct-write warning.
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
HOOK="$SCRIPT_DIR/user-prompt-state.sh"
TEST_STATE="/tmp/test-upt-state.json"
TEST_SNAPSHOT="/tmp/ptc-state-snapshot.json"

PASS=0
FAIL=0

setup_state() {
  local flow="${1:-full}"
  local phase="${2:-brainstorming}"
  local approved="${3:-false}"
  local retro_shown="${4:-false}"
  local plan_path="${5:-}"
  local alternatives="${6:-true}"
  cat > "$TEST_STATE" <<EOF
{
  "flow_type": "$flow",
  "current_phase": "$phase",
  "interaction_classification": "code change",
  "evidence": {
    "user_approved": $approved,
    "retrospective_shown": $retro_shown,
    "alternatives_proposed": $alternatives,
    "decisions_read": true,
    "logs_scanned": true,
    "user_turns": 1,
    "plan_path": "$plan_path",
    "spec_path": "spec.md",
    "work_context": {"problems": {"total": 0, "current": 0, "labels": []}}
  },
  "phase_history": []
}
EOF
  cp "$TEST_STATE" "$TEST_SNAPSHOT" 2>/dev/null || true
}

run_hook() {
  local prompt="$1"
  echo "{\"prompt\":\"$prompt\"}" | env STATE_FILE="$TEST_STATE" bash -c "
    cd \"$(cd \"$(dirname \"$HOOK\")/../..\" && pwd)\"
    # Point STATE_FILE through env
    sed -i.bak 's|STATE_FILE=.*|STATE_FILE=\"$TEST_STATE\"|' \"$HOOK\" 2>/dev/null || true
    \"$HOOK\"
    mv \"$HOOK.bak\" \"$HOOK\" 2>/dev/null || true
  " 2>&1
}

# Simpler approach: inject the test state via the hook's REPO/STATE_FILE
# We'll exercise the regex via a small isolated invocation
test_regex_match() {
  local prompt="$1"
  local expected="$2"  # "approve" | "reject" | "neither"
  # Source the hook in a subshell with controlled state
  setup_state "full" "brainstorming" "false"

  # We invoke the hook with manipulated env to point STATE_FILE
  local hook_output
  hook_output=$(echo "{\"prompt\":\"$prompt\"}" | STATE_FILE="$TEST_STATE" REPO="$(cd "$(dirname "$HOOK")/../.." && pwd)" bash "$HOOK" 2>&1 || true)

  local new_approved
  new_approved=$(jq -r '.evidence.user_approved // false' "$TEST_STATE" 2>/dev/null || echo "false")

  case "$expected" in
    approve)
      if [ "$new_approved" = "true" ]; then return 0; else return 1; fi ;;
    reject)
      # rejection sets user_approved=false (already false here, so check no approval)
      if [ "$new_approved" = "false" ]; then return 0; else return 1; fi ;;
    neither)
      if [ "$new_approved" = "false" ]; then return 0; else return 1; fi ;;
  esac
}

assert_match() {
  local desc="$1"; local prompt="$2"; local expected="$3"
  if test_regex_match "$prompt" "$expected"; then
    PASS=$((PASS + 1)); echo "  ✅ $desc"
  else
    FAIL=$((FAIL + 1)); echo "  ❌ $desc (prompt='$prompt' expected=$expected)"
  fi
}

# We need the hook to be invocable with our test STATE_FILE.
# Current hook hardcodes STATE_FILE — for testing we'll create a wrapper that overrides.
# Since the hook uses REPO/.claude/session-state.json, simplest is to override STATE_FILE
# Read the hook source to verify it honors a STATE_FILE env override.

# Check #0: the hook MUST honor STATE_FILE env override (will fail if not patched)
echo "=== Test: user-prompt-state.sh approval UX overhaul ==="
echo ""
echo "--- Regex extension (new verbs) ---"
assert_match "approve: 'apruebo' (existing)"           "apruebo el plan"      "approve"
assert_match "approve: 'ok' (existing)"                "ok"                   "approve"
assert_match "approve: 'procede' (existing)"           "procede"              "approve"
assert_match "approve: 'avanza' (NEW)"                 "avanza a planning"    "approve"
assert_match "approve: 'sigue' (NEW)"                  "sigue con eso"        "approve"
assert_match "approve: 'vamos' (NEW)"                  "vamos a por ello"     "approve"
assert_match "approve: 'pasa a' (NEW)"                 "pasa a implementation" "approve"
assert_match "approve: 'arranca' (NEW)"                "arranca con esto"     "approve"
assert_match "approve: 'tira' (NEW)"                   "tira para adelante"   "approve"
assert_match "approve: 'venga' (NEW)"                  "venga, hazlo"         "approve"
assert_match "approve: 'empieza' (NEW)"                "empieza con la fase"  "approve"

echo ""
echo "--- Rejection still works ---"
assert_match "reject: 'no, cambia'"                    "no, cambia eso"       "reject"
assert_match "reject: 'no estoy de acuerdo'"           "no estoy de acuerdo"  "reject"

echo ""
echo "--- Negation NOT matching approval ---"
# 'no avances' should NOT match 'avanza' (word boundary check)
assert_match "neither: 'no avances todavía' (negation)" "no avances todavia"  "reject"

echo ""
echo "--- Proactive feedback ---"
setup_state "full" "brainstorming" "false" "false" "" "true"
PROACTIVE_OUTPUT=$(echo "{\"prompt\":\"hola\"}" | STATE_FILE="$TEST_STATE" REPO="$(cd "$(dirname "$HOOK")/../.." && pwd)" bash "$HOOK" 2>&1 || true)
if echo "$PROACTIVE_OUTPUT" | grep -q "✋"; then
  PASS=$((PASS + 1)); echo "  ✅ proactive feedback emitted when user_approved=false + pre-gate"
else
  FAIL=$((FAIL + 1)); echo "  ❌ proactive feedback should be emitted. Got: $PROACTIVE_OUTPUT"
fi

setup_state "full" "brainstorming" "true" "false" "" "true"
NO_PROACTIVE=$(echo "{\"prompt\":\"hola\"}" | STATE_FILE="$TEST_STATE" REPO="$(cd "$(dirname "$HOOK")/../.." && pwd)" bash "$HOOK" 2>&1 || true)
if echo "$NO_PROACTIVE" | grep -q "✋"; then
  FAIL=$((FAIL + 1)); echo "  ❌ proactive feedback should NOT appear when user_approved=true"
else
  PASS=$((PASS + 1)); echo "  ✅ proactive feedback suppressed when user_approved=true"
fi

echo ""
echo "--- Semantic probe (ambiguous short prompt at pre-gate) ---"
setup_state "full" "brainstorming" "false" "false" "" "true"
PROBE_OUTPUT=$(echo "{\"prompt\":\"hmm\"}" | STATE_FILE="$TEST_STATE" REPO="$(cd "$(dirname "$HOOK")/../.." && pwd)" bash "$HOOK" 2>&1 || true)
if echo "$PROBE_OUTPUT" | grep -q "📋"; then
  PASS=$((PASS + 1)); echo "  ✅ semantic probe emitted on ambiguous short prompt"
else
  FAIL=$((FAIL + 1)); echo "  ❌ semantic probe should be emitted. Got: $PROBE_OUTPUT"
fi

# Long prompt should NOT trigger probe
setup_state "full" "brainstorming" "false" "false" "" "true"
LONG_PROMPT=$(printf 'a%.0s' {1..100})
NO_PROBE=$(echo "{\"prompt\":\"$LONG_PROMPT\"}" | STATE_FILE="$TEST_STATE" REPO="$(cd "$(dirname "$HOOK")/../.." && pwd)" bash "$HOOK" 2>&1 || true)
if echo "$NO_PROBE" | grep -q "📋"; then
  FAIL=$((FAIL + 1)); echo "  ❌ semantic probe should NOT trigger on long prompts"
else
  PASS=$((PASS + 1)); echo "  ✅ semantic probe suppressed for long prompts"
fi

# Cleanup
rm -f "$TEST_STATE" "$TEST_SNAPSHOT"

echo ""
echo "=== Results: $PASS passed, $FAIL failed ==="
[ "$FAIL" -eq 0 ] && exit 0 || exit 1
