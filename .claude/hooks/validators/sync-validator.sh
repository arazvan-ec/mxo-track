#!/usr/bin/env bash
# Sync validator — plan↔diff drift detection (HARD gate)
#
# Invoked at verification → capture (sub-invocation from verification-validator)
# to verify that every file changed in the branch is declared in the plan's
# `→ files:` task annotations. Workflow artifact paths (specs, plans, logs,
# manifest, decision log, session-state) are out of scope by construction:
# they are produced by the workflow itself and have no canonical declaration
# in any plan. This is the gate's scope, not an exception list.
#
# Exit 0 = pass, Exit 1 = soft warn (cannot determine — fail open),
# Exit 2 = block (drift detected)
#
# Env:
#   SYNC_REPO_ROOT — override repo path (used by test harness); defaults to
#                    /home/user/mxo-track when unset.
#   SKIP_SYNC_GATE=1 — documented bypass (decision log entry required).

set -euo pipefail

STATE_FILE="${1:-.claude/session-state.json}"
REPO="${SYNC_REPO_ROOT:-/home/user/mxo-track}"

# Documented bypass — never silent
if [ "${SKIP_SYNC_GATE:-0}" = "1" ]; then
  echo "⚠ SKIP_SYNC_GATE=1 — sync gate bypassed (requires decision log entry)"
  exit 0
fi

PLAN_PATH=$(jq -r '.evidence.plan_path // ""' "$STATE_FILE" 2>/dev/null || echo "")

# No plan → nothing to validate (light/explore/micro flows have no plan)
if [ -z "$PLAN_PATH" ]; then
  exit 0
fi

# Resolve plan file (relative to REPO or absolute)
PLAN_FULL=""
if [ -f "$REPO/$PLAN_PATH" ]; then
  PLAN_FULL="$REPO/$PLAN_PATH"
elif [ -f "$PLAN_PATH" ]; then
  PLAN_FULL="$PLAN_PATH"
fi

if [ -z "$PLAN_FULL" ]; then
  exit 0
fi

# ── Parse `→ files:` declarations from plan ──
# shellcheck source=../lib/files-decl-parser.sh
source "$(dirname "$0")/../lib/files-decl-parser.sh"
DECLARED=$(parse_files_decl "$PLAN_FULL")

# ── Determine diff baseline ──
# A branch may accumulate multiple interactions before merge. Three baseline
# strategies, in order:
#   1. Plan committed → baseline = parent of plan-introducing commit
#      (scopes diff to current interaction's changes).
#   2. Plan on disk but uncommitted (just authored) → no committed diff;
#      working-tree-only check (added 2026-04-29 to fix Hitos 2/4/5
#      recurrence where origin/main fallback captured whole branch).
#   3. Plan path missing → origin/main fallback (test fixtures with
#      synthetic state files).
PLAN_INTRODUCED=$(cd "$REPO" && git log --diff-filter=A --reverse --format=%H -- "$PLAN_PATH" 2>/dev/null | head -1 || true)

# Detect test-fixture case: plan path resolves outside $REPO (absolute path in
# TMPDIR). Fixtures have isolated git histories where origin/main captures the
# expected synthetic diff; real sessions have plan paths inside the repo.
PLAN_IN_REPO=false
case "$PLAN_FULL" in
  "$REPO"/*) PLAN_IN_REPO=true ;;
esac

if [ -n "$PLAN_INTRODUCED" ]; then
  BASELINE=$(cd "$REPO" && git rev-parse "${PLAN_INTRODUCED}^" 2>/dev/null || true)
  if [ -z "$BASELINE" ]; then
    BASELINE=$(cd "$REPO" && git rev-parse origin/main 2>/dev/null || echo "")
  fi
  DIFF_RAW=$(cd "$REPO" && git diff --name-only "${BASELINE}...HEAD" 2>/dev/null || true)
elif [ "$PLAN_IN_REPO" = "true" ]; then
  # Plan exists in repo's working tree but not in git log → authored in
  # current session and not yet committed. Skip committed-diff scope; the
  # working-tree merge below covers the actual changes. Avoids the
  # "origin/main fallback captures whole branch" trap (Hitos 2/4/5).
  DIFF_RAW=""
else
  # Test fixture: plan path is outside $REPO. Use origin/main baseline.
  DIFF_RAW=$(cd "$REPO" && git diff --name-only origin/main...HEAD 2>/dev/null || true)
fi

# Always include working-tree changes (uncommitted edits past plan creation)
WORKING_TREE_DIRTY=$(cd "$REPO" && git diff --name-only HEAD 2>/dev/null || true)
UNTRACKED=$(cd "$REPO" && git ls-files --others --exclude-standard 2>/dev/null || true)
DIFF_RAW=$(printf "%s\n%s\n%s" "$DIFF_RAW" "$WORKING_TREE_DIRTY" "$UNTRACKED" | { grep -v '^$' || true; } | sort -u)

# Empty diff is legitimate (no changes yet)
if [ -z "$DIFF_RAW" ]; then
  exit 0
fi

# ── Filter workflow artifact paths from diff ──
# These paths are produced by the workflow itself and are categorically
# out of scope for the plan↔diff comparison. Documented in spec
# 2026-04-28-sync-validator-design.md as "scope of the gate, not exceptions".
WORKFLOW_ARTIFACTS_PATHS='^(docs/superpowers/(specs|plans|execution-logs|retrospectives)/|docs/codebase-manifest\.md$|docs/decisions/log\.md$|\.claude/(session-state|parallel-tasks)\.json$)'

DIFF_FILTERED=$(echo "$DIFF_RAW" | { grep -vE "$WORKFLOW_ARTIFACTS_PATHS" || true; } | sort -u)

if [ -z "$DIFF_FILTERED" ]; then
  # Only workflow artifacts changed → pass
  exit 0
fi

# ── Compute drift = filtered_diff − declared ──
DRIFT=$(comm -23 <(echo "$DIFF_FILTERED") <(echo "$DECLARED") || true)

if [ -n "$DRIFT" ]; then
  echo "BLOCKED: Sync gate detectó drift plan↔código:"
  echo "Archivos modificados pero NO declarados en el plan ($PLAN_PATH):"
  echo "$DRIFT" | sed 's/^/  - /'
  echo
  echo "Acciones posibles:"
  echo "  1. Revertir los cambios en estos archivos si fueron accidentales."
  echo "  2. Actualizar el plan añadiéndolos a una tarea con '→ files: <path>'."
  echo "  3. Si son artefactos del workflow no listados, añadir a WORKFLOW_ARTIFACTS_PATHS"
  echo "     (requiere entrada en docs/decisions/log.md tras 3+ ocurrencias)."
  exit 2
fi

exit 0
