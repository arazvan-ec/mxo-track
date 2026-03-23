#!/usr/bin/env bash
# PostToolUse hook — generates a one-line workflow status for Claude to display.
# Reads session-state.json, writes .claude/workflow-status-line.txt
# Non-blocking: always exits 0.

set -euo pipefail

REPO="/home/user/mxo-track"
STATE_FILE="$REPO/.claude/session-state.json"
OUTPUT="$REPO/.claude/workflow-status-line.txt"

# Graceful fallback
if [ ! -f "$STATE_FILE" ]; then
  echo "📍 status unavailable" > "$OUTPUT"
  exit 0
fi

FLOW_TYPE=$(jq -r '.flow_type // "null"' "$STATE_FILE" 2>/dev/null || echo "null")
CURRENT_PHASE=$(jq -r '.current_phase // "null"' "$STATE_FILE" 2>/dev/null || echo "null")
DEV_ACTIVE=$(jq -r '.deviation.active // false' "$STATE_FILE" 2>/dev/null || echo "false")

DEVIATION_SUFFIX=""
if [ "$DEV_ACTIVE" = "true" ]; then
  DEVIATION_SUFFIX=" | ⚠ DESVÍO"
fi

# No flow declared
if [ "$FLOW_TYPE" = "null" ] || [ -z "$FLOW_TYPE" ]; then
  echo "📍 no flow declared${DEVIATION_SUFFIX}" > "$OUTPUT"
  exit 0
fi

# Simple flows
case "$FLOW_TYPE" in
  micro)
    echo "📍 micro | Responder${DEVIATION_SUFFIX}" > "$OUTPUT"
    exit 0
    ;;
  light)
    echo "📍 light | Documentar${DEVIATION_SUFFIX}" > "$OUTPUT"
    exit 0
    ;;
  explore)
    echo "📍 explore | Investigar${DEVIATION_SUFFIX}" > "$OUTPUT"
    exit 0
    ;;
esac

# Full-flow: 8 phases
if [ "$FLOW_TYPE" = "full" ]; then
  PHASES=("consult" "brainstorming" "planning" "implementation" "verification" "capture" "retrospective" "finalize")
  TOTAL=8

  # Find current phase index (1-based)
  CURRENT_INDEX=0
  for i in "${!PHASES[@]}"; do
    if [ "${PHASES[$i]}" = "$CURRENT_PHASE" ]; then
      CURRENT_INDEX=$((i + 1))
      break
    fi
  done

  # If current_phase not in list, show raw
  if [ "$CURRENT_INDEX" -eq 0 ]; then
    echo "📍 full | ${CURRENT_PHASE} | ⚠ fase no reconocida${DEVIATION_SUFFIX}" > "$OUTPUT"
    exit 0
  fi

  # Build completed phases string
  COMPLETED=""
  for i in "${!PHASES[@]}"; do
    idx=$((i + 1))
    if [ "$idx" -lt "$CURRENT_INDEX" ]; then
      if [ -n "$COMPLETED" ]; then
        COMPLETED="${COMPLETED} → ✅ ${PHASES[$i]}"
      else
        COMPLETED="✅ ${PHASES[$i]}"
      fi
    fi
  done

  # Build pending phases string
  PENDING=""
  for i in "${!PHASES[@]}"; do
    idx=$((i + 1))
    if [ "$idx" -gt "$CURRENT_INDEX" ]; then
      if [ -n "$PENDING" ]; then
        PENDING="${PENDING}, ${PHASES[$i]}"
      else
        PENDING="${PHASES[$i]}"
      fi
    fi
  done

  # Capitalize current phase for display
  DISPLAY_PHASE="$(echo "${CURRENT_PHASE:0:1}" | tr '[:lower:]' '[:upper:]')${CURRENT_PHASE:1}"

  # Build full line
  LINE="📍 full | ${DISPLAY_PHASE} (${CURRENT_INDEX}/${TOTAL})"
  if [ -n "$COMPLETED" ]; then
    LINE="${LINE} | ${COMPLETED} → 🔄 ${CURRENT_PHASE}"
  else
    LINE="${LINE} | 🔄 ${CURRENT_PHASE}"
  fi
  if [ -n "$PENDING" ]; then
    LINE="${LINE} | Pendiente: ${PENDING}"
  fi
  LINE="${LINE}${DEVIATION_SUFFIX}"

  echo "$LINE" > "$OUTPUT"
  exit 0
