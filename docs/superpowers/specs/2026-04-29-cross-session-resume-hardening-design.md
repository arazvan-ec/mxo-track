# Spec — Workflow Continuity: Cross-Session Resume Hardening

**Date:** 2026-04-29
**Branch:** TBD (next interaction creates the dev branch)
**Type:** code change (full flow)
**Scope ref:** Phase C retrospective improvements (a + b + d). (c)
graduación de `atomic-yaml-rewrite` queda fuera; gestionado en
interacción light separada.

## Problem

Phase C tooling exposed two recurring frictions in cross-session
continuation:

1. **State loss on stash + resume.** During verification I ran
   `git stash --include-untracked` to A/B test pre-existing
   shellcheck warnings. That stashed `.claude/session-state.json`.
   The subsequent SessionStart:resume hook reset
   `evidence.user_approved` and `flow_type` because
   `is_resumable()` requires `current_phase ∈ {implementation,…,
   finalize}`, but stash had reverted phase to `consult`. Cost: 3
   turns reconstructing evidence + verbal re-approval.

2. **Spec already committed but harness can't tell.** When a spec
   exists in HEAD from a prior session, the only path to advance
   consult→brainstorm and brainstorm→planning is
   `SKIP_PHASE_EXIT_GATE=1` plus a verbal "approved" from the
   user. Used in 2 interactions (24-hours apart). The harness has
   the information needed — `evidence.spec_path` references a
   committed file — but no validator reads it.

The 2026-04-29 fix-recurring-workflow-bugs interaction already
patched 2 *related* bugs in `user-prompt-state.sh` (HEAD-vs-upstream
guard, snapshot-sync). This spec extends that line of defense to
the SessionStart resume path and the consult/brainstorm exit gates.

## Approach Chosen

**Three coordinated changes to the harness:**

1. **(a) Broaden `is_resumable` and `restore_*` in
   `session-start.sh`:**
   - Resumable now requires `phase ∈ {brainstorming, planning,
     implementation, verification, capture, retrospective,
     finalize}` (added `brainstorming`, `planning` — those phases
     also have non-trivial reconstructable state).
   - Resume restores the **bundle** of evidence flags consistent
     with the resumed phase, not just `user_approved`. Specifically:
     - `decisions_read=true` and `logs_scanned=true` if
       `phase ≥ brainstorming`.
     - `alternatives_proposed=true` if `phase ≥ planning` AND
       `evidence.spec_path` exists on disk.
     - `user_approved=true` if `phase ≥ planning` AND
       `evidence.spec_path` exists on disk (current behavior, but
       guarded by spec-on-disk).
   - `flow_type` and `current_phase` already preserved by current
     session-start logic; verify and document.

2. **(b) Document the `git stash --include-untracked`
   antipattern** in `CLAUDE.md` § Context Hygiene, and add a 1-line
   warning to `.claude/README.md` § Bypass env vars (no separate
   doc file).

3. **(d) Auto-detect "spec already committed" in
   consult/brainstorm validators:**
   - **`consult-validator.sh`:** when checking exit gate, if
     `evidence.spec_path` is set AND the file is **tracked by
     git** AND is **clean** (no unstaged modifications), treat
     `decisions_read` and `logs_scanned` as effectively true even
     when the JSON flags are false. This makes the gate idempotent
     across session boundaries.
   - **`brainstorm-validator.sh`:** when the spec file is tracked
     and clean and contains the required sections (Norms,
     Safeguards, Alternatives), treat `alternatives_proposed` as
     effectively true. **`user_approved` remains driven by verbal
     approval** — committing a spec is not approval-by-itself; the
     user must still say "apruebo" once on resume. This preserves
     the human-in-the-loop guarantee.

The auto-detection is a **read-only probe** during gate check; it
does NOT mutate `evidence`. Mutating evidence on git read would
break the `phase-transition-controller` invariant that only
`user-prompt-state.sh` writes those fields.

