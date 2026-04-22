#!/usr/bin/env bash
# plan-progress.sh — Parse plan markdown into session-state task/wave structure.
#
# Usage:
#   plan-progress.sh init                   → parse evidence.plan_path, populate task_progress + wave
#   plan-progress.sh advance <task_id>      → set current to task_id (e.g. "2a")
#   plan-progress.sh complete               → archive current label, advance, reset if all done
#   plan-progress.sh on_edit <file_path>    → if file_path matches a task's files:, auto-advance (never decrement)
#   plan-progress.sh show                   → debug: print parsed structure
#
# Parsed plan format expected:
#   ### Wave N — <label>            (also tolerates "### Wave N [parallel: ...]" or "### Wave N")
#   #### **Na — <task title>**       (also tolerates "#### **N — title**" without letter suffix)
#
# Task file declarations (optional, used by on_edit auto-advance):
#   → files: path/a.php, path/b.php
#   files: path/a.php, path/b.php
#   - Files: path/a.php, path/b.php
# Lines following a task header until the next task/wave header are scanned for these.
#
# Non-blocking: errors print to stderr and exit non-zero, but never corrupt session-state.

set -euo pipefail

REPO="/home/user/mxo-track"
STATE_FILE="$REPO/.claude/session-state.json"

if [ ! -f "$STATE_FILE" ]; then
  echo "ERROR: session-state.json not found at $STATE_FILE" >&2
  exit 1
fi

# ── Parse the plan file into a JSON structure ────────────────────────────────
parse_plan() {
  local plan_rel
  plan_rel=$(jq -r '.evidence.plan_path // ""' "$STATE_FILE")
  if [ -z "$plan_rel" ]; then
    echo "ERROR: evidence.plan_path is empty — set it before running 'init'" >&2
    exit 2
  fi
  local plan_abs="$REPO/$plan_rel"
  if [ ! -f "$plan_abs" ]; then
    echo "ERROR: plan file not found: $plan_abs" >&2
    exit 3
  fi
  parse_plan_file "$plan_abs"
}

# parse_plan_file <absolute_path> — emits "<waves_json>|||<tasks_json>"
# Each task may include a "files" array when the plan declares `files:` / `→ files:`
# lines between the task header and the next task/wave.
parse_plan_file() {
  local plan_abs="$1"
  python3 - "$plan_abs" <<'PYEOF'
import sys, json, re

path = sys.argv[1]
waves = []
tasks = []
current_wave = 0
current_task = None  # ref to tasks[-1] while collecting `files:` lines

# Wave header: ### [opt-prefix] Wave N [— label] [opt-trailing-brackets]
# Accepts:
#   ### Wave 1
#   ### Wave 1: Title
#   ### Wave 1 — Title
#   ### [parallel] Wave 2: Title
#   ### Wave 3 [parallel]
re_wave = re.compile(r'^###\s+(?:\[[^\]]*\]\s+)?Wave\s+(\d+)(?:\s*[—\-:]\s*(.+?))?(?:\s*\[.*\])?\s*$')
# Task header: #### **Na — Title** ...   OR   - **Na — Title** ...
re_task = re.compile(r'^(?:####\s+|-\s+)\*\*([0-9]+[a-z]?)\s*[—\-:]\s*(.+?)\*\*')
# files declaration: optional leading arrow/bullet/whitespace, "Files:" or "files:"
# Examples matched:
#   → files: a.php, b.php
#   - Files: a.php
#   files: a.php, b.php
#   * → files: a.php
re_files = re.compile(r'^[\s\-\*]*(?:→\s*)?[Ff]iles\s*:\s*(.+?)\s*$')

def flush_files(line):
    if current_task is None:
        return False
    m = re_files.match(line)
    if not m:
        return False
    decl = m.group(1).strip()
    # split by comma, then trim; also split any whitespace inside a token
    parts = []
    for chunk in decl.split(','):
        chunk = chunk.strip().strip('`')
        if not chunk:
            continue
        # Drop trailing punctuation like "." or ")" or markdown emphasis
        chunk = chunk.rstrip('.,;)')
        chunk = chunk.strip('*_')
        if chunk:
            parts.append(chunk)
    if parts:
        current_task.setdefault('files', []).extend(parts)
    return True

with open(path, 'r', encoding='utf-8') as f:
    for raw in f:
        line = raw.rstrip('\n')
        m = re_wave.match(line)
        if m:
            n = int(m.group(1))
            label = (m.group(2) or '').strip()
            waves.append({"n": n, "label": label})
            current_wave = n
            current_task = None
            continue
        m = re_task.match(line)
        if m:
            tid = m.group(1).strip()
            title = m.group(2).strip()
            tobj = {"id": tid, "wave": current_wave, "label": title}
            tasks.append(tobj)
            current_task = tobj
            continue
        # Any other line: see if it declares files for the active task.
        flush_files(line)

sys.stdout.write(json.dumps(waves, ensure_ascii=False) + "|||" + json.dumps(tasks, ensure_ascii=False))
PYEOF
}

