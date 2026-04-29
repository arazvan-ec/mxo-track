---
type: feature
tags: [harness, validator, brainstorm, 4-test, anti-reduction, tdd]
files_touched:
  - .claude/hooks/validators/brainstorm-validator.sh
  - .claude/hooks/test-brainstorm-validator.sh
  - CLAUDE.md
  - docs/superpowers/specs/2026-04-28-anti-reduction-validator-design.md
  - docs/superpowers/plans/2026-04-28-anti-reduction-validator.md
  - docs/decisions/log.md
patterns: [conditional-hard-gate, positive-signal-keyword-check, recursive-validator-application]
outcome: success
outcome_verified_at: 2026-04-28
regressions_later: []
pr_number: null
estimated_lines: 150
actual_lines: 220
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-04-28 — Layer K (Anti-Reduction Validator)

**Type:** feature (harness — workflow gate)
**Branch:** `claude/review-workflow-improvements-x78Zp`
**Backlog ref:** Propuesta #0 del análisis SPDD vs CLAUDE.md (2026-04-28)
**Spec:** `docs/superpowers/specs/2026-04-28-anti-reduction-validator-design.md`
**Plan:** `docs/superpowers/plans/2026-04-28-anti-reduction-validator.md`

## Summary

Added Layer K (HARD gate) to `brainstorm-validator.sh`. When a spec contains
reduction markers (the closed list documented in CLAUDE.md) outside fenced
code blocks, the validator requires a `## Maximal Version Considered`
section with an "Independent superiority" bullet defending the proposal on
grounds other than cost. The bullet must contain at least one design-quality
keyword (pattern, garantiz/ensure, drift, consistency, boundary, correctness,
alignment, prevent, atomic, decoupl, encapsul, etc.); cost-only language
fails the check.

The mechanical gate complements commit `d3ce7c5` (textual rule in CLAUDE.md
forbidding "rewrite to pass" concessions) — the rule alone was insufficient
because the recoil-to-smaller-scope bias originates in the model's priors,
not in CLAUDE.md text.

## Origin (motivating incident)

In the same conversation that produced this layer, the model proposed
the "Ubiquitous Language System" in maximal form, applied the 4-test,
saw Test 3 (cost/value) was tight, and scaled the proposal down to a
trimmed version instead of accepting "fails Test 3 → discard". The user
identified the bias and demanded the maximal version be re-presented.
This was the third documented instance (after Layers I+J removal in
commits `231f951` and `9782fb4`) of the model gaming a quality gate by
recoiling instead of discarding.

## Approach Chosen

**A — Trigger-based conditional HARD gate** mirroring Layer H pattern
(commit `ad11cc4`):

1. Strip fenced code blocks via awk (`/^```/{f=!f} !f`).
2. Scan body for marker regex (closed list, case-insensitive).
3. If markers present → require `## Maximal Version Considered`
   section. Block (exit 2) if absent.
4. If section present → extract the multiline "Independent
   superiority" bullet (continuation lines included via state
   machine in awk).
5. Verify the bullet contains at least one positive-signal keyword.
   Block if not.

## Alternatives Rejected (in spec)

- **B — Always-required section.** Failed Test 3 of the 4-test:
  ceremony for the majority of specs that do not contemplate
  reduction. Same failure mode that removed Layers I and J.
- **C — Warning + conditional HARD.** Failed because the warning
  path is itself the concession this layer aims to prevent.
  Accepting C would replicate the bias.

## Changes

| File | Change |
|------|--------|
| `.claude/hooks/validators/brainstorm-validator.sh` | +37 lines: Layer K block (marker detection, section extraction, multiline bullet capture, positive-signal check) |
| `.claude/hooks/test-brainstorm-validator.sh` | +99 lines: 4 TDD fixtures (K1 baseline, K2 missing section, K3 cost-only bullet, K4 valid non-cost bullet) + `run_k_scenario` helper |
| `CLAUDE.md` | +1 row in "Enforcement gates" table documenting Layer K |
| `docs/superpowers/specs/2026-04-28-anti-reduction-validator-design.md` | +21 lines: section added when smoke test of validator against the spec itself revealed a recursive true-positive (the spec narrates a reduction event while explaining the motivating incident) |
| `docs/superpowers/plans/2026-04-28-anti-reduction-validator.md` | new — TDD plan with 3 waves |
| `docs/decisions/log.md` | +1 entry: SKIP_PHASE_EXIT_GATE bypass for `phase-transition-controller` false-positive |

Net lines: ~220 (estimate was 150; gap below).

## Verification

- `bash .claude/hooks/test-brainstorm-validator.sh` → **15/15 pass**
  (11 existing + 4 new for Layer K)
