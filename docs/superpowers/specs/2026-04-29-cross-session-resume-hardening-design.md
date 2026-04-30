# Spec — Workflow Continuity v2: Cross-Session Resume + Push Gate Fix + Session-Cut Gates

**Date:** 2026-04-29
**Branch:** `claude/phase-c-tooling-Ks1mw` (continuation)
**Type:** code change (full flow)
**Scope ref:** Phase C retrospective improvements (a + b + d) **+
v2 expansion** (A1 push gate fix, B3 session-cut gates). (c)
graduación de `atomic-yaml-rewrite` queda fuera; gestionado en
interacción light separada.

> **v2 changelog (2026-04-29):** Expanded scope after observing
> two additional frictions:
> - The pre-push gate evaluates `git diff origin/main...HEAD`
>   instead of the unpushed-commits diff, blocking legitimate
>   doc-only checkpoint pushes (A1).
> - The single-session full flow allows the model to act as both
>   designer and implementer, defeating independent review (B3).

## Problem

Phase C tooling and the immediate follow-up exposed **four**
recurring frictions in cross-session continuation:

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

3. **Pre-push gate over-triggers (v2).** The gate evaluates
   `git diff origin/main...HEAD` (the whole branch vs main) when
   deciding whether the push contains "protected changes". For
   any branch that has ever held protected code, the gate
   demands full evidence — even when the **unpushed** commits
   touch only docs/specs/plans. Result: doc-only checkpoint
   pushes mid-flow are blocked, defeating the "push as savepoint"
   philosophy from CLAUDE.md.

4. **Single-session bias (v2).** The current full flow allows
   `consult → brainstorm → planning → implementation → … →
   finalize` to run end-to-end in one session. This means the
   same model acts as designer, planner, implementer, and
   reviewer with continuous context. After N turns defending a
   design, confirmation bias becomes load-bearing: the model
   "knows" the plan is correct because it wrote it. Independent
   review is impossible without a fresh session boundary at the
   highest-risk transitions.

The 2026-04-29 fix-recurring-workflow-bugs interaction already
patched 2 *related* bugs in `user-prompt-state.sh` (HEAD-vs-upstream
guard, snapshot-sync). This spec extends that line of defense to
the SessionStart resume path, the consult/brainstorm exit gates,
**the pre-push gate's diff source, and the planning→implementation
+ implementation→finalize transitions.**

## Approach Chosen

**Five coordinated changes to the harness** (a/b/d original +
A1/B3 v2):

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

4. **(A1) Pre-push gate: evaluate unpushed-commits diff, not
   whole-branch diff.** Replace
   `git diff origin/main...HEAD` with
   `git diff @{upstream}...HEAD` (fallback to `origin/main...HEAD`
   only when no upstream exists). The gate's intent is "don't
   push protected code without flow completion" — that intent is
   preserved when only unpushed commits are evaluated. Doc-only
   checkpoint pushes pass through silently (the file
   classification logic already handles markdown/yaml as
   non-protected).

   The gate stays **HARD** for protected unpushed code; it just
   stops false-positiving on doc-only checkpoint pushes when the
   branch contains earlier protected commits.

5. **(B3) Session-cut gates at the two highest-bias transitions:**
   - **`planning → implementation` cut:** implementation cannot
     start in the same session that registered the last write to
     `evidence.plan_path`. Enforced by storing
     `evidence.plan_session_date` (set when `plan_path` is
     assigned) and comparing against `session_date` at advance
     time. Different date = different session = green light.
   - **`retrospective → finalize` cut:** finalize cannot start in
     the same session that registered the last code commit
     (commit on a `code/test`-classified file). Enforced by
     storing `evidence.last_code_commit_session_date` (updated by
     a `post-commit` hook helper or computed at validate time
     via `git log --format='%cd' -n 1 --since=<session_start>`)
     and comparing.

   Bypass: `SKIP_SESSION_CUT_GATE=1` with mandatory decision-log
   entry. Same convention as other HARD gate bypasses. Designed
   for emergency hotfix flows where independent review is
   provably waived (e.g., production rollback that the user
   explicitly authorizes within one session).

   This forces exactly two session boundaries — the ones with
   the highest bias-cost — and leaves the other phases free to
   flow continuously. Validates the user's hypothesis selectively
   per the analysis: only the two cuts where the cost of model
   confirmation bias is non-recoverable in a single window.

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

