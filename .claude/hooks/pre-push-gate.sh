#!/usr/bin/env bash
# Pre-Push Gate — PreToolUse hook for Bash
#
# Detects `git push` commands and gates them with completion checks:
# - Detects if push contains changes to protected paths
# - For full/debug flows with protected changes:
#   HARD: verification (tests_passed + lint_clean)
#   HARD: capture (execution_log_path exists, file ≥500B)
#   HARD: finalize (branch_strategy declared)
#   SOFT: retrospective (warning only)
# - Deviation mode: converts DENY to WARN
#
# Skips git push --dry-run commands.

set -euo pipefail

REPO="/home/user/mxo-track"
STATE_FILE="$REPO/.claude/session-state.json"

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
has_protected_changes() {
  local changed_files
  changed_files=$(cd "$REPO" && git diff --name-only origin/main...HEAD 2>/dev/null || echo "")

  if [ -z "$changed_files" ]; then
    return 1
  fi

  # Protected path patterns
  local protected_patterns=(
    "backend/src/"
    "backend/tests/"
    "backend/templates/"
    "backend/config/"
    "backend/migrations/"
    "backend/assets/"
    "frontend/src/"
    "ml-service/"
    "docker/"
    "scripts/"
    "openspec/"
  )

  for pattern in "${protected_patterns[@]}"; do
    if echo "$changed_files" | grep -q "^${pattern}"; then
      return 0
    fi
  done

  return 1
}

# If no protected files changed, pass silently
if ! has_protected_changes; then
  exit 0
fi

# ── Check deviation mode ──
DEVIATION_ACTIVE=$(jq -r '.deviation.active // false' "$STATE_FILE" 2>/dev/null || echo "false")

# Helper: gate or warn based on deviation mode
gate() {
  local reason="$1"
  if [ "$DEVIATION_ACTIVE" = "true" ]; then
    warn "PRE-PUSH WARNING (deviation): $reason"
  else
    deny "PRE-PUSH GATE: $reason"
  fi
}

# Helper: check if a phase is in phase_history or is current_phase
phase_completed() {
  local phase="$1"
  local current_phase
  current_phase=$(jq -r '.current_phase // ""' "$STATE_FILE" 2>/dev/null || echo "")

  if [ "$current_phase" = "$phase" ]; then
    return 0
  fi

  local in_history
  in_history=$(jq -r --arg p "$phase" '.phase_history | if . then map(select(. == $p)) | length else 0 end' "$STATE_FILE" 2>/dev/null || echo "0")

  if [ "$in_history" -gt 0 ]; then
    return 0
  fi

  return 1
}

# ══════════════════════════════════════════════════════════════
# HARD gates: verification, capture, finalize
# ══════════════════════════════════════════════════════════════

ERRORS=""

# ── 1. Verification: tests_passed + lint_clean ──
TESTS_PASSED=$(jq -r '.evidence.tests_passed // "null"' "$STATE_FILE" 2>/dev/null || echo "null")
LINT_CLEAN=$(jq -r '.evidence.lint_clean // "null"' "$STATE_FILE" 2>/dev/null || echo "null")

if [ "$TESTS_PASSED" != "true" ]; then
  ERRORS="${ERRORS}[verification] tests_passed no es true (actual: $TESTS_PASSED). Ejecuta tests primero. "
fi

if [ "$LINT_CLEAN" != "true" ]; then
  ERRORS="${ERRORS}[verification] lint_clean no es true (actual: $LINT_CLEAN). Ejecuta linter primero. "
fi

# ── 2. Capture: execution_log_path exists and file ≥500B ──
EXEC_LOG_PATH=$(jq -r '.evidence.execution_log_path // ""' "$STATE_FILE" 2>/dev/null || echo "")

if [ -z "$EXEC_LOG_PATH" ]; then
  ERRORS="${ERRORS}[capture] No hay execution_log_path. Crea un execution log en docs/superpowers/execution-logs/. "
else
  # Resolve path (could be relative or absolute)
  FULL_PATH="$EXEC_LOG_PATH"
  if [ ! -f "$FULL_PATH" ]; then
    FULL_PATH="$REPO/$EXEC_LOG_PATH"
  fi

  if [ ! -f "$FULL_PATH" ]; then
    ERRORS="${ERRORS}[capture] Execution log '$EXEC_LOG_PATH' no existe. Crealo antes de pushear. "
  else
    FILE_SIZE=$(wc -c < "$FULL_PATH" 2>/dev/null || echo "0")
    if [ "$FILE_SIZE" -lt 500 ]; then
      ERRORS="${ERRORS}[capture] Execution log es muy pequeno (${FILE_SIZE}B < 500B). Completa el log antes de pushear. "
    fi
  fi
fi

# ── 3. Finalize: branch_strategy declared ──
BRANCH_STRATEGY=$(jq -r '.evidence.branch_strategy // ""' "$STATE_FILE" 2>/dev/null || echo "")

case "$BRANCH_STRATEGY" in
  merge|pr|keep|discard)
    ;; # valid
  *)
    ERRORS="${ERRORS}[finalize] branch_strategy no declarado o invalido (actual: '$BRANCH_STRATEGY'). Usa Skill 12 para finalizar la rama. "
    ;;
esac

# ── Apply HARD gates ──
if [ -n "$ERRORS" ]; then
  gate "$ERRORS"
fi

# ══════════════════════════════════════════════════════════════
# SOFT warning: retrospective
# ══════════════════════════════════════════════════════════════

if ! phase_completed "retrospective"; then
  echo "{\"systemMessage\":\"PRE-PUSH WARNING: Fase 'retrospective' no completada. Considera actualizar docs/decisions/log.md antes del push final.\"}"
  exit 0
fi

exit 0
