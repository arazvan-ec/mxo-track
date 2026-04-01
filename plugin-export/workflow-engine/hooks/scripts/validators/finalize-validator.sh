#!/usr/bin/env bash
# Finalize phase validator (SOFT gate)
# Checks:
#   1. branch_strategy declared
#   2. Knowledge modules that may need updating based on changed files
# Exit 0 = pass, Exit 1 = warn
set -euo pipefail
source "$(dirname "$0")/../config-helper.sh"

WARNINGS=""

# ── Check 1: branch_strategy ──
BRANCH_STRATEGY=$(jq -r '.evidence.branch_strategy // ""' "$STATE_FILE" 2>/dev/null || echo "")

if [ -z "$BRANCH_STRATEGY" ]; then
  WARNINGS="WARNING: No branch strategy declared."
fi

# ── Check 2: Knowledge modules that may need updating ──
# Get files changed in this branch vs main
CHANGED_FILES=$(cd "$REPO" && git diff --name-only origin/main...HEAD 2>/dev/null || echo "")

if [ -n "$CHANGED_FILES" ]; then
  SUGGESTED_MODULES=""

  # Check configured src paths for changes
  local_src_paths=$(read_config_array "src_paths" "src")
  while IFS= read -r pattern; do
    if echo "$CHANGED_FILES" | grep -q "$pattern"; then
      # Determine which modules based on file patterns within src
      if echo "$CHANGED_FILES" | grep -q "Controller/\|controller"; then
        SUGGESTED_MODULES="${SUGGESTED_MODULES}api-surface.md "
      fi
      if echo "$CHANGED_FILES" | grep -qE "Entity/|Enum/|entity|enum"; then
        SUGGESTED_MODULES="${SUGGESTED_MODULES}domain-model.md "
      fi
      if echo "$CHANGED_FILES" | grep -qiE "Provider|Factory"; then
        SUGGESTED_MODULES="${SUGGESTED_MODULES}provider-framework.md "
      fi
      if echo "$CHANGED_FILES" | grep -qiE "Gps|Traccar"; then
        SUGGESTED_MODULES="${SUGGESTED_MODULES}gps-tracking.md "
      fi
      if echo "$CHANGED_FILES" | grep -qiE "Optim|Routing"; then
        SUGGESTED_MODULES="${SUGGESTED_MODULES}route-optimization.md "
      fi
      if echo "$CHANGED_FILES" | grep -qiE "Mercure|Realtime"; then
        SUGGESTED_MODULES="${SUGGESTED_MODULES}realtime.md "
      fi
      if echo "$CHANGED_FILES" | grep -qiE "Security"; then
        SUGGESTED_MODULES="${SUGGESTED_MODULES}security.md "
      fi
      if echo "$CHANGED_FILES" | grep -qiE "Notification|Sms"; then
        SUGGESTED_MODULES="${SUGGESTED_MODULES}notifications.md "
      fi
      break
    fi
  done <<< "$local_src_paths"

  # Check configured test paths
  local_test_paths=$(read_config_array "test_paths" "tests")
  while IFS= read -r pattern; do
    if echo "$CHANGED_FILES" | grep -q "$pattern"; then
      SUGGESTED_MODULES="${SUGGESTED_MODULES}testing.md "
      break
    fi
  done <<< "$local_test_paths"

  # Check other common patterns
  if echo "$CHANGED_FILES" | grep -qE "frontend/|app/"; then
    SUGGESTED_MODULES="${SUGGESTED_MODULES}ui-frontend.md "
  fi
  if echo "$CHANGED_FILES" | grep -qE "docker/|Dockerfile|railway"; then
    SUGGESTED_MODULES="${SUGGESTED_MODULES}deployment.md "
  fi
  if echo "$CHANGED_FILES" | grep -qiE "\.claude/hooks/|session-state"; then
    SUGGESTED_MODULES="${SUGGESTED_MODULES}superpowers-skills.md "
  fi

  if [ -n "$SUGGESTED_MODULES" ]; then
    # Deduplicate
    SUGGESTED_MODULES=$(echo "$SUGGESTED_MODULES" | tr ' ' '\n' | sort -u | tr '\n' ' ')

    # Check which of these were NOT modified in the branch
    NEEDS_REVIEW=""
    for module in $SUGGESTED_MODULES; do
      if ! echo "$CHANGED_FILES" | grep -q "docs/knowledge/$module"; then
        NEEDS_REVIEW="${NEEDS_REVIEW}$module "
      fi
    done

    if [ -n "$NEEDS_REVIEW" ]; then
      KNOWLEDGE_WARNING="KNOWLEDGE CHECK: Source files changed but these knowledge modules were NOT updated: ${NEEDS_REVIEW}— Revisa si necesitan actualizacion."
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
