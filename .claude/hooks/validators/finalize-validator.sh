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
  NEW_FILES=$(cd "$REPO" && git diff --diff-filter=A --name-only origin/main...HEAD 2>/dev/null || echo "")

  # Helper: suggest module only if ≥5 files match OR new files were added
  check_pattern() {
    local pattern="$1" module="$2"
    local count new_count
    count=$(echo "$CHANGED_FILES" | grep -c "$pattern" 2>/dev/null || echo "0")
    new_count=$(echo "$NEW_FILES" | grep -c "$pattern" 2>/dev/null || echo "0")
    if [ "$count" -ge 5 ] || [ "$new_count" -gt 0 ]; then
      SUGGESTED_MODULES="${SUGGESTED_MODULES}${module} "
    fi
  }

  check_pattern_i() {
    local pattern="$1" module="$2"
    local count new_count
    count=$(echo "$CHANGED_FILES" | grep -ciE "$pattern" 2>/dev/null || echo "0")
    new_count=$(echo "$NEW_FILES" | grep -ciE "$pattern" 2>/dev/null || echo "0")
    if [ "$count" -ge 5 ] || [ "$new_count" -gt 0 ]; then
      SUGGESTED_MODULES="${SUGGESTED_MODULES}${module} "
    fi
  }

  # Map directory patterns to knowledge modules (threshold: ≥5 files OR new files)
  check_pattern "backend/src/Controller/" "api-surface.md"
  check_pattern_i "backend/src/Entity/|backend/src/Enum/" "domain-model.md"
  check_pattern "frontend/src/" "ui-frontend.md"
  check_pattern_i "backend/src/Service/.*Provider|backend/src/Service/.*Factory" "provider-framework.md"
  check_pattern_i "backend/src/Service/.*Gps|backend/src/Service/.*Traccar" "gps-tracking.md"
  check_pattern_i "backend/src/Service/.*Optim|backend/src/Service/.*Routing" "route-optimization.md"
  check_pattern_i "backend/src/Service/.*Mercure|backend/src/Service/.*Realtime" "realtime.md"
  check_pattern "backend/src/Security/" "security.md"
  check_pattern "backend/tests/" "testing.md"
  check_pattern_i "docker/|Dockerfile|railway" "deployment.md"
  check_pattern_i "backend/src/Service/.*Notification|backend/src/Service/.*Sms" "notifications.md"
  check_pattern_i "\.claude/hooks/|session-state" "superpowers-skills.md"

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
