# Plan v3 — Cross-Session Resume Hardening: (a)+(b)+(d)+B3 Implementation

**Spec:** `docs/superpowers/specs/2026-04-29-cross-session-resume-hardening-design.md`
**Continuation of:** A1 dogfood (commit `3aee271` 2026-04-30).

This plan implements the remaining four scope items deferred from
the A1 interaction:

- **(a)** session-start.sh evidence-bundle restore on resume.
- **(b)** Document the `git stash --include-untracked` antipattern
  in CLAUDE.md + README cross-ref.
- **(d)** consult/brainstorm validators git-probe fallback.
- **(B3)** Session-cut gates at `planning → implementation` and
  `retrospective → finalize`.

## Phase 1: edit + verify

Three waves. Wave A is fully parallel (7 disjoint files). Wave B
parallelizes 3 integration points that need Wave A artifacts.
Wave C parallelizes 5 smoke tests.

### [parallel] Wave A: independent shell + doc files

- **1 — session-start.sh bundle restore** Rename
  `restore_approval_if_resumable` → `restore_evidence_bundle_if_resumable`.
  Extend `is_resumable()` to include `brainstorming` and
  `planning` phases. Restore bundle: `decisions_read=true` and
  `logs_scanned=true` whenever resumable; `alternatives_proposed`
  and `user_approved` only when `evidence.spec_path` references a
  file present on disk AND phase ≥ planning. Update both call
  sites (existing lines ~281 and ~291).
  → files: `.claude/hooks/session-start.sh`

- **2 — lib/git-probe.sh new helper** New file under
  `.claude/hooks/lib/`. Two functions:
  `is_path_committed_clean <path>` returns 0 iff
  `git ls-files --error-unmatch <path>` succeeds AND
  `git diff --quiet --exit-code -- <path>` succeeds (tracked + clean).
  `is_spec_committed_clean <state_file>` reads
  `evidence.spec_path` from state and delegates to the first.
  Read-only; never mutates anything.
  → files: `.claude/hooks/lib/git-probe.sh`

- **3 — user-prompt-state.sh plan_session_date stamp** When the
  hook detects `evidence.plan_path` becoming non-null (transition
  null → set), additionally set
  `evidence.plan_session_date = $(date +%Y-%m-%d)`. Single-writer
  invariant matches the existing `user_approved` write pattern.
  → files: `.claude/hooks/user-prompt-state.sh`

- **4 — post-commit-session-stamp.sh new hook** New file under
  `.claude/hooks/`. PostCommit hook: classifies committed files
  (via existing `lib/classify-file.sh`); if any classifies as
  `code` or `test`, set
  `evidence.last_code_commit_session_date = $(date +%Y-%m-%d)`.
  Atomic write via mktemp+mv.
  → files: `.claude/hooks/post-commit-session-stamp.sh`

- **5 — session-cut-validator.sh new validator** New file under
  `.claude/hooks/validators/`. Reads current `session_date` and
  the appropriate stamp field (`plan_session_date` for
  planning→implementation, `last_code_commit_session_date` for
  retrospective→finalize). Blocks (exit 2) when stamp == current
  session_date. Honors `SKIP_SESSION_CUT_GATE=1` with stderr
  notice that a decision-log entry is required.
  → files: `.claude/hooks/validators/session-cut-validator.sh`

- **6 — CLAUDE.md docs** Two additions: § Context Hygiene gets a
  3-line antipattern note for `git stash --include-untracked`
  (specifically: stashes session-state.json, resume hook resets
  evidence). § Workflow gets a row in the "What each gate blocks"
  table for the two new session-cut gates.
  → files: `CLAUDE.md`

- **7 — .claude/README.md cross-ref** § Bypass env vars gets a
  new row for `SKIP_SESSION_CUT_GATE=1` (decision-log entry
  required). 1-line cross-ref to CLAUDE.md antipattern.
  → files: `.claude/README.md`

### [parallel] Wave B: integrations (need Wave A)

- **8 — consult-validator git-probe** Source `lib/git-probe.sh`.
  When the existing AND-gate of `decisions_read` and
  `logs_scanned` would fail, invoke
  `is_spec_committed_clean` as fallback; if 0, treat the gate as
  passed. Read-only — does NOT mutate evidence.
  → files: `.claude/hooks/validators/consult-validator.sh`

- **9 — brainstorm-validator git-probe** Same helper. When
  `alternatives_proposed=false` but spec is committed-clean AND
  contains required sections (Norms, Safeguards, ≥2
  Alternatives), treat `alternatives_proposed` as effectively
  true. **`user_approved` deliberately excluded** — verbal
  approval still blocks if false.
  → files: `.claude/hooks/validators/brainstorm-validator.sh`

