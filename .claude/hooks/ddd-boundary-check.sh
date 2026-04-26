#!/usr/bin/env bash
# ddd-boundary-check.sh - PreToolUse hook for Edit|Write (Layer F).
#
# Emits a non-blocking warning when an edit introduces ORM coupling
# (createQueryBuilder / getRepository() calls) inside a critical bounded
# context, OUTSIDE backend/src/Infrastructure/. Pre-existing tech debt
# listed in docs/knowledge/_ddd-boundaries.yaml under known_violations
# is silently skipped.
#
# Policy reference: backend/CLAUDE.md "Architecture: Two Worlds".
# Design:           docs/superpowers/specs/2026-04-24-workflow-enforcement-layers-CHFIJ-design.md (Layer F)
#
# Bypass: export SKIP_DDD_BOUNDARY_GATE=1

set -uo pipefail

REPO="/home/user/mxo-track"
BOUNDARIES_FILE="$REPO/docs/knowledge/_ddd-boundaries.yaml"

# Bypass
if [ "${SKIP_DDD_BOUNDARY_GATE:-0}" = "1" ]; then
  exit 0
fi

# YAML missing -> nothing to enforce
[ -f "$BOUNDARIES_FILE" ] || exit 0

INPUT=$(cat || true)
[ -z "$INPUT" ] && exit 0

FILE_PATH=$(echo "$INPUT" | jq -r '.tool_input.file_path // ""' 2>/dev/null)
[ -z "$FILE_PATH" ] && exit 0

# Normalise to repo-relative path (strip REPO prefix and leading slash)
REL_PATH="${FILE_PATH#"$REPO/"}"
REL_PATH="${REL_PATH#/}"

# ---------------------------------------------------------------------------
# YAML parsing: prefer yq when present, fall back to grep/sed heuristics.
# We only need:
#   - known_violations[].file
#   - critical_contexts[].path  (glob, e.g. backend/src/Domain/Route/**)
# ---------------------------------------------------------------------------

KNOWN_VIOLATIONS=""
CRITICAL_PATHS=""

if command -v yq >/dev/null 2>&1; then
  KNOWN_VIOLATIONS=$(yq -r '.known_violations[].file' "$BOUNDARIES_FILE" 2>/dev/null | grep -v '^null$' || true)
  CRITICAL_PATHS=$(yq -r '.critical_contexts[].path' "$BOUNDARIES_FILE" 2>/dev/null | grep -v '^null$' || true)
fi

# Fallback (or supplement if yq returned nothing)
if [ -z "$KNOWN_VIOLATIONS" ] || [ -z "$CRITICAL_PATHS" ]; then
  KNOWN_VIOLATIONS=$(sed -n '/^known_violations:/,/^[a-z_]/p' "$BOUNDARIES_FILE" \
    | grep -E '^[[:space:]]*-[[:space:]]*file:' \
    | sed -E 's/^[[:space:]]*-[[:space:]]*file:[[:space:]]*//' \
    | sed -E 's/[[:space:]]*#.*$//' \
    | sed -E 's/^"(.*)"$/\1/' \
    | sed -E "s/^'(.*)'\$/\\1/" \
    || true)
  CRITICAL_PATHS=$(sed -n '/^critical_contexts:/,/^[a-z_]/p' "$BOUNDARIES_FILE" \
    | grep -E '^[[:space:]]*-[[:space:]]*path:' \
    | sed -E 's/^[[:space:]]*-[[:space:]]*path:[[:space:]]*//' \
    | sed -E 's/[[:space:]]*#.*$//' \
    | sed -E 's/^"(.*)"$/\1/' \
    | sed -E "s/^'(.*)'\$/\\1/" \
    || true)
fi

# ---------------------------------------------------------------------------
# 1. Known-violation check -- skip silently (don't re-flag legacy).
# ---------------------------------------------------------------------------
if [ -n "$KNOWN_VIOLATIONS" ]; then
  while IFS= read -r violation; do
    [ -z "$violation" ] && continue
    if [ "$REL_PATH" = "$violation" ]; then
      exit 0
    fi
  done <<< "$KNOWN_VIOLATIONS"
fi

