#!/usr/bin/env bash
# git-probe.sh — read-only git introspection for harness validators.
#
# Source me; do not execute. Functions are read-only — they never
# mutate the working tree, the index, or evidence state.
#
# Origin: 2026-04-30 cross-session resume hardening (d).
# Spec: docs/superpowers/specs/2026-04-29-cross-session-resume-hardening-design.md
#
# Usage:
#   source /home/user/mxo-track/.claude/hooks/lib/git-probe.sh
#
#   if is_path_committed_clean "$REPO" "docs/superpowers/specs/foo.md"; then ...
#   if is_spec_committed_clean "$REPO" "$STATE_FILE"; then ...

# ── is_path_committed_clean <repo> <relative_path> ──
# Returns 0 iff the path is tracked by git AND has no unstaged
# modifications. Both conditions are required because:
#   - Tracked-only without clean check would let a half-edited spec
#     pass the gate.
#   - Clean-only without tracked check would pass for nonexistent
#     files (git diff --quiet returns 0 for absent paths).
is_path_committed_clean() {
  local repo="$1"
  local rel_path="$2"
  [ -z "$rel_path" ] && return 1
  [ -d "$repo/.git" ] || [ -f "$repo/.git" ] || return 1
  (cd "$repo" && git ls-files --error-unmatch -- "$rel_path" >/dev/null 2>&1) || return 1
  (cd "$repo" && git diff --quiet --exit-code -- "$rel_path" >/dev/null 2>&1) || return 1
  return 0
}

# ── is_spec_committed_clean <repo> <state_file> ──
# Reads evidence.spec_path from state and delegates to
# is_path_committed_clean. Returns 1 if spec_path is empty/null.
is_spec_committed_clean() {
  local repo="$1"
  local state_file="$2"
  [ -f "$state_file" ] || return 1
  local spec_path
  spec_path=$(jq -r '.evidence.spec_path // ""' "$state_file" 2>/dev/null || echo "")
  [ -z "$spec_path" ] && return 1
  is_path_committed_clean "$repo" "$spec_path"
}
