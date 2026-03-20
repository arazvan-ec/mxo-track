#!/usr/bin/env bash
# End-to-end tests for self-gating levels 1, 2, 3 in full-flow-gate.sh
#
# Usage: bash .claude/hooks/test-self-gating.sh
# Returns: exit 0 if all pass, exit 1 on first failure

set -euo pipefail

REPO="/home/user/mxo-track"
GATE="$REPO/.claude/hooks/full-flow-gate.sh"
STATE_FILE="$REPO/.claude/session-state.json"
SPEC_DIR="$REPO/docs/superpowers/specs"
PLANS_DIR="$REPO/docs/superpowers/plans"
TODAY=$(date +%Y-%m-%d)
ACTIVE_SPEC_FILE="$REPO/.claude/active-spec"

# Save originals to restore later
ORIG_STATE=""
[ -f "$STATE_FILE" ] && ORIG_STATE=$(cat "$STATE_FILE")
ORIG_ACTIVE_SPEC=""
[ -f "$ACTIVE_SPEC_FILE" ] && ORIG_ACTIVE_SPEC=$(cat "$ACTIVE_SPEC_FILE")

PASS=0
FAIL=0

cleanup() {
  # Restore originals
  if [ -n "$ORIG_STATE" ]; then
    echo "$ORIG_STATE" > "$STATE_FILE"
  fi
  if [ -n "$ORIG_ACTIVE_SPEC" ]; then
    echo "$ORIG_ACTIVE_SPEC" > "$ACTIVE_SPEC_FILE"
  fi
  # Remove test artifacts
  rm -f "$SPEC_DIR/test-self-gating-spec.md"
  rm -f "$PLANS_DIR/${TODAY}-test-self-gating.md"
  echo ""
  echo "==============================="
  echo "Results: $PASS passed, $FAIL failed"
  echo "==============================="
  [ "$FAIL" -eq 0 ] && exit 0 || exit 1
}
trap cleanup EXIT

# Helper: run gate with a fake Edit input and check result
run_gate() {
  local file_path="$1"
  echo "{\"tool_name\":\"Edit\",\"tool_input\":{\"file_path\":\"$file_path\"}}" | bash "$GATE" 2>/dev/null || true
}

expect_deny() {
  local test_name="$1"
  local file_path="$2"
  local expected_msg="$3"
  local result
  result=$(run_gate "$file_path")

  if echo "$result" | jq -r '.hookSpecificOutput.permissionDecision' 2>/dev/null | grep -q "deny"; then
    if [ -n "$expected_msg" ] && ! echo "$result" | grep -qiF "$expected_msg"; then
      echo "FAIL: $test_name — denied but wrong reason"
      echo "  Expected substring: $expected_msg"
      echo "  Got: $(echo "$result" | jq -r '.hookSpecificOutput.permissionDecisionReason' 2>/dev/null)"
      FAIL=$((FAIL + 1))
      return
    fi
    echo "PASS: $test_name"
    PASS=$((PASS + 1))
  else
    echo "FAIL: $test_name — expected deny but got allow"
    FAIL=$((FAIL + 1))
  fi
}

expect_allow() {
  local test_name="$1"
  local file_path="$2"
  local result
  result=$(run_gate "$file_path")

  # If result is empty, gate exited 0 with no output = allow
  if [ -z "$result" ]; then
    echo "PASS: $test_name"
    PASS=$((PASS + 1))
    return
  fi

  if echo "$result" | jq -r '.hookSpecificOutput.permissionDecision' 2>/dev/null | grep -q "deny"; then
    echo "FAIL: $test_name — expected allow but got deny"
    echo "  Reason: $(echo "$result" | jq -r '.hookSpecificOutput.permissionDecisionReason' 2>/dev/null)"
    FAIL=$((FAIL + 1))
  else
    echo "PASS: $test_name"
    PASS=$((PASS + 1))
  fi
}

SRC_FILE="$REPO/backend/src/Controller/SomeController.php"

# =====================================================
# Setup helpers
# =====================================================
setup_base_state() {
  cat > "$STATE_FILE" <<EOJSON
{
  "session_date": "$TODAY",
  "flow_type": "full",
  "flow_declared": true,
  "learning_loop_done": true,
  "brainstorm_done": true,
  "brainstorm_user_turns": 5,
  "active_spec": null,
  "active_plan": "docs/superpowers/plans/${TODAY}-test-self-gating.md",
  "execution_log": null,
  "tdd_bypass": false
}
EOJSON
}