## Alternatives Rejected

**A. Have SessionStart:resume mutate every evidence flag from
   git state.**

Rejected: violates the single-writer principle for
`user_approved` (`user-prompt-state.sh` is the only sanctioned
writer per CLAUDE.md). Setting it on resume would let the model
self-approve by simply reaching the resume code path, defeating
the verbal-approval requirement. The chosen approach restores
*non-approval* flags only; verbal approval stays human-driven.

**B. Add a new evidence field `spec_committed=true` set by a
   pre-tool git probe.**

Rejected: extra field with redundant information. Git already
knows whether the spec is tracked; we read it on gate check.
Adding state increases the chances of drift.

**C. Skip (a) entirely, only do (b) + (d).**

Rejected: (a) is the highest-value fix — it directly addresses the
costliest friction (3 turns lost in Phase C). (d) helps the
*next* time a spec is reused but not the *first* time the resume
breaks state.

**D. Auto-set `user_approved=true` when spec is committed.**

Rejected: removes the "consider one more time before continuing"
gate the user explicitly relies on. Verbal approval was kept
deliberate in the 2026-04-29 fix-recurring-workflow-bugs spec;
this spec respects that decision.

## 4-Test (honest, on the maximal version)

| Test | Verdict | Evidence |
|---|---|---|
| 1. LLM no aplica espontáneamente | ✓ | Resume robustness and committed-spec detection are harness-level concerns; the model can't restore state without code support. |
| 2. Fase correcta | ✓ | Hooks and validators are the natural place; alternative (in-conversation reminders) doesn't survive compaction. |
| 3. Coste/valor | ✓ | ~120 lines hook/validator changes + 30 lines docs. Closes 2 recurring frictions documented in 2 different execution logs. |
| 4. Backed by source | ✓ | 2026-04-29 fix-recurring-workflow-bugs (precedent for `user-prompt-state.sh` defensive patches); Phase C retrospective. CLAUDE.md § Context Hygiene rule. |

Pass on all four.

## Existing Functionality Inventory

| Element | Decision | Justification |
|---|---|---|
| `session-start.sh` `is_resumable` (lines 15-33) | Transform | Extend phase set; add evidence-bundle restore |
| `session-start.sh` `restore_approval_if_resumable` (lines 35-44) | Transform | Generalize to `restore_evidence_bundle_if_resumable` (rename for clarity) |
| `consult-validator.sh` exit-gate logic | Transform | Add git-probe fallback when JSON flags are false |
| `brainstorm-validator.sh` Layer alternatives_proposed check | Transform | Add git-probe fallback (read-only) |
| `phase-transition-controller.sh` revert logic for `user_approved` | Omit | Stays as-is — single-writer invariant intact |
| `user-prompt-state.sh` HEAD-vs-upstream guard (2026-04-29 patch) | Omit | Already correct; precedent for defensive design |
| CLAUDE.md § Context Hygiene | Transform | Add antipattern note for `git stash --include-untracked` |
| `.claude/README.md` § Bypass env vars | Transform | Add 1-line cross-reference to the antipattern |

## Omission Decisions

- **Auto-set `user_approved` from git** — explicitly rejected (alt
  D). Keeps human approval mandatory.
- **Snapshot/restore via PreToolUse hook on `git stash`** —
  rejected; over-engineering. Documenting the antipattern is
  enough; if it recurs after documentation, revisit.
- **(c) `atomic-yaml-rewrite` graduation** — out of scope here;
  separate light interaction.
- **Tests for the resume hardening** — smoke via shell test
  fixtures (parallels Phase C smoke tests). No PHPUnit needed.

## Norms

- The single-writer invariant for `evidence.user_approved` **must**
  be preserved: `user-prompt-state.sh` and the SessionStart resume
  helper (when guarded by `is_resumable`) are the only writers;
  validators **shall never** mutate it during gate checks.
