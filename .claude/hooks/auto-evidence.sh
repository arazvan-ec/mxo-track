#!/usr/bin/env bash
# PostToolUse hook — Auto-detects evidence from tool usage and updates session-state.json.
# Runs BEFORE workflow-status-line.sh so status reflects fresh evidence.
# Non-blocking: always exits 0.

set -euo pipefail

REPO="/home/user/mxo-track"
STATE_FILE="$REPO/.claude/session-state.json"

# Graceful fallback
if [ ! -f "$STATE_FILE" ]; then
  exit 0
fi

# Read hook input from stdin
INPUT=$(cat)
TOOL_NAME=$(echo "$INPUT" | jq -r '.tool_name // ""')

# No tool name = nothing to detect
if [ -z "$TOOL_NAME" ]; then
  exit 0
fi

# Read current state
STATE=$(cat "$STATE_FILE" 2>/dev/null || echo "{}")
FLOW_TYPE=$(echo "$STATE" | jq -r '.flow_type // "null"')

# Only detect evidence during active flows
if [ "$FLOW_TYPE" = "null" ] || [ -z "$FLOW_TYPE" ]; then
  exit 0
fi

# Helper: atomic update of session-state.json
update_state() {
  local filter="$1"
  jq "$filter" "$STATE_FILE" > /tmp/auto-ev.json && mv /tmp/auto-ev.json "$STATE_FILE"
}

# Extract tool-specific fields
FILE_PATH=$(echo "$INPUT" | jq -r '.tool_input.file_path // ""')
COMMAND=$(echo "$INPUT" | jq -r '.tool_input.command // ""')
TOOL_EXIT=$(echo "$INPUT" | jq -r '.tool_response.exit_code // ""')

