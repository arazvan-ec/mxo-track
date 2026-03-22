#!/usr/bin/env bash
# Finalize phase validator (SOFT gate)
# Checks: branch_strategy declared
# Exit 0 = pass, Exit 1 = warn
set -euo pipefail

STATE_FILE="${1:-.claude/session-state.json}"

BRANCH_STRATEGY=$(jq -r '.evidence.branch_strategy // ""' "$STATE_FILE" 2>/dev/null || echo "")

if [ -z "$BRANCH_STRATEGY" ]; then
  echo "WARNING: No branch strategy declared. Usa Skill 12 para finalizar la rama."
  exit 1
fi

exit 0