**E (A1 alt). Honor `SKIP_PRE_PUSH_GATE=1` env var.**

Rejected as primary fix: an explicit bypass becomes a permanent
escape hatch the model would learn to invoke whenever the gate
fires — including when it shouldn't. The root cause is the diff
source, not the gate's strictness. Fixing the source keeps the
gate trustworthy. (May still be added later as a documented
emergency override, but only after the diff fix lands.)

**F (A1 alt). Recognize "checkpoint commits" by message prefix
   (`wip:`, `docs:`, `chore:`).**

Rejected: trusts a string the model itself authors. The model can
spuriously prefix a code commit `wip:` to bypass the gate.
Classifying by **file content** (markdown vs code) is grounded in
git's own classification primitives and not gameable.

**G (B3 alt). New `validation` phase between `planning` and
   `implementation`.**

Rejected as the primary mechanism: adds a full phase plus its
validator to the workflow engine, multiplying surface area for
~the same effect that a single-bit session-date check delivers.
The session cut already FORCES the model to read spec/plan with
fresh context — the "validation" content emerges naturally
without dedicated phase ceremony. Keep B3 as a temporal guard,
not a phase.

**H (B3 alt). Cut between every consecutive phase.**

Rejected: 8x ceremony for diminishing returns. Per the Phase C
retrospective analysis, only **two transitions** carry high enough
bias-cost to justify the overhead:
- `planning → implementation` (catches over-engineered plans
  before implementation cost is sunk).
- `retrospective → finalize` (acts as PR-review-by-fresh-eyes
  before merge).
Other transitions either have low bias-cost or actively benefit
from continuous context (verification, capture, retrospective).

## 4-Test (honest, on the maximal v2 version)

| Test | Verdict | Evidence |
|---|---|---|
| 1. LLM no aplica espontáneamente | ✓ | Resume robustness, committed-spec detection, push-gate diff source, and session-cut gates are all harness-level concerns; the model can't enforce them by convention alone. |
| 2. Fase correcta | ✓ | Hooks and validators are the natural place. Session-cut gates fire at phase-advance time (correct moment). Push-gate fix is at the gate's actual decision point, not earlier. |
| 3. Coste/valor | ✓ | ~200 lines hook/validator changes + 30 lines docs. Closes 4 recurring frictions documented across 3 different execution logs (Phase C, fix-recurring-workflow-bugs, and the current handoff session). |
| 4. Backed by source | ✓ | 2026-04-29 fix-recurring-workflow-bugs precedent; Phase C retrospective; CLAUDE.md § Context Hygiene; explicit user-driven analysis of multi-session validation in this interaction's brainstorming turn (mapping bias-cost to phase transitions). |

Pass on all four.

## Existing Functionality Inventory

