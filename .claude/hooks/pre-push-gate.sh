#!/usr/bin/env bash
# Pre-Push Gate — PreToolUse hook for Bash
#
# Detects `git push` commands and gates them with completion checks:
# - Detects if push contains changes to protected paths
# - For full/debug flows with protected changes:
#   HARD: verification (tests_passed + lint_clean)
#   HARD: capture (execution_log_path exists, file ≥500B)
#   HARD: retrospective (must be in phase_history)
#   HARD: finalize (branch_strategy declared)
#
# Skips git push --dry-run commands.

set -euo pipefail

REPO="/home/user/mxo-track"
STATE_FILE="$REPO/.claude/session-state.json"

# Shared file classification (single source of truth with workflow-engine.sh)
source "$REPO/.claude/hooks/lib/classify-file.sh"

# Parse tool input from stdin
INPUT=$(cat)
COMMAND=$(echo "$INPUT" | jq -r '.tool_input.command // ""')

# Only activate on git push commands
if ! echo "$COMMAND" | grep -qE '\bgit\s+push\b'; then
  exit 0
fi

# Skip --dry-run
if echo "$COMMAND" | grep -qE '\-\-dry-run'; then
  exit 0
fi

deny() {
  local reason="$1"
  echo "{\"hookSpecificOutput\":{\"hookEventName\":\"PreToolUse\",\"permissionDecision\":\"deny\",\"permissionDecisionReason\":\"$reason\"}}"
  exit 0
}

warn() {
  local msg="$1"
  echo "{\"systemMessage\":\"$msg\"}"
  exit 0
}

# If no session-state, pass (don't block pushes when engine isn't active)
if [ ! -f "$STATE_FILE" ]; then
  exit 0
fi

FLOW_TYPE=$(jq -r '.flow_type // "null"' "$STATE_FILE" 2>/dev/null || echo "null")

# Only gate full and debug flows
if [ "$FLOW_TYPE" != "full" ] && [ "$FLOW_TYPE" != "debug" ]; then
  exit 0
fi

# ── Check if push contains protected path changes ──
# A1 (2026-04-29): evaluate the *unpushed* commits diff
# (`@{upstream}...HEAD`) instead of the whole-branch diff. The gate's
# intent is "don't push protected code without flow completion" —
# preserved when only unpushed commits are evaluated. Doc-only
# checkpoint pushes mid-flow are no longer false-positives.
# Spec: docs/superpowers/specs/2026-04-29-cross-session-resume-hardening-design.md § A1.
# Fallback to `origin/main...HEAD` only when no upstream exists
# (initial branch push).
# Uses shared classify_file() — single source of truth with workflow-engine.sh.
has_protected_changes() {
  local changed_files diff_range
  if (cd "$REPO" && git rev-parse --verify --quiet '@{upstream}' >/dev/null 2>&1); then
    diff_range='@{upstream}...HEAD'
  else
    diff_range='origin/main...HEAD'
  fi
  changed_files=$(cd "$REPO" && git diff --name-only "$diff_range" 2>/dev/null || echo "")

  if [ -z "$changed_files" ]; then
    return 1
  fi

  while IFS= read -r file; do
    local file_class
    file_class=$(classify_file "$REPO/$file")
    if [ "$file_class" = "code" ] || [ "$file_class" = "test" ]; then
      return 0
    fi
  done <<< "$changed_files"

  return 1
}

# If no protected files changed, pass silently
if ! has_protected_changes; then
  exit 0
fi

# Helper: deny on gate failure (deviation mode removed 2026-04-29 —
# emergency escape is SKIP_PHASE_EXIT_GATE=1 + decision log entry)
gate() {
  local reason="$1"
  deny "PRE-PUSH GATE: $reason"
}

# Helper: check if a phase is in phase_history or is current_phase
# Supports both old format (strings) and new format (objects with .phase)
phase_completed() {
  local phase="$1"
  local current_phase
  current_phase=$(jq -r '.current_phase // ""' "$STATE_FILE" 2>/dev/null || echo "")

  if [ "$current_phase" = "$phase" ]; then
    return 0
  fi

  # Check new format: objects with .phase field
  local in_history_obj
  in_history_obj=$(jq -r --arg p "$phase" '.phase_history | if . then [.[] | if type == "object" then .phase else . end | select(. == $p)] | length else 0 end' "$STATE_FILE" 2>/dev/null || echo "0")

  if [ "$in_history_obj" -gt 0 ]; then
    return 0
  fi

  return 1
}

