#!/usr/bin/env bash
# Shared config helper for workflow-engine hooks.
# Sources repo root and reads workflow.json config with defaults.
#
# Usage: source this file at the top of each hook script.
#   source "$(dirname "$0")/config-helper.sh"

# Discover repo root
REPO=$(git rev-parse --show-toplevel 2>/dev/null || pwd)

# Plugin root (two levels up from scripts/)
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

# State files
STATE_FILE="$REPO/.claude/session-state.json"
STATUS_FILE="$REPO/.claude/workflow-status-line.txt"

# Config file discovery
CONFIG_FILE=""
if [ -f "$REPO/workflow.json" ]; then
  CONFIG_FILE="$REPO/workflow.json"
elif [ -f "$REPO/.claude/workflow.json" ]; then
  CONFIG_FILE="$REPO/.claude/workflow.json"
fi

# Read a string value from config with fallback default
read_config() {
  local key="$1" default="$2"
  if [ -n "$CONFIG_FILE" ] && [ -f "$CONFIG_FILE" ]; then
    local val
    val=$(jq -r --arg k "$key" --arg d "$default" '.[$k] // $d' "$CONFIG_FILE" 2>/dev/null)
    echo "${val:-$default}"
  else
    echo "$default"
  fi
}

# Read a JSON array from config as newline-separated values
read_config_array() {
  local key="$1" default="$2"
  if [ -n "$CONFIG_FILE" ] && [ -f "$CONFIG_FILE" ]; then
    local val
    val=$(jq -r --arg k "$key" '.[$k] // empty | if type == "array" then .[] else empty end' "$CONFIG_FILE" 2>/dev/null)
    if [ -n "$val" ]; then
      echo "$val"
    else
      echo "$default"
    fi
  else
    echo "$default"
  fi
}

# Configured paths (with defaults)
SPECS_PATH=$(read_config "specs_path" "docs/specs")
PLANS_PATH=$(read_config "plans_path" "docs/plans")
EXEC_LOGS_PATH=$(read_config "execution_logs_path" "docs/execution-logs")
DECISIONS_LOG=$(read_config "decisions_log" "docs/decisions/log.md")
TEST_COMMAND=$(read_config "test_command" "npm test")
LINT_COMMAND=$(read_config "lint_command" "npm run lint")
COMMIT_PREFIXES=$(read_config "commit_prefixes" "feat|fix|refactor|test|docs|chore")

# Validators directory
VALIDATORS_DIR="$PLUGIN_DIR/hooks/scripts/validators"

# Helper: atomic update of session-state.json
update_state() {
  local filter="$1"
  jq "$filter" "$STATE_FILE" > /tmp/wfe-state.json && mv /tmp/wfe-state.json "$STATE_FILE"
}

# Helper: classify a file path based on configured patterns
classify_file() {
  local filepath="$1"

  # Check configured src paths
  local src_paths
  src_paths=$(read_config_array "src_paths" "src")
  while IFS= read -r pattern; do
    if [[ "$filepath" == *"$pattern"* ]]; then
      echo "code"
      return
    fi
  done <<< "$src_paths"

  # Check configured test paths
  local test_paths
  test_paths=$(read_config_array "test_paths" "tests")
  while IFS= read -r pattern; do
    if [[ "$filepath" == *"$pattern"* ]]; then
      echo "test"
      return
    fi
  done <<< "$test_paths"

  # Check spec/plan/log paths
  if [[ "$filepath" == *"$SPECS_PATH"* ]]; then
    echo "spec"
  elif [[ "$filepath" == *"$PLANS_PATH"* ]]; then
    echo "plan"
  elif [[ "$filepath" == *"$EXEC_LOGS_PATH"* ]]; then
    echo "execution-log"
  elif [[ "$filepath" == *"$DECISIONS_LOG"* ]] || [[ "$filepath" == *"docs/decisions"* ]]; then
    echo "decision"
  elif [[ "$filepath" == *"docs/"* ]] || [[ "$filepath" == *"CLAUDE.md"* ]]; then
    echo "docs"
  elif [[ "$filepath" == *"CLAUDE.md"* ]] || [[ "$filepath" == *"AGENTS.md"* ]]; then
    echo "config"
  else
    echo "other"
  fi
}

# Helper: Y/N from bool
yn() { [ "$1" = "true" ] && echo "Y" || echo "N"; }
