# Spec — I8: Two Recurring Workflow Bugs

**Date:** 2026-04-29
**Branch:** `claude/review-workflow-improvements-x78Zp`
**Type:** code change (full flow) — bug fixes

## Problem

Two bugs in `user-prompt-state.sh` cause friction in every full-flow
interaction reaching `finalize`:

**Bug 1 — Premature auto-reset of `branch_strategy`.** Lines 157-188
of `user-prompt-state.sh` auto-reset the entire interaction state when
`current_phase=finalize` AND `branch_strategy` is set. The intent is
to clean up after a completed flow. The bug: the auto-reset fires on
ANY user prompt during finalize, including prompts that arrive
*before* `git push` succeeds (e.g., the stop-hook emits "uncommitted
files", the user replies "ok", the hook resets, the next push fails
because `branch_strategy` is now null). Documented in 4 consecutive
Hitos (Layer K, N+S, Sync, Layer Agent, Phase A).

**Bug 2 — `user_approved` revert false-positive.** The
`phase-transition-controller.sh` reverts `user_approved` when
`OLD=false → NEW=true AND command contains "user_approved = true"`.
This is correct in principle but the SNAPSHOT_FILE only updates in
PostToolUse. The `user-prompt-state.sh` (UserPromptSubmit) mutates
state when the user types "Apruebo" but never updates the snapshot.
Result: a subsequent bash whose `jq` happens to contain
`user_approved = true` (even redundantly) sees stale `OLD=false`,
matches both conditions, and reverts. Documented in 3 Hitos.

## Approach Chosen

**A — Fix both at the source (`user-prompt-state.sh`).**

1. **Bug 1:** before the auto-reset, verify `HEAD == @{upstream}`
   (i.e., the branch's latest commit is already pushed). If HEAD is
   ahead of upstream (commits not yet pushed), defer the reset. The
   reset only fires when the work is truly complete.

2. **Bug 2:** after `user-prompt-state.sh` mutates state (sets
   `user_approved=true` or runs the auto-reset), copy the new
   STATE_FILE to `/tmp/ptc-state-snapshot.json` (the controller's
   snapshot path). The next PostToolUse run in the controller sees
   the updated OLD value and correctly identifies that no manipulation
   occurred.

Both fixes localize in the writer (`user-prompt-state.sh`) without
touching the consumer (`phase-transition-controller.sh`). The
controller's defense against direct manipulation remains intact;
only the false-positive condition is closed.

## Alternatives Rejected

**B — Remove the controller's `user_approved` revert entirely.**

- Rejected: scope expansion. The defense exists for a reason
  (preventing direct manipulation); removing it deserves its own
  Hito with explicit Layer K analysis. False-positives are fixable
  without removing the gate.

**C — Remove the auto-reset entirely.**

- Rejected: the auto-reset has legitimate value (cleanup between
  interactions). Removing it forces manual reclassification every
  time. Worse trade-off than fixing the trigger condition.

**D — Tighten the controller's regex** (e.g., require the command
to set `user_approved` AND nothing else).

- Rejected: heuristic detection in shell is brittle. Fixing the
  snapshot-sync issue at the source is mechanically simpler and
  semantically correct (the snapshot should reflect all writes,
  including hook writes).

## 4-Test (honest, on the maximal version)

| Test | Verdict | Evidence |
|---|---|---|
| 1. LLM no aplica espontáneamente | ✓ | The model has hit both bugs in 7 cumulative occurrences without auto-correcting. Each Hito repeats the same fixes manually. |
| 2. Fase correcta | ✓ | At the source (the writer hook). Fixes both bugs by construction. Coste de corregir: ~10 lines. Coste de no hacerlo: friction in every future interaction. |
| 3. Coste/valor | ✓ | ~10 lines total in one file. Eliminates two recurring friction sources documented across the entire branch. ROI: every future Hito runs cleaner. |
| 4. Backed by source | ✓ | Execution logs 2026-04-28-layer-k, -norms-safeguards, -sync, -agent-prompt, -harness-consolidation, -remove-deviation, -ULS-phase-a all document the bugs as recurring follow-ups. |

Pass on all four.

## Existing Functionality Inventory

