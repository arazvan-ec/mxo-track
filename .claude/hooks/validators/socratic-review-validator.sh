#!/usr/bin/env bash
# Socratic review validator — reads `## Architectural Adversarial Review`
# section from a spec file and validates it.
#
# Invoked as a library by brainstorm-validator.sh when a spec references
# critical contexts. Repositioned 2026-04-24 from post-verification phase
# (original placement too late — code already written, rollback expensive)
# to brainstorm exit (architectural review during design, zero rollback).
#
# Usage: socratic-review-validator.sh <spec_path>
#
# Contract:
# - The spec must contain a `## Architectural Adversarial Review` section.
# - Section must contain >=3 questions (format: "N. **Q:**" lines).
# - Each question must have >=30 chars of combined Q+A content.
# - When the spec references critical paths, >=1 question must include an
#   architectural keyword (endorsed, boundary, DDD, tech-debt,
#   architecture, coupling, pattern, tradeoff).
#
# Exit 0 = pass, Exit 2 = block.

set -euo pipefail

SPEC_PATH="${1:-}"

if [ -z "$SPEC_PATH" ] || [ ! -f "$SPEC_PATH" ]; then
  echo "BLOCKED: socratic-review-validator needs a valid spec path (got: '$SPEC_PATH')"
  exit 2
fi

ERRORS=""

# Extract the Architectural Adversarial Review section (from its header to
# the next top-level ## header or EOF).
SECTION=$(awk '
  /^## Architectural Adversarial Review/ { flag = 1; next }
  /^## / { flag = 0 }
  flag
' "$SPEC_PATH")

if [ -z "$SECTION" ]; then
  ERRORS="${ERRORS}- C: Spec no tiene seccion '## Architectural Adversarial Review'. Agrega >=3 preguntas adversariales numeradas (formato: N. **Q:** <pregunta> / **A:** <respuesta>).\n"
fi

# Count questions — lines matching "N. **Q:**"
QCOUNT=$(echo "$SECTION" | grep -cE '^[[:space:]]*[0-9]+\.[[:space:]]+\*\*Q:\*\*' || true)

if [ -n "$SECTION" ] && [ "$QCOUNT" -lt 3 ]; then
  ERRORS="${ERRORS}- C: Seccion '## Architectural Adversarial Review' tiene $QCOUNT preguntas; se requieren >=3.\n"
fi

# Parse each question block. A block starts at "N. **Q:**" and extends
# until the next "N. **Q:**" or end of section. Require >=30 chars of
# real content (excluding the Q:/A: markers). Only buffers that began
# with a Q: marker are counted — preamble whitespace is ignored.
if [ -n "$SECTION" ] && [ "$QCOUNT" -ge 1 ]; then
  SHORT_COUNT=$(
    awk '
      function finalize(buf,   stripped) {
        if (buf == "") return
        # Only count buffers that actually contained a Q: marker.
        if (buf !~ /\*\*Q:\*\*/) return
        stripped = buf
        gsub(/\*\*Q:\*\*/, "", stripped); gsub(/\*\*A:\*\*/, "", stripped)
        gsub(/^[[:space:]]+|[[:space:]]+$/, "", stripped)
        if (length(stripped) < 30) short++
      }
      /^[[:space:]]*[0-9]+\.[[:space:]]+\*\*Q:\*\*/ {
        finalize(buf)
        buf = $0; next
      }
      { buf = buf "\n" $0 }
      END { finalize(buf); print (short ? short : 0) }
    ' <<< "$SECTION"
  )
  if [ "${SHORT_COUNT:-0}" -gt 0 ]; then
    ERRORS="${ERRORS}- C: $SHORT_COUNT pregunta(s) demasiado cortas (<30 chars combinando Q+A). Las preguntas adversariales deben ser especificas.\n"
  fi
fi

# If the spec references critical paths, at least one Q must contain an
# architectural keyword.
if [ -n "$SECTION" ] && [ "$QCOUNT" -ge 3 ]; then
  if grep -qE '(src/Domain/(Route|Shipment)/|src/Controller/Api/Admin/)' "$SPEC_PATH"; then
    ARCH_KEYWORDS='endorsed|boundary|DDD|tech.?debt|architecture|coupling|pattern|tradeoff|trade-off'
    if ! echo "$SECTION" | grep -qiE "$ARCH_KEYWORDS"; then
      ERRORS="${ERRORS}- C: Spec referencia contextos criticos pero ninguna pregunta menciona keywords arquitectonicas (endorsed|boundary|DDD|tech-debt|architecture|coupling|pattern|tradeoff). Agrega al menos una pregunta sobre arquitectura/boundaries.\n"
    fi
  fi
fi

if [ -n "$ERRORS" ]; then
  echo "BLOCKED: Architectural Adversarial Review incompleto:"
  echo -e "$ERRORS"
  exit 2
fi

exit 0
