#!/usr/bin/env bash
# Retrospective phase validator (SOFT gate)
# Reminds to update decision log if applicable
# Exit 0 = pass, Exit 1 = warn
set -euo pipefail

STATE_FILE="${1:-.claude/session-state.json}"

# This is always a soft gate — just remind
echo "WARNING: Retrospective phase — recuerda actualizar docs/decisions/log.md si hubo decisiones de diseno."
exit 1
