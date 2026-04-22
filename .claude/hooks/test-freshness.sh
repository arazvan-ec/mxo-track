#!/usr/bin/env bash
# Test suite for pre-tool-freshness.sh (Layer D, non-blocking).
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "$(readlink -f "$0")")" && pwd)"
SCRIPT="$SCRIPT_DIR/pre-tool-freshness.sh"

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

# make_state <dest> <flow_type> <current_phase> [spec_path] [plan_path] [branch_strategy]
make_state() {
  local dest="$1" flow="$2" phase="$3"
  local spec="${4:-}" plan="${5:-}" bs="${6:-}"
  cat > "$dest" <<EOF
{
  "flow_type": "$flow",
  "current_phase": "$phase",
  "evidence": {
    "spec_path": $([ -z "$spec" ] && echo "null" || echo "\"$spec\""),
    "plan_path": $([ -z "$plan" ] && echo "null" || echo "\"$plan\""),
    "branch_strategy": $([ -z "$bs" ] && echo "null" || echo "\"$bs\""),
    "tests_passed": null,
    "lint_clean": null
  }
}
EOF
}

# Run hook, capture stderr (warnings) and exit code (always 0 expected)
run_hook() {
  local input_json="$1"
  local state_file="$2"
  local tmp_repo
  tmp_repo=$(mktemp -d)
  mkdir -p "$tmp_repo/.claude"
  cp "$state_file" "$tmp_repo/.claude/session-state.json"
  local tmp_hook
  tmp_hook=$(mktemp)
  sed "s|^REPO=\".*\"|REPO=\"$tmp_repo\"|" "$SCRIPT" > "$tmp_hook"
  chmod +x "$tmp_hook"
  local stderr
  stderr=$(echo "$input_json" | "$tmp_hook" 2>&1 >/dev/null)
  echo "$stderr"
  rm -rf "$tmp_repo" "$tmp_hook"
}

run_hook_exit() {
  local input_json="$1"
  local state_file="$2"
  local tmp_repo
  tmp_repo=$(mktemp -d)
  mkdir -p "$tmp_repo/.claude"
  cp "$state_file" "$tmp_repo/.claude/session-state.json"
  local tmp_hook
  tmp_hook=$(mktemp)
  sed "s|^REPO=\".*\"|REPO=\"$tmp_repo\"|" "$SCRIPT" > "$tmp_hook"
  chmod +x "$tmp_hook"
  local exit_code=0
  echo "$input_json" | "$tmp_hook" >/dev/null 2>&1 || exit_code=$?
  echo "$exit_code"
  rm -rf "$tmp_repo" "$tmp_hook"
}

FIX=$(mktemp -d)
trap 'rm -rf "$FIX"' EXIT

echo "=== pre-tool-freshness tests ==="

# T1: Writing spec during implementation → WARN
make_state "$FIX/impl.json" "full" "implementation"
INPUT1='{"tool_name":"Write","tool_input":{"file_path":"docs/superpowers/specs/foo.md"}}'
OUT=$(run_hook "$INPUT1" "$FIX/impl.json")
if echo "$OUT" | grep -q "POSIBLE STALE STATE"; then has_warn=yes; else has_warn=no; fi
assert "T1: spec write during implementation → warn" "yes" "$has_warn"

# T2: Writing spec during brainstorming → silent
make_state "$FIX/brain.json" "full" "brainstorming"
OUT=$(run_hook "$INPUT1" "$FIX/brain.json")
if echo "$OUT" | grep -q "POSIBLE STALE STATE"; then has_warn=yes; else has_warn=no; fi
assert "T2: spec write during brainstorming → silent" "no" "$has_warn"

# T3: Writing plan during implementation → WARN
make_state "$FIX/impl2.json" "full" "implementation"
INPUT3='{"tool_name":"Write","tool_input":{"file_path":"docs/superpowers/plans/foo.md"}}'
OUT=$(run_hook "$INPUT3" "$FIX/impl2.json")
if echo "$OUT" | grep -q "POSIBLE STALE STATE"; then has_warn=yes; else has_warn=no; fi
assert "T3: plan write during implementation → warn" "yes" "$has_warn"

# T4: Writing plan during planning → silent
make_state "$FIX/plan.json" "full" "planning"
OUT=$(run_hook "$INPUT3" "$FIX/plan.json")
if echo "$OUT" | grep -q "POSIBLE STALE STATE"; then has_warn=yes; else has_warn=no; fi
assert "T4: plan write during planning → silent" "no" "$has_warn"

# T5: git push in finalize without branch_strategy → WARN
make_state "$FIX/fin.json" "full" "finalize"
INPUT5='{"tool_name":"Bash","tool_input":{"command":"git push -u origin main"}}'
OUT=$(run_hook "$INPUT5" "$FIX/fin.json")
if echo "$OUT" | grep -q "branch_strategy unset"; then has_warn=yes; else has_warn=no; fi
assert "T5: git push in finalize without branch_strategy → warn" "yes" "$has_warn"

# T6: git push in finalize WITH branch_strategy → silent
make_state "$FIX/fin2.json" "full" "finalize" "" "" "pr"
OUT=$(run_hook "$INPUT5" "$FIX/fin2.json")
if echo "$OUT" | grep -q "branch_strategy unset"; then has_warn=yes; else has_warn=no; fi
assert "T6: git push in finalize WITH branch_strategy → silent" "no" "$has_warn"

# T7: git commit during consult phase → WARN
make_state "$FIX/consult.json" "full" "consult"
INPUT7='{"tool_name":"Bash","tool_input":{"command":"git commit -m test"}}'
OUT=$(run_hook "$INPUT7" "$FIX/consult.json")
if echo "$OUT" | grep -q "consult phase"; then has_warn=yes; else has_warn=no; fi
assert "T7: git commit during consult → warn" "yes" "$has_warn"

# T8: Writing execution log during capture → silent
make_state "$FIX/cap.json" "full" "capture"
INPUT8='{"tool_name":"Write","tool_input":{"file_path":"docs/superpowers/execution-logs/x.md"}}'
OUT=$(run_hook "$INPUT8" "$FIX/cap.json")
if echo "$OUT" | grep -q "POSIBLE STALE STATE"; then has_warn=yes; else has_warn=no; fi
assert "T8: execution-log write during capture → silent" "no" "$has_warn"

# T9: Writing execution log during planning → WARN
make_state "$FIX/plan2.json" "full" "planning"
OUT=$(run_hook "$INPUT8" "$FIX/plan2.json")
if echo "$OUT" | grep -q "POSIBLE STALE STATE"; then has_warn=yes; else has_warn=no; fi
assert "T9: execution-log write during planning → warn" "yes" "$has_warn"

# T10: Exit code is always 0 (non-blocking)
RC=$(run_hook_exit "$INPUT1" "$FIX/impl.json")
assert "T10: non-blocking, exit 0 even when warning" "0" "$RC"

echo ""
echo "── Summary ──"
echo "PASS: $PASS"
echo "FAIL: $FAIL"
[ "$FAIL" -eq 0 ] && exit 0 || exit 1