# Creates a valid spec (>500 bytes, with keywords) and a valid plan (>300 bytes, with structure, mentioning SomeController.php)
setup_valid_artifacts() {
  cat > "$SPEC_DIR/test-self-gating-spec.md" <<'EOF'
# Design Spec for Test Feature

## Problema
We need to solve X because the current implementation has performance issues
that affect user experience in production environments with high traffic.

## Approach A — Direct refactor
Trade-off: fast to implement but fragile under edge cases.
This approach modifies the existing controller directly.

## Alternativa B — New service layer
More robust approach that introduces a service abstraction.
Ventaja: Better scalability and testability.
Desventaja: More files to maintain.

## Opcion elegida
We chose Approach B because it scales better for future requirements
and allows proper unit testing without database dependencies.
This provides enough content to pass the 500 byte minimum threshold.
EOF
  echo "docs/superpowers/specs/test-self-gating-spec.md" > "$ACTIVE_SPEC_FILE"

  cat > "$PLANS_DIR/${TODAY}-test-self-gating.md" <<'EOF'
# Implementation Plan for Test Feature

## Task 1: Crear SomeController.php
### Step 1: Modificar File
- Archivo: SomeController.php
- Actualizar the controller to handle new endpoint
- Add validation for request parameters

## Task 2: Crear unit tests
### Step 1: Write test file
- Archivo: SomeControllerTest.php
- Verify endpoint returns correct response

This plan has enough content and structure to pass the 300 byte minimum threshold.
EOF
}

# =====================================================
# NIVEL 1: Evidencia verificable
# =====================================================
echo ""
echo "=== NIVEL 1: Evidencia verificable ==="
echo ""

# Test 1.1: Spec too small (< 500 bytes) → deny
setup_base_state
echo "Small spec" > "$SPEC_DIR/test-self-gating-spec.md"
echo "docs/superpowers/specs/test-self-gating-spec.md" > "$ACTIVE_SPEC_FILE"
# Create valid plan for now (so we isolate Nivel 1)
cat > "$PLANS_DIR/${TODAY}-test-self-gating.md" <<'EOF'
# Test Plan
## Task 1: Crear SomeController.php
### Step 1: Modificar File
- Archivo: SomeController.php
EOF
expect_deny "1.1 Spec too small" "$SRC_FILE" "Nivel 1"

# Test 1.2: Spec large enough but missing keywords → deny
setup_base_state
python3 -c "print('x' * 600)" > "$SPEC_DIR/test-self-gating-spec.md"
echo "docs/superpowers/specs/test-self-gating-spec.md" > "$ACTIVE_SPEC_FILE"
expect_deny "1.2 Spec missing keywords" "$SRC_FILE" "Nivel 1"

# Test 1.3: Spec valid, plan too small → deny
setup_base_state
cat > "$SPEC_DIR/test-self-gating-spec.md" <<'EOF'
# Design Spec for Test Feature

## Problema
We need to solve X because the current implementation has performance issues
that affect user experience in production environments with high traffic.

## Approach A — Direct refactor
Trade-off: fast to implement but fragile under edge cases.
This approach modifies the existing controller directly.

## Alternativa B — New service layer
More robust approach that introduces a service abstraction.
Ventaja: Better scalability and testability.
Desventaja: More files to maintain.

## Opcion elegida
We chose Approach B because it scales better for future requirements
and allows proper unit testing without database dependencies.
This provides enough content to pass the 500 byte minimum threshold.
EOF
echo "docs/superpowers/specs/test-self-gating-spec.md" > "$ACTIVE_SPEC_FILE"
echo "Small plan" > "$PLANS_DIR/${TODAY}-test-self-gating.md"
expect_deny "1.3 Plan too small" "$SRC_FILE" "Nivel 1"

# Test 1.4: Plan large enough but missing structure → deny
setup_base_state
echo "docs/superpowers/specs/test-self-gating-spec.md" > "$ACTIVE_SPEC_FILE"
python3 -c "print('x' * 400)" > "$PLANS_DIR/${TODAY}-test-self-gating.md"
expect_deny "1.4 Plan missing structure keywords" "$SRC_FILE" "Nivel 1"

# Test 1.5: Both spec and plan valid → passes Nivel 1
setup_base_state
setup_valid_artifacts
expect_allow "1.5 Valid spec + valid plan" "$SRC_FILE"