# ══════════════════════════════════════════════════════════════
# Cross-validation (Capa 5): verify evidence matches reality
# ══════════════════════════════════════════════════════════════

CROSS_WARNINGS=""

# Check: phase_history uses timestamp format (not fabricated strings)
HISTORY_FORMAT=$(jq '[.phase_history // [] | .[] | select(type == "string")] | length' "$STATE_FILE" 2>/dev/null || echo "0")
if [ "$HISTORY_FORMAT" -gt 0 ]; then
  CROSS_WARNINGS="${CROSS_WARNINGS}⚠ phase_history contiene strings planos (formato antiguo). Usa phase-advance.sh. | "
fi

# Check: phase_history timestamps are chronological (>30s apart)
HISTORY_LEN=$(jq '.phase_history | length' "$STATE_FILE" 2>/dev/null || echo "0")
if [ "$HISTORY_LEN" -gt 1 ]; then
  # Check if all entries have timestamps
  ENTRIES_WITH_TS=$(jq '[.phase_history[] | select(type == "object" and .at != null)] | length' "$STATE_FILE" 2>/dev/null || echo "0")
  if [ "$ENTRIES_WITH_TS" -eq "$HISTORY_LEN" ]; then
    # Check chronological order
    SORTED_CHECK=$(jq '[.phase_history | [.[].at] | [range(length-1) as $i | {a: .[$i], b: .[$i+1]} | select(.a > .b)] | length] | .[0]' "$STATE_FILE" 2>/dev/null || echo "0")
    if [ "$SORTED_CHECK" -gt 0 ]; then
      CROSS_WARNINGS="${CROSS_WARNINGS}⚠ phase_history timestamps no son cronologicos (posible fabricacion). | "
    fi
  fi
fi

# Check: spec_path and plan_path point to real files with content
if [ "$FLOW_TYPE" = "full" ]; then
  SPEC_PATH=$(jq -r '.evidence.spec_path // ""' "$STATE_FILE" 2>/dev/null || echo "")
  PLAN_PATH=$(jq -r '.evidence.plan_path // ""' "$STATE_FILE" 2>/dev/null || echo "")

  for path_var in "$SPEC_PATH" "$PLAN_PATH"; do
    if [ -n "$path_var" ]; then
      RESOLVED="$path_var"
      [ ! -f "$RESOLVED" ] && RESOLVED="$REPO/$path_var"
      if [ ! -f "$RESOLVED" ]; then
        CROSS_WARNINGS="${CROSS_WARNINGS}⚠ Artifact path '$path_var' no existe en disco. | "
      else
        SIZE=$(wc -c < "$RESOLVED" 2>/dev/null || echo "0")
        if [ "$SIZE" -lt 300 ]; then
          CROSS_WARNINGS="${CROSS_WARNINGS}⚠ Artifact '$path_var' demasiado pequeno (${SIZE}B). | "
        fi
      fi
    fi
  done

  # Check: decisions log has diff vs main (SOFT — informational)
  DECISIONS_DIFF=$(cd "$REPO" && git diff --name-only origin/main...HEAD -- docs/decisions/log.md 2>/dev/null || echo "")
  if [ -z "$DECISIONS_DIFF" ]; then
    CROSS_WARNINGS="${CROSS_WARNINGS}⚠ docs/decisions/log.md no tiene cambios vs main (considerar actualizar). | "
  fi
fi

# Emit cross-validation warnings (SOFT — don't block, just inform)
if [ -n "$CROSS_WARNINGS" ]; then
  echo "{\"systemMessage\":\"CROSS-VALIDATION: $CROSS_WARNINGS\"}" >&2
fi

# ══════════════════════════════════════════════════════════════
# HARD gates: verification, capture, finalize
# ══════════════════════════════════════════════════════════════

