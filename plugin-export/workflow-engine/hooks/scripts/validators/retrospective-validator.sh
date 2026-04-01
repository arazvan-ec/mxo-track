#!/usr/bin/env bash
# Retrospective phase validator (SOFT gate)
# Reminds to update decision log if applicable
# Exit 0 = pass, Exit 1 = warn
set -euo pipefail
source "$(dirname "$0")/../config-helper.sh"

# This is always a soft gate — just remind
echo "WARNING: Retrospective phase — recuerda actualizar $DECISIONS_LOG si hubo decisiones de diseno."
exit 1