| Element | Decision | Justification |
|---|---|---|
| `.claude/hooks/user-prompt-state.sh` (~370 lines) | Transform | Two surgical insertions: HEAD-vs-upstream check before auto-reset; snapshot-sync after mutations |
| `.claude/hooks/phase-transition-controller.sh` (~110 lines) | Omit | Defense logic intact; bug was upstream snapshot staleness |
| `/tmp/ptc-state-snapshot.json` | Omit (cross-write target) | Controller writes; this hook now also writes the same path. Documented as shared file. |

## Omission Decisions

- **Tests:** the existing harness (`test-phase-advance.sh`,
  `test-freshness.sh`, `test-enforcement-layers.sh`) doesn't cover
  these specific scenarios because they involve UserPromptSubmit hook
  side effects. Building a fixture that simulates a user prompt then
  a subsequent bash + PostToolUse is complex in shell. Smoke test
  via this very interaction's finalize flow validates both fixes;
  unit fixtures deferred as follow-up.
- **Documentation in CLAUDE.md:** these are bug fixes, not new
  capabilities. CLAUDE.md text doesn't change.
- **Decision log entry:** no architectural decision (bug fix),
  no entry needed.

## Norms

- The auto-reset in `user-prompt-state.sh` **must** verify that the
  branch is in sync with upstream (HEAD pushed) before clearing
  evidence; **shall never** reset when commits are pending push.
- After any state mutation, `user-prompt-state.sh` **must** sync
  `/tmp/ptc-state-snapshot.json` to match `session-state.json`;
  the snapshot **shall always** reflect the most recent legitimate
  write.
- The `phase-transition-controller.sh` **must not** be modified —
  its defense logic is correct; only the upstream snapshot staleness
  was the bug.
- The fixes **shall never** weaken existing defenses (controller
  still reverts genuine direct manipulation when OLD=false at
  snapshot time AND the command contains the pattern).

## Safeguards

| Risk | Mitigation |
|------|------------|
| `git rev-parse @{upstream}` fails when no upstream is configured (fresh branch with no remote) | Wrap in `2>/dev/null \|\| echo ""` and treat empty as "not configured → assume not pushed → don't reset". Conservative default. |
| `cp` to `/tmp/ptc-state-snapshot.json` fails (e.g., race with controller writing simultaneously) | Use atomic temp-file + mv pattern (precedent in repo: every jq write does this). Failure leaves snapshot in previous valid state — degrades to current behavior. |
| HEAD-upstream check breaks when remote is unreachable | `git rev-parse @{upstream}` works against the local cached ref; doesn't need network. Documented constraint. |
| Snapshot sync introduces a new write race condition between hook and controller | Both writers use atomic mv; the file is read by the controller in PreToolUse-equivalent (PostToolUse start). The window is tiny and either order is correct: if hook writes after controller reads, next PostToolUse picks up the change. |
| The fixes mask a regression in some other invariant we didn't account for | Smoke test in this interaction's finalize cycle: after fix, push without manual `branch_strategy` re-set; verify `user_approved` survives jq commands containing the pattern. If smoke passes, fixes are correct. |
| Future bugs in `user-prompt-state.sh` snapshot logic could corrupt the snapshot file | The hook always overwrites the snapshot after a successful state mutation, so a corrupted snapshot from a partial write self-heals on the next user prompt. |

## Implementation outline

1. **Wave 1 — Read current `user-prompt-state.sh`** to identify exact
   insertion points for both fixes.
2. **Wave 2 — Bug 1 fix.** Insert HEAD-vs-upstream check at the start
   of the `if [ "$FLOW_TYPE" = "full" ] && [ "$CURRENT_PHASE" = "finalize" ]` block.
3. **Wave 3 — Bug 2 fix.** After `user_approved=true` mutation
   (lines 75 and 84) and after the auto-reset (line 185), `cp
   $STATE_FILE /tmp/ptc-state-snapshot.json`.
4. **Wave 4 — Verify.**
   - `bash -n` syntax check.
   - All 31 existing tests still pass.
   - Smoke during this interaction's finalize: push without manual
     branch_strategy re-set; verify success.
   - Smoke: sequence "user approve → bash with redundant
     `user_approved=true` text → user_approved still true".

## Verification plan

- 31 existing tests pass.
- `bash -n` clean.
- Real-world smoke: this interaction's finalize cycle works without
  the manual workarounds we've been forced to apply.
