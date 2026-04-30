# Plan v2 — Workflow Continuity: Cross-Session Resume + Push Gate + Session-Cut Gates

**Spec:** `docs/superpowers/specs/2026-04-29-cross-session-resume-hardening-design.md`

Five coordinated changes: (a) session-start bundle, (b) docs
antipattern, (d) consult/brainstorm git-probe, **A1** push gate
fix, **B3** session-cut gates.

**Session split (per B3 itself, applied to this work):**

- **This session (N):** spec v2 + plan v2 + **A1 implementation
  only** (dogfooding, ~25 lines + smoke test). Push the bundle
  to remote.
- **Session N+1:** implement (a) + (b) + (d) + B3 (~330 lines
  + smoke tests).
- **Session N+2:** verify, capture, retrospective, finalize.

The split itself prefigures B3: A1 (a tiny precursor) ships in
session N; the bulk of harness work waits until N+1's fresh
context to enforce independent review of the plan written here.

## Phase 1: edit + verify

### [parallel] Wave 1: A1 + smoke test (this session)

- **1 — pre-push-gate A1 fix** Modify `has_protected_changes()`
  in `.claude/hooks/pre-push-gate.sh`: replace
  `git diff --name-only origin/main...HEAD` with
  `git diff --name-only @{upstream}...HEAD`, and fall back to the
  old expression only when `git rev-parse @{upstream}` fails
  (initial branch push). Comment the rationale (link to this
  spec).
  → produces: updated pre-push-gate.sh
  → files: `.claude/hooks/pre-push-gate.sh`

- **2 — A1 smoke test** Create
  `.claude/hooks/test-pre-push-gate-upstream-diff.sh`. Builds a
  temp git repo with: a commit on `origin/main` containing code,
  a feature branch with one pushed code commit, then an unpushed
  doc-only commit. Assert: `has_protected_changes` returns 1
  (no protected unpushed). Then add an unpushed code commit;
  assert returns 0 (protected unpushed). Negative case: branch
  with no upstream → fallback path runs.
  → produces: new test script
  → files: `.claude/hooks/test-pre-push-gate-upstream-diff.sh`

### Wave 2: dogfood push (this session, after Wave 1 verify)

- **3 — manifest + commit + push** Run `make manifest`, commit
  the spec v2 + plan v2 + A1 fix + smoke test, then push. The
  push itself is the dogfood: A1 must pass the gate when the
  unpushed commits include code (the A1 fix) — gate fires
  correctly on the new behavior.
  → produces: pushed branch state
  → files: `docs/codebase-manifest.md`

## Phase 2: defer to session N+1

The remaining tasks (~330 lines) live in a separate plan file
that session N+1 will execute. This plan stops after Wave 2 by
design — per B3 itself.

### Tasks deferred to N+1 (summary, full plan in N+1's plan file)

| Task | Files | Approx |
|---|---|---|
| (a) session-start bundle | `.claude/hooks/session-start.sh` | +35 |
| (a) smoke test | `.claude/hooks/test-session-start-resume-bundle.sh` | +60 |
| `lib/git-probe.sh` helper | `.claude/hooks/lib/git-probe.sh` | +30 |
| `lib/git-probe.sh` smoke test | `.claude/hooks/test-git-probe.sh` | +50 |
| (d) consult-validator integration | `.claude/hooks/validators/consult-validator.sh` | +20 |
| (d) consult smoke test | `.claude/hooks/test-consult-validator-gitprobe.sh` | +60 |
| (d) brainstorm-validator integration | `.claude/hooks/validators/brainstorm-validator.sh` | +25 |
| (d) brainstorm smoke test | `.claude/hooks/test-brainstorm-validator-gitprobe.sh` | +60 |
| B3 session-cut validator | `.claude/hooks/validators/session-cut-validator.sh` | +60 |
| B3 phase-advance dispatch | `.claude/hooks/phase-advance.sh` | +15 |
| B3 user-prompt-state stamp | `.claude/hooks/user-prompt-state.sh` | +10 |
| B3 post-commit hook | `.claude/hooks/post-commit-session-stamp.sh` | +30 |
| B3 smoke test | `.claude/hooks/test-session-cut-validator.sh` | +80 |
| (b) CLAUDE.md antipattern + workflow | `CLAUDE.md` | +20 |
| (b) README cross-ref | `.claude/README.md` | +5 |

**Subtotal session N+1:** ~560 lines (code + tests + docs).

### Tasks deferred to N+2

- Run all smoke tests + existing 31 harness tests.
- Manual smoke of stash+resume, session-cut bypass with
  decision-log entry.
- Write execution log.
- Retrospective (visible).
- Update decision log.
- Branch strategy declaration + final push.

## Estimación (this session only)

| Métrica | Estimación |
|---|---|
| `pre-push-gate.sh` A1 fix | ~15 lines |
| A1 smoke test | ~80 lines |
| Spec v2 expansion | already done |
| Plan v2 (this file) | already done |
| Manifest update | auto |
| Total net (this session) | ~95 lines new |
| Files (this session) | 4 (pre-push-gate.sh + test + spec + plan + manifest) |

## Done criteria (this session only)

- [ ] A1 fix lands in `pre-push-gate.sh` with rationale comment
      linking to spec.
- [ ] A1 smoke test passes (3 assertions: doc-only-unpushed
      passes, code-unpushed blocks, no-upstream falls back).
- [ ] `bash -n` clean on both files; shellcheck warning-free for
      the two new/modified files.
- [ ] Spec v2 + plan v2 + A1 fix + smoke test committed.
- [ ] Push succeeds (gate now passes because the unpushed-commits
      diff includes the A1 fix as expected protected change AND
      evidence is in order — A1 is the implementation phase of
      this micro-bundle).
- [ ] Session N+1 has clear handoff notes (`evidence.plan_path`
      points to this plan; subsequent session N+1 will write a
      separate plan for the remaining work).

## Notes for session N+1

When session N+1 starts:

1. SessionStart:resume should pick up `current_phase=` whatever
   this session ended at. Per the dogfooding split, this session
   ends at `verification` (after running A1 smoke test) without
   advancing further. N+1 starts a NEW interaction with its own
   spec+plan focused on (a)+(b)+(d)+B3 implementation.

2. The B3 session-cut gate is exactly what makes the split safe:
   N+1 will read this plan with fresh context and decide if the
   approach (A1 first, rest later) still makes sense, or if it
   should be reorganized.

3. Verbal approval will be needed once at the start of N+1 (per
   spec Alternative D).

## Out of scope (separate interactions)

- (c) `atomic-yaml-rewrite` graduation — light interaction.
- Extending `phase-transition-controller.sh` to track
  `plan_session_date` / `last_code_commit_session_date` reverts.
  Filed as follow-up; v2 launches with documented single-writer
  invariant in Norms.
- Auto-detect of `user_approved` from git (rejected — alt D).
- Permanent `SKIP_PRE_PUSH_GATE=1` env var (rejected — alt E).
- Phase `validation` dedicated to fresh-eyes review (rejected —
  alt G; B3 delivers the same effect with less surface area).
