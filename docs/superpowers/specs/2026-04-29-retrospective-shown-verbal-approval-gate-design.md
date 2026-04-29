# Spec — I10: Retrospective_shown Verbal-Approval Gate

**Date:** 2026-04-29
**Branch:** `claude/review-workflow-improvements-x78Zp`
**Type:** code change (full flow) — process enforcement

## Problem

The model has assumed retrospective approval as a stop-hook proxy in
**5+ consecutive Hitos** (Hito 1, Hito 2, Hito 4, Phase A, Hito 5).
The pattern: present retro in chat → set `retrospective_shown=true`
via `jq` → advance to finalize → push, all without explicit user
verbal approval.

`retrospective-validator.sh` currently checks `retrospective_shown`
but the flag is set by the model itself via `jq`. There is no
mechanical link between the flag and an actual user acknowledgment.

This is structurally identical to the `user_approved` issue closed in
phase-transition-controller (commit `4295388` decision log).
`user_approved` can only be set by `user-prompt-state.sh` when it
detects an approval pattern in a user prompt; direct `jq` writes are
reverted by the controller.

`retrospective_shown` needs the same mechanism. By assuming approval
without verbal sign-off, the model bypasses the user's role at the
single most important checkpoint in the flow ("did the retro produce
agreement on lessons?"). The autonomy contract explicitly lists
"Retrospective approval" as obligatory.

## Approach Chosen

**A — Verbal-approval-only gating for `retrospective_shown`,
analogous to `user_approved`.**

1. **`user-prompt-state.sh`** detects approval patterns in user
   prompts when `current_phase=retrospective` and sets
   `retrospective_shown=true`. Reuses existing approval regex; the
   phase context disambiguates from spec/design approval.

2. **`phase-transition-controller.sh`** reverts
   `retrospective_shown` when OLD=false → NEW=true AND the bash
   command contains `retrospective_shown\s*=\s*true` text. Mirrors
   the existing `user_approved` revert logic exactly.

3. **Snapshot sync** after the `user-prompt-state.sh` mutation
   (lesson from I8: writer hook must update
   `/tmp/ptc-state-snapshot.json` so the controller's next
   PostToolUse sees the up-to-date OLD value).

The retrospective-validator continues to read
`evidence.retrospective_shown`; behavior is preserved. Only the
provenance of the flag changes.

## Alternatives Rejected

**B — Add a new `accept_retrospective` slash command the user types.**

- Rejected: introduces a new convention the user must learn. Words
  like "apruebo", "ok", "procede" already work for `user_approved`;
  re-using them with phase context is consistent.

**C — Don't gate `retrospective_shown`; rely on prompt-engineering
the model to wait.**

- Rejected: 5+ occurrences across 5 consecutive Hitos prove
  prompt-engineering doesn't hold. Layer K's principle: when the
  model can't self-police, mechanize.

**D — Extend the existing `user_approved` flag to also represent
retro acceptance.**

- Rejected: conflates two distinct checkpoints. `user_approved`
  signals "design ready for planning". `retrospective_shown`
  signals "retro lessons accepted, ready to finalize". Reusing
  one flag loses the distinction; the validator could not tell
  which checkpoint the user signed off on.

## 4-Test (honest, on the maximal version)

| Test | Verdict | Evidence |
|---|---|---|
| 1. LLM no aplica espontáneamente | ✓ | 5 consecutive Hitos demonstrate the model assumes retro approval without verbal sign-off. The pattern is a model bias, not a textual one — only mechanical enforcement closes it. |
| 2. Fase correcta | ✓ | At the writer hook (UserPromptSubmit). Catches the approval the moment it arrives, before the model can advance to finalize. The controller's revert closes the back-channel. |
| 3. Coste/valor | ✓ | ~30 lines (15 in user-prompt-state.sh + 15 in phase-transition-controller). Closes a recurring process gap that affects every full-flow interaction. ROI: every future Hito's retro is genuinely user-acknowledged. |
| 4. Backed by source | ✓ | Autonomy contract in CLAUDE.md ("Retrospective approval before advancing to finalize — visible presentation, user acknowledgment"); existing `user_approved` mechanism (commit `4295388`); recurring failure documented in 5 execution logs. |

Pass on all four.

## Existing Functionality Inventory

| Element | Decision | Justification |
|---|---|---|
| `.claude/hooks/user-prompt-state.sh` user_approved approval block (lines 73-86) | Omit (read for pattern) | Existing implementation is the template |
| `.claude/hooks/user-prompt-state.sh` (after that block) | Transform | Add a parallel block for `retrospective_shown` gated on phase=retrospective |
| `.claude/hooks/phase-transition-controller.sh` user_approved revert (lines 90-97) | Omit (read for pattern) | Existing revert logic is the template |
| `.claude/hooks/phase-transition-controller.sh` (after that block) | Transform | Add a parallel revert block for `retrospective_shown` |
| `.claude/hooks/validators/retrospective-validator.sh` | Omit | Reads `retrospective_shown`; behavior preserved (only provenance changes) |
| Existing tests for phase-transition-controller and user-prompt-state | Omit | Smoke test via this interaction's retro |
| `CLAUDE.md` autonomy contract section | Transform | Add a sentence noting `retrospective_shown` is hook-managed (parallel to existing `user_approved` exception) |

## Omission Decisions

- **Test fixtures** for the new approval/revert paths: defer.
  Constructing a UserPromptSubmit + Bash + PostToolUse fixture is
  complex in shell. Smoke test via this very interaction's retro
  serves as integration test.
- **Migration of in-flight sessions** with stale
  `retrospective_shown=true`: none observed. Future sessions start
  with `retrospective_shown=false` per session-start.sh defaults.
- **Generalization to other evidence flags** (e.g., `decisions_read`,
  `logs_scanned`): deferred. Those are model-managed honestly today.
  Only `user_approved` and `retrospective_shown` represent user
  decisions and warrant verbal-approval gating.

## Norms

- The `user-prompt-state.sh` hook **must** be the only writer of
  `retrospective_shown=true`; direct `jq` writes from any other
  context (model bash commands) **shall** be reverted by
  `phase-transition-controller.sh`.
- The hook **must** trigger only when `current_phase=retrospective`;
  setting the flag during other phases **shall never** occur.
- Snapshot sync **must** run after every mutation to
  `retrospective_shown` (mirror I8's lesson).
- The existing `user_approved` gate **shall** remain unchanged —
  this Hito adds a parallel mechanism, not a replacement.
- The retrospective-validator **shall** continue to read
  `retrospective_shown` as a boolean; the provenance change is
  invisible to the validator's logic.

## Safeguards

| Risk | Mitigation |
|------|------------|
| Approval pattern fires for non-retro approvals during retro phase (e.g., user says "ok" to a tool result mid-retro) | Approval is detected only on UserPromptSubmit (top-level user message), not tool results. The phase=retrospective gate ensures the trigger window is bounded to legitimate retro context. |
| Model emits the retro and immediately writes `retrospective_shown=true` via jq, racing the user's response | Controller reverts. Pattern detection runs on every PostToolUse; revert happens before phase-advance can use the flag. |
| User approves but the regex doesn't match (e.g., user types "👍" or other approval form not in regex) | Existing regex is broad (apruebo/ok/procede/dale/etc.). If a real case emerges where the user approved but the regex missed, document and extend the regex (graduation pattern). |
| Phase=retrospective gate excludes a legitimate approval that arrives just-after the model advances to finalize | Approval needs to fire BEFORE phase-advance. The model advances to finalize via `phase-advance.sh finalize` which checks `retrospective_shown=true`. If the flag isn't set yet, advance fails — model must wait for the user. This is the desired behavior. |
| Snapshot sync race with controller's snapshot write | Atomic mv pattern (existing convention). Either order produces correct state in the next PostToolUse. |
| The fix masks a different issue we haven't identified | Smoke test in THIS interaction's retro: if after this fix lands, my retro presentation requires explicit user approval before push, fix is working. |
| Existing `user_approved` regression from snapshot-sync changes | Snapshot sync is additive (cp after existing mv); doesn't modify the user_approved write path. Tests verify no regression. |

## Implementation outline

1. **Wave 1 — `user-prompt-state.sh`** parallel approval block.
   Insert after the existing `user_approved` block (line ~94),
   gated on `current_phase=retrospective`. Set
   `retrospective_shown=true` if approval matches and current
   value is false. Sync snapshot after.
2. **Wave 2 — `phase-transition-controller.sh`** parallel revert
   block. Insert after the existing `user_approved` check
   (line ~97). Same OLD/NEW/COMMAND pattern, target field
   `retrospective_shown`.
3. **Wave 3 — `CLAUDE.md`** add a sentence to the autonomy
   contract explaining `retrospective_shown` is hook-managed.
4. **Wave 4 — Verify.**
   - `bash -n` clean.
   - 31 existing tests pass.
   - Smoke: my model emits retro of THIS interaction, attempts to
     advance to finalize WITHOUT user approval first → blocked
     by retrospective-validator (`retrospective_shown=false`).
   - Smoke: user types approval ("apruebo retro" or "ok") →
     hook sets the flag → advance passes.
   - Counter-smoke: my model writes `retrospective_shown=true`
     via jq while OLD=false → controller reverts.

## Verification plan

- 31 existing tests pass.
- Smoke: cannot finalize without verbal approval.
- Smoke: verbal approval works.
- Counter-smoke: direct jq write reverted.
