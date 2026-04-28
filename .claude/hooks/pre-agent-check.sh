#!/usr/bin/env bash
# Pre-Agent Check — PreToolUse hook for Agent
#
# Behavior:
# 1. Block Agent dispatch (except "Explore") when uncommitted changes exist.
# 2. Warn when the agent prompt references `.claude/**` paths AND the current
#    interaction_classification is insufficient for framework-path edits —
#    subagents inherit the same classify-validator as main, so the dispatch
#    would fail silently without this guard.

set -euo pipefail

REPO="/home/user/mxo-track"
STATE_FILE="$REPO/.claude/session-state.json"

# Parse tool input from stdin
INPUT=$(cat)
SUBAGENT_TYPE=$(echo "$INPUT" | jq -r '.tool_input.subagent_type // ""')
AGENT_PROMPT=$(echo "$INPUT" | jq -r '.tool_input.prompt // ""')

# Read-only agents are safe — skip check
if [ "$SUBAGENT_TYPE" = "Explore" ]; then
  exit 0
fi

# ── Gate 1: uncommitted changes ──
DIRTY=$(git -C "$REPO" status --porcelain 2>/dev/null || true)

if [ -n "$DIRTY" ]; then
  FILE_LIST=$(echo "$DIRTY" | head -10 | awk '{print $NF}' | tr '\n' ', ' | sed 's/,$//')
  TOTAL=$(echo "$DIRTY" | wc -l | tr -d ' ')
  SUFFIX=""
  if [ "$TOTAL" -gt 10 ]; then
    SUFFIX=" ... y $((TOTAL - 10)) mas"
  fi

  echo "{\"hookSpecificOutput\":{\"hookEventName\":\"PreToolUse\",\"permissionDecision\":\"deny\",\"permissionDecisionReason\":\"❌ Commit changes before dispatching agents. Uncommitted files ($TOTAL): $FILE_LIST$SUFFIX\"}}"
  exit 0
fi

# ── Gate 2: classification sufficient for .claude/** prompt references ──
# If the agent prompt mentions .claude/ paths and classification is weak,
# the agent will hit classify-validator mid-task. Warn so the dispatcher
# can reclassify before committing.
if [ -f "$STATE_FILE" ] && echo "$AGENT_PROMPT" | grep -qE '\.claude/'; then
  CLASS=$(jq -r '.interaction_classification // "null"' "$STATE_FILE" 2>/dev/null)
  case "$CLASS" in
    full|debug)
      : # OK — subagent will pass classify-validator for .claude/** writes.
      ;;
    *)
      MSG="⚠ Agent prompt references .claude/** paths but classification='$CLASS' is insufficient. classify-validator will block the agent's writes. Reclassify before retrying: jq '.interaction_classification=\"full\" | .flow_type=\"full\"' .claude/session-state.json > /tmp/ss.json && mv /tmp/ss.json .claude/session-state.json"
      echo "{\"systemMessage\":\"$MSG\"}"
      ;;
  esac
fi

# ── Gate 3: Norms & Safeguards in agent prompt (Layer Agent, HARD) ──
# Every write-capable subagent dispatch must include `## Norms` and
# `## Safeguards` sections in the prompt. Each section can be satisfied
# either inline (imperative keyword for Norms / Risk|Mitigation table for
# Safeguards) or by spec-reference (path + section token within proximity).
# Origin: 2026-04-28 hito 4, SPDD REASONS Canvas applied to subagent prompts.

write_prompt_to_tmp() {
  local f="$1"
  printf '%s' "$AGENT_PROMPT" > "$f"
}

PROMPT_TMP="$(mktemp)"
trap 'rm -f "$PROMPT_TMP"' EXIT
write_prompt_to_tmp "$PROMPT_TMP"

# Helper: check a section satisfies inline criterion or spec-reference criterion.
# Returns 0 if satisfied, 1 otherwise.
check_section() {
  local prompt_file="$1"
  local heading="$2"          # "Norms" or "Safeguards"
  local inline_check="$3"     # "imperative" or "risk-mitigation-table"

  # 1. Heading must exist
  if ! grep -qE "^## $heading" "$prompt_file"; then
    return 1
  fi

  # 2. Extract section body (until next ## or EOF)
  local body
  body=$(awk -v h="^## $heading" '
    $0 ~ h {flag=1; next}
    /^## / {flag=0}
    flag {print}
  ' "$prompt_file")

  # 3. Try spec-reference: path + section-token within ~200 chars.
  # Compact the body to single-line for proximity check.
  local body_oneline
  body_oneline=$(echo "$body" | tr '\n' ' ')
  if echo "$body_oneline" | grep -qE "docs/superpowers/specs/[^[:space:]]+\.md.{0,200}$heading"; then
    return 0
  fi
  # Also accept reverse order (token before path) within proximity
  if echo "$body_oneline" | grep -qE "$heading.{0,200}docs/superpowers/specs/[^[:space:]]+\.md"; then
    return 0
  fi

  # 4. Try inline criterion
  case "$inline_check" in
    imperative)
      if echo "$body" | grep -qiE '\<(must|shall|never|always|no se permite|no debe|siempre|jamás|jamas)\>'; then
        return 0
      fi
      ;;
    risk-mitigation-table)
      if echo "$body" | grep -qiE '^\|.*risk.*\|.*mitigation.*\||^\|.*mitigation.*\|.*risk.*\|'; then
        return 0
      fi
      ;;
  esac

  return 1
}

NORMS_OK=0
SAFEGUARDS_OK=0
check_section "$PROMPT_TMP" "Norms" "imperative" && NORMS_OK=1
check_section "$PROMPT_TMP" "Safeguards" "risk-mitigation-table" && SAFEGUARDS_OK=1

if [ "$NORMS_OK" = "0" ] || [ "$SAFEGUARDS_OK" = "0" ]; then
  MISSING=""
  [ "$NORMS_OK" = "0" ] && MISSING="${MISSING}Norms "
  [ "$SAFEGUARDS_OK" = "0" ] && MISSING="${MISSING}Safeguards"
  echo "{\"hookSpecificOutput\":{\"hookEventName\":\"PreToolUse\",\"permissionDecision\":\"deny\",\"permissionDecisionReason\":\"❌ Agent prompt missing structured section(s): $MISSING. Each section requires either inline content (imperative keyword for Norms; Risk|Mitigation table for Safeguards) or spec-reference (path to docs/superpowers/specs/X.md within proximity of the section token). See AGENTS.md § Norms & Safeguards.\"}}"
  exit 0
fi

# Clean repo — allow (warnings emitted above do not block)
exit 0