case "$TOOL_NAME" in

  Read)
    # decisions_read: reading docs/decisions/log.md
    if [[ "$FILE_PATH" == *"docs/decisions/log.md"* ]]; then
      CURRENT=$(echo "$STATE" | jq -r '.evidence.decisions_read // false')
      if [ "$CURRENT" != "true" ]; then
        update_state '.evidence.decisions_read = true'
      fi
    fi

    # logs_scanned: reading any execution log
    if [[ "$FILE_PATH" == *"docs/superpowers/execution-logs/"* ]]; then
      CURRENT=$(echo "$STATE" | jq -r '.evidence.logs_scanned // false')
      if [ "$CURRENT" != "true" ]; then
        update_state '.evidence.logs_scanned = true'
      fi
    fi
    ;;

  Write|Edit)
    # ── TDD Order Tracking (Capa 4) ──
    # Track edit order: test files vs src files per task
    CURRENT_TASK=$(echo "$STATE" | jq -r '.evidence.task_progress.current // 0')
    TDD_TASK=$(echo "$STATE" | jq -r '.evidence.tdd_tracker.current_task // 0')

    # Reset tracker when task changes
    if [ "$CURRENT_TASK" != "$TDD_TASK" ] && [ "$CURRENT_TASK" -gt 0 ] 2>/dev/null; then
      update_state ".evidence.tdd_tracker = {\"current_task\": $CURRENT_TASK, \"edits\": []}"
    fi

    # Classify file as test or src
    EDIT_TYPE=""
    case "$FILE_PATH" in
      */backend/tests/*|*Test.php|*.test.*|*.spec.*) EDIT_TYPE="test" ;;
      */backend/src/*|*/frontend/src/*) EDIT_TYPE="src" ;;
    esac

    if [ -n "$EDIT_TYPE" ] && [ "$CURRENT_TASK" -gt 0 ] 2>/dev/null; then
      TIMESTAMP=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
      # Ensure tdd_tracker exists before appending
      HAS_TRACKER=$(echo "$STATE" | jq -r '.evidence.tdd_tracker // null')
      if [ "$HAS_TRACKER" = "null" ]; then
        update_state ".evidence.tdd_tracker = {\"current_task\": $CURRENT_TASK, \"edits\": [{\"file\": \"$FILE_PATH\", \"at\": \"$TIMESTAMP\", \"type\": \"$EDIT_TYPE\"}]}"
      else
        update_state ".evidence.tdd_tracker.edits += [{\"file\": \"$FILE_PATH\", \"at\": \"$TIMESTAMP\", \"type\": \"$EDIT_TYPE\"}]"
      fi
    fi

    # spec_path: writing to specs/
    if [[ "$FILE_PATH" == *"docs/superpowers/specs/"*".md" ]]; then
      CURRENT=$(echo "$STATE" | jq -r '.evidence.spec_path // ""')
      if [ "$CURRENT" != "$FILE_PATH" ]; then
        update_state ".evidence.spec_path = \"$FILE_PATH\""
      fi
    fi

    # plan_path: writing to plans/ (exclude conversation/ subdirectory)
    if [[ "$FILE_PATH" == *"docs/superpowers/plans/"*".md" ]] && [[ "$FILE_PATH" != *"/conversation/"* ]]; then
      CURRENT=$(echo "$STATE" | jq -r '.evidence.plan_path // ""')
      if [ "$CURRENT" != "$FILE_PATH" ]; then
        update_state ".evidence.plan_path = \"$FILE_PATH\""
      fi
    fi

    # execution_log_path: writing to execution-logs/
    if [[ "$FILE_PATH" == *"docs/superpowers/execution-logs/"*".md" ]]; then
      CURRENT=$(echo "$STATE" | jq -r '.evidence.execution_log_path // ""')
      if [ "$CURRENT" != "$FILE_PATH" ]; then
        update_state ".evidence.execution_log_path = \"$FILE_PATH\""
      fi
    fi

    # tests_written: writing to backend/tests/
    if [[ "$FILE_PATH" == *"backend/tests/"* ]]; then
      update_state '.evidence.tests_written = (.evidence.tests_written + 1)'
    fi

    # Ephemeral artifact warning
    if [[ "$FILE_PATH" == /tmp/* || "$FILE_PATH" == /root/.claude/* ]] && [[ "$FILE_PATH" != *session-state* ]]; then
      # Check if content looks like a spec/plan/execution-log
      CONTENT=$(echo "$INPUT" | jq -r '.tool_input.content // .tool_input.new_string // ""' | head -5)
      if echo "$CONTENT" | grep -qiE '(spec|plan|execution.log|design|approach|alternativ)'; then
        echo "{\"systemMessage\":\"⚠ Artifact escrito en path efímero ($FILE_PATH). Considera guardarlo en docs/superpowers/ para persistencia.\"}"
      fi
    fi
    ;;

  Bash)
    EVIDENCE_TS=$(date -u +"%Y-%m-%dT%H:%M:%SZ")

    # tests_passed: phpunit command (with fresh timestamp)
    if [[ "$COMMAND" == *"phpunit"* ]]; then
      if [ "$TOOL_EXIT" = "0" ]; then
        update_state ".evidence.tests_passed = true | .evidence.tests_ran_at = \"$EVIDENCE_TS\""
      else
        update_state ".evidence.tests_passed = false | .evidence.tests_ran_at = \"$EVIDENCE_TS\""
      fi
    fi

    # lint_clean: lint commands (with fresh timestamp)
    if [[ "$COMMAND" == *"make lint"* ]] || [[ "$COMMAND" == *"php -l"* ]] || [[ "$COMMAND" == *"phpcs"* ]]; then
      if [ "$TOOL_EXIT" = "0" ]; then
        update_state ".evidence.lint_clean = true | .evidence.lint_ran_at = \"$EVIDENCE_TS\""
      else
        update_state ".evidence.lint_clean = false | .evidence.lint_ran_at = \"$EVIDENCE_TS\""
      fi
    fi

    # Track canonical verification commands
    if echo "$COMMAND" | grep -qE '^(cd\s+\S+\s*&&\s*)?npm run build'; then
      update_state '.evidence.verified_commands = ((.evidence.verified_commands // []) + ["npm_run_build"] | unique)'
    fi
    if echo "$COMMAND" | grep -qE '(^|\s)make lint'; then
      update_state '.evidence.verified_commands = ((.evidence.verified_commands // []) + ["make_lint"] | unique)'
    fi
    # Track approximate commands as warnings
    if echo "$COMMAND" | grep -qE 'tsc\s+--noEmit' && ! echo "$COMMAND" | grep -qE 'npm run build'; then
      echo "{\"systemMessage\":\"⚠ tsc --noEmit no es el comando de deploy. Usa 'npm run build' para verificación exacta.\"}"
    fi
    ;;

esac

exit 0
