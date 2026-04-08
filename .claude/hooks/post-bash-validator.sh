#!/usr/bin/env bash
# PostToolUse:Bash — Single consolidated hook for ALL Bash post-processing.
# Combines: auto-evidence(Bash) + workflow-status-line + phase-transition-controller
#           + post-commit-validator + post-push-validator
#
# This is the ONLY PostToolUse hook that fires on Bash commands,
# reducing UI events from 3+ to 1 per Bash call.
#
# Routes internally based on command content:
# 0a. Auto-evidence: detect phpunit/lint results → update session-state
# 0b. Workflow status line: generate status display
# 1.  session-state.json manipulation → phase transition integrity check
# 2.  git commit → commit message validation
# 3.  git push → manifest update + push tasks
#
# Non-blocking: always exits 0.

set -euo pipefail

REPO="/home/user/mxo-track"
STATE_FILE="$REPO/.claude/session-state.json"

# Read hook input from stdin
INPUT=$(cat)
TOOL_NAME=$(echo "$INPUT" | jq -r '.tool_name // ""')
COMMAND=$(echo "$INPUT" | jq -r '.tool_input.command // ""')

# Safety check
if [ "$TOOL_NAME" != "Bash" ]; then
  exit 0
fi

# ══════════════════════════════════════════════════════════════════════════════
# Route 0a: Auto-Evidence (Bash)
# Detect test/lint results and update session-state evidence fields
# ══════════════════════════════════════════════════════════════════════════════

if [ -f "$STATE_FILE" ]; then
  STATE=$(cat "$STATE_FILE" 2>/dev/null || echo "{}")
  FLOW_TYPE=$(echo "$STATE" | jq -r '.flow_type // "null"')
  TOOL_EXIT=$(echo "$INPUT" | jq -r '.tool_response.exit_code // ""')

  # Only detect evidence during active flows
  if [ "$FLOW_TYPE" != "null" ] && [ -n "$FLOW_TYPE" ]; then
    # Helper: atomic update of session-state.json
    update_evidence() {
      local filter="$1"
      jq "$filter" "$STATE_FILE" > /tmp/auto-ev.json && mv /tmp/auto-ev.json "$STATE_FILE"
    }

    # tests_passed: phpunit command
    if [[ "$COMMAND" == *"phpunit"* ]]; then
      if [ "$TOOL_EXIT" = "0" ]; then
        update_evidence '.evidence.tests_passed = true'
      else
        update_evidence '.evidence.tests_passed = false'
      fi
    fi

    # lint_clean: lint commands
    if [[ "$COMMAND" == *"make lint"* ]] || [[ "$COMMAND" == *"php -l"* ]] || [[ "$COMMAND" == *"phpcs"* ]]; then
      if [ "$TOOL_EXIT" = "0" ]; then
        update_evidence '.evidence.lint_clean = true'
      else
        update_evidence '.evidence.lint_clean = false'
      fi
    fi
  fi
fi

# ══════════════════════════════════════════════════════════════════════════════
# Route 0b: Workflow Status Line
# Generate status display (delegates to workflow-status-line.sh)
# ══════════════════════════════════════════════════════════════════════════════

STATUS_SCRIPT="$REPO/.claude/hooks/workflow-status-line.sh"
if [ -x "$STATUS_SCRIPT" ]; then
  echo "$INPUT" | "$STATUS_SCRIPT" 2>/dev/null || true
fi

# ══════════════════════════════════════════════════════════════════════════════
# Route 1: Phase Transition Controller
# Detects unauthorized manipulation of session-state.json
# ══════════════════════════════════════════════════════════════════════════════

