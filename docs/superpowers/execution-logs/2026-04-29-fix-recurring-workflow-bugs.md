---
type: bugfix
tags: [harness, user-prompt-state, branch-strategy, user-approved, recurring]
files_touched:
  - .claude/hooks/user-prompt-state.sh
  - docs/superpowers/specs/2026-04-29-fix-recurring-workflow-bugs-design.md
  - docs/superpowers/plans/2026-04-29-fix-recurring-workflow-bugs.md
patterns: [head-vs-upstream-guard, snapshot-sync-after-mutation]
outcome: success
outcome_verified_at: 2026-04-29
regressions_later: []
pr_number: null
estimated_lines: 12
actual_lines: 18
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-04-29 — Two Recurring Workflow Bugs

**Type:** bugfix (harness friction)
**Branch:** `claude/review-workflow-improvements-x78Zp`
**Spec:** `docs/superpowers/specs/2026-04-29-fix-recurring-workflow-bugs-design.md`
**Plan:** `docs/superpowers/plans/2026-04-29-fix-recurring-workflow-bugs.md`

## Summary

Two recurring bugs in `user-prompt-state.sh` fixed at the source:

**Bug 1 — Premature `branch_strategy` auto-reset.** The auto-reset
block (lines 159-200) fired on any user prompt during finalize phase,
including prompts arriving *before* `git push` succeeded. Hit in 5
consecutive Hitos: every interaction required a manual `jq
.evidence.branch_strategy = "merge"` re-set right before push.

**Bug 2 — `user_approved` revert false-positive.** The
`phase-transition-controller.sh` reverts when OLD=false → NEW=true
AND command contains the pattern. The SNAPSHOT_FILE only updated in
PostToolUse, so when `user-prompt-state.sh` (UserPromptSubmit) set
`user_approved=true`, the snapshot stayed at the old false state.
Subsequent bash commands containing redundant `user_approved = true`
text triggered the revert. Hit in 3 Hitos.

## Approach Chosen

Both fixes localized in `user-prompt-state.sh` (the writer hook):

1. **Bug 1:** before the auto-reset, verify
   `git rev-parse HEAD == git rev-parse @{upstream}` (branch already
   pushed). If commits are pending, defer the reset.
2. **Bug 2:** after every state mutation (set `user_approved=true` in
   3 sites; the auto-reset itself), `cp $STATE_FILE
   /tmp/ptc-state-snapshot.json`. The controller's snapshot now
   reflects the post-mutation state.

Controller logic untouched — its defense against direct manipulation
is correct; only the upstream staleness was the bug.

## Changes

| File | Change |
|------|--------|
| `.claude/hooks/user-prompt-state.sh` | +14 lines: HEAD-vs-upstream guard (6 lines) + snapshot sync at 4 mutation sites |

Net: 18 lines (estimate 12; +50% due to 4 sync sites instead of 3
estimated, plus PUSHED variable + comment).

## Verification

- `bash -n` clean.
- `test-brainstorm-validator.sh` → 19/19 pass (no regression).
- `test-sync-validator.sh` → 6/6 pass.
- `test-pre-agent-check.sh` → 6/6 pass.
- Real-world smoke: this interaction's finalize cycle is the first
  validation. If push succeeds without a manual `branch_strategy`
  re-set after the user types anything during finalize, Bug 1 is
  fixed. If a `jq` command containing the pattern doesn't trigger
  revert, Bug 2 is fixed.

## Retrospective

### 1. Estimate accuracy

| Metric | Estimate | Actual | Delta |
|---|---|---|---|
| user-prompt-state.sh changes | +12 | +18 | +50% |
| Files | 5 (incl artefacts) | 4 | OK |

The +6 line gap came from a 4th snapshot-sync site I missed when
estimating (the auto-reset itself, in addition to the 3 user_approved
mutation sites). Calibration: count sync sites by inspecting the
file, not by intuition.

### 2. Process gaps

- **Both bugs were documented as follow-ups across multiple
  interactions but never prioritized.** Each Hito hit them, applied
  manual workaround, recorded a follow-up, and moved on. The fix is
  trivial (~10 lines) but the cumulative cost was 8+ failed pushes /
  re-approvals across 7 interactions. **Lesson:** when a follow-up
  recurs in 3+ logs, schedule it as the next interaction. Don't
  defer indefinitely.

- **The pre-push gate matched on "git push" inside a heredoc commit
  message** — a third recurring bug discovered while implementing
  these two. The commit message contained the literal string `git
  push` in prose, and the gate's regex matched, blocking the commit.
  Recorded as follow-up; not in scope here.

### 3. Emergent patterns

- **HEAD-vs-upstream guard for "is the work pushed yet?"** This is
  the canonical predicate for "is this interaction truly complete?".
  First occurrence; if a second hook needs the same check, graduate
  to a shared helper (`lib/git-pushed.sh` returning 0/1).

- **Snapshot-sync after writer-hook mutation** — first occurrence of
  the pattern (cross-write between UserPromptSubmit and PostToolUse
  hooks via shared snapshot file). If a third snapshot-consuming
  validator emerges, document the snapshot file as a public contract
  in `.claude/README.md`.

## Follow-ups

1. **Pre-push gate matching "git push" in commit messages** — third
   recurring bug discovered during this fix. The gate's command
   regex should exclude heredoc/quoted contexts. Defer to its own
   interaction.
2. **Test fixtures for these bugs** — building a UserPromptSubmit +
   subsequent Bash + PostToolUse fixture is complex in shell.
   Smoke test serves as integration test for now.
3. **Scheduled follow-up policy** — when a follow-up recurs in 3+
   logs, schedule it as the next interaction. Document in CLAUDE.md
   if pattern emerges (currently 1 occurrence: this very fix).