# =====================================================
# NIVEL 2: Turnos de conversacion
# =====================================================
echo ""
echo "=== NIVEL 2: Turnos de conversacion ==="
echo ""

# Test 2.1: 0 turns → deny
setup_base_state
setup_valid_artifacts
jq '.brainstorm_user_turns = 0' "$STATE_FILE" > "$STATE_FILE.tmp" && mv "$STATE_FILE.tmp" "$STATE_FILE"
expect_deny "2.1 Zero turns" "$SRC_FILE" "Nivel 2"

# Test 2.2: 1 turn → deny
setup_base_state
setup_valid_artifacts
jq '.brainstorm_user_turns = 1' "$STATE_FILE" > "$STATE_FILE.tmp" && mv "$STATE_FILE.tmp" "$STATE_FILE"
expect_deny "2.2 One turn (insufficient)" "$SRC_FILE" "Nivel 2"

# Test 2.3: 2 turns → allow
setup_base_state
setup_valid_artifacts
jq '.brainstorm_user_turns = 2' "$STATE_FILE" > "$STATE_FILE.tmp" && mv "$STATE_FILE.tmp" "$STATE_FILE"
expect_allow "2.3 Two turns (minimum)" "$SRC_FILE"

# Test 2.4: 5 turns → allow
setup_base_state
setup_valid_artifacts
expect_allow "2.4 Five turns (plenty)" "$SRC_FILE"

# =====================================================
# NIVEL 3: Coherencia plan<->edit
# =====================================================
echo ""
echo "=== NIVEL 3: Coherencia plan<->edit ==="
echo ""

# Test 3.1: Edit file NOT in plan → deny
setup_base_state
setup_valid_artifacts
# Override plan to NOT mention SomeController.php but still pass Nivel 1
cat > "$PLANS_DIR/${TODAY}-test-self-gating.md" <<'EOF'
# Implementation Plan for Different Feature

## Task 1: Crear OtherFile.php
### Step 1: Modificar File
- Archivo: OtherFile.php
- Actualizar something else entirely with different logic

## Task 2: Crear tests for OtherFile
### Step 1: Write test file
- Archivo: OtherFileTest.php
- Verify the behavior matches expectations

This plan intentionally does not mention SomeController to test Nivel 3.
EOF
expect_deny "3.1 File not in plan" "$SRC_FILE" "Nivel 3"

# Test 3.2: Edit file IN plan → allow
setup_base_state
setup_valid_artifacts
expect_allow "3.2 File in plan" "$SRC_FILE"

# Test 3.3: File mentioned case-insensitively → allow
setup_base_state
setup_valid_artifacts
# Override plan with lowercase filename but still pass Nivel 1
cat > "$PLANS_DIR/${TODAY}-test-self-gating.md" <<'EOF'
# Implementation Plan for Case Test

## Task 1: Modificar somecontroller.php
### Step 1: Actualizar controller
- Archivo: somecontroller.php
- Actualizar controller with new functionality and validation

## Task 2: Crear related tests
### Step 1: Write tests for the modified controller behavior

This plan uses lowercase filenames to verify case-insensitive matching.
EOF
expect_allow "3.3 Case-insensitive match" "$SRC_FILE"

# =====================================================
# INTEGRACION: Non-full flows skip self-gating
# =====================================================
echo ""
echo "=== INTEGRACION: Bypass para non-full flows ==="
echo ""

# Test 4.1: debug flow skips self-gating entirely
cat > "$STATE_FILE" <<EOJSON
{
  "session_date": "$TODAY",
  "flow_type": "debug",
  "flow_declared": true,
  "learning_loop_done": true,
  "brainstorm_done": true,
  "brainstorm_user_turns": 0,
  "active_spec": null,
  "active_plan": null,
  "execution_log": null,
  "tdd_bypass": false
}
EOJSON
echo "docs/superpowers/specs/test-self-gating-spec.md" > "$ACTIVE_SPEC_FILE"
cat > "$PLANS_DIR/${TODAY}-test-self-gating.md" <<'EOF'
# Plan
## Task 1: File OtherFile.php
EOF
expect_allow "4.1 Debug flow bypasses self-gating" "$SRC_FILE"

# Test 4.2: Files outside src always allowed
setup_base_state
jq '.brainstorm_user_turns = 0' "$STATE_FILE" > "$STATE_FILE.tmp" && mv "$STATE_FILE.tmp" "$STATE_FILE"
expect_allow "4.2 Non-src files bypass all gates" "$REPO/docs/some-doc.md"