if echo "$COMMAND" | grep -q 'session-state.json'; then
  # Skip if command IS phase-advance.sh (the sanctioned tool)
  if ! echo "$COMMAND" | grep -q 'phase-advance.sh'; then
    SNAPSHOT_FILE="/tmp/ptc-state-snapshot.json"

    if [ -f "$STATE_FILE" ] && [ -f "$SNAPSHOT_FILE" ]; then
      WARNINGS=""

      # Check 1: phase_history manipulation
      OLD_HISTORY=$(jq -c '.phase_history // []' "$SNAPSHOT_FILE" 2>/dev/null || echo "[]")
      NEW_HISTORY=$(jq -c '.phase_history // []' "$STATE_FILE" 2>/dev/null || echo "[]")

      if [ "$OLD_HISTORY" != "$NEW_HISTORY" ]; then
        OLD_LEN=$(echo "$OLD_HISTORY" | jq 'length')
        NEW_LEN=$(echo "$NEW_HISTORY" | jq 'length')

        if [ "$NEW_LEN" -lt "$OLD_LEN" ]; then
          jq --argjson old "$OLD_HISTORY" '.phase_history = $old' "$STATE_FILE" > /tmp/ptc-fix.json && mv /tmp/ptc-fix.json "$STATE_FILE"
          WARNINGS="${WARNINGS}⚠ REVERT: phase_history fue reducido (de $OLD_LEN a $NEW_LEN entries). Revertido. Usa phase-advance.sh para transiciones legales. "
        elif [ "$NEW_LEN" -gt "$OLD_LEN" ]; then
          OLD_PREFIX=$(echo "$OLD_HISTORY" | jq -c ".[0:$OLD_LEN]")
          NEW_PREFIX=$(echo "$NEW_HISTORY" | jq -c ".[0:$OLD_LEN]")
          if [ "$OLD_PREFIX" != "$NEW_PREFIX" ]; then
            jq --argjson old "$OLD_HISTORY" '.phase_history = $old' "$STATE_FILE" > /tmp/ptc-fix.json && mv /tmp/ptc-fix.json "$STATE_FILE"
            WARNINGS="${WARNINGS}⚠ REVERT: phase_history fue reescrito (entries existentes modificados). Revertido. Usa phase-advance.sh. "
          else
            NEW_ENTRIES=$(echo "$NEW_HISTORY" | jq -c ".[$OLD_LEN:]")
            HAS_BAD_FORMAT=$(echo "$NEW_ENTRIES" | jq '[.[] | select(type == "string" or (.phase == null) or (.at == null))] | length')
            if [ "$HAS_BAD_FORMAT" -gt 0 ]; then
              jq --argjson old "$OLD_HISTORY" '.phase_history = $old' "$STATE_FILE" > /tmp/ptc-fix.json && mv /tmp/ptc-fix.json "$STATE_FILE"
              WARNINGS="${WARNINGS}⚠ REVERT: phase_history tiene entries sin timestamp (formato antiguo). Revertido. Usa phase-advance.sh. "
            fi
          fi
        fi
      fi

      # Check 2: user_approved set to true directly
      OLD_APPROVED=$(jq -r '.evidence.user_approved // false' "$SNAPSHOT_FILE" 2>/dev/null || echo "false")
      NEW_APPROVED=$(jq -r '.evidence.user_approved // false' "$STATE_FILE" 2>/dev/null || echo "false")

      if [ "$OLD_APPROVED" = "false" ] && [ "$NEW_APPROVED" = "true" ]; then
        if echo "$COMMAND" | grep -qE 'user_approved\s*=\s*true'; then
          jq '.evidence.user_approved = false' "$STATE_FILE" > /tmp/ptc-fix.json && mv /tmp/ptc-fix.json "$STATE_FILE"
          WARNINGS="${WARNINGS}⚠ REVERT: user_approved fue seteado directamente via jq. Solo el hook UserPromptSubmit puede aprobarlo. "
        fi
      fi

      if [ -n "$WARNINGS" ]; then
        ESCAPED=$(echo "$WARNINGS" | sed 's/"/\\"/g')
        echo "{\"systemMessage\":\"PHASE-TRANSITION-CONTROLLER: $ESCAPED\"}"
      fi
    fi

    # Update snapshot for next comparison
    [ -f "$STATE_FILE" ] && cp "$STATE_FILE" "/tmp/ptc-state-snapshot.json"
  fi
fi

# ══════════════════════════════════════════════════════════════════════════════
# Route 2: Post-Commit Validator
# Validates commit message format after git commit
# ══════════════════════════════════════════════════════════════════════════════