| Element | Decision | Justification |
|---|---|---|
| `session-start.sh` `is_resumable` (lines 15-33) | Transform | Extend phase set; add evidence-bundle restore |
| `session-start.sh` `restore_approval_if_resumable` (lines 35-44) | Transform | Generalize to `restore_evidence_bundle_if_resumable` (rename for clarity) |
| `consult-validator.sh` exit-gate logic | Transform | Add git-probe fallback when JSON flags are false |
| `brainstorm-validator.sh` Layer alternatives_proposed check | Transform | Add git-probe fallback (read-only) |
| `pre-push-gate.sh` `has_protected_changes()` (line 62) | Transform (A1) | Replace `origin/main...HEAD` with `@{upstream}...HEAD` (fallback to old when no upstream) |
| `pre-push-gate.sh` ERRORS aggregation block (lines 232-244) | Omit | Strictness intact; only the diff source changes |
| `phase-advance.sh` validators dispatch | Transform (B3) | Add session-cut check at `planning → implementation` and `retrospective → finalize` |
| New: `validators/session-cut-validator.sh` | Add (B3) | Read `evidence.plan_session_date` / `evidence.last_code_commit_session_date` and compare against current `session_date` |
| `evidence` schema | Transform (B3) | Add `plan_session_date` (set on `plan_path` write) and `last_code_commit_session_date` (set by post-commit helper) |
| `phase-transition-controller.sh` revert logic for `user_approved` | Omit | Stays as-is — single-writer invariant intact |
| `user-prompt-state.sh` HEAD-vs-upstream guard (2026-04-29 patch) | Omit | Already correct; precedent for defensive design |
| CLAUDE.md § Context Hygiene | Transform | Add antipattern note for `git stash --include-untracked` |
| CLAUDE.md § Workflow / Autonomy Contract | Transform | Document the two session-cut gates in the appropriate section |
| `.claude/README.md` § Bypass env vars | Transform | Add `SKIP_SESSION_CUT_GATE=1` entry + cross-reference to stash antipattern |

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
- The pre-push gate **must** evaluate the unpushed-commits diff
  (`@{upstream}...HEAD`) when an upstream is configured; **shall**
  fall back to `origin/main...HEAD` only when no upstream exists
  (initial branch push). The strictness of the gate **must** stay
  HARD for protected unpushed code.
- Session-cut gates **must** be HARD by default and **shall** only
  be bypassable via `SKIP_SESSION_CUT_GATE=1` accompanied by a
  decision-log entry. The bypass **shall never** be the default
  path; it is reserved for emergency hotfix flows where the user
  explicitly waives independent review.
