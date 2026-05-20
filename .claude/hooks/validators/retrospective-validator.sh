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

  # 4. Backlog candidates analysis (P2 2026-05-20 — HARD)
  # Spec: docs/superpowers/specs/2026-05-20-retrospective-backlog-candidates-design.md
  # The log must contain either:
  #   (a) a "## Backlog candidates" heading with ≥1 bullet, OR
  #   (b) the literal "0 — no surfaced improvements" line.
  # If (a), docs/backlog.md must be modified in the interaction's git diff.
  HAS_CANDIDATES_SECTION=0
  HAS_ZERO_LINE=0

  if grep -qiE '^##\s+Backlog\s+candidates' "$LOG_FULL" 2>/dev/null; then
    # Count bullets under the section (lines starting with - or * within the section)
    CANDIDATES_BULLETS=$(awk '/^##\s+[Bb]acklog\s+[Cc]andidates/{flag=1; next} /^##\s+/{flag=0} flag && /^[*-]\s+/' "$LOG_FULL" 2>/dev/null | wc -l | tr -d ' ')
    if [ "$CANDIDATES_BULLETS" -gt 0 ]; then
      HAS_CANDIDATES_SECTION=1
    fi
  fi

  # Simpler regex avoids em-dash encoding issues across shells/locales.
  # Matches: "Backlog candidates: 0 — no surfaced" with any chars between tokens.
  if grep -qiE '^Backlog candidates.*0.*no surfaced' "$LOG_FULL" 2>/dev/null; then
    HAS_ZERO_LINE=1
  fi

  if [ "$HAS_CANDIDATES_SECTION" = "0" ] && [ "$HAS_ZERO_LINE" = "0" ]; then
    ERRORS="${ERRORS}- Falta seccion '## Backlog candidates' con bullets O linea literal 'Backlog candidates: 0 — no surfaced improvements this interaction'. (Spec 2026-05-20-retrospective-backlog-candidates)\n"
  fi

  # If candidates listed, docs/backlog.md must be in the git diff.
  if [ "$HAS_CANDIDATES_SECTION" = "1" ]; then
    cd "$REPO" 2>/dev/null || true
    # Use plan commit parent as the diff base (consistent with sync-validator pattern).
    # Fallback to origin/main.
    DIFF_BASE=""
    if [ -f "$REPO/.claude/hooks/lib/git-refs.sh" ]; then
      # shellcheck source=../lib/git-refs.sh
      source "$REPO/.claude/hooks/lib/git-refs.sh" 2>/dev/null || true
      if declare -F get_plan_commit_parent >/dev/null 2>&1; then
        DIFF_BASE=$(get_plan_commit_parent "$STATE_FILE" 2>/dev/null || echo "")
      fi
    fi
    [ -z "$DIFF_BASE" ] && DIFF_BASE=$(git rev-parse origin/main 2>/dev/null || echo "")

    BACKLOG_MODIFIED=""
    if [ -n "$DIFF_BASE" ]; then
      BACKLOG_MODIFIED=$(git diff --name-only "$DIFF_BASE...HEAD" 2>/dev/null | grep -c 'docs/backlog.md' || true)
    fi
    BACKLOG_WT=$(git status --porcelain 2>/dev/null | grep -c 'docs/backlog.md' || true)

    if [ "$BACKLOG_MODIFIED" = "0" ] && [ "$BACKLOG_WT" = "0" ]; then
      ERRORS="${ERRORS}- '## Backlog candidates' section tiene bullets pero docs/backlog.md no está modificado. Agrega entradas al backlog antes de avanzar.\n"
    fi
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
