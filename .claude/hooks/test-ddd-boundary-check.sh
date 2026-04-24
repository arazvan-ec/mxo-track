#!/usr/bin/env bash
# Test suite for ddd-boundary-check.sh (Layer F).
#
# Covers 5 cases per plan task 1b:
#   a. Edit to non-critical path                       -> silent, exit 0
#   b. Edit to known-violation file + createQueryBuilder -> silent, exit 0 (legacy)
#   c. Edit to critical-context controller + createQueryBuilder -> warning emitted, exit 0
#   d. Edit to Infrastructure/ + createQueryBuilder    -> silent, exit 0
#   e. SKIP_DDD_BOUNDARY_GATE=1 overrides case (c)     -> silent, exit 0

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

summary
