#!/usr/bin/env bash
# PostToolUse hook — Auto-detects evidence from tool usage and updates session-state.json.
# Runs BEFORE workflow-status-line.sh so status reflects fresh evidence.
# Non-blocking: always exits 0.

set -euo pipefail

source "$(dirname "$0")/config-helper.sh"

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

# Extract tool-specific fields
FILE_PATH=$(echo "$INPUT" | jq -r '.tool_input.file_path // ""')
COMMAND=$(echo "$INPUT" | jq -r '.tool_input.command // ""')
TOOL_EXIT=$(echo "$INPUT" | jq -r '.tool_response.exit_code // ""')

# Extract the base command name from TEST_COMMAND for matching
# e.g. "php vendor/bin/phpunit" -> "phpunit"
TEST_CMD_BASE=$(basename "${TEST_COMMAND##* }" 2>/dev/null || echo "$TEST_COMMAND")

# Build test paths for matching
TEST_PATHS=$(read_config_array "test_paths" "tests")

case "$TOOL_NAME" in

  Read)
    # decisions_read: reading decisions log
    if [[ "$FILE_PATH" == *"$DECISIONS_LOG"* ]]; then
      CURRENT=$(echo "$STATE" | jq -r '.evidence.decisions_read // false')
      if [ "$CURRENT" != "true" ]; then
        update_state '.evidence.decisions_read = true'
      fi
    fi

    # logs_scanned: reading any execution log
    if [[ "$FILE_PATH" == *"$EXEC_LOGS_PATH"* ]]; then
      CURRENT=$(echo "$STATE" | jq -r '.evidence.logs_scanned // false')
      if [ "$CURRENT" != "true" ]; then
        update_state '.evidence.logs_scanned = true'
      fi
    fi
    ;;

  Write|Edit)
    # spec_path: writing to specs/
    if [[ "$FILE_PATH" == *"$SPECS_PATH"*".md" ]]; then
      CURRENT=$(echo "$STATE" | jq -r '.evidence.spec_path // ""')
      if [ "$CURRENT" != "$FILE_PATH" ]; then
        update_state ".evidence.spec_path = \"$FILE_PATH\""
      fi
    fi

    # plan_path: writing to plans/ (exclude conversation/ subdirectory)
    if [[ "$FILE_PATH" == *"$PLANS_PATH"*".md" ]] && [[ "$FILE_PATH" != *"/conversation/"* ]]; then
      CURRENT=$(echo "$STATE" | jq -r '.evidence.plan_path // ""')
      if [ "$CURRENT" != "$FILE_PATH" ]; then
        update_state ".evidence.plan_path = \"$FILE_PATH\""
      fi
    fi

    # execution_log_path: writing to execution-logs/
    if [[ "$FILE_PATH" == *"$EXEC_LOGS_PATH"*".md" ]]; then
      CURRENT=$(echo "$STATE" | jq -r '.evidence.execution_log_path // ""')
      if [ "$CURRENT" != "$FILE_PATH" ]; then
        update_state ".evidence.execution_log_path = \"$FILE_PATH\""
      fi
    fi

    # tests_written: writing to configured test paths
    while IFS= read -r test_path; do
      if [[ "$FILE_PATH" == *"$test_path"* ]]; then
        update_state '.evidence.tests_written = (.evidence.tests_written + 1)'
        break
      fi
    done <<< "$TEST_PATHS"
    ;;

  Bash)
    # tests_passed: test command
    if [[ "$COMMAND" == *"$TEST_CMD_BASE"* ]]; then
      if [ "$TOOL_EXIT" = "0" ]; then
        update_state '.evidence.tests_passed = true'
      else
        update_state '.evidence.tests_passed = false'
      fi
    fi

    # lint_clean: lint commands
    if [[ "$COMMAND" == *"$LINT_COMMAND"* ]] || [[ "$COMMAND" == *"php -l"* ]] || [[ "$COMMAND" == *"phpcs"* ]]; then
      if [ "$TOOL_EXIT" = "0" ]; then
        update_state '.evidence.lint_clean = true'
      else
        update_state '.evidence.lint_clean = false'
      fi
    fi
    ;;

esac

exit 0
