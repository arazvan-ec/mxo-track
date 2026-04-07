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
    ;;

  Bash)
    # tests_passed: phpunit command
    if [[ "$COMMAND" == *"phpunit"* ]]; then
      if [ "$TOOL_EXIT" = "0" ]; then
        update_state '.evidence.tests_passed = true'
      else
        update_state '.evidence.tests_passed = false'
      fi
    fi

    # lint_clean: lint commands
    if [[ "$COMMAND" == *"make lint"* ]] || [[ "$COMMAND" == *"php -l"* ]] || [[ "$COMMAND" == *"phpcs"* ]]; then
      if [ "$TOOL_EXIT" = "0" ]; then
        update_state '.evidence.lint_clean = true'
      else
        update_state '.evidence.lint_clean = false'
      fi
    fi
    ;;

esac

exit 0
