#!/usr/bin/env bash
# Socratic review phase validator (HARD gate — Layer C of Option 3-Enforced).
#
# Enforces adversarial re-reading of the shipped work before capture.
# Rationale: the 2026-04-24 routes-widget audit revealed that retrospectives
# without an explicit adversarial lens miss architectural issues (DDD
# violations, TZ inconsistencies, perf gaps) that shape-only tests never
# surface. This gate forces the question "what did we ship wrong?" into
# every full/debug flow.
#
# Contract:
# - evidence.socratic_questions must be a JSON array with >=3 entries.
# - Each entry must be a string >=30 chars (anti-templating).
# - If any file in `git diff --name-only origin/main...HEAD` matches a
#   critical-path regex, at least one question must contain an
#   architectural keyword.
#
# Exit codes:
#   0 = pass
#   2 = block (hard gate)
#
# Bypass: SKIP_PHASE_EXIT_GATE=1 (same as other phase exit validators).

set -euo pipefail

REPO="/home/user/mxo-track"
STATE_FILE="${1:-$REPO/.claude/session-state.json}"

# Bypass honored by phase-advance.sh driver
if [ "${SKIP_PHASE_EXIT_GATE:-0}" = "1" ]; then
  exit 0
fi

ERRORS=""

# ── Check 1: array exists and is the right shape ──
QCOUNT=$(jq -r '(.evidence.socratic_questions // []) | length' "$STATE_FILE" 2>/dev/null || echo "0")

if [ "$QCOUNT" -lt 3 ]; then
  ERRORS="${ERRORS}- C: socratic_review requiere >=3 preguntas adversariales en evidence.socratic_questions (actual: $QCOUNT).\n"
  ERRORS="${ERRORS}  Set con: jq '.evidence.socratic_questions = [\"Q1 ...\", \"Q2 ...\", \"Q3 ...\"]' .claude/session-state.json > /tmp/ss.json && mv /tmp/ss.json .claude/session-state.json\n"
fi

# ── Check 2: each question must be substantive (>=30 chars) ──
if [ "$QCOUNT" -ge 1 ]; then
  SHORT_COUNT=$(jq -r '(.evidence.socratic_questions // []) | map(select(type == "string" and (. | length) < 30)) | length' "$STATE_FILE" 2>/dev/null || echo "0")
  if [ "$SHORT_COUNT" -gt 0 ]; then
    ERRORS="${ERRORS}- C: $SHORT_COUNT pregunta(s) demasiado cortas (<30 chars). Las preguntas adversariales deben ser especificas, no genericas.\n"
  fi
fi

# ── Check 3: if critical paths touched, at least one question must be architectural ──
CRITICAL_REGEX='backend/src/Domain/|backend/src/Controller/Api/|frontend/src/widgets/|\.claude/hooks/'
CHANGED_FILES=$(cd "$REPO" && git diff --name-only origin/main...HEAD 2>/dev/null || echo "")

if [ -n "$CHANGED_FILES" ] && echo "$CHANGED_FILES" | grep -qE "$CRITICAL_REGEX"; then
  ARCH_KEYWORDS='endorsed|boundary|DDD|tech.?debt|architecture|coupling|pattern|tradeoff|trade-off'
  ARCH_MATCHES=$(jq -r '(.evidence.socratic_questions // []) | .[]' "$STATE_FILE" 2>/dev/null | grep -ciE "$ARCH_KEYWORDS" || true)

  if [ "${ARCH_MATCHES:-0}" -eq 0 ] && [ "$QCOUNT" -ge 3 ]; then
    ERRORS="${ERRORS}- C: Los cambios tocan paths criticos (Domain, Controller/Api, widgets, hooks) pero ninguna pregunta menciona keywords arquitectonicas (endorsed|boundary|DDD|tech-debt|architecture|coupling|pattern|tradeoff). Agrega una pregunta sobre arquitectura/boundaries.\n"
  fi
fi

if [ -n "$ERRORS" ]; then
  echo "BLOCKED: Socratic review incompleto:"
  echo -e "$ERRORS"
  echo "Bypass (last resort): export SKIP_PHASE_EXIT_GATE=1"
  exit 2
fi

exit 0
