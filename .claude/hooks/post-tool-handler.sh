#!/usr/bin/env bash
# PostToolUse consolidated handler — replaces 3 separate hooks with 1.
# Runs in order: auto-evidence → plan-persistence → workflow-status-line
# This reduces hook notifications in the UI from 3 per tool call to 1.
# Non-blocking: always exits 0.

set -euo pipefail

REPO="/home/user/mxo-track"
HOOKS_DIR="$REPO/.claude/hooks"

# Read stdin once — all sub-handlers need this
INPUT=$(cat 2>/dev/null || echo "{}")

# ── Phase 1: Auto-evidence detection ──
# Updates session-state.json based on what tool was just used
echo "$INPUT" | "$HOOKS_DIR/auto-evidence.sh" 2>/dev/null || true

# ── Phase 2: Plan persistence ──
# Copies conversation plans from /root/.claude/plans/ to repo
TOOL_NAME=$(echo "$INPUT" | jq -r '.tool_name // ""' 2>/dev/null || true)
FILE_PATH=$(echo "$INPUT" | jq -r '.tool_input.file_path // ""' 2>/dev/null || true)

if [[ "$TOOL_NAME" == "Write" || "$TOOL_NAME" == "Edit" ]] && [[ "$FILE_PATH" == /root/.claude/plans/* ]]; then
  BASENAME=$(basename "$FILE_PATH")
  DATE=$(date +%Y-%m-%d)
  DEST_DIR="$REPO/docs/superpowers/plans/conversation"
  mkdir -p "$DEST_DIR"
  DEST="$DEST_DIR/${DATE}-${BASENAME}"
  if [ -f "$FILE_PATH" ]; then
    cp "$FILE_PATH" "$DEST" && \
    cd "$REPO" && \
    git add "$DEST" && \
    git commit -m "docs: persist conversation plan ${DATE}-${BASENAME}" && \
    git push 2>/dev/null || true
  fi
fi

# ── Phase 3: Workflow status line ──
# Generates status output for Claude's context (only if state changed)
echo "$INPUT" | "$HOOKS_DIR/workflow-status-line.sh" 2>/dev/null || true

exit 0
