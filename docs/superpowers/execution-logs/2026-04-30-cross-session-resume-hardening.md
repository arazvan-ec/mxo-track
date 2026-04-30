---
type: feature
tags: [harness, workflow-engine, cross-session, resume, session-cut, git-probe, dogfooding, b3, a1]
files_touched:
  - .claude/hooks/pre-push-gate.sh
  - .claude/hooks/test-pre-push-gate-upstream-diff.sh
  - .claude/hooks/session-start.sh
  - .claude/hooks/lib/git-probe.sh
  - .claude/hooks/user-prompt-state.sh
  - .claude/hooks/post-commit-session-stamp.sh
  - .claude/hooks/validators/session-cut-validator.sh
  - .claude/hooks/validators/consult-validator.sh
  - .claude/hooks/validators/brainstorm-validator.sh
  - .claude/hooks/phase-advance.sh
  - .claude/hooks/test-git-probe.sh
  - .claude/hooks/test-session-start-resume-bundle.sh
  - .claude/hooks/test-consult-validator-gitprobe.sh
  - .claude/hooks/test-brainstorm-validator-gitprobe.sh
  - .claude/hooks/test-session-cut-validator.sh
  - CLAUDE.md
  - .claude/README.md
  - docs/superpowers/specs/2026-04-29-cross-session-resume-hardening-design.md
  - docs/superpowers/plans/2026-04-29-cross-session-resume-hardening.md
  - docs/superpowers/plans/2026-04-30-resume-hardening-implementation.md
patterns: [git-probe-fallback, session-cut-gate, evidence-bundle-restore, single-writer-stamp]
outcome: success
outcome_verified_at: 2026-04-30
regressions_later: []
pr_number: null
estimated_lines: 655
actual_lines: 720
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-04-30 — Cross-Session Resume Hardening (full v2)

**Branch:** `claude/phase-c-tooling-Ks1mw`
**Spec:** `docs/superpowers/specs/2026-04-29-cross-session-resume-hardening-design.md`
**Plans:** `docs/superpowers/plans/2026-04-29-cross-session-resume-hardening.md` (A1)
+ `docs/superpowers/plans/2026-04-30-resume-hardening-implementation.md` (a+b+d+B3)

## Summary

Five coordinated harness changes addressing four recurring frictions
in cross-session continuation, all rolled into one feature branch:

- **A1 (shipped 2026-04-30 commit `3aee271`):** `pre-push-gate.sh`
  evaluates `@{upstream}...HEAD` (unpushed-commits diff) instead of
  `origin/main...HEAD` (whole-branch diff). Fallback to `origin/main`
  only when no upstream exists. Doc-only checkpoint pushes mid-flow
  no longer false-positive. Smoke test: 4/4 assertions.

- **(a) shipped commit `1cca9d4`:** `session-start.sh` resume now
  restores a **bundle** of evidence flags (not just `user_approved`):
  - `decisions_read` and `logs_scanned` whenever resumable.
  - `alternatives_proposed` and `user_approved` only when phase ≥
    planning AND spec_path on disk.
  - `is_resumable` extended to include `brainstorming` and
    `planning` phases (was implementation+).
  - Helper renamed `restore_approval_if_resumable` →
    `restore_evidence_bundle_if_resumable` with backward-compat
    alias. Smoke test: 10/10 assertions.

- **(b) shipped commit `1cca9d4`:** Documentation in `CLAUDE.md` §
  Context Hygiene + § Bypass env vars; `.claude/README.md` Hooks
  table. Names the `git stash --include-untracked` antipattern
  explicitly, documents `SKIP_SESSION_CUT_GATE=1`, links to spec.

- **(d) shipped commit `1cca9d4`:** New `lib/git-probe.sh` (read-only
  primitives `is_path_committed_clean`, `is_spec_committed_clean`)
  consumed by `consult-validator.sh` (treats `decisions_read` and
  `logs_scanned` as effective when spec is committed-clean) and
  `brainstorm-validator.sh` (same for `alternatives_proposed`,
  PROVIDED spec contains required sections — Norms, Safeguards,
  Alternatives Rejected). **`user_approved` deliberately excluded
  from the probe** — verbal approval remains mandatory per spec
  Alternatives Rejected D. Smoke tests: 5/5 + 4/4 assertions.