if echo "$COMMAND" | grep -q 'git commit'; then
  STDOUT=$(echo "$INPUT" | jq -r '.tool_response.stdout // ""')

  # Check if commit actually happened
  if echo "$STDOUT" | grep -qE '^\['; then
    cd "$REPO"
    COMMIT_MSG=$(git log -1 --pretty=%s 2>/dev/null || echo "")

    if [ -n "$COMMIT_MSG" ]; then
      ITEMS=""
      HAS_WARNINGS=false

      # Validate commit message prefix
      VALID_PREFIXES="^(feat|fix|refactor|test|docs|chore):"
      if echo "$COMMIT_MSG" | grep -qE "$VALID_PREFIXES"; then
        PREFIX=$(echo "$COMMIT_MSG" | grep -oE "^(feat|fix|refactor|test|docs|chore)")
        ITEMS="${ITEMS}✅ prefix:$PREFIX | "
      else
        ITEMS="${ITEMS}❌ prefix invalido (esperado: feat|fix|refactor|test|docs|chore) | "
        HAS_WARNINGS=true
      fi

      # Check message length
      MSG_LEN=${#COMMIT_MSG}
      if [ "$MSG_LEN" -le 72 ]; then
        ITEMS="${ITEMS}✅ largo:${MSG_LEN}c | "
      else
        ITEMS="${ITEMS}⚠ largo:${MSG_LEN}c (max 72) | "
        HAS_WARNINGS=true
      fi

      # Check for generic messages
      MSG_BODY=$(echo "$COMMIT_MSG" | sed -E 's/^(feat|fix|refactor|test|docs|chore):\s*//')
      GENERIC_PATTERNS="^(WIP|updates|changes|fix|wip|misc|tmp|temp)$"
      if echo "$MSG_BODY" | grep -qiE "$GENERIC_PATTERNS"; then
        ITEMS="${ITEMS}❌ mensaje generico ('$MSG_BODY') | "
        HAS_WARNINGS=true
      fi

      # Execution log reminder
      TODAY=$(date +%Y-%m-%d)
      EXEC_LOG_DIR="$REPO/docs/superpowers/execution-logs"
      if ! ls "$EXEC_LOG_DIR/${TODAY}-"*.md 1>/dev/null 2>&1; then
        if echo "$COMMIT_MSG" | grep -qE "^(feat|fix):"; then
          ITEMS="${ITEMS}⚠ sin execution log para hoy | "
          HAS_WARNINGS=true
        fi
      fi

      # Unpushed commits warning
      UNPUSHED=$(git log @{u}..HEAD --oneline 2>/dev/null | wc -l || echo "0")
      if [ "$UNPUSHED" -gt 3 ]; then
        ITEMS="${ITEMS}⚠ $UNPUSHED commits sin push"
        HAS_WARNINGS=true
      else
        ITEMS="${ITEMS}$UNPUSHED commits sin push"
      fi

      if [ "$HAS_WARNINGS" = true ]; then
        echo "{\"systemMessage\":\"COMMIT: ${ITEMS}\"}"
      fi
    fi
  fi
fi

# ══════════════════════════════════════════════════════════════════════════════
# Route 3: Post-Push Validator
# Runs manifest update after git push
# ══════════════════════════════════════════════════════════════════════════════

if echo "$COMMAND" | grep -q 'git push'; then
  # Skip --dry-run
  if ! echo "$COMMAND" | grep -q '\-\-dry-run'; then
    STDOUT=$(echo "$INPUT" | jq -r '.tool_response.stdout // ""')
    STDERR=$(echo "$INPUT" | jq -r '.tool_response.stderr // ""')
    COMBINED="$STDOUT $STDERR"

    # Skip if push failed
    if ! echo "$COMBINED" | grep -qiE '(rejected|failed|fatal:|error:)'; then
      cd "$REPO"
      MESSAGES=""

      # Run make manifest
      if [ -f "Makefile" ] && grep -q "^manifest:" Makefile; then
        make manifest 2>/dev/null || true

        if ! git diff --quiet docs/codebase-manifest.md 2>/dev/null; then
          git add docs/codebase-manifest.md
          git commit -m "chore: update codebase manifest" 2>/dev/null || true

          CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "")
          if [ -n "$CURRENT_BRANCH" ]; then
            git push origin "$CURRENT_BRANCH" 2>/dev/null || true
          fi

          MESSAGES="${MESSAGES}Manifest updated and pushed. "
        fi
      fi

      # Generate workflow status
      WORKFLOW_STATUS_SCRIPT="$REPO/.claude/hooks/workflow-status.sh"
      if [ -x "$WORKFLOW_STATUS_SCRIPT" ]; then
        "$WORKFLOW_STATUS_SCRIPT" 2>/dev/null || true
        MESSAGES="${MESSAGES}Workflow status updated. "
      fi

      if [ -n "$MESSAGES" ]; then
        echo "{\"systemMessage\":\"POST-PUSH: ${MESSAGES}\"}"
      fi
    fi
  fi
fi

exit 0
