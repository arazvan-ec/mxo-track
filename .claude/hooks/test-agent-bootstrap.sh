#!/usr/bin/env bash
# Test suite for agent-bootstrap.sh.
# Verifies idempotence + null recovery + validation.
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "$(readlink -f "$0")")" && pwd)"
SCRIPT="$SCRIPT_DIR/agent-bootstrap.sh"

PASS=0
FAIL=0

assert() {
  local desc="$1" expected="$2" actual="$3"
  if [ "$expected" = "$actual" ]; then
    echo "  ✓ $desc"
    PASS=$((PASS+1))
  else
    echo "  ✗ $desc (expected='$expected' actual='$actual')"
    FAIL=$((FAIL+1))
  fi
}

make_state() {
  local dest="$1" class="$2" phase="$3"
  cat > "$dest" <<EOF
{
  "session_date": "$(date +%Y-%m-%d)",
  "interaction_classification": $([ "$class" = "null" ] && echo "null" || echo "\"$class\""),
  "flow_type": $([ "$class" = "null" ] && echo "null" || echo "\"$class\""),
  "current_phase": $([ "$phase" = "null" ] && echo "null" || echo "\"$phase\""),
  "phase_history": [],
  "evidence": {"spec_path": "docs/fake-spec.md", "plan_path": "docs/fake-plan.md"}
}
EOF
}

# Run bootstrap with a fake REPO
run_bootstrap() {
  local state_file="$1"
  local class="$2"
  local phase="${3:-implementation}"
  local tmp_repo
  tmp_repo=$(mktemp -d)
  mkdir -p "$tmp_repo/.claude"
  cp "$state_file" "$tmp_repo/.claude/session-state.json"
  local tmp_hook
  tmp_hook=$(mktemp)
  sed "s|^REPO=\".*\"|REPO=\"$tmp_repo\"|" "$SCRIPT" > "$tmp_hook"
  chmod +x "$tmp_hook"
  local exit_code=0
  "$tmp_hook" "$class" "$phase" >/dev/null 2>&1 || exit_code=$?
  echo "$exit_code"
  cp "$tmp_repo/.claude/session-state.json" "$state_file.result"
  rm -rf "$tmp_repo" "$tmp_hook"
}

FIX=$(mktemp -d)
trap 'rm -rf "$FIX"' EXIT

echo "=== agent-bootstrap tests ==="

# T1: null classification → bootstrap sets to "full"
make_state "$FIX/s1.json" "null" "null"
RC=$(run_bootstrap "$FIX/s1.json" "full")
assert "T1: null class → exit 0" "0" "$RC"
CLASS=$(jq -r '.interaction_classification' "$FIX/s1.json.result")
FLOW=$(jq -r '.flow_type' "$FIX/s1.json.result")
PHASE=$(jq -r '.current_phase' "$FIX/s1.json.result")
assert "T1: classification set to full" "full" "$CLASS"
assert "T1: flow_type set to full" "full" "$FLOW"
assert "T1: phase defaulted to implementation" "implementation" "$PHASE"

# T2: already-correct state → idempotent no-op
make_state "$FIX/s2.json" "full" "implementation"
RC=$(run_bootstrap "$FIX/s2.json" "full")
assert "T2: already correct → exit 0" "0" "$RC"
CLASS=$(jq -r '.interaction_classification' "$FIX/s2.json.result")
assert "T2: classification still full" "full" "$CLASS"

# T3: different classification → overwritten
make_state "$FIX/s3.json" "light" "implementation"
RC=$(run_bootstrap "$FIX/s3.json" "full")
assert "T3: mismatched class → exit 0" "0" "$RC"
CLASS=$(jq -r '.interaction_classification' "$FIX/s3.json.result")
assert "T3: light overwritten with full" "full" "$CLASS"

# T4: phase preserved if already set
make_state "$FIX/s4.json" "full" "verification"
RC=$(run_bootstrap "$FIX/s4.json" "full" "implementation")
assert "T4: phase=verification preserved → exit 0" "0" "$RC"
PHASE=$(jq -r '.current_phase' "$FIX/s4.json.result")
assert "T4: existing phase 'verification' not overwritten" "verification" "$PHASE"

# T5: phase defaulted only when null
make_state "$FIX/s5.json" "full" "null"
RC=$(run_bootstrap "$FIX/s5.json" "full" "implementation")
assert "T5: null phase → exit 0" "0" "$RC"
PHASE=$(jq -r '.current_phase' "$FIX/s5.json.result")
assert "T5: null phase defaulted to implementation" "implementation" "$PHASE"

# T6: unknown classification → exit 2
make_state "$FIX/s6.json" "null" "null"
RC=$(run_bootstrap "$FIX/s6.json" "bogus")
assert "T6: unknown class 'bogus' → exit 2" "2" "$RC"

# T7: evidence preserved
make_state "$FIX/s7.json" "null" "null"
run_bootstrap "$FIX/s7.json" "full" >/dev/null
SPEC=$(jq -r '.evidence.spec_path' "$FIX/s7.json.result")
PLAN=$(jq -r '.evidence.plan_path' "$FIX/s7.json.result")
assert "T7: spec_path preserved" "docs/fake-spec.md" "$SPEC"
assert "T7: plan_path preserved" "docs/fake-plan.md" "$PLAN"

echo ""
echo "── Summary ──"
echo "PASS: $PASS"
echo "FAIL: $FAIL"
[ "$FAIL" -eq 0 ] && exit 0 || exit 1