- **10 — phase-advance.sh dispatch** When advancing to
  `implementation` from `planning`, or to `finalize` from
  `retrospective`, invoke `session-cut-validator.sh`. Block on
  non-zero exit unless `SKIP_SESSION_CUT_GATE=1`.
  → files: `.claude/hooks/phase-advance.sh`

### [parallel] Wave C: smoke tests (need their targets)

- **11 — test-session-start-resume-bundle** Build temp state
  with `phase=planning`, `spec_path` pointing to a committed
  file, all evidence flags `false`. Run
  `restore_evidence_bundle_if_resumable`. Assert
  `decisions_read`, `logs_scanned`, `alternatives_proposed`,
  `user_approved` become `true`. Negatives: empty `spec_path` →
  flags stay false; `phase=consult` → not resumable.
  → files: `.claude/hooks/test-session-start-resume-bundle.sh`

- **12 — test-git-probe** Standalone test for the helper:
  tracked-clean → 0; tracked-modified → 1; untracked → 1;
  nonexistent → 1.
  → files: `.claude/hooks/test-git-probe.sh`

- **13 — test-consult-validator-gitprobe** State with
  `decisions_read=false` + spec_path → committed-clean file →
  validator passes (exit 0). Same with modified file → blocks.
  No spec_path → blocks.
  → files: `.claude/hooks/test-consult-validator-gitprobe.sh`

- **14 — test-brainstorm-validator-gitprobe** State with
  `alternatives_proposed=false` + spec committed with required
  sections → validator passes; spec without Alternatives →
  blocks; verify `user_approved=false` STILL blocks even with
  spec committed (probe doesn't auto-approve).
  → files: `.claude/hooks/test-brainstorm-validator-gitprobe.sh`

- **15 — test-session-cut-validator** State with
  `plan_session_date == session_date` + advance to
  implementation → blocks; with different date → passes;
  `SKIP_SESSION_CUT_GATE=1` → bypass succeeds with stderr
  decision-log notice.
  → files: `.claude/hooks/test-session-cut-validator.sh`

### Wave D: verification (after A+B+C)

- **16 — bash-n** all touched + new shell files.
- **17 — shellcheck** `-S warning` clean on the 13 new/modified
  files.
- **18 — smoke tests** all 5 new tests pass.
- **19 — existing tests** existing harness tests still pass
  (no regressions on baseline-passing tests).
- **20 — manifest** regenerate via `make manifest`.

## Estimación

| Métrica | Estimación |
|---|---|
| Wave A code | ~190 lines (35+30+10+30+60+20+5) |
| Wave B code | ~60 lines (20+25+15) |
| Wave C tests | ~310 lines (60+50+60+60+80) |
| Total net code+tests | ~560 lines |
| Artefactos (plan, exec log, decision-log) | +3 |
| Files touched | 13 (5 new validators/hooks, 5 new tests, 3 modified) |

## Done criteria

- [ ] All Wave A files written, all Wave B integrations land,
      all Wave C smoke tests pass.
- [ ] `bash -n` + `shellcheck -S warning` clean on the 13 files.
- [ ] 5 new smoke tests pass; existing harness tests still pass.
- [ ] CLAUDE.md and README updated.
- [ ] Manifest regenerated.
- [ ] Execution log written for the v2 bundle (folds A1 + this
      session's work into one feature log).
- [ ] Decision log entry for `SKIP_SESSION_CUT_GATE=1` policy
      (heuristic for graduating).
- [ ] Retrospective presented to user before finalize.
- [ ] `branch_strategy` declared.

## Quality safeguards (per user request)

1. **TDD where reasonable:** Wave C smoke tests written
   alongside their Wave A/B targets, not after. Each Wave A
   target is followed by its smoke test before declaring task
   done.
2. **Per-file shellcheck:** every new/modified file is shellcheck
   warning-free for changes I introduce. Pre-existing baseline
   warnings in unrelated files are not regressions but are
   noted in the execution log.
3. **Sync-gate cleanliness:** all `→ files:` declarations on
   single lines so `parse_files_decl` picks them up.
4. **Atomic writes for evidence:** all evidence mutations use
   mktemp + mv pattern (matches existing convention from
   `user-prompt-state.sh`).
5. **Single-writer invariants:** `last_code_commit_session_date`
   only ever set by `post-commit-session-stamp.sh`; the model
   never sets it via `jq` (per spec § Norms).
6. **Read-only probes:** `lib/git-probe.sh` never mutates state;
   validators using it treat git failures as fail-closed.
7. **Bypass policy:** every `SKIP_SESSION_CUT_GATE=1` invocation
   in this session's smoke tests counts as a fixture, not as a
   real bypass — no decision-log entry needed for tests.
   Real bypasses elsewhere DO require entries.
