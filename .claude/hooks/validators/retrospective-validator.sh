#!/usr/bin/env bash
# Retrospective phase validator (HARD gate)
# Checks: execution log has a retrospective/lessons section with real content.
# Exit 0 = pass, Exit 2 = block
set -euo pipefail

STATE_FILE="${1:-.claude/session-state.json}"
REPO="/home/user/mxo-track"

LOG_PATH=$(jq -r '.evidence.execution_log_path // ""' "$STATE_FILE" 2>/dev/null || echo "")
RETRO_SHOWN=$(jq -r '.evidence.retrospective_shown // false' "$STATE_FILE" 2>/dev/null || echo "false")

# Resolve log file
LOG_FULL=""
if [ -n "$LOG_PATH" ]; then
  if [ -f "$REPO/$LOG_PATH" ]; then
    LOG_FULL="$REPO/$LOG_PATH"
  elif [ -f "$LOG_PATH" ]; then
    LOG_FULL="$LOG_PATH"
  fi
fi

ERRORS=""

# 0. Retrospective visibility gate (Option 3-Enforced, Layer B).
#    Model MUST present the retrospective to the user as visible chat text
#    BEFORE writing it to the execution log. After presenting, set the flag via:
#      jq '.evidence.retrospective_shown = true' .claude/session-state.json ...
if [ "$RETRO_SHOWN" != "true" ]; then
  ERRORS="${ERRORS}- evidence.retrospective_shown=false. Presenta la retrospectiva al usuario (estimación vs real, process gap, patrones emergentes) ANTES de escribir al log o avanzar. Set: jq '.evidence.retrospective_shown=true' ...\n"
fi

# 1. Execution log must exist
if [ -z "$LOG_FULL" ] || [ ! -f "$LOG_FULL" ]; then
  ERRORS="${ERRORS}- No existe execution log (execution_log_path: $LOG_PATH)\n"
fi

if [ -n "$LOG_FULL" ] && [ -f "$LOG_FULL" ]; then
  # 2. Must have a retrospective/lessons section
  if ! grep -qiE '(## (Lessons|Retrospectiva|Retrospective|Lecciones)|What worked|Qué funcionó|Estimación vs|Patrón recurrente)' "$LOG_FULL" 2>/dev/null; then
    ERRORS="${ERRORS}- Execution log no tiene seccion de retrospectiva/lecciones aprendidas\n"
  fi

  # 3. Retrospective section must have real content (not just a header)
  RETRO_CONTENT=$(sed -n '/^## \(Lessons\|Retrospectiva\|Retrospective\|Lecciones\)/,/^## /p' "$LOG_FULL" 2>/dev/null | tail -n +2 | head -n -1)
  RETRO_SIZE=${#RETRO_CONTENT}
  if [ "$RETRO_SIZE" -lt 100 ]; then
    ERRORS="${ERRORS}- Seccion de retrospectiva demasiado corta ($RETRO_SIZE chars, minimo 100). Reflexiona sobre estimacion, blockers, y lecciones.\n"
  fi

  # Layer I — REMOVED 2026-04-26.
  # Original intent: require an architectural keyword (adversarial / DDD /
  # boundary / coupling / etc.) in the Lessons section, or an explicit
  # opt-out flag. Removed because: (1) Layer C now runs at brainstorm exit
  # where adversarial review is cheaper (no rollback cost); (2) the keyword
  # regex was trivial to bypass (an author writing "this change did not
  # touch architecture" passes); (3) post-Layer-I retros that contain
  # authentic architectural content plausibly owe that to Layer C + cultural
  # shift, not to this regex. Analysis: /tmp/layer-i-analysis.md (2026-04-26).
fi

if [ -n "$ERRORS" ]; then
  echo "BLOCKED: Retrospectiva incompleta:"
  echo -e "$ERRORS"
  echo "Escribe la retrospectiva en el execution log antes de avanzar a finalize."
  exit 2
fi

exit 0