- **(B3) shipped commit `1cca9d4`:** New
  `validators/session-cut-validator.sh` invoked by
  `phase-advance.sh` at two transitions:
  - `planning → implementation` blocks when
    `evidence.plan_session_date` equals current `session_date`.
  - `retrospective → finalize` blocks when
    `evidence.last_code_commit_session_date` equals current
    `session_date`.

  Stamps written by single-writer hooks:
  - `user-prompt-state.sh` sets `plan_session_date` when
    `evidence.plan_path` first transitions null → set.
  - New `post-commit-session-stamp.sh` sets
    `last_code_commit_session_date` when a commit touches code/test
    classified files (script created; manual install via
    `.git/hooks/post-commit` symlink is a follow-up — not auto-wired).

  Bypass: `SKIP_SESSION_CUT_GATE=1` with stderr decision-log notice.
  Smoke test: 7/7 assertions.

## Approach

Per spec v2 (approved 2026-04-29). The interaction split into two
sub-bundles:

- **Bundle 1 (A1, dogfood):** session N planning + N+1
  implementation. Shipped first to validate the diff-fix in
  production. Push gate now passes silently for doc-only mid-flow
  pushes; blocks when unpushed commits include code.

- **Bundle 2 ((a)+(b)+(d)+B3):** session N+1 (today, 2026-04-30).
  Plan written today; user explicitly authorized parallel impl in
  the same session because plan was committed yesterday
  (2026-04-29) — B3's date semantics are satisfied even though the
  date-based gate isn't yet enforced (chicken-and-egg: B3 IS what
  we're implementing).

## Phases (this interaction)

- **Consult / brainstorm:** spec v2 already approved 2026-04-29
  (commit `6e6e663`); on resume today, advanced through consult →
  brainstorm → planning via `SKIP_PHASE_EXIT_GATE=1` since the
  artifact + decision-log entry already existed and verbal
  re-approval was given ("Apruebo el spec v2", "Apruebo A").
- **Planning:** parallel plan with 4 waves (A independent files,
  B integrations needing A artifacts, C smoke tests needing B/A
  targets, D verification).
- **Implementation:** Wave A (7 files), Wave B (3 files), Wave C
  (5 smoke tests). All in one session as the user explicitly
  authorized parallel-batch execution.
- **Verification:** all 5 new smoke tests green (33 assertions
  total: 7+10+5+4+7). All 13 new/modified files pass
  `shellcheck -S warning`. 6 pre-existing baseline failures in
  unrelated harness tests confirmed not regressions.

## Blockers / corrections during implementation

1. **`>` in case patterns triggers shell parse error.** Initial
   `session-cut-validator.sh` used `case "$TRANSITION" in
   planning->implementation|...)` — `>` in case glob is a syntax
   error. Fixed: use only the dash-separated form
   `planning-to-implementation` and update the caller in
   `phase-advance.sh` to match.

2. **REPO hardcoded in `brainstorm-validator.sh` blocked test
   isolation.** Pre-existing line `REPO="/home/user/mxo-track"`
   couldn't be overridden by env var. Smoke test that builds a
   temp repo with its own spec couldn't exercise the git-probe.
   Fixed: changed to `REPO="${REPO:-/home/user/mxo-track}"`.

3. **Brainstorm-validator returns rc=1 for `user_turns < 3` even
   on fully valid specs.** Soft warning (rc=1) is "non-blocking";
   smoke test originally asserted rc=0 which failed. Fixed:
   added `assert_not_block` that accepts rc != 2 (the validator
   contract for "passes the gate").

4. **Stream idle timeout during execution-log write.** Recoverable
   — re-issued the write with full content. State machine survived
   (capture phase intact).

## Verification results

- `bash -n` clean on all 13 new/modified shell files.
- `shellcheck -S warning` clean on the 13 files I touched.
- 5 new smoke tests: 33/33 assertions passing.
- Pre-existing baseline failures in 6 hook tests
  (test-enforcement-layers, test-full-flow-e2e, test-phase-advance,
  test-self-gating, test-status-line, test-workflow-engine) are
  unchanged. Verified by stash A/B in earlier interaction.
- Manifest regenerated.

## Patterns (graduation candidates)

- **git-probe-fallback** — 1st explicit occurrence. Pattern:
  read-only validator helper that derives implicit truth from git
  state when JSON evidence is stale. Watch for recurrence; if
  pattern appears in another validator (e.g., consult-validator
  reading file mtime), graduate to a knowledge module on
  "validator fallback design".