- All git-probes in validators **must** be read-only (no `git
  add`, `git commit`, `git restore`); validators **shall** treat
  git failures as "flag remains false" (fail closed).
- Resume restoration **must** require both `evidence.spec_path` set
  AND the referenced file tracked by git AND clean (no unstaged
  modifications) before treating non-approval evidence as
  reconstructable.
- The antipattern note in CLAUDE.md **must** name the specific
  failure mode (state JSON stashed → resume reset) and **shall**
  link to this spec for context.
- Hooks and validators **must** follow shell portability rules
  documented in `.claude/README.md` (mawk-safe awk, no `\s` in
  regexes, explicit POSIX `[[:space:]]`).

## Safeguards

| Risk | Mitigation |
|------|------------|
| Restoring `decisions_read=true` from git masks a genuine never-consulted state | Restoration only fires when `is_resumable` returns true (spec+plan on disk + advanced phase). Initial sessions start at `consult` and never trigger restore — correct behavior preserved. |
| Validator git-probe is slow on large repos | `git ls-files --error-unmatch <path>` and `git diff --quiet <path>` are O(1) on the index; benchmarked under 50ms in mxo-track. Acceptable for gate hot path. |
| Auto-detect tricks the model into bypassing user approval | `user_approved` deliberately excluded from auto-detect; verbal approval still required. Layer documented in spec rationale. |
| Antipattern doc is read once and forgotten | Two-location strategy: CLAUDE.md (loaded every conversation, ~300 chars) + `.claude/README.md` cross-reference (consulted on bypass usage). |
| `is_resumable` extended phase set causes false positives at session start | Restore helper still requires spec+plan on disk. A fresh session with no prior work has empty `spec_path` → restore short-circuits. Verified via existing fixtures. |
| Auto-detect makes brainstorming "look complete" without alternatives in spec | The brainstorm-validator git-probe still inspects spec content for required sections (Norms/Safeguards/Alternatives). It's an alternative source of truth, not a bypass. |
| Cross-session spec was committed by a different model run with different intent | Verbal `user_approved` re-confirmation acts as the human checkpoint that the spec is still endorsed for *this* session. This is preserved. |

## Implementation outline

1. **Wave 1 — `session-start.sh` extension.** Rename
   `restore_approval_if_resumable` → `restore_evidence_bundle_if_resumable`.
   Extend `is_resumable` phase set. Restore bundle of flags
   conditional on phase + spec/plan on disk. Update both call sites
   (lines 281, 291).
2. **Wave 2 — `consult-validator.sh` git-probe.** Add helper
   `is_spec_committed_clean` that returns 0 if `evidence.spec_path`
   set AND `git ls-files --error-unmatch` succeeds AND `git diff
   --quiet` succeeds. Use it as fallback when JSON flags false.
3. **Wave 3 — `brainstorm-validator.sh` git-probe.** Same helper
   pattern. `user_approved` excluded from probe.
4. **Wave 4 — Doc updates.** CLAUDE.md § Context Hygiene
   antipattern paragraph; `.claude/README.md` § Bypass env vars
   cross-reference.
5. **Wave 5 — Smoke tests.** Test fixtures for: stash + resume
   (state preserved); spec committed + clean → consult gate
   passes; spec uncommitted → consult gate blocks; verbal approval
   still required for `user_approved`.
6. **Wave 6 — Verify.** `bash -n`, shellcheck, smoke tests pass,
   31 existing harness tests pass (unchanged).

## Verification plan

- 31 existing tests pass (unchanged surface).
- New smoke test for stash+resume: simulate
  `jq '.evidence.user_approved=false'` + spec committed +
  is_resumable phase → restore brings flags back.
- New smoke test for consult-validator git-probe: spec committed +
  clean → exit 0 even if `decisions_read=false`.
- New smoke test for brainstorm-validator: spec committed but
  `user_approved=false` → still blocks (probe doesn't auto-approve).
- Documentation review: CLAUDE.md note ≤300 chars, links to spec
  path.