# ---------------------------------------------------------------------------
# 2. Critical-context match -- only applies to non-Infrastructure files.
# ---------------------------------------------------------------------------
# Infrastructure/ is where ORM lives by design; never warn there.
case "$REL_PATH" in
  backend/src/Infrastructure/*) exit 0 ;;
esac

IS_CRITICAL=0
if [ -n "$CRITICAL_PATHS" ]; then
  while IFS= read -r glob; do
    [ -z "$glob" ] && continue
    # Convert glob -> regex prefix: strip trailing ** and treat the remainder
    # as a literal prefix match against REL_PATH. A critical context glob
    # like "backend/src/Domain/Route/**" must also match controllers/services
    # that TOUCH Route (per spec: "controllers and services outside
    # Infrastructure/ against aggregates listed in critical_contexts").
    # Heuristic: extract the aggregate directory name (e.g. "Route") and
    # match any path containing it as a path segment that looks like a
    # controller/service referencing that aggregate.
    prefix="${glob%/\*\*}"           # backend/src/Domain/Route
    aggregate_token="${prefix##*/}"  # Route
    # Exact domain path hit
    case "$REL_PATH" in
      "$prefix"/*) IS_CRITICAL=1; break ;;
    esac
    # Heuristic: file references the aggregate token in a controller/service path
    if echo "$REL_PATH" | grep -qE "(^|/)(Controller|Application|Service)/.*${aggregate_token}[A-Za-z0-9]*\.php$"; then
      IS_CRITICAL=1
      break
    fi
  done <<< "$CRITICAL_PATHS"
fi

[ "$IS_CRITICAL" -eq 0 ] && exit 0

# ---------------------------------------------------------------------------
# 3. Content inspection -- look at the proposed edit payload.
# ---------------------------------------------------------------------------
# Edit uses new_string; Write uses content. Both are plain strings.
PAYLOAD=$(echo "$INPUT" | jq -r '.tool_input.new_string // .tool_input.content // ""' 2>/dev/null)

# No payload to inspect -> allow
[ -z "$PAYLOAD" ] && exit 0

if echo "$PAYLOAD" | grep -qE 'createQueryBuilder|getRepository\('; then
  # Conditional severity: BLOCK in full/debug flows when the spec's Prior
  # Art Audit doesn't cover this file. WARNING otherwise (light/explore,
  # or no spec yet in early brainstorm).
  STATE_FILE="$REPO/.claude/session-state.json"
  SHOULD_BLOCK=0

  if [ -f "$STATE_FILE" ]; then
    FLOW=$(jq -r '.flow_type // "null"' "$STATE_FILE" 2>/dev/null)
    if [ "$FLOW" = "full" ] || [ "$FLOW" = "debug" ]; then
      SPEC_PATH=$(jq -r '.evidence.spec_path // ""' "$STATE_FILE" 2>/dev/null)
      SPEC_FULL=""
      if [ -n "$SPEC_PATH" ]; then
        if [ -f "$SPEC_PATH" ]; then
          SPEC_FULL="$SPEC_PATH"
        elif [ -f "$REPO/$SPEC_PATH" ]; then
          SPEC_FULL="$REPO/$SPEC_PATH"
        fi
      fi
      if [ -n "$SPEC_FULL" ]; then
        # Spec exists. Does its Prior Art Audit section cover this file?
        AUDIT_SECTION=$(awk '/^## Prior Art Audit/{flag=1; next} /^## /{flag=0} flag' "$SPEC_FULL" 2>/dev/null)
        if [ -z "$AUDIT_SECTION" ]; then
          # Spec lacks audit entirely (Layer H should have caught, but be defensive)
          SHOULD_BLOCK=1
        elif ! echo "$AUDIT_SECTION" | grep -qF "$REL_PATH"; then
          SHOULD_BLOCK=1
        fi
      fi
      # If no spec yet (still in consult/early brainstorm), fall through to
      # WARNING — not enough state to BLOCK.
    fi
  fi

  if [ "$SHOULD_BLOCK" -eq 1 ]; then
    cat >&2 <<EOF
BLOCKED DDD boundary: adding ORM coupling in critical context at $REL_PATH.
The current $FLOW-flow spec does NOT include this file in its Prior Art Audit.
Either:
  (a) Add this file to the spec's '## Prior Art Audit' table and classify
      it (✅ endorsed, ❌ tech-debt, or new) with justification, OR
  (b) Refactor through a RepositoryInterface in Domain/{Context}/Repository/
      backed by an Infrastructure/{Context}/Doctrine/ implementation, OR
  (c) Bypass: export SKIP_DDD_BOUNDARY_GATE=1 (decision-log entry required)
  Background: backend/CLAUDE.md "Architecture: Two Worlds".
  Boundaries:  docs/knowledge/_ddd-boundaries.yaml
  Spec:        $SPEC_PATH
EOF
    echo "{\"hookSpecificOutput\":{\"hookEventName\":\"PreToolUse\",\"permissionDecision\":\"deny\",\"permissionDecisionReason\":\"DDD boundary BLOCKED at $REL_PATH (full-flow + missing Prior Art Audit row)\"}}"
    exit 2
  fi

  cat >&2 <<EOF
WARNING DDD boundary: adding ORM coupling in critical context at $REL_PATH.
Consider using the RepositoryInterface pattern.
  Endorsed approach: inject a Domain/{Context}/Repository/XRepositoryInterface
  and let Infrastructure/{Context}/Doctrine/ provide the implementation.
  Background: backend/CLAUDE.md "Architecture: Two Worlds".
  Boundaries:  docs/knowledge/_ddd-boundaries.yaml
  Bypass:      export SKIP_DDD_BOUNDARY_GATE=1 (decision-log entry required)
EOF
  # Warning only -- exit 0 so the edit proceeds.
  exit 0
fi

exit 0
