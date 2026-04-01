#!/usr/bin/env bash
# Spec-compliance validator (SOFT gate)
# When spec has an Existing Functionality Inventory, verifies the plan
# references those inventory items. Prevents silent omissions.
# Exit 0 = pass, Exit 1 = warn
set -euo pipefail
source "$(dirname "$0")/../config-helper.sh"

SPEC_PATH=$(jq -r '.evidence.spec_path // ""' "$STATE_FILE" 2>/dev/null || echo "")
PLAN_PATH=$(jq -r '.evidence.plan_path // ""' "$STATE_FILE" 2>/dev/null || echo "")

# Resolve spec file
SPEC_FULL=""
if [ -n "$SPEC_PATH" ]; then
  if [ -f "$REPO/$SPEC_PATH" ]; then
    SPEC_FULL="$REPO/$SPEC_PATH"
  elif [ -f "$SPEC_PATH" ]; then
    SPEC_FULL="$SPEC_PATH"
  fi
fi

# Resolve plan file
PLAN_FULL=""
if [ -n "$PLAN_PATH" ]; then
  if [ -f "$REPO/$PLAN_PATH" ]; then
    PLAN_FULL="$REPO/$PLAN_PATH"
  elif [ -f "$PLAN_PATH" ]; then
    PLAN_FULL="$PLAN_PATH"
  fi
fi

# If no spec or no plan, skip (other validators handle those)
if [ -z "$SPEC_FULL" ] || [ ! -f "$SPEC_FULL" ]; then
  exit 0
fi
if [ -z "$PLAN_FULL" ] || [ ! -f "$PLAN_FULL" ]; then
  exit 0
fi

# Only check if spec has an inventory section
if ! grep -qE '## Existing Functionality Inventory' "$SPEC_FULL" 2>/dev/null; then
  exit 0
fi

# Extract inventory items (lines starting with - or | in the inventory section)
# We look for content between "## Existing Functionality Inventory" and the next "##"
INVENTORY_SECTION=$(sed -n '/^## Existing Functionality Inventory/,/^## /p' "$SPEC_FULL" | head -n -1)

if [ -z "$INVENTORY_SECTION" ]; then
  exit 0
fi

# Count inventory items (non-empty lines that start with - or |, excluding header)
INVENTORY_COUNT=$(echo "$INVENTORY_SECTION" | grep -cE '^\s*[-|]' 2>/dev/null || echo "0")

if [ "$INVENTORY_COUNT" -eq 0 ]; then
  exit 0
fi

# Check if plan references the inventory or omission decisions
WARNINGS=""

if ! grep -qiE '(inventory|omission|omit|include all|existing functionality)' "$PLAN_FULL" 2>/dev/null; then
  WARNINGS="${WARNINGS}- SPEC-COMPLIANCE: El plan no referencia el inventory del spec ($INVENTORY_COUNT items listados). Verifica que el plan cubra todos los items o documente omisiones.\n"
fi

# Check if omission decisions exist in spec and have actual content
if grep -qE '## Omission Decisions' "$SPEC_FULL" 2>/dev/null; then
  OMISSION_SECTION=$(sed -n '/^## Omission Decisions/,/^## /p' "$SPEC_FULL" | head -n -1)
  OMISSION_ROWS=$(echo "$OMISSION_SECTION" | grep -cE '^\s*\|.*\|.*\|' 2>/dev/null || echo "0")
  # Subtract header rows (typically 2: header + separator)
  if [ "$OMISSION_ROWS" -le 2 ]; then
    WARNINGS="${WARNINGS}- SPEC-COMPLIANCE: La tabla de Omission Decisions esta vacia. Cada item del inventory necesita una decision explicita.\n"
  fi
fi

if [ -n "$WARNINGS" ]; then
  echo "WARNING: Spec-compliance check:"
  echo -e "$WARNINGS"
  echo "Revisa que el plan cubra todos los items del Existing Functionality Inventory del spec."
  exit 1
fi

exit 0
