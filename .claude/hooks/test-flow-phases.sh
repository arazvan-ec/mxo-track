#!/usr/bin/env bash
# Test: flow-phases.sh — single source of truth for phase arrays.
#
# Sources the lib and asserts array shapes, canonical naming decisions, and
# absence of legacy inconsistencies (no `consult` in debug, no `pattern_search`).

set -euo pipefail

REPO="/home/user/mxo-track"
LIB="$REPO/.claude/hooks/lib/flow-phases.sh"

# shellcheck source=./lib/test-harness.sh
source "$REPO/.claude/hooks/lib/test-harness.sh"
init_harness

echo "── flow-phases.sh single source of truth ──"

# Source the lib under test
# shellcheck source=/home/user/mxo-track/.claude/hooks/lib/flow-phases.sh
source "$LIB"

# 1. Arrays are non-empty
assert_eq "FULL_PHASES non-empty"  "yes" "$([ "${#FULL_PHASES[@]}"  -gt 0 ] && echo yes || echo no)"
assert_eq "DEBUG_PHASES non-empty" "yes" "$([ "${#DEBUG_PHASES[@]}" -gt 0 ] && echo yes || echo no)"
assert_eq "AGENT_PHASES non-empty" "yes" "$([ "${#AGENT_PHASES[@]}" -gt 0 ] && echo yes || echo no)"

# 2. First phase per flow
assert_eq "FULL_PHASES[0]"  "consult"        "${FULL_PHASES[0]}"
assert_eq "DEBUG_PHASES[0]" "root_cause"     "${DEBUG_PHASES[0]}"
assert_eq "AGENT_PHASES[0]" "implementation" "${AGENT_PHASES[0]}"

# 3. Canonical name: pattern_wide, not pattern_search
assert_contains     "DEBUG_PHASES contains pattern_wide"     "pattern_wide"   "${DEBUG_PHASES[@]}"
assert_not_contains "DEBUG_PHASES rejects pattern_search"    "pattern_search" "${DEBUG_PHASES[@]}"

# 4. Debug excludes consult (phase-advance SoT)
assert_not_contains "DEBUG_PHASES rejects consult" "consult" "${DEBUG_PHASES[@]}"

# 5. End-to-end sequences (last phase)
assert_eq "FULL_PHASES ends at finalize"      "finalize"     "${FULL_PHASES[-1]}"
assert_eq "DEBUG_PHASES ends at finalize"     "finalize"     "${DEBUG_PHASES[-1]}"
assert_eq "AGENT_PHASES ends at verification" "verification" "${AGENT_PHASES[-1]}"

# 6. Short arrays parallel to long arrays
assert_eq "FULL_PHASES_SHORT length matches"  "${#FULL_PHASES[@]}"  "${#FULL_PHASES_SHORT[@]}"
assert_eq "DEBUG_PHASES_SHORT length matches" "${#DEBUG_PHASES[@]}" "${#DEBUG_PHASES_SHORT[@]}"
assert_eq "AGENT_PHASES_SHORT length matches" "${#AGENT_PHASES[@]}" "${#AGENT_PHASES_SHORT[@]}"

summary
