#!/usr/bin/env bash
# Test suite for classification suggestion in user-prompt-state.sh (Feature 4, Wave 1 A4).
# Verifies the 💡 Sugerencia line appears when:
#   - CASE A: classification=null + last_action.tool=Edit + framework path → SUGGEST
#   - CASE B: classification=null + last_action.tool=Edit + docs path → NO suggest
#   - CASE C: classification=full + last_action.tool=Edit + framework path → NO suggest
#
# Harness pattern mirrors test-classify-validator.sh: isolated tmp repo per case,
# point the hook script's REPO to it, feed prompt via stdin.
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
    echo "  ✗ $desc"
    echo "    expected to contain: $needle"
    echo "    actual:"
    echo "$haystack" | sed 's/^/      /'
    FAIL=$((FAIL+1))
  fi
}

assert_not_contains() {
  local desc="$1" needle="$2" haystack="$3"
  if echo "$haystack" | grep -qF "$needle"; then
    echo "  ✗ $desc"
    echo "    did NOT expect to contain: $needle"
    echo "    actual:"
    echo "$haystack" | sed 's/^/      /'
    FAIL=$((FAIL+1))
  else
    echo "  ✓ $desc"
    PASS=$((PASS+1))
  fi
}

# Build an isolated tmp repo with a state file and a copy of the hook pointed at it.
make_tmp_repo() {
  local class="$1" tool="$2" file_path="$3"
  local tmp_repo
  tmp_repo=$(mktemp -d)
  mkdir -p "$tmp_repo/.claude/hooks"

  cat > "$tmp_repo/.claude/session-state.json" <<EOF
{
  "session_date": "2026-04-22",
  "flow_type": null,
  "current_phase": null,
  "interaction_classification": $class,
  "phase_history": [],
  "evidence": {
    "decisions_read": false,
    "logs_scanned": false,
    "user_turns": 0,
    "alternatives_proposed": false,
    "user_approved": false,
    "spec_path": null,
    "plan_path": null,
    "tests_written": 0,
    "tests_passed": null,
    "lint_clean": null,
    "execution_log_path": null,
    "branch_strategy": null,
    "root_cause_identified": false,
    "pattern_wide_search_done": false,
    "last_action": {"tool": "$tool", "file_path": "$file_path", "at": "2026-04-22T10:00:00Z"},
    "task_progress": {"current": 0, "total": 0, "label": null, "completed_labels": [], "task_index": []},
    "work_context": {"description": null, "problems": {"total": 0, "current": 0, "labels": []}, "wave": {"total": 0, "current": 0, "label": null, "labels": []}},
    "todo_progress": {"total": 0, "completed": 0, "in_progress_label": null, "items": []}
  },
  "deviation": {"active": false, "reason": null, "skipped_phases": [], "return_to_phase": null, "acknowledged_by_user": false}
}
EOF

  local tmp_hook="$tmp_repo/.claude/hooks/user-prompt-state.sh"
  sed "s|^REPO=\".*\"|REPO=\"$tmp_repo\"|" "$SCRIPT" > "$tmp_hook"
  chmod +x "$tmp_hook"

  echo "$tmp_repo"
}

run_hook() {
  local tmp_repo="$1"
  local tmp_hook="$tmp_repo/.claude/hooks/user-prompt-state.sh"
  echo '{"prompt":""}' | "$tmp_hook" 2>/dev/null || true
}

echo "=== classify-suggestion tests ==="

# CASE A: classification=null + Edit + framework path → SUGGEST
echo "A. null + Edit + .claude/hooks/foo.sh → suggestion present"
REPO_A=$(make_tmp_repo "null" "Edit" ".claude/hooks/foo.sh")
OUT_A=$(run_hook "$REPO_A")
assert_contains "A1: stdout contains '💡 Sugerencia'" "💡 Sugerencia" "$OUT_A"
assert_contains "A2: stdout mentions 'full'" "'full'" "$OUT_A"
rm -rf "$REPO_A"

# CASE B: classification=null + Edit + docs path → NO suggest
echo "B. null + Edit + docs/foo.md → no suggestion (docs carve-out)"
REPO_B=$(make_tmp_repo "null" "Edit" "docs/foo.md")
OUT_B=$(run_hook "$REPO_B")
assert_not_contains "B: stdout does NOT contain '💡 Sugerencia'" "💡 Sugerencia" "$OUT_B"
rm -rf "$REPO_B"

# CASE C: classification=full + Edit + framework path → NO suggest
echo "C. full + Edit + .claude/hooks/foo.sh → no suggestion (already classified)"
REPO_C=$(make_tmp_repo '"full"' "Edit" ".claude/hooks/foo.sh")
OUT_C=$(run_hook "$REPO_C")
assert_not_contains "C: stdout does NOT contain '💡 Sugerencia'" "💡 Sugerencia" "$OUT_C"
rm -rf "$REPO_C"

echo ""
echo "── Summary ──"
echo "PASS: $PASS"
echo "FAIL: $FAIL"
[ "$FAIL" -eq 0 ] && exit 0 || exit 1