- `bash -n` syntax check on validator + test harness → clean
- `make lint-shell` → not run (shellcheck not installed in this
  sandbox; same precedent as log 2026-04-22)
- Smoke test: validator against this task's own spec → originally
  blocked (true positive on narrating a reduction event), section
  added, re-runs → exit 0

## Recursive validation moment

The smoke test of Layer K against its own spec produced a genuine
self-reference event: the spec discusses reduction as a topic and
narrates a past reduction incident. Layer K cannot distinguish
narration from proposal, so it required the spec to add
`## Maximal Version Considered`. The section was added, declaring
"this is the maximal version, no reduction was considered" and
defending the choice of Approach A over B/C on consistency grounds
(alignment with Layer H pattern). This is the correct behavior: the
gate forces every spec touching reduction language to produce the
section, regardless of whether the topic arises by proposal or
narration. The cost is one section; the value is no false negatives.

## Retrospective

### 1. Estimate accuracy

| Metric | Estimate | Actual | Delta |
|---|---|---|---|
| Files | 3 | 6 | +100% |
| Net validator lines | +35 | +37 | OK |
| Net test lines | +110 | +99 | OK (within calibration `30 + 20·N`) |
| Net CLAUDE.md lines | +6 | +1 | -83% (estimate over-budgeted) |
| Total net lines | ~150 | ~220 | +47% |

**Root cause of the file gap:** the plan listed 3 files but execution
required 6 because (a) the spec itself needed updating for the
recursive Layer K self-application, (b) decision log entry for the
phase-transition-controller bypass, and (c) plan/spec are themselves
artifacts. Future estimates: count all artifacts (spec, plan, log
entry), not only source files.

**Root cause of the line gap:** the multiline-bullet awk extraction
came in iteration 2 (initial implementation extracted only first line,
which failed the smoke test against the multiline bullet in the spec).
+12 lines for the multiline state machine. Future estimates: budget
for multiline content extraction in any markdown validator.

### 2. Process gaps

- **`phase-transition-controller.sh` false positive on `user_approved`.**
  When the model issues a jq command containing the literal text
  `user_approved = true` (even redundantly, when the value was already
  true after a `user-prompt-state.sh` hook), the controller reverts to
  false. This caused the `brainstorming → planning` advance to be
  blocked despite the user having explicitly said "Apruebo A" in the
  conversation. Bypass via `SKIP_PHASE_EXIT_GATE=1` documented in
  decision log. **Lesson:** when updating evidence post-approval, do
  NOT include `user_approved` in the jq command. The user prompt hook
  is the only sanctioned writer. **Follow-up:** harden the controller
  to only revert when OLD=false → NEW=true AND the command assigns,
  not when OLD=true and the command merely repeats the assignment.

- **CLAUDE.md text fix is necessary but insufficient.** Commit
  `d3ce7c5` updated the 4-test text to forbid "rewrite to pass"
  concessions. The model nonetheless cannot self-police; Layer K is
  the mechanical complement that closes the loophole. This confirms
  the repo's stated philosophy ("LLMs do not autonomously apply
  development practices") at the meta level: even a rule about how
  to apply rules needs mechanical enforcement.

### 3. Emergent patterns

- **Conditional HARD gate with positive-signal keyword check.** Layer K
  is the second validator (after Layer H + C) using the structure:
  trigger detection → section requirement → content classification by
  closed-list keywords. If a fourth gate uses this shape, extract a
  shared helper. First occurrence as a *positive-signal* check (Layer H
  uses negative-signal "no row classified"). Pattern not graduated yet.

- **Recursive validator self-application.** The validator was tested
  against its own spec and produced a true-positive that required
  updating the spec. This is a useful smoke-test pattern: if a new
  validator can pass its own spec without modification, the validator
  may not be strict enough; if it requires a real change, the rule has
  bite. Pattern not graduated yet (single occurrence).

- **Bypass-induced lessons.** The `SKIP_PHASE_EXIT_GATE` bypass produced
  a follow-up (harden phase-transition-controller heuristic). Bypasses
  should be expected to produce follow-ups; if a bypass leaves no
  follow-up, the gate's heuristic is correct and the case was
  legitimate exception. If a bypass produces a follow-up, the heuristic
  needs tuning.

## Follow-ups

1. **Harden `phase-transition-controller.sh`** — only revert
   `user_approved` when the snapshot value differs AND the command
   assigns. Small fix (~3 lines), separate interaction.
2. **Graduation pathway for Layer K markers.** Currently a closed
   list. If execution logs show 3+ instances of a new marker term
   causing recoil, add to the regex.
3. **Backlog #1-#5** — proceed with the remaining proposals from the
   2026-04-28 SPDD analysis. Layer K is now in place to enforce
   honest 4-test application on each.