- **session-cut-gate** — 1st occurrence. Pattern: HARD gate based
  on session-date stamps comparing to today, forcing fresh-window
  review at high-bias transitions. If a third date-based gate
  emerges (e.g., "no merge same day as PR creation"), graduate.

- **evidence-bundle-restore** — generalization of the existing
  `restore_approval_if_resumable` pattern. The bundle approach
  (group of flags conditional on phase) is more powerful than
  per-flag helpers. If we add a third "resume helper" (e.g., for
  task_progress restoration), graduate.

- **single-writer-stamp** — `plan_session_date` and
  `last_code_commit_session_date` are stamped by exactly one hook
  each. Pattern matches existing `user_approved` invariant.
  Already graduated implicitly in CLAUDE.md autonomy contract;
  this just adds two more fields under the same rule.

## Decisions

- **`user_approved` excluded from git-probe.** Verbal human
  approval remains mandatory even when spec is committed-clean.
  Rationale: a spec committed by a prior session by a different
  model run with potentially different intent must be re-endorsed
  for THIS session. Preserves the "consider one more time"
  checkpoint.

- **`post-commit-session-stamp.sh` is created but not auto-wired
  to `.git/hooks/post-commit`.** Rationale: not every developer
  wants the harness installing git hooks unprompted. Wiring is a
  one-line manual step (`ln -s ../../.claude/hooks/post-commit-session-stamp.sh
  .git/hooks/post-commit`). Documented as opt-in. Without wiring,
  the field stays null and the retrospective→finalize gate
  fall-through-with-WARN is the safe default.

- **`session-cut-validator` exits 0 with WARN when stamp is
  empty.** Conservative: cannot enforce a check based on missing
  data. Surfaces in stderr so the user knows enforcement is
  passive in this state.

## Bypass usage

- `SKIP_PHASE_EXIT_GATE=1` used to walk consult → brainstorming →
  planning when the spec was already committed and approved in a
  prior session. Decision-log entry pending; documented as the
  prototypical case the (d) git-probe is supposed to eliminate
  going forward. Heuristic check: once (d) is live, this bypass
  should drop to zero for cross-session continuations.

## Follow-ups

- **Auto-wire `post-commit-session-stamp.sh`** as opt-in. Add a
  `make install-hooks` target or a one-liner in `.claude/README.md`
  setup instructions. Without this, B3's `retrospective → finalize`
  gate has reduced enforcement strength.

- **Pre-existing harness test failures** (6 tests). Filed as
  follow-up; not in scope for this v2 bundle.

- **Auto-commit manifest hooks.** Two `chore: update codebase
  manifest` commits appeared after my code commit (`2888a8b`,
  `46f5764`). They came from automation (likely a post-commit
  hook regenerating the manifest). Confirm the source and document
  the contract in `.claude/README.md`. Worth investigating because
  these silent commits reset session-state in some cases.

- **Bypass heuristic graduation.** Per CLAUDE.md, ≥3 bypass
  occurrences should trigger gate-tuning. Current
  `SKIP_PHASE_EXIT_GATE=1` count for cross-session continuation is
  ≥3. (d) git-probe SHOULD bring this to zero. Re-audit at the
  next interaction.

## Retrospectiva

### Estimate accuracy

| Métrica | Estimado | Real | Δ |
|---|---|---|---|
| Wave A code | ~190 | ~210 | +10% |
| Wave B code | ~60 | ~70 | +17% |
| Wave C tests | ~310 | ~330 | +6% |
| Total net | 560 | ~720 | +28% |
| Files | 13 | 17 (incl. 4 docs/plans) | — |

Razonable. El gap (+28%) viene de docs y los 4 blockers (~150
líneas de fixes y ajustes de tests).

### Process gap

**Stream idle timeout durante write del execution log.** Recoverable
una vez que el usuario lo reportó, pero expone que el modelo no
detecta proactivamente cuando un response stream se interrumpe.
**Fix accionable:** monitorear evidencia de "expected file" después
de cada Write — si el state dice `execution_log_path` está set
pero `ls` lo niega, re-emit. Ya cubierto implícitamente por el
hook `pre-tool-freshness.sh`.

### Emergent patterns

- `git-probe-fallback`, `session-cut-gate`, `evidence-bundle-restore`
  — todos 1ª ocurrencia, ver § Patterns para criterio de graduación.
- `single-writer-stamp` — 3ª aplicación del invariante
  ya-graduado. Reafirma su valor.
