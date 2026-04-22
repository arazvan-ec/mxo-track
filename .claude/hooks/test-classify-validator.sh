#!/usr/bin/env bash
# Test suite for classify-validator.sh (Layer A).
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "$(readlink -f "$0")")" && pwd)"
SCRIPT="$SCRIPT_DIR/validators/classify-validator.sh"

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
  local dest="$1"; shift
  local class="$1"
  cat > "$dest" <<EOF
{
  "interaction_id": 1,
  "interaction_classification": "$class",
  "flow_type": "$class",
  "current_phase": "implementation",
  "phase_history": [],
  "evidence": {},
  "deviation": {"active": false}
}
EOF
}

run_hook() {
  local input_json="$1"
  local state_file="$2"
  local env_prefix="${3:-}"
  local tmp_repo
  tmp_repo=$(mktemp -d)
  mkdir -p "$tmp_repo/.claude/hooks/validators"
  cp "$state_file" "$tmp_repo/.claude/session-state.json"
  local tmp_hook="$tmp_repo/.claude/hooks/validators/classify-validator.sh"
  sed "s|^REPO=\".*\"|REPO=\"$tmp_repo\"|" "$SCRIPT" > "$tmp_hook"
  chmod +x "$tmp_hook"
  local exit_code=0
  if [ -n "$env_prefix" ]; then
    echo "$input_json" | env $env_prefix "$tmp_hook" >/dev/null 2>&1 || exit_code=$?
  else
    echo "$input_json" | "$tmp_hook" >/dev/null 2>&1 || exit_code=$?
  fi
  echo "$exit_code"
  rm -rf "$tmp_repo"
}

FIX=$(mktemp -d)
trap 'rm -rf "$FIX"' EXIT

echo "=== classify-validator tests ==="

# T1: framework path (.claude/hooks/foo.sh) + light → BLOCK (exit 2)
make_state "$FIX/light.json" "light"
INPUT1='{"tool_input":{"file_path":".claude/hooks/foo.sh"}}'
RC=$(run_hook "$INPUT1" "$FIX/light.json")
assert "T1: .claude/ + light → block (exit 2)" "2" "$RC"

# T2: framework path + full → allow (exit 0)
make_state "$FIX/full.json" "full"
RC=$(run_hook "$INPUT1" "$FIX/full.json")
assert "T2: .claude/ + full → allow (exit 0)" "0" "$RC"

# T3: framework path + debug → allow (exit 0)
make_state "$FIX/debug.json" "debug"
RC=$(run_hook "$INPUT1" "$FIX/debug.json")
assert "T3: .claude/ + debug → allow (exit 0)" "0" "$RC"

# T4: docs/ path + light → allow (carve-out)
INPUT4='{"tool_input":{"file_path":"docs/superpowers/specs/x.md"}}'
RC=$(run_hook "$INPUT4" "$FIX/light.json")
assert "T4: docs/ + light → allow (carve-out)" "0" "$RC"

# T5: *.md at repo root + light → allow (carve-out)
INPUT5='{"tool_input":{"file_path":"CLAUDE.md"}}'
RC=$(run_hook "$INPUT5" "$FIX/light.json")
assert "T5: CLAUDE.md + light → allow (*.md carve-out)" "0" "$RC"

# T6: session-state.json + light → allow (carve-out)
INPUT6='{"tool_input":{"file_path":".claude/session-state.json"}}'
RC=$(run_hook "$INPUT6" "$FIX/light.json")
assert "T6: session-state.json + light → allow (carve-out)" "0" "$RC"

# T7: backend/src/ + micro → block
INPUT7='{"tool_input":{"file_path":"backend/src/Controller/Foo.php"}}'
make_state "$FIX/micro.json" "micro"
RC=$(run_hook "$INPUT7" "$FIX/micro.json")
assert "T7: backend/src/ + micro → block" "2" "$RC"

# T8: SKIP_CLASSIFY_GATE=1 bypass
RC=$(run_hook "$INPUT1" "$FIX/light.json" "SKIP_CLASSIFY_GATE=1")
assert "T8: SKIP_CLASSIFY_GATE=1 → allow even with light" "0" "$RC"

# T9: frontend/src/ + explore → block
INPUT9='{"tool_input":{"file_path":"frontend/src/App.tsx"}}'
make_state "$FIX/explore.json" "explore"
RC=$(run_hook "$INPUT9" "$FIX/explore.json")
assert "T9: frontend/src/ + explore → block" "2" "$RC"

# T10: non-framework path (README.md outside docs) + light → allow
INPUT10='{"tool_input":{"file_path":"README.md"}}'
RC=$(run_hook "$INPUT10" "$FIX/light.json")
assert "T10: README.md + light → allow (*.md carve-out)" "0" "$RC"

# T11: absolute path normalization
INPUT11='{"tool_input":{"file_path":"/home/user/mxo-track/.claude/hooks/x.sh"}}'
RC=$(run_hook "$INPUT11" "$FIX/light.json")
assert "T11: absolute framework path + light → block" "2" "$RC"

echo ""
echo "── Summary ──"
echo "PASS: $PASS"
echo "FAIL: $FAIL"
[ "$FAIL" -eq 0 ] && exit 0 || exit 1
