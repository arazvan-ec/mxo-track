#!/usr/bin/env bash
# test-session-start-resume-bundle.sh — smoke for restore_evidence_bundle_if_resumable.

set -uo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"

pass=0; fail=0
assert() {
  local label="$1" actual="$2" expected="$3"
  if [ "$actual" = "$expected" ]; then
    echo "  ✅ $label"; pass=$((pass+1))
  else
    echo "  ❌ $label (got '$actual', expected '$expected')"; fail=$((fail+1))
  fi
}

# Build a probe that imports the helper from session-start.sh.
PROBE=$(mktemp)
trap 'rm -f "$PROBE"' EXIT
cat > "$PROBE" <<PROBE_EOF
#!/usr/bin/env bash
set -uo pipefail
REPO="\$(pwd)"
TODAY=\$(date +%Y-%m-%d)
# Extract just the two helpers (skip the rest of session-start.sh's main flow)
eval "\$(awk '/^# ── Helper: check if previous state is resumable/,/^# ── Helper: surface related/' "$REPO_ROOT/.claude/hooks/session-start.sh")"
restore_evidence_bundle_if_resumable "\$1"
jq '{
  decisions_read: .evidence.decisions_read,
  logs_scanned: .evidence.logs_scanned,
  alternatives_proposed: .evidence.alternatives_proposed,
  user_approved: .evidence.user_approved
}' "\$1"
PROBE_EOF
chmod +x "$PROBE"

# Build a temp repo with spec+plan committed.
TMP=$(mktemp -d)
trap 'rm -rf "$TMP" "$PROBE"' EXIT
cd "$TMP" || exit 1
git init -q
git config user.email t@t && git config user.name t
git config commit.gpgsign false
mkdir -p docs/superpowers/specs docs/superpowers/plans .claude
echo "# spec" > docs/superpowers/specs/foo.md
echo "# plan" > docs/superpowers/plans/foo.md
git add . && git commit -q -m seed 2>/dev/null

mkstate() {
  local phase="$1"
  cat > "$TMP/state.json" <<JSON
{
  "current_phase": "$phase",
  "evidence": {
    "spec_path": "docs/superpowers/specs/foo.md",
    "plan_path": "docs/superpowers/plans/foo.md",
    "decisions_read": false,
    "logs_scanned": false,
    "alternatives_proposed": false,
    "user_approved": false
  }
}
JSON
}

echo "Test 1: phase=planning + spec/plan on disk → bundle restored (all 4 true)"
mkstate "planning"
out=$(bash "$PROBE" "$TMP/state.json")
assert "decisions_read=true" "$(echo "$out" | jq -r .decisions_read)" "true"
assert "logs_scanned=true" "$(echo "$out" | jq -r .logs_scanned)" "true"
assert "alternatives_proposed=true" "$(echo "$out" | jq -r .alternatives_proposed)" "true"
assert "user_approved=true" "$(echo "$out" | jq -r .user_approved)" "true"

echo "Test 2: phase=brainstorming → only consult flags restored (approval flags stay false)"
mkstate "brainstorming"
out=$(bash "$PROBE" "$TMP/state.json")
assert "decisions_read=true" "$(echo "$out" | jq -r .decisions_read)" "true"
assert "logs_scanned=true" "$(echo "$out" | jq -r .logs_scanned)" "true"
assert "alternatives_proposed stays false" "$(echo "$out" | jq -r .alternatives_proposed)" "false"
assert "user_approved stays false" "$(echo "$out" | jq -r .user_approved)" "false"

echo "Test 3: phase=consult → not resumable, no flags restored"
mkstate "consult"
out=$(bash "$PROBE" "$TMP/state.json")
assert "decisions_read stays false" "$(echo "$out" | jq -r .decisions_read)" "false"

echo "Test 4: empty spec_path → not resumable"
cat > "$TMP/state.json" <<'JSON'
{
  "current_phase": "planning",
  "evidence": {
    "spec_path": "",
    "plan_path": "docs/superpowers/plans/foo.md",
    "decisions_read": false,
    "logs_scanned": false,
    "alternatives_proposed": false,
    "user_approved": false
  }
}
JSON
out=$(bash "$PROBE" "$TMP/state.json")
assert "decisions_read stays false (no spec)" "$(echo "$out" | jq -r .decisions_read)" "false"

echo
echo "Total: $pass passed, $fail failed"
[ "$fail" -eq 0 ]
