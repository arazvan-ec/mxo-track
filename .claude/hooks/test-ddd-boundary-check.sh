#!/usr/bin/env bash
# Test suite for ddd-boundary-check.sh (Layer F).
#
# Original WARNING-only cases (a..e):
#   a. Edit to non-critical path                       -> silent, exit 0
#   b. Edit to known-violation file + createQueryBuilder -> silent, exit 0 (legacy)
#   c. Edit to critical-context controller + createQueryBuilder -> warning emitted, exit 0
#                                                       (when no session-state present)
#   d. Edit to Infrastructure/ + createQueryBuilder    -> silent, exit 0
#   e. SKIP_DDD_BOUNDARY_GATE=1 overrides case (c)     -> silent, exit 0
#
# Conditional BLOCK branch added 2026-04-26:
#   f. full-flow + spec without Prior Art Audit covering file -> BLOCK, exit 2
#   g. full-flow + spec with Prior Art Audit covering file    -> WARN, exit 0
#   h. light-flow + no coverage                               -> WARN, exit 0
#                                                                (BLOCK is full/debug only)

set -uo pipefail

REPO="/home/user/mxo-track"
SCRIPT_DIR="$(cd "$(dirname "$(readlink -f "$0")")" && pwd)"
SCRIPT="$SCRIPT_DIR/ddd-boundary-check.sh"
LIB="$SCRIPT_DIR/lib/test-harness.sh"

# shellcheck source=lib/test-harness.sh
source "$LIB"
init_harness

echo "=== ddd-boundary-check tests ==="

# Build a minimal fixture repo with the YAML so the hook has something to read.
FIX_REPO="$TEST_TMPDIR/repo"
mkdir -p "$FIX_REPO/docs/knowledge" "$FIX_REPO/.claude/hooks"

