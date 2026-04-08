#!/usr/bin/env bash
# PostToolUse:Read|Write|Edit|Agent — Single consolidated hook.
# Combines: auto-evidence (Read/Write/Edit) + workflow-status-line
#
# This is the ONLY PostToolUse hook that fires on Read/Write/Edit/Agent,
# reducing UI events from 2-3 to 1 per tool call.
#
# Non-blocking: always exits 0.

set -euo pipefail

REPO="/home/user/mxo-track"
STATE_FILE="$REPO/.claude/session-state.json"

# Read hook input from stdin
INPUT=$(cat)
TOOL_NAME=$(echo "$INPUT" | jq -r '.tool_name // ""')

if [ -z "$TOOL_NAME" ]; then
  exit 0
fi

# ══════════════════════════════════════════════════════════════════════════════
# Route 1: Auto-Evidence (Read/Write/Edit)
# ══════════════════════════════════════════════════════════════════════════════

if [ -f "$STATE_FILE" ]; then
  STATE=$(cat "$STATE_FILE" 2>/dev/null || echo "{}")
  FLOW_TYPE=$(echo "$STATE" | jq -r '.flow_type // "null"')

  if [ "$FLOW_TYPE" != "null" ] && [ -n "$FLOW_TYPE" ]; then
    FILE_PATH=$(echo "$INPUT" | jq -r '.tool_input.file_path // ""')

    update_state() {
      local filter="$1"
      jq "$filter" "$STATE_FILE" > /tmp/auto-ev.json && mv /tmp/auto-ev.json "$STATE_FILE"
    }

    case "$TOOL_NAME" in
      Read)
        if [[ "$FILE_PATH" == *"docs/decisions/log.md"* ]]; then
          CURRENT=$(echo "$STATE" | jq -r '.evidence.decisions_read // false')
          [ "$CURRENT" != "true" ] && update_state '.evidence.decisions_read = true'
        fi
        if [[ "$FILE_PATH" == *"docs/superpowers/execution-logs/"* ]]; then
          CURRENT=$(echo "$STATE" | jq -r '.evidence.logs_scanned // false')
          [ "$CURRENT" != "true" ] && update_state '.evidence.logs_scanned = true'
        fi
        ;;
      Write|Edit)
        # TDD Order Tracking
        CURRENT_TASK=$(echo "$STATE" | jq -r '.evidence.task_progress.current // 0')
        TDD_TASK=$(echo "$STATE" | jq -r '.evidence.tdd_tracker.current_task // 0')

        if [ "$CURRENT_TASK" != "$TDD_TASK" ] && [ "$CURRENT_TASK" -gt 0 ] 2>/dev/null; then
          update_state ".evidence.tdd_tracker = {\"current_task\": $CURRENT_TASK, \"edits\": []}"
        fi

        EDIT_TYPE=""
        case "$FILE_PATH" in
          */backend/tests/*|*Test.php|*.test.*|*.spec.*) EDIT_TYPE="test" ;;
          */backend/src/*|*/frontend/src/*) EDIT_TYPE="src" ;;
        esac

        if [ -n "$EDIT_TYPE" ] && [ "$CURRENT_TASK" -gt 0 ] 2>/dev/null; then
          TIMESTAMP=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
          HAS_TRACKER=$(echo "$STATE" | jq -r '.evidence.tdd_tracker // null')
          if [ "$HAS_TRACKER" = "null" ]; then
            update_state ".evidence.tdd_tracker = {\"current_task\": $CURRENT_TASK, \"edits\": [{\"file\": \"$FILE_PATH\", \"at\": \"$TIMESTAMP\", \"type\": \"$EDIT_TYPE\"}]}"
          else
            update_state ".evidence.tdd_tracker.edits += [{\"file\": \"$FILE_PATH\", \"at\": \"$TIMESTAMP\", \"type\": \"$EDIT_TYPE\"}]"
          fi
        fi

        # Artifact path tracking
        if [[ "$FILE_PATH" == *"docs/superpowers/specs/"*".md" ]]; then
          CURRENT=$(echo "$STATE" | jq -r '.evidence.spec_path // ""')
          [ "$CURRENT" != "$FILE_PATH" ] && update_state ".evidence.spec_path = \"$FILE_PATH\""
        fi
        if [[ "$FILE_PATH" == *"docs/superpowers/plans/"*".md" ]] && [[ "$FILE_PATH" != *"/conversation/"* ]]; then
          CURRENT=$(echo "$STATE" | jq -r '.evidence.plan_path // ""')
          [ "$CURRENT" != "$FILE_PATH" ] && update_state ".evidence.plan_path = \"$FILE_PATH\""
        fi
        if [[ "$FILE_PATH" == *"docs/superpowers/execution-logs/"*".md" ]]; then
          CURRENT=$(echo "$STATE" | jq -r '.evidence.execution_log_path // ""')
          [ "$CURRENT" != "$FILE_PATH" ] && update_state ".evidence.execution_log_path = \"$FILE_PATH\""
        fi
        if [[ "$FILE_PATH" == *"backend/tests/"* ]]; then
          update_state '.evidence.tests_written = (.evidence.tests_written + 1)'
        fi
        ;;
    esac
  fi
fi

# ══════════════════════════════════════════════════════════════════════════════
# Route 2: Workflow Status Line
# ══════════════════════════════════════════════════════════════════════════════

STATUS_SCRIPT="$REPO/.claude/hooks/workflow-status-line.sh"
if [ -x "$STATUS_SCRIPT" ]; then
  echo "$INPUT" | "$STATUS_SCRIPT" 2>/dev/null || true
fi

exit 0
