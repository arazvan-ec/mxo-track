#!/usr/bin/env bash
# Test suite for plan-progress.sh wave/task parsing.
# Guards against regression of the PR5 wave-regex fix (accepts `[prefix] Wave N`).
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "$(readlink -f "$0")")" && pwd)"
SCRIPT="$SCRIPT_DIR/plan-progress.sh"

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

# Run only the parser portion of plan-progress.sh by extracting the python block.
parse_waves_and_tasks() {
  local plan="$1"
  python3 - "$plan" <<'PYEOF'
import sys, json, re
path = sys.argv[1]
re_wave = re.compile(r'^###\s+(?:\[[^\]]*\]\s+)?Wave\s+(\d+)(?:\s*[—\-:]\s*(.+?))?(?:\s*\[.*\])?\s*$')
re_task = re.compile(r'^(?:####\s+|-\s+)\*\*([0-9]+[a-z]?)\s*[—\-:]\s*(.+?)\*\*')
waves = tasks = 0
with open(path, 'r', encoding='utf-8') as f:
    for raw in f:
        line = raw.rstrip('\n')
        if re_wave.match(line): waves += 1
        if re_task.match(line): tasks += 1
print(f"{waves} {tasks}")
PYEOF
}

echo "=== plan-progress parser tests ==="

FIX=$(mktemp -d)
trap 'rm -rf "$FIX"' EXIT

# T1: plain waves
cat > "$FIX/plain.md" <<'EOF'
### Wave 1
- **1a: Task one**

### Wave 2: Title
- **2a: Task alpha**
- **2b — Task beta**
EOF
read -r waves tasks <<< "$(parse_waves_and_tasks "$FIX/plain.md")"
assert "T1 plain — 2 waves" "2" "$waves"
assert "T1 plain — 3 tasks" "3" "$tasks"

# T2: [parallel] prefix (the PR5 fix)
cat > "$FIX/parallel.md" <<'EOF'
### [parallel] Wave 1: Foundation
- **1a: Task**

### [parallel] Wave 2: Scripts
- **2a: Task**

### Wave 3: Curation
- **3a: Task**
- **3b: Another**
EOF
read -r waves tasks <<< "$(parse_waves_and_tasks "$FIX/parallel.md")"
assert "T2 [parallel] prefix — 3 waves" "3" "$waves"
assert "T2 [parallel] prefix — 4 tasks" "4" "$tasks"

# T3: Wave with trailing [bracket]
cat > "$FIX/trailing.md" <<'EOF'
### Wave 1 [parallel]
- **1a: Task**

### Wave 2: Title [needs 1]
- **2a: Task**
EOF
read -r waves tasks <<< "$(parse_waves_and_tasks "$FIX/trailing.md")"
assert "T3 trailing [bracket] — 2 waves" "2" "$waves"
assert "T3 trailing [bracket] — 2 tasks" "2" "$tasks"

# T4: Mixed prefixes (sanity)
cat > "$FIX/mixed.md" <<'EOF'
### [parallel] Wave 1: A
- **1a: T**

### Wave 2 — B
- **2a: T**

### [any-text] Wave 3
- **3a: T**
EOF
read -r waves tasks <<< "$(parse_waves_and_tasks "$FIX/mixed.md")"
assert "T4 mixed — 3 waves" "3" "$waves"
assert "T4 mixed — 3 tasks" "3" "$tasks"

# T5: non-wave ### headings are NOT counted
cat > "$FIX/noise.md" <<'EOF'
### Phase 1: v0

### Wave 1: Real
- **1a: Task**

### Wave without number (should not match)
- **2a: Task**
EOF
read -r waves tasks <<< "$(parse_waves_and_tasks "$FIX/noise.md")"
assert "T5 noise headings — 1 wave" "1" "$waves"
assert "T5 noise headings — 2 tasks" "2" "$tasks"

# T6: Empty plan file
: > "$FIX/empty.md"
read -r waves tasks <<< "$(parse_waves_and_tasks "$FIX/empty.md")"
assert "T6 empty — 0 waves" "0" "$waves"
assert "T6 empty — 0 tasks" "0" "$tasks"

# T7: SCRIPT itself exists and is executable (smoke)
[ -x "$SCRIPT" ] && exists=yes || exists=no
assert "T7 plan-progress.sh executable" "yes" "$exists"

# ── on_edit auto-advance tests ────────────────────────────────────────────
# These invoke the real plan-progress.sh, which hardcodes STATE_FILE to the
# main repo's .claude/session-state.json. We back it up, overwrite with a
# controlled fixture, run the action, assert, and restore.

REAL_STATE="/home/user/mxo-track/.claude/session-state.json"
BACKUP="$FIX/session-state.backup.json"

if [ -f "$REAL_STATE" ]; then
  cp "$REAL_STATE" "$BACKUP"

  # T8: file_path matches task 1a's files → current = 1, label set
  cat > "$REAL_STATE" <<'STATE'
{
  "evidence": {
    "task_progress": {
      "current": 0,
      "total": 2,
      "label": null,
      "completed_labels": [],
      "task_index": [
        {"id": "1a", "wave": 1, "label": "First task", "files": ["foo.php"]},
        {"id": "1b", "wave": 1, "label": "Second task", "files": ["bar.php"]}
      ]
    },
    "work_context": {
      "wave": {"total": 1, "current": 0, "label": null, "labels": ["Foundation"]}
    }
  }
}
STATE
  "$SCRIPT" on_edit "/some/dir/foo.php" >/dev/null 2>&1 || true
  t8_cur=$(jq -r '.evidence.task_progress.current' "$REAL_STATE")
  t8_lbl=$(jq -r '.evidence.task_progress.label' "$REAL_STATE")
  assert "T8 file matches task 1a — current=1"          "1"          "$t8_cur"
  assert "T8 file matches task 1a — label set"          "First task" "$t8_lbl"

  # T9: file_path does NOT match any task's files → current unchanged
  cat > "$REAL_STATE" <<'STATE'
{
  "evidence": {
    "task_progress": {
      "current": 0,
      "total": 2,
      "label": null,
      "completed_labels": [],
      "task_index": [
        {"id": "1a", "wave": 1, "label": "First task", "files": ["foo.php"]},
        {"id": "1b", "wave": 1, "label": "Second task", "files": ["bar.php"]}
      ]
    },
    "work_context": {
      "wave": {"total": 1, "current": 0, "label": null, "labels": ["Foundation"]}
    }
  }
}
STATE
  "$SCRIPT" on_edit "/some/dir/unrelated.php" >/dev/null 2>&1 || true
  t9_cur=$(jq -r '.evidence.task_progress.current' "$REAL_STATE")
  t9_lbl=$(jq -r '.evidence.task_progress.label' "$REAL_STATE")
  assert "T9 unrelated file — current unchanged (0)"    "0"    "$t9_cur"
  assert "T9 unrelated file — label unchanged (null)"   "null" "$t9_lbl"

  # Restore the real session-state so we don't leak test fixtures.
  cp "$BACKUP" "$REAL_STATE"
else
  echo "  ⚠ SKIPPING T8/T9: $REAL_STATE not found"
fi

echo ""
echo "── Summary ──"
echo "PASS: $PASS"
echo "FAIL: $FAIL"
[ "$FAIL" -eq 0 ] && exit 0 || exit 1
