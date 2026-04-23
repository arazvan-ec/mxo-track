#!/usr/bin/env bash
# Test suite for the classification-suggestion block in user-prompt-state.sh.
# Tests only the suggestion output; does not exercise flow-specific render.
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "$(readlink -f "$0")")" && pwd)"
SCRIPT="$SCRIPT_DIR/user-prompt-state.sh"

PASS=0
FAIL=0

assert_contains() {
  local desc="$1" needle="$2" haystack="$3"
  if echo "$haystack" | grep -qF "$needle"; then
    echo "  ✓ $desc"
    PASS=$((PASS+1))
  else
    echo "  ✗ $desc (missing: '$needle')"
    FAIL=$((FAIL+1))
  fi
}

assert_not_contains() {
  local desc="$1" needle="$2" haystack="$3"
  if echo "$haystack" | grep -qF "$needle"; then
    echo "  ✗ $desc (unexpected: '$needle')"
    FAIL=$((FAIL+1))
  else
    echo "  ✓ $desc"
    PASS=$((PASS+1))
  fi
}

make_state() {
  local dest="$1" class="$2" tool="$3" fp="$4"
  local class_json
  if [ "$class" = "null" ]; then
    class_json="null"
  else
    class_json="\"$class\""
  fi
  cat > "$dest" <<EOF
{
  "flow_type": null,
  "interaction_classification": $class_json,
  "current_phase": null,
  "phase_history": [],
  "evidence": {
    "last_action": {"tool": "$tool", "file_path": "$fp", "at": "2026-04-23T00:00:00Z"}
  }
}
EOF
}

run_hook() {
  local state_file="$1"
  local tmp_repo
  tmp_repo=$(mktemp -d)
  mkdir -p "$tmp_repo/.claude"
  cp "$state_file" "$tmp_repo/.claude/session-state.json"
  local tmp_hook
  tmp_hook=$(mktemp)
  sed "s|^REPO=\".*\"|REPO=\"$tmp_repo\"|" "$SCRIPT" > "$tmp_hook"
  chmod +x "$tmp_hook"
  "$tmp_hook" 2>/dev/null || true
  rm -rf "$tmp_repo" "$tmp_hook"
}

FIX=$(mktemp -d)
trap 'rm -rf "$FIX"' EXIT

echo "=== classify-suggestion tests ==="

# A: null class + Edit to framework path → suggestion fires
make_state "$FIX/a.json" "null" "Edit" ".claude/hooks/foo.sh"
OUT=$(run_hook "$FIX/a.json")
assert_contains "A: framework path + null class → suggestion printed" "💡 Sugerencia" "$OUT"
assert_contains "A: suggestion mentions 'full'" "'full'" "$OUT"
assert_contains "A: suggestion mentions the path" ".claude/hooks/foo.sh" "$OUT"

# B: null class + Edit to docs path → silent
make_state "$FIX/b.json" "null" "Edit" "docs/foo.md"
OUT=$(run_hook "$FIX/b.json")
assert_not_contains "B: docs path + null class → no suggestion" "💡 Sugerencia" "$OUT"

# C: full class + Edit to framework path → silent
make_state "$FIX/c.json" "full" "Edit" ".claude/hooks/foo.sh"
OUT=$(run_hook "$FIX/c.json")
assert_not_contains "C: framework path + full class → no suggestion" "💡 Sugerencia" "$OUT"

# D: null class + Bash (not Edit/Write) → silent
make_state "$FIX/d.json" "null" "Bash" ""
OUT=$(run_hook "$FIX/d.json")
assert_not_contains "D: Bash tool + null class → no suggestion" "💡 Sugerencia" "$OUT"

# E: null class + Write to backend/src → suggestion fires
make_state "$FIX/e.json" "null" "Write" "backend/src/Controller/Foo.php"
OUT=$(run_hook "$FIX/e.json")
assert_contains "E: backend/src + null class + Write → suggestion printed" "💡 Sugerencia" "$OUT"

# F: null class + Edit to absolute framework path → suggestion fires
make_state "$FIX/f.json" "null" "Edit" "/home/user/mxo-track/.claude/hooks/x.sh"
OUT=$(run_hook "$FIX/f.json")
assert_contains "F: absolute framework path + null class → suggestion printed" "💡 Sugerencia" "$OUT"

echo ""
echo "── Summary ──"
echo "PASS: $PASS"
echo "FAIL: $FAIL"
[ "$FAIL" -eq 0 ] && exit 0 || exit 1
