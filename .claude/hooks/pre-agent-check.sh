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

# Use shared lib for section presence + content classification.
# Lib path is absolute (decoupled from REPO, which the test harness rewrites
# to point at fixture repos that lack .claude/hooks/lib/).
# shellcheck source=lib/section-validator.sh
source "/home/user/mxo-track/.claude/hooks/lib/section-validator.sh"

PROMPT_TMP="$(mktemp)"
trap 'rm -f "$PROMPT_TMP"' EXIT
printf '%s' "$AGENT_PROMPT" > "$PROMPT_TMP"

NORMS_OK=0
SAFEGUARDS_OK=0
if section_present "$PROMPT_TMP" "Norms"; then
  NORMS_BODY=$(section_body "$PROMPT_TMP" "Norms")
  section_satisfied_inline_or_ref "$NORMS_BODY" "Norms" imperative && NORMS_OK=1
fi
if section_present "$PROMPT_TMP" "Safeguards"; then
  SAFE_BODY=$(section_body "$PROMPT_TMP" "Safeguards")
  section_satisfied_inline_or_ref "$SAFE_BODY" "Safeguards" risk-mitigation-table && SAFEGUARDS_OK=1
fi

if [ "$NORMS_OK" = "0" ] || [ "$SAFEGUARDS_OK" = "0" ]; then
  MISSING=""
  [ "$NORMS_OK" = "0" ] && MISSING="${MISSING}Norms "
  [ "$SAFEGUARDS_OK" = "0" ] && MISSING="${MISSING}Safeguards"
  echo "{\"hookSpecificOutput\":{\"hookEventName\":\"PreToolUse\",\"permissionDecision\":\"deny\",\"permissionDecisionReason\":\"❌ Agent prompt missing structured section(s): $MISSING. Each section requires either inline content (imperative keyword for Norms; Risk|Mitigation table for Safeguards) or spec-reference (path to docs/superpowers/specs/X.md within proximity of the section token). See AGENTS.md § Norms & Safeguards.\"}}"
  exit 0
fi

# ── Gate 4: Vocabulary consultation (Phase B B-1, WARN only) ──
# Surfaces deprecated-alias mentions in the agent prompt. Migrated to
# lib/vocabulary-reader.sh in i12 (2026-04-29).
VOCAB_FILE="/home/user/mxo-track/docs/knowledge/_vocabulary.yaml"
if [ -f "$VOCAB_FILE" ]; then
  # shellcheck source=lib/vocabulary-reader.sh
  source "/home/user/mxo-track/.claude/hooks/lib/vocabulary-reader.sh"

  DEPRECATED_HITS=""
  while IFS='|' read -r alias canonical; do
    [ -z "$alias" ] && continue
    if echo "$AGENT_PROMPT" | grep -qiwE -- "\b${alias}\b" 2>/dev/null; then
      DEPRECATED_HITS="${DEPRECATED_HITS}vocab: \"${alias}\" is deprecated alias for \"${canonical}\"; "
    fi
  done <<< "$(vocab_deprecated_aliases "$VOCAB_FILE")"
  DEPRECATED_HITS=$(echo "$DEPRECATED_HITS" | sed 's/; $//')

  if [ -n "$DEPRECATED_HITS" ]; then
    ESCAPED=$(echo "⚠ $DEPRECATED_HITS. Consider replacing with canonical term in agent prompt." | sed 's/"/\\"/g')
    echo "{\"systemMessage\":\"$ESCAPED\"}"
  fi
fi

# Clean repo — allow (warnings emitted above do not block)
exit 0