- `evidence.plan_session_date` and
  `evidence.last_code_commit_session_date` **must** be set
  automatically by their respective writers (`user-prompt-state.sh`
  and a post-commit helper); the model **shall never** set them
  via `jq` directly.

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
| A1 fix lets unverified code through if upstream tracks a feature branch with bad code | The gate still fails closed: the diff `@{upstream}...HEAD` always shows code if any unpushed commit touches code paths. Strictness applies per push attempt; cannot be amortized across pushes. |
| A1 fallback to `origin/main` on no-upstream produces same false positive as before | Acceptable: first push of a new branch is rare and inherently mid-flow. The fallback preserves current behavior for the edge case. |
| B3 session-cut gate blocks legitimate single-session work for very small changes | The gate fires only on `planning → implementation` and `retrospective → finalize`. Micro/light/explore/debug flows are unaffected (they don't have those transitions). For genuine same-session emergencies, `SKIP_SESSION_CUT_GATE=1 + decision log` is the documented escape. |
| Session-date computation differs from user's perception of "session" | Session date = `session_state.session_date` field, set by the SessionStart hook to `date +%Y-%m-%d`. Documented in CLAUDE.md so the user can predict gate behavior. |
| `last_code_commit_session_date` accidentally set by the model via `jq` | Single-writer invariant in Norms; phase-transition-controller can be extended later if needed (follow-up). For v2 launch, the field is documented as model-untouchable; violation surfaces in retrospective. |
| Session-cut bypass becomes habitual | Each `SKIP_SESSION_CUT_GATE=1` invocation requires a decision-log entry per CLAUDE.md bypass policy. Heuristic: ≥3 entries across logs → graduate to either tightening the gate or relaxing it. |

## Implementation outline

1. **Wave 1 (parallel) — independent shell changes.**
   - `session-start.sh` extension: rename
     `restore_approval_if_resumable` →
     `restore_evidence_bundle_if_resumable`, extend `is_resumable`
     phase set, restore bundle conditional on phase + spec/plan on
     disk.
   - `lib/git-probe.sh` new helper: `is_spec_committed_clean`,
     `is_path_committed_clean`, returning 0/1 read-only.
   - `pre-push-gate.sh` A1 fix: replace `origin/main...HEAD` with
     `@{upstream}...HEAD` (with fallback).
   - New `validators/session-cut-validator.sh`: read
     `evidence.plan_session_date` and
     `evidence.last_code_commit_session_date`, compare against
     current `session_date`. Honors `SKIP_SESSION_CUT_GATE=1`.
   - `user-prompt-state.sh` writer extension: when assigning
     `evidence.plan_path`, also set
     `evidence.plan_session_date = $(date +%Y-%m-%d)`.
   - New `post-commit-session-stamp.sh` hook: when commit touches
     a code/test classified file, set
     `evidence.last_code_commit_session_date`.

2. **Wave 2 (parallel, needs Wave 1) — validator integrations.**
   - `consult-validator.sh`: source `lib/git-probe.sh`; use
     `is_spec_committed_clean` as fallback when
     `decisions_read=false` or `logs_scanned=false`.
   - `brainstorm-validator.sh`: same helper for
     `alternatives_proposed`. `user_approved` excluded.
   - `phase-advance.sh`: dispatch `session-cut-validator.sh` on
     `planning → implementation` and `retrospective → finalize`.

3. **Wave 3 (parallel, needs Wave 1) — doc updates.**
   - `CLAUDE.md` § Context Hygiene: antipattern note for
     `git stash --include-untracked`.
   - `CLAUDE.md` § Workflow: document the two session-cut gates.
   - `.claude/README.md` § Bypass env vars: add
     `SKIP_SESSION_CUT_GATE=1` row + cross-reference to stash
     antipattern.

4. **Wave 4 (parallel, needs Waves 1+2) — smoke tests.**
   - `test-session-start-resume-bundle.sh`
   - `test-git-probe.sh`
   - `test-consult-validator-gitprobe.sh`
   - `test-brainstorm-validator-gitprobe.sh`
   - `test-pre-push-gate-upstream-diff.sh` (A1)
   - `test-session-cut-validator.sh` (B3)

5. **Wave 5 — Verify.** `bash -n`, shellcheck, smoke tests pass,
   existing harness tests still pass.

## Verification plan

- All existing tests pass (unchanged surface where applicable).
- New smoke test for stash+resume: simulate
  `jq '.evidence.user_approved=false'` + spec committed +
  is_resumable phase → restore brings flags back.
- New smoke test for consult-validator git-probe: spec committed +
  clean → exit 0 even if `decisions_read=false`.
- New smoke test for brainstorm-validator: spec committed but
  `user_approved=false` → still blocks (probe doesn't auto-approve).
- New smoke test for A1: temp branch with protected commit on
  upstream + doc-only unpushed commit → gate passes silently;
  same branch with code unpushed → gate blocks.
- New smoke test for B3: state with
  `plan_session_date == session_date` + advance to implementation
  → blocks; with `plan_session_date != session_date` → passes;
  with `SKIP_SESSION_CUT_GATE=1` → bypass succeeds and
  decision-log requirement is logged in stderr.

## Dogfooding plan (this interaction)

Because the v2 expansion includes A1 (push gate fix), this
interaction can demonstrate the fix in production by:

1. Implementing A1 in this session (~15 lines surgical change to
   `pre-push-gate.sh` + smoke test).
2. Pushing the expanded spec/plan after A1 lands → the gate
   passes because the unpushed-commits diff is doc-only.
3. Handing off (a)/(b)/(d) and B3 to session N+1.

The dogfooding is **optional** for spec validity — the spec is
self-consistent without it — but it provides immediate evidence
that A1 works as designed.
- Documentation review: CLAUDE.md note ≤300 chars, links to spec
  path.
