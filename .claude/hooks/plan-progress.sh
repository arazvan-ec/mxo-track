#!/usr/bin/env bash
# plan-progress.sh — Parse plan markdown into session-state task/wave structure.
#
# Usage:
#   plan-progress.sh init                 → parse evidence.plan_path, populate task_progress + wave
#   plan-progress.sh advance <task_id>    → set current to task_id (e.g. "2a")
#   plan-progress.sh complete             → archive current label, advance, reset if all done
#   plan-progress.sh show                 → debug: print parsed structure
#
# Parsed plan format expected:
#   ### Wave N — <label>            (also tolerates "### Wave N [parallel: ...]" or "### Wave N")
#   #### **Na — <task title>**       (also tolerates "#### **N — title**" without letter suffix)
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

  # Python parser → emits two JSON arrays separated by '|||'
  # Robust against UTF-8 (em-dash, accented chars). Supports both:
  #   #### **1a — Title** (extra notes)
  #   - **1a — Title** (extra notes)
  python3 - "$plan_abs" <<'PYEOF'
import sys, json, re

path = sys.argv[1]
waves = []
tasks = []
current_wave = 0

# Wave header: ### Wave N [— label] [trailing brackets]
re_wave = re.compile(r'^###\s+Wave\s+(\d+)(?:\s*[—\-:]\s*(.+?))?(?:\s*\[.*\])?\s*$')
# Task header: #### **Na — Title** ...   OR   - **Na — Title** ...
re_task = re.compile(r'^(?:####\s+|-\s+)\*\*([0-9]+[a-z]?)\s*[—\-:]\s*(.+?)\*\*')

with open(path, 'r', encoding='utf-8') as f:
    for raw in f:
        line = raw.rstrip('\n')
        m = re_wave.match(line)
        if m:
            n = int(m.group(1))
            label = (m.group(2) or '').strip()
            waves.append({"n": n, "label": label})
            current_wave = n
            continue
        m = re_task.match(line)
        if m:
            tid = m.group(1).strip()
            title = m.group(2).strip()
            tasks.append({"id": tid, "wave": current_wave, "label": title})

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
  show)     action_show ;;
  *)
    echo "Usage: $0 {init|advance <task_id>|complete|show}" >&2
    exit 1
    ;;
esac