# ── Action: init ─────────────────────────────────────────────────────────────
action_init() {
  local parsed waves_json tasks_json
  parsed=$(parse_plan)
  waves_json="${parsed%|||*}"
  tasks_json="${parsed##*|||}"

  if [ "$waves_json" = "[]" ] && [ "$tasks_json" = "[]" ]; then
    echo "WARN: plan parsed empty — no '### Wave N' or '#### **Na' headers found" >&2
  fi

  local total_tasks total_waves
  total_tasks=$(echo "$tasks_json" | jq 'length')
  total_waves=$(echo "$waves_json" | jq 'length')

  jq --argjson waves "$waves_json" --argjson tasks "$tasks_json" --argjson tw "$total_waves" --argjson tt "$total_tasks" '
    .evidence.task_progress.total = $tt |
    .evidence.task_progress.current = (.evidence.task_progress.current // 0) |
    .evidence.task_progress.label = (.evidence.task_progress.label // null) |
    .evidence.task_progress.completed_labels = (.evidence.task_progress.completed_labels // []) |
    .evidence.task_progress.task_index = $tasks |
    .evidence.work_context.wave.total = $tw |
    .evidence.work_context.wave.current = (.evidence.work_context.wave.current // 0) |
    .evidence.work_context.wave.label = (.evidence.work_context.wave.label // null) |
    .evidence.work_context.wave.labels = ($waves | map(.label))
  ' "$STATE_FILE" > /tmp/pp_init.json && mv /tmp/pp_init.json "$STATE_FILE"

  echo "✅ Plan parseado: $total_waves waves, $total_tasks tareas"
}

# ── Action: advance <task_id> ────────────────────────────────────────────────
action_advance() {
  local target="${1:-}"
  if [ -z "$target" ]; then
    echo "ERROR: usage: plan-progress.sh advance <task_id>" >&2
    exit 2
  fi

  # Find ordinal (1-based index in tasks array) and metadata
  local found
  found=$(jq -r --arg t "$target" '
    .evidence.task_progress.task_index // [] | to_entries
    | map(select(.value.id == $t))
    | if length == 0 then "" else
        .[0] | "\(.key + 1)|\(.value.wave)|\(.value.label)"
      end
  ' "$STATE_FILE")

  if [ -z "$found" ]; then
    echo "ERROR: task_id '$target' not found in plan index. Run 'init' first or check spelling." >&2
    exit 4
  fi

  local ordinal wave_n task_label wave_label
  ordinal="${found%%|*}"
  local rest="${found#*|}"
  wave_n="${rest%%|*}"
  task_label="${rest#*|}"

  wave_label=$(jq -r --argjson n "$wave_n" '
    .evidence.work_context.wave.labels // [] | .[$n - 1] // ""
  ' "$STATE_FILE")

  jq --argjson o "$ordinal" --arg tl "$task_label" --argjson wn "$wave_n" --arg wl "$wave_label" '
    .evidence.task_progress.current = $o |
    .evidence.task_progress.label = $tl |
    .evidence.work_context.wave.current = $wn |
    .evidence.work_context.wave.label = $wl
  ' "$STATE_FILE" > /tmp/pp_adv.json && mv /tmp/pp_adv.json "$STATE_FILE"

  echo "✅ Tarea actual: $target ($ordinal/$(jq -r '.evidence.task_progress.total' "$STATE_FILE")) — Wave $wave_n: $wave_label"
}

# ── Action: complete ─────────────────────────────────────────────────────────
action_complete() {
  local cur tot label
  cur=$(jq -r '.evidence.task_progress.current // 0' "$STATE_FILE")
  tot=$(jq -r '.evidence.task_progress.total // 0' "$STATE_FILE")
  label=$(jq -r '.evidence.task_progress.label // ""' "$STATE_FILE")

  if [ "$cur" = "0" ] || [ "$tot" = "0" ]; then
    echo "WARN: no current task to complete" >&2
    exit 0
  fi

  jq --arg lbl "$label" '
    .evidence.task_progress.completed_labels = ((.evidence.task_progress.completed_labels // []) + [$lbl] | unique)
  ' "$STATE_FILE" > /tmp/pp_cmp.json && mv /tmp/pp_cmp.json "$STATE_FILE"

  local done_count
  done_count=$(jq -r '.evidence.task_progress.completed_labels | length' "$STATE_FILE")

  if [ "$done_count" -ge "$tot" ]; then
    jq '.evidence.task_progress.current = 0 | .evidence.task_progress.label = null' "$STATE_FILE" > /tmp/pp_rst.json && mv /tmp/pp_rst.json "$STATE_FILE"
    echo "🎉 Todas las $tot tareas completadas. Listo para verification."
  else
    echo "✅ Tarea $cur/$tot completada ($done_count completadas en total)"
  fi
}

# ── Action: on_edit <file_path> ──────────────────────────────────────────────
# Auto-advance task_progress.current when the edited file matches a task's `files:`
# declaration (substring match, case-sensitive, path-relative). Never decrements.
# Silent no-op when:
#   • task_index is empty (no plan loaded)
#   • file_path is empty
#   • no task declares a matching file
# First matching task wins (iteration order = plan order).
action_on_edit() {
  local file_path="${1:-}"
  [ -z "$file_path" ] && exit 0

  # Short-circuit if task_index is not populated.
  local has_tasks
  has_tasks=$(jq -r '.evidence.task_progress.task_index // [] | length' "$STATE_FILE" 2>/dev/null || echo 0)
  [ "$has_tasks" = "0" ] && exit 0

  # Find first task whose `files` array contains an entry that is a substring
  # of $file_path. Emit "<ordinal>|<label>" or empty string.
  local found
  found=$(jq -r --arg fp "$file_path" '
    .evidence.task_progress.task_index // [] | to_entries
    | map(select(
        (.value.files // []) as $fs
        | any($fs[]; . as $f | ($f != "") and ($fp | contains($f)))
      ))
    | if length == 0 then "" else
        .[0] | "\(.key + 1)|\(.value.label)"
      end
  ' "$STATE_FILE" 2>/dev/null || echo "")

  [ -z "$found" ] && exit 0

  local ordinal label
  ordinal="${found%%|*}"
  label="${found#*|}"

  # Never decrement: only advance if ordinal > current.
  local cur
  cur=$(jq -r '.evidence.task_progress.current // 0' "$STATE_FILE")
  if [ "$ordinal" -le "$cur" ] 2>/dev/null; then
    exit 0
  fi

  jq --argjson o "$ordinal" --arg tl "$label" '
    .evidence.task_progress.current = $o |
    .evidence.task_progress.label = $tl
  ' "$STATE_FILE" > /tmp/pp_onedit.json && mv /tmp/pp_onedit.json "$STATE_FILE"
}

# ── Action: show ─────────────────────────────────────────────────────────────
action_show() {
  jq '{
    plan_path: .evidence.plan_path,
    task_progress: .evidence.task_progress,
    wave: .evidence.work_context.wave
  }' "$STATE_FILE"
}

# ── Dispatch ─────────────────────────────────────────────────────────────────
ACTION="${1:-}"
case "$ACTION" in
  init)     action_init ;;
  advance)  action_advance "${2:-}" ;;
  complete) action_complete ;;
  on_edit)  action_on_edit "${2:-}" ;;
  show)     action_show ;;
  *)
    echo "Usage: $0 {init|advance <task_id>|complete|on_edit <file_path>|show}" >&2
    exit 1
    ;;
esac