fi

# Debug-flow: 4 phases
if [ "$FLOW_TYPE" = "debug" ]; then
  # Determine debug phase from evidence
  ROOT_CAUSE=$(jq -r '.evidence.root_cause_identified // false' "$STATE_FILE" 2>/dev/null || echo "false")
  PATTERN_SEARCH=$(jq -r '.evidence.pattern_wide_search_done // false' "$STATE_FILE" 2>/dev/null || echo "false")

  DEBUG_PHASES=("consult" "root_cause" "pattern_search" "fix")
  TOTAL=4

  # Determine current debug phase
  if [ "$CURRENT_PHASE" = "implementation" ] || ([ "$ROOT_CAUSE" = "true" ] && [ "$PATTERN_SEARCH" = "true" ]); then
    DEBUG_CURRENT="fix"
    DEBUG_INDEX=4
  elif [ "$PATTERN_SEARCH" = "true" ]; then
    DEBUG_CURRENT="fix"
    DEBUG_INDEX=4
  elif [ "$ROOT_CAUSE" = "true" ]; then
    DEBUG_CURRENT="pattern_search"
    DEBUG_INDEX=3
  elif [ "$CURRENT_PHASE" = "consult" ]; then
    DEBUG_CURRENT="consult"
    DEBUG_INDEX=1
  else
    # Default: infer from current_phase
    DEBUG_CURRENT="root_cause"
    DEBUG_INDEX=2
  fi

  # Build completed
  COMPLETED=""
  for i in "${!DEBUG_PHASES[@]}"; do
    idx=$((i + 1))
    if [ "$idx" -lt "$DEBUG_INDEX" ]; then
      if [ -n "$COMPLETED" ]; then
        COMPLETED="${COMPLETED} → ✅ ${DEBUG_PHASES[$i]}"
      else
        COMPLETED="✅ ${DEBUG_PHASES[$i]}"
      fi
    fi
  done

  # Build pending
  PENDING=""
  for i in "${!DEBUG_PHASES[@]}"; do
    idx=$((i + 1))
    if [ "$idx" -gt "$DEBUG_INDEX" ]; then
      if [ -n "$PENDING" ]; then
        PENDING="${PENDING}, ${DEBUG_PHASES[$i]}"
      else
        PENDING="${DEBUG_PHASES[$i]}"
      fi
    fi
  done

  # Capitalize
  DISPLAY_PHASE="$(echo "${DEBUG_CURRENT:0:1}" | tr '[:lower:]' '[:upper:]')${DEBUG_CURRENT:1}"

  LINE="📍 debug | ${DISPLAY_PHASE} (${DEBUG_INDEX}/${TOTAL})"
  if [ -n "$COMPLETED" ]; then
    LINE="${LINE} | ${COMPLETED} → 🔄 ${DEBUG_CURRENT}"
  else
    LINE="${LINE} | 🔄 ${DEBUG_CURRENT}"
  fi
  if [ -n "$PENDING" ]; then
    LINE="${LINE} | Pendiente: ${PENDING}"
  fi
  LINE="${LINE}${DEVIATION_SUFFIX}"

  echo "$LINE" > "$OUTPUT"
  exit 0
fi

# Unknown flow type — show raw
echo "📍 ${FLOW_TYPE} | ${CURRENT_PHASE}${DEVIATION_SUFFIX}" > "$OUTPUT"
exit 0
