#!/usr/bin/env bash
# Finalize phase validator (SOFT gate)
# Checks:
#   1. branch_strategy declared
#   2. Knowledge modules that may need updating based on changed files
# Exit 0 = pass, Exit 1 = warn
set -euo pipefail

STATE_FILE="${1:-.claude/session-state.json}"
REPO="/home/user/mxo-track"

WARNINGS=""

# ── Check 1: branch_strategy ──
BRANCH_STRATEGY=$(jq -r '.evidence.branch_strategy // ""' "$STATE_FILE" 2>/dev/null || echo "")

if [ -z "$BRANCH_STRATEGY" ]; then
  WARNINGS="WARNING: No branch strategy declared. Usa Skill 12 para finalizar la rama."
fi

# ── Check 2: Knowledge modules that may need updating ──
# Get files changed in this branch vs main
CHANGED_FILES=$(cd "$REPO" && git diff --name-only origin/main...HEAD 2>/dev/null || echo "")

if [ -n "$CHANGED_FILES" ]; then
  SUGGESTED_MODULES=""

  # Map directory patterns to knowledge modules
  if echo "$CHANGED_FILES" | grep -q "backend/src/Controller/"; then
    SUGGESTED_MODULES="${SUGGESTED_MODULES}api-surface.md "
  fi
  if echo "$CHANGED_FILES" | grep -qE "backend/src/Entity/|backend/src/Enum/"; then
    SUGGESTED_MODULES="${SUGGESTED_MODULES}domain-model.md "
  fi
  if echo "$CHANGED_FILES" | grep -q "frontend/src/"; then
    SUGGESTED_MODULES="${SUGGESTED_MODULES}ui-frontend.md "
  fi
  if echo "$CHANGED_FILES" | grep -qiE "backend/src/Service/.*Provider|backend/src/Service/.*Factory"; then
    SUGGESTED_MODULES="${SUGGESTED_MODULES}provider-framework.md "
  fi
  if echo "$CHANGED_FILES" | grep -qiE "backend/src/Service/.*Gps|backend/src/Service/.*Traccar"; then
    SUGGESTED_MODULES="${SUGGESTED_MODULES}gps-tracking.md "
  fi
  if echo "$CHANGED_FILES" | grep -qiE "backend/src/Service/.*Optim|backend/src/Service/.*Routing"; then
    SUGGESTED_MODULES="${SUGGESTED_MODULES}route-optimization.md "
  fi
  if echo "$CHANGED_FILES" | grep -qiE "backend/src/Service/.*Mercure|backend/src/Service/.*Realtime"; then
    SUGGESTED_MODULES="${SUGGESTED_MODULES}realtime.md "
  fi
  if echo "$CHANGED_FILES" | grep -q "backend/src/Security/"; then
    SUGGESTED_MODULES="${SUGGESTED_MODULES}security.md "
  fi
  if echo "$CHANGED_FILES" | grep -q "backend/tests/"; then
    SUGGESTED_MODULES="${SUGGESTED_MODULES}testing.md "
  fi
  if echo "$CHANGED_FILES" | grep -qE "docker/|Dockerfile|railway"; then
    SUGGESTED_MODULES="${SUGGESTED_MODULES}deployment.md "
  fi
  if echo "$CHANGED_FILES" | grep -qiE "backend/src/Service/.*Notification|backend/src/Service/.*Sms"; then
    SUGGESTED_MODULES="${SUGGESTED_MODULES}notifications.md "
  fi
  if echo "$CHANGED_FILES" | grep -qiE "\.claude/hooks/|session-state"; then
    SUGGESTED_MODULES="${SUGGESTED_MODULES}superpowers-skills.md "
  fi

  if [ -n "$SUGGESTED_MODULES" ]; then
    # Check which of these were NOT modified in the branch
    NEEDS_REVIEW=""
    for module in $SUGGESTED_MODULES; do
      if ! echo "$CHANGED_FILES" | grep -q "docs/knowledge/$module"; then
        NEEDS_REVIEW="${NEEDS_REVIEW}$module "
      fi
    done

    if [ -n "$NEEDS_REVIEW" ]; then
      KNOWLEDGE_WARNING="KNOWLEDGE CHECK: Archivos en src/ cambiaron pero estos knowledge modules NO fueron actualizados: ${NEEDS_REVIEW}— Revisa si necesitan actualizacion."
      if [ -n "$WARNINGS" ]; then
        WARNINGS="${WARNINGS} | ${KNOWLEDGE_WARNING}"
      else
        WARNINGS="$KNOWLEDGE_WARNING"
      fi
    fi
  fi
fi

if [ -n "$WARNINGS" ]; then
  echo "$WARNINGS"
  exit 1
fi

exit 0