ERRORS=""

# ── 1. Verification: tests_passed + lint_clean ──
TESTS_PASSED=$(jq -r '.evidence.tests_passed // "null"' "$STATE_FILE" 2>/dev/null || echo "null")
LINT_CLEAN=$(jq -r '.evidence.lint_clean // "null"' "$STATE_FILE" 2>/dev/null || echo "null")

CHECKLIST=""

case "$TESTS_PASSED" in
  true)    CHECKLIST="${CHECKLIST}✅ tests_passed | " ;;
  skipped) CHECKLIST="${CHECKLIST}⚠ tests_passed (skipped) | " ;;
  *)       CHECKLIST="${CHECKLIST}❌ tests_passed (actual: $TESTS_PASSED) | "; ERRORS="${ERRORS}tests " ;;
esac

case "$LINT_CLEAN" in
  true)    CHECKLIST="${CHECKLIST}✅ lint_clean | " ;;
  skipped) CHECKLIST="${CHECKLIST}⚠ lint_clean (skipped) | " ;;
  *)       CHECKLIST="${CHECKLIST}❌ lint_clean (actual: $LINT_CLEAN) | "; ERRORS="${ERRORS}lint " ;;
esac

# ── 2. Capture: execution_log_path exists and file ≥500B ──
EXEC_LOG_PATH=$(jq -r '.evidence.execution_log_path // ""' "$STATE_FILE" 2>/dev/null || echo "")

if [ -z "$EXEC_LOG_PATH" ]; then
  CHECKLIST="${CHECKLIST}❌ execution_log (no path) | "
  ERRORS="${ERRORS}exec_log "
else
  # Resolve path (could be relative or absolute)
  FULL_PATH="$EXEC_LOG_PATH"
  if [ ! -f "$FULL_PATH" ]; then
    FULL_PATH="$REPO/$EXEC_LOG_PATH"
  fi

  if [ ! -f "$FULL_PATH" ]; then
    CHECKLIST="${CHECKLIST}❌ execution_log (archivo no existe) | "
    ERRORS="${ERRORS}exec_log "
  else
    FILE_SIZE=$(wc -c < "$FULL_PATH" 2>/dev/null || echo "0")
    if [ "$FILE_SIZE" -lt 500 ]; then
      CHECKLIST="${CHECKLIST}❌ execution_log (${FILE_SIZE}B < 500B) | "
      ERRORS="${ERRORS}exec_log "
    else
      CHECKLIST="${CHECKLIST}✅ execution_log | "
    fi
  fi
fi

# ── 3. Retrospective: must be in phase_history ──
if phase_completed "retrospective"; then
  CHECKLIST="${CHECKLIST}✅ retrospective | "
else
  CHECKLIST="${CHECKLIST}❌ retrospective (not in phase_history) | "
  ERRORS="${ERRORS}retrospective "
fi

# ── 4. Finalize: branch_strategy declared ──
BRANCH_STRATEGY=$(jq -r '.evidence.branch_strategy // ""' "$STATE_FILE" 2>/dev/null || echo "")

case "$BRANCH_STRATEGY" in
  merge|pr|keep|discard)
    CHECKLIST="${CHECKLIST}✅ branch_strategy ($BRANCH_STRATEGY)"
    ;;
  *)
    CHECKLIST="${CHECKLIST}❌ branch_strategy (actual: '$BRANCH_STRATEGY')"
    ERRORS="${ERRORS}branch_strategy "
    ;;
esac

# ── Apply HARD gates ──
if [ -n "$ERRORS" ]; then
  gate "❌ PUSH BLOQUEADO [$FLOW_TYPE] | $CHECKLIST | Accion: completa los items marcados con ❌"
fi

# ── SOFT: Manifest freshness check ──
MANIFEST_UPDATED=$(git diff origin/main..HEAD --name-only 2>/dev/null | grep -c 'codebase-manifest.md' || true)
if [ "$MANIFEST_UPDATED" -eq 0 ]; then
  echo "{\"systemMessage\":\"⚠ manifest no actualizado en este branch. Ejecuta 'make manifest' antes del push final.\"}"
fi

exit 0