cat > "$FIX_REPO/docs/knowledge/_ddd-boundaries.yaml" <<'YAML'
critical_contexts:
  - path: backend/src/Domain/Route/**
    aggregates: [Route, RouteStop, RouteSnapshot, RouteEvent]
  - path: backend/src/Domain/Shipment/**
    aggregates: [Shipment, Parcel, DeliveryEvidence]

known_violations:
  - file: backend/src/Application/Delivery/DeliveryService.php
    note: "legacy - don't re-flag"
  - file: backend/src/Controller/Api/Admin/RouteListApiController.php
    method: list
    note: "Resolved 2026-04-24 - uses RouteStopRepositoryInterface"
YAML

# Rewrite REPO= in a local copy so the hook reads our fixture YAML.
HOOK_COPY="$FIX_REPO/.claude/hooks/ddd-boundary-check.sh"
sed "s|^REPO=\".*\"|REPO=\"$FIX_REPO\"|" "$SCRIPT" > "$HOOK_COPY"
chmod +x "$HOOK_COPY"

run_hook() {
  # args: input_json [env_prefix]
  local input_json="$1"
  local env_prefix="${2:-}"
  local exit_code=0
  local stderr_out
  stderr_out=$(mktemp -p "$TEST_TMPDIR")
  if [ -n "$env_prefix" ]; then
    # shellcheck disable=SC2086
    echo "$input_json" | env $env_prefix "$HOOK_COPY" 2>"$stderr_out" >/dev/null || exit_code=$?
  else
    echo "$input_json" | "$HOOK_COPY" 2>"$stderr_out" >/dev/null || exit_code=$?
  fi
  # Emit "<exit_code>|<stderr_content>" so both can be asserted.
  printf '%s|%s' "$exit_code" "$(cat "$stderr_out")"
}

# ---------- Case (a): non-critical path ----------
INPUT_A='{"tool_input":{"file_path":"backend/src/Controller/Api/Public/X.php","new_string":"createQueryBuilder(\"q\")"}}'
RES_A=$(run_hook "$INPUT_A")
EC_A="${RES_A%%|*}"
ERR_A="${RES_A#*|}"
assert_eq "a: non-critical path -> exit 0" "0" "$EC_A"
if [ -z "$ERR_A" ]; then
  pass "a: non-critical path -> no warning"
else
  fail "a: non-critical path -> no warning" "stderr='$ERR_A'"
fi

# ---------- Case (b): known violation ----------
INPUT_B='{"tool_input":{"file_path":"backend/src/Application/Delivery/DeliveryService.php","new_string":"$qb = $this->createQueryBuilder(\"d\");"}}'
RES_B=$(run_hook "$INPUT_B")
EC_B="${RES_B%%|*}"
ERR_B="${RES_B#*|}"
assert_eq "b: known violation -> exit 0" "0" "$EC_B"
if [ -z "$ERR_B" ]; then
  pass "b: known violation -> no warning (legacy)"
else
  fail "b: known violation -> no warning (legacy)" "stderr='$ERR_B'"
fi

# ---------- Case (c): new critical-context controller + createQueryBuilder ----------
INPUT_C='{"tool_input":{"file_path":"backend/src/Controller/Api/Admin/NewRouteController.php","new_string":"$qb = $this->em->createQueryBuilder();"}}'
RES_C=$(run_hook "$INPUT_C")
EC_C="${RES_C%%|*}"
ERR_C="${RES_C#*|}"
assert_eq "c: critical controller + createQueryBuilder -> exit 0 (non-blocking)" "0" "$EC_C"
if echo "$ERR_C" | grep -q "DDD boundary"; then
  pass "c: critical controller + createQueryBuilder -> warning emitted"
else
  fail "c: critical controller + createQueryBuilder -> warning emitted" "stderr='$ERR_C'"
fi

# ---------- Case (d): Infrastructure/ is exempt ----------
INPUT_D='{"tool_input":{"file_path":"backend/src/Infrastructure/Route/Doctrine/NewRepo.php","new_string":"return $this->createQueryBuilder(\"r\");"}}'
RES_D=$(run_hook "$INPUT_D")
EC_D="${RES_D%%|*}"
ERR_D="${RES_D#*|}"
assert_eq "d: Infrastructure path -> exit 0" "0" "$EC_D"
if [ -z "$ERR_D" ]; then
  pass "d: Infrastructure path -> no warning (ORM allowed)"
else
  fail "d: Infrastructure path -> no warning (ORM allowed)" "stderr='$ERR_D'"
fi

# ---------- Case (e): bypass env var ----------
RES_E=$(run_hook "$INPUT_C" "SKIP_DDD_BOUNDARY_GATE=1")
EC_E="${RES_E%%|*}"
ERR_E="${RES_E#*|}"
assert_eq "e: SKIP_DDD_BOUNDARY_GATE=1 -> exit 0" "0" "$EC_E"
if [ -z "$ERR_E" ]; then
  pass "e: SKIP_DDD_BOUNDARY_GATE=1 -> no warning (bypass)"
else
  fail "e: SKIP_DDD_BOUNDARY_GATE=1 -> no warning (bypass)" "stderr='$ERR_E'"
fi

# ---------------------------------------------------------------------------
# Conditional BLOCK branch (Layer F strengthening, 2026-04-26).
# Cases f/g/h require a session-state file in $FIX_REPO so the hook reads
# flow_type + spec_path. Using the same INPUT_C as case (c) since it's the
# same edit that triggers the boundary detection.
# ---------------------------------------------------------------------------
mkdir -p "$FIX_REPO/.claude" "$FIX_REPO/docs/superpowers/specs"

# Two spec fixtures: one with NewRouteController in PAA, one without.
cat > "$FIX_REPO/docs/superpowers/specs/spec-no-coverage.md" <<'EOF'
# Spec
## Prior Art Audit
| Path | Endorsed? |
|---|---|
| backend/src/Domain/Route/SomeOtherFile.php | new |
EOF

cat > "$FIX_REPO/docs/superpowers/specs/spec-covered.md" <<'EOF'
# Spec
## Prior Art Audit
| Path | Endorsed? |
|---|---|
| backend/src/Controller/Api/Admin/NewRouteController.php | new |
EOF

write_state() {
  local flow="$1"
  local spec="$2"
  cat > "$FIX_REPO/.claude/session-state.json" <<EOF
{"flow_type":"$flow","evidence":{"spec_path":"$spec"}}
EOF
}

# ---------- Case (f): full-flow + spec WITHOUT coverage -> BLOCK ----------
write_state "full" "docs/superpowers/specs/spec-no-coverage.md"
RES_F=$(run_hook "$INPUT_C")
EC_F="${RES_F%%|*}"
ERR_F="${RES_F#*|}"
assert_eq "f: full + Prior Art doesn't cover -> exit 2 (BLOCK)" "2" "$EC_F"
if echo "$ERR_F" | grep -q "BLOCKED DDD boundary"; then
  pass "f: BLOCK message emitted"
else
  fail "f: BLOCK message emitted" "stderr='$ERR_F'"
fi

# ---------- Case (g): full-flow + spec WITH coverage -> WARN only ----------
write_state "full" "docs/superpowers/specs/spec-covered.md"
RES_G=$(run_hook "$INPUT_C")
EC_G="${RES_G%%|*}"
ERR_G="${RES_G#*|}"
assert_eq "g: full + Prior Art covers -> exit 0 (WARN, not BLOCK)" "0" "$EC_G"
if echo "$ERR_G" | grep -q "WARNING DDD boundary"; then
  pass "g: WARNING message emitted (PAA covers file)"
else
  fail "g: WARNING message emitted (PAA covers file)" "stderr='$ERR_G'"
fi

# ---------- Case (h): light-flow -> WARN only (BLOCK is full/debug only) -----
write_state "light" "docs/superpowers/specs/spec-no-coverage.md"
RES_H=$(run_hook "$INPUT_C")
EC_H="${RES_H%%|*}"
ERR_H="${RES_H#*|}"
assert_eq "h: light flow -> exit 0 (BLOCK reserved for full/debug)" "0" "$EC_H"
if echo "$ERR_H" | grep -q "WARNING DDD boundary"; then
  pass "h: WARNING (light flow doesn't trigger BLOCK)"
else
  fail "h: WARNING (light flow doesn't trigger BLOCK)" "stderr='$ERR_H'"
fi

# Cleanup state to avoid contaminating any later run
rm -f "$FIX_REPO/.claude/session-state.json"

summary
