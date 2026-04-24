---
type: plan
feature: socratic-review-relocation-into-brainstorm
spec: docs/superpowers/specs/2026-04-24-socratic-review-relocation-design.md
date: 2026-04-24
---

# Plan — Relocate socratic_review Into Brainstorm Gate

## Estimate

- ~150 net lines changed across ~12 files
- 1 implementation wave (no parallelization — all files are tightly coupled to the flow-phases change)
- Verification wave runs existing test suite

## Phase 1 (v0) — Working refactor

### Wave 1 — Refactor + all updates (single wave, foreground)

All changes are tightly coupled through `flow-phases.sh`. Parallelization
would create merge conflicts on the sequence of test-file updates.
Sequential in the main session.

- **1a — Refactor `socratic-review-validator.sh`** → files: `.claude/hooks/validators/socratic-review-validator.sh`
  - Change input from `$STATE_FILE` to `$SPEC_PATH` as positional arg.
  - Read the `## Architectural Adversarial Review` section from the spec markdown.
  - Count questions via `grep -cE '^[[:space:]]*[0-9]+\.[[:space:]]+\*\*Q:\*\*'`.
  - For each question, read its body (Q + A lines) and verify length.
  - Preserve architectural-keyword regex and critical-path gating (check if spec mentions critical paths).
  - Exit codes: 0 pass, 2 block. No bypass env var inside (called as library; the caller owns bypass logic).

- **1b — Refactor `test-socratic-review-validator.sh`** → files: `.claude/hooks/test-socratic-review-validator.sh`
  - Replace JSON state fixtures with `.md` spec fixtures.
  - 5 cases: empty section → block; 2 questions → block; 3 short Qs → block; 3 long Qs w/o arch keyword (critical path) → block; 3 long Qs w/ arch keyword → pass.

- **1c — Extend `brainstorm-validator.sh`** → files: `.claude/hooks/validators/brainstorm-validator.sh`
  - After Layer H + J checks, when critical paths are referenced, invoke socratic-review-validator with the spec path.
  - Capture its output and exit code; append its errors to `$ERRORS` on exit 2.

- **1d — Extend `test-brainstorm-validator.sh`** → files: `.claude/hooks/test-brainstorm-validator.sh`
  - Add case: spec with critical paths + no Architectural Adversarial Review section → blocks.
  - Add case: spec with critical paths + valid Architectural Adversarial Review → passes.

- **1e — Remove `socratic_review` from `flow-phases.sh`** → files: `.claude/hooks/lib/flow-phases.sh`
  - FULL back to 8 entries; DEBUG back to 7. Both *_SHORT arrays sync.

- **1f — `phase-advance.sh` usage banner** → files: `.claude/hooks/phase-advance.sh`
  - Restore 8-phase and 7-phase banner text.

- **1g — `user-prompt-state.sh` clean up** → files: `.claude/hooks/user-prompt-state.sh`
  - PHASES and PHASE_SHORT arrays: remove `socratic_review` and `socratic`.
  - Debug-flow late-phase case: remove `socratic_review|` prefix.
  - NEXT action case: remove `socratic_review)` arm.
  - Narration guard case: remove `socratic_review|` prefix.
  - Initial placeholder: remove `socratic` from timeline string.

- **1h — `workflow-status-line.sh` clean up** → files: `.claude/hooks/workflow-status-line.sh`
  - Same mechanical cleanup as 1g.

- **1i — `test-phase-advance.sh` walk-back to 8 phases** → files: `.claude/hooks/test-phase-advance.sh`
  - Remove `socratic_review` from the PHASES array in Test 11.
  - Remove the `socratic_review)` case in the evidence setup.
  - Assert `HISTORY_LEN -eq 8`.

- **1j — `test-enforcement-layers.sh` walk-back to 8 phases** → files: `.claude/hooks/test-enforcement-layers.sh`
  - Same mechanical walk-back.
  - Remove `socratic_review` from the fabricated history in Test 1.2.

- **1k — Update `CLAUDE.md` 14-shortcuts table** → files: `CLAUDE.md`
  - Remove "verification → capture without adversarial review" row.
  - Update the H row to note that it now includes architectural review.

- **1l — Update `.claude/README.md` evidence matrix** → files: `.claude/README.md`
  - Remove `socratic_review` row.
  - Update `brainstorming` row to note the new required section.
  - Remove `socratic_review` from the current_phase enum comment.

- **1m — Regenerate manifest** (via `make manifest`).

### Wave 2 — Verification

- **2a** — Run every test script:
  - `test-flow-phases.sh` — expect 15/15 (fixture assertions referencing phase arrays need lengths updated to 8 and 7).
  - `test-brainstorm-validator.sh` — expect 13/13 (11 existing + 2 new for adversarial-review section).
  - `test-phase-advance-entry.sh` — expect 5/5.
  - `test-phase-advance.sh` — expect 21/21 (walk length 8).
  - `test-phase-transition-controller.sh` — expect 7/7.
  - `test-enforcement-layers.sh` — expect 15/15.
  - `test-socratic-review-validator.sh` — expect 5/5 (rewritten fixtures).
  - `test-ddd-boundary-check.sh` — expect 10/10 (unchanged).
  - `test-retrospective-validator.sh` — expect 8/8 (unchanged).
- **2b** — `bash -n` syntax on every modified `.sh`.
- **2c** — Backend phpunit regression: no backend changes, should be untouched.

### Wave 3 — Capture + retro + finalize

- Write execution log.
- Present retrospective (architectural content required by Layer I: mention the DDD-of-this-refactor = moving a gate to the correct architectural location).
- Commit + push.

## Acceptance checklist

- [ ] `socratic-review-validator.sh` accepts `<spec_path>` as arg.
- [ ] `brainstorm-validator.sh` invokes it when critical paths referenced.
- [ ] `socratic_review` removed from both FULL_PHASES and DEBUG_PHASES.
- [ ] All tests green (97 total harness tests).
- [ ] CLAUDE.md + README documents reflect new layout.
- [ ] Execution log + retrospective + push.

## Non-goals

- Migration path for active sessions that have `evidence.socratic_questions` set (moot — any active session's state is ephemeral after session reset).
- Dual-run compatibility during transition.
- Changes to Layer I, F, or J beyond doc updates.
