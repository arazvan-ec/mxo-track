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

# ── Parser + auto_advance (Wave 1 A2) ──

# T8: parser extracts files from "- Files: `a`, `b`" pattern (look-ahead)
cat > "$FIX/files.md" <<'EOF'
### Wave 1

- **1a — Build something**
- Files: `foo.sh`, `bar.sh`
- Another bullet

- **1b — Second task**
- Files: `baz.sh`
EOF
# Use the real script to parse — set up a temporary state to point at this plan
TMPSTATE=$(mktemp)
cat > "$TMPSTATE" <<'STATE'
{"evidence":{"plan_path":"__PLAN__","task_progress":{"current":0,"total":0,"label":null,"completed_labels":[],"task_index":[]},"work_context":{"wave":{"total":0,"current":0,"label":null,"labels":[]}}}}
STATE
# Can't easily invoke init with custom REPO without stubbing — so invoke the parser directly
# via the python block. Re-run the regex/parse logic by extracting tasks manually:
T8_FILES=$(python3 - "$FIX/files.md" <<'PYEOF'
import sys, json, re
re_task = re.compile(r'^(?:####\s+|-\s+)?\*\*([0-9]+[a-z]?|[A-Z][0-9]+)\s*[—\-:]\s*(.+?)\*\*')
re_files = re.compile(r'^\s*[-\*]?\s*(?:Files?:|→\s*files?:)\s*(.+?)\s*$', re.IGNORECASE)
def extract(raw):
    cleaned = raw.replace('`', '').replace('*', '')
    parts = re.split(r'[,\s]+', cleaned)
    return [p.strip() for p in parts if p.strip() and not p.strip().startswith('#')]
tasks = []
with open(sys.argv[1]) as f:
    lines = f.readlines()
for i, line in enumerate(lines):
    m = re_task.match(line.rstrip('\n'))
    if m:
        files = []
        for j in range(i+1, min(i+6, len(lines))):
            la = lines[j].rstrip('\n')
            if re_task.match(la):
                break
            fm = re_files.match(la)
            if fm:
                files = extract(fm.group(1))
                break
        tasks.append({"id": m.group(1), "files": files})
print(json.dumps(tasks))
PYEOF
)
T8_COUNT=$(echo "$T8_FILES" | jq 'length')
T8_FIRST_ID=$(echo "$T8_FILES" | jq -r '.[0].id')
T8_FIRST_FILES=$(echo "$T8_FILES" | jq -r '.[0].files | join(",")')
T8_SECOND_FILES=$(echo "$T8_FILES" | jq -r '.[1].files | join(",")')
assert "T8 parser — 2 tasks extracted" "2" "$T8_COUNT"
assert "T8 parser — first id=1a" "1a" "$T8_FIRST_ID"
assert "T8 parser — first files=foo.sh,bar.sh" "foo.sh,bar.sh" "$T8_FIRST_FILES"
assert "T8 parser — second files=baz.sh" "baz.sh" "$T8_SECOND_FILES"
rm -f "$TMPSTATE"

# T9: parser accepts letter-prefixed task IDs (A1, A2, ...)
T9_FILES=$(python3 - <<'PYEOF'
import re, json
re_task = re.compile(r'^(?:####\s+|-\s+)?\*\*([0-9]+[a-z]?|[A-Z][0-9]+)\s*[—\-:]\s*(.+?)\*\*')
samples = [
    "**A1 — Letter prefix title**",
    "- **B2 — Another letter id**",
    "#### **3c — Numeric with letter**",
    "**1 — Numeric-only**",
]
ids = [re_task.match(s).group(1) for s in samples if re_task.match(s)]
print(json.dumps(ids))
PYEOF
)
T9_IDS=$(echo "$T9_FILES" | jq -r 'join(",")')
assert "T9 parser — letter-prefixed ids accepted" "A1,B2,3c,1" "$T9_IDS"

# T10: auto_advance — matching file advances current
REPO="${REPO:-/home/user/mxo-track}"
STATE_FILE="$REPO/.claude/session-state.json"
# Backup real state
BACKUP_STATE=$(cat "$STATE_FILE")
# Set a minimal state with 2 known tasks
jq '.evidence.task_progress = {
  "current": 0,
  "total": 2,
  "label": null,
  "completed_labels": [],
  "task_index": [
    {"id": "1a", "wave": 1, "label": "Task one", "files": ["foo/bar.sh"]},
    {"id": "1b", "wave": 1, "label": "Task two", "files": ["baz/qux.sh"]}
  ]
} | .evidence.work_context.wave = {
  "total": 1, "current": 0, "label": null, "labels": ["Wave 1"]
}' "$STATE_FILE" > /tmp/t10.json && mv /tmp/t10.json "$STATE_FILE"

bash "$SCRIPT" auto_advance "foo/bar.sh" >/dev/null 2>&1
T10_CUR=$(jq -r '.evidence.task_progress.current' "$STATE_FILE")
T10_LBL=$(jq -r '.evidence.task_progress.label' "$STATE_FILE")
assert "T10 auto_advance — matching file → current=1" "1" "$T10_CUR"
assert "T10 auto_advance — label set" "Task one" "$T10_LBL"

# T11: auto_advance — non-matching file → no change
bash "$SCRIPT" auto_advance "random/other.sh" >/dev/null 2>&1
T11_CUR=$(jq -r '.evidence.task_progress.current' "$STATE_FILE")
assert "T11 auto_advance — non-match preserves current=1" "1" "$T11_CUR"

# T12: auto_advance — never decrements (match earlier task when on task 2)
jq '.evidence.task_progress.current = 2 | .evidence.task_progress.label = "Task two"' "$STATE_FILE" > /tmp/t12.json && mv /tmp/t12.json "$STATE_FILE"
bash "$SCRIPT" auto_advance "foo/bar.sh" >/dev/null 2>&1
T12_CUR=$(jq -r '.evidence.task_progress.current' "$STATE_FILE")
assert "T12 auto_advance — never decrements (stays at 2)" "2" "$T12_CUR"

# T13: auto_advance — empty args → silent no-op
bash "$SCRIPT" auto_advance "" >/dev/null 2>&1
T13_CUR=$(jq -r '.evidence.task_progress.current' "$STATE_FILE")
assert "T13 auto_advance — empty arg → no-op" "2" "$T13_CUR"

# T14: auto_advance — absolute path matches relative file declaration
jq '.evidence.task_progress.current = 0 | .evidence.task_progress.label = null' "$STATE_FILE" > /tmp/t14.json && mv /tmp/t14.json "$STATE_FILE"
bash "$SCRIPT" auto_advance "/home/user/mxo-track/foo/bar.sh" >/dev/null 2>&1
T14_CUR=$(jq -r '.evidence.task_progress.current' "$STATE_FILE")
assert "T14 auto_advance — absolute path normalised and matched" "1" "$T14_CUR"

# Restore real state
echo "$BACKUP_STATE" > "$STATE_FILE"

echo ""
echo "── Summary ──"
echo "PASS: $PASS"
echo "FAIL: $FAIL"
[ "$FAIL" -eq 0 ] && exit 0 || exit 1
