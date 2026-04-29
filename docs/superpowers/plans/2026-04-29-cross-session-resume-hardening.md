# Plan — Workflow Continuity: Cross-Session Resume Hardening

**Spec:** `docs/superpowers/specs/2026-04-29-cross-session-resume-hardening-design.md`

Three coordinated changes (a/b/d). (c) graduación queda fuera.

This plan is written in **session N**; **implementation runs in
session N+1** to keep the implementation window free of Phase C
context bias.

## Phase 1: edit + verify

Tasks 1, 2, 3 touch disjoint files; Wave 1 is parallel. Wave 2
needs Wave 1 helpers existing (probe added to consult and brainstorm
validators must be source-able). Wave 3 is doc-only and parallel to
Wave 2.

### [parallel] Wave 1: hook + validator extensions

- **1 — session-start.sh resume bundle** Extend
  `is_resumable` (add `brainstorming` and `planning` to the phase
  set), rename `restore_approval_if_resumable` →
  `restore_evidence_bundle_if_resumable`, restore the bundle
  conditional on phase + spec/plan on disk: `decisions_read=true`
  and `logs_scanned=true` always when resumable;
  `alternatives_proposed=true` and `user_approved=true` only when
  `evidence.spec_path` exists on disk AND phase ≥ planning. Update
  both call sites (current lines 281 and 291).
  → produces: updated session-start.sh
  → files: `.claude/hooks/session-start.sh`

- **2 — git-probe helper + consult-validator integration** Add a
  shared helper `is_spec_committed_clean` in
  `.claude/hooks/lib/` (new file
  `git-probe.sh`). The helper takes a path and returns 0 iff
  `git ls-files --error-unmatch <path>` succeeds AND `git diff
  --quiet --exit-code -- <path>` succeeds (file tracked + clean).
  Wire it into `consult-validator.sh`: when JSON
  `decisions_read`/`logs_scanned` are false but
  `evidence.spec_path` is set and the helper returns 0, treat the
  gate as passing (read-only — does NOT mutate evidence).
  → produces: new `lib/git-probe.sh`, updated
    `consult-validator.sh`
  → files: `.claude/hooks/lib/git-probe.sh`, `.claude/hooks/validators/consult-validator.sh`

- **3 — brainstorm-validator git-probe** Same helper used in
  `brainstorm-validator.sh`: when `alternatives_proposed=false`
  but spec is committed-clean AND contains required sections
  (Norms, Safeguards, ≥2 alternatives), treat
  `alternatives_proposed` as effectively true.
  **`user_approved` deliberately excluded** — verbal approval
  remains mandatory.
  → produces: updated brainstorm-validator.sh
  → files: `.claude/hooks/validators/brainstorm-validator.sh`

### Wave 2: smoke tests for hook + validators (needs Wave 1)

- **4a — test session-start resume bundle** Build a temp
  state file with `evidence.user_approved=false`,
  `decisions_read=false`, `logs_scanned=false`, `phase=planning`,
  `spec_path` pointing to a committed file. Run
  `restore_evidence_bundle_if_resumable`. Assert all four flags
  become `true`. Negative case: spec_path empty → flags stay
  false. Negative case: phase=consult → flags stay false.
  → files: `.claude/hooks/test-session-start-resume-bundle.sh`

- **4b — test git-probe helper** Standalone test for
  `is_spec_committed_clean`: tracked-clean → 0; tracked-modified
  → 1; untracked → 1; nonexistent → 1.
  → files: `.claude/hooks/test-git-probe.sh`

- **4c — test consult-validator git-fallback** Build state with
  `decisions_read=false` + `spec_path` to committed-clean file →
  validator passes (exit 0). Same with `spec_path` to modified
  file → validator blocks. Same with no `spec_path` set →
  validator blocks.
  → files: `.claude/hooks/test-consult-validator-gitprobe.sh`

- **4d — test brainstorm-validator git-fallback** Build state
  with `alternatives_proposed=false` + spec committed-clean
  containing Alternatives Rejected + Norms + Safeguards →
  validator passes. Same spec without Alternatives → validator
  blocks. Verify `user_approved=false` still blocks even when
  spec is committed-clean.
  → files: `.claude/hooks/test-brainstorm-validator-gitprobe.sh`

### [parallel] Wave 3: documentation (parallel to Wave 2)

- **5 — CLAUDE.md antipatrón** Add a paragraph in § Context
  Hygiene (after the "Checkpoint" bullet) describing the `git
  stash --include-untracked` antipattern: stashes
  `.claude/session-state.json` (untracked file) and the
  subsequent SessionStart:resume can reset evidence flags. Link
  to spec path. ≤300 chars in the rule sentence + 1-line example.
  → files: `CLAUDE.md`

- **6 — .claude/README.md cross-reference** In § Bypass env
  vars (or near it), add a 1-line note pointing to CLAUDE.md
  antipatrón.
  → files: `.claude/README.md`

### Wave 4: verification (needs Waves 1-3)

- **7a — bash -n** clean on all touched + new shell files.
- **7b — make lint-shell** clean on the new files (baseline
  warnings unchanged).
- **7c — new smoke tests** all pass.
- **7d — existing tests** 31 existing harness tests still pass
  (unchanged surface).
- **7e — manual smoke session-start** Force a state with
  `decisions_read=false` + spec committed; run session-start
  manually; verify state restored.
- **7f — manifest** regenerate via `make manifest`.

## Estimación

| Métrica | Estimación |
|---|---|
| `session-start.sh` extension | +30 lines |
| `lib/git-probe.sh` new helper | +25 lines |
| `consult-validator.sh` integration | +20 lines |
| `brainstorm-validator.sh` integration | +25 lines |
| 4 smoke tests | ~250 lines aggregate |
| CLAUDE.md note | +15 lines |
| `.claude/README.md` cross-ref | +3 lines |
| Total net (code + docs) | ~370 lines |
| Artefactos (spec, plan, exec log, decision-log entry, manifest) | +5 |
| Files touched | 11 |

## Done criteria (next session)

- [ ] `session-start.sh` restores evidence bundle when resumable.
- [ ] `lib/git-probe.sh` helper exists + sourced by validators.
- [ ] `consult-validator.sh` honors git-probe fallback.
- [ ] `brainstorm-validator.sh` honors git-probe fallback for
      `alternatives_proposed`; `user_approved` still blocks if
      false.
- [ ] CLAUDE.md § Context Hygiene documents antipatrón.
- [ ] `.claude/README.md` cross-references the antipatrón.
- [ ] 4 smoke tests pass.
- [ ] 31 existing harness tests pass.
- [ ] `bash -n` + `shellcheck -S warning` clean on new files.
- [ ] Execution log written.
- [ ] Decision log entry referencing this interaction.
- [ ] Branch strategy declared; commit + push.

## Out of scope (separate interactions)

- (c) `atomic-yaml-rewrite` graduation to knowledge module —
  light interaction (~20 lines, nuevo doc + entry in
  `_graduations.yaml`).
- Extending `phase-transition-controller.sh` to track more fields.
- Auto-detect of `user_approved` from git (explicitly rejected in
  spec § Alternatives Rejected D).

## Notes for next-session continuation

When session N+1 starts:

1. SessionStart:resume should pick up `current_phase=planning`
   (or `implementation` if this session advances) and restore
   `evidence.user_approved`, `decisions_read`, `logs_scanned`,
   `alternatives_proposed` automatically (this is exactly the
   feature being implemented — current resume helper restores
   only `user_approved`).
2. Until (a) lands, the model in session N+1 will need to
   manually re-set `decisions_read` and `logs_scanned` to advance
   consult→brainstorm. This is a one-time bootstrapping cost; the
   feature being implemented prevents it from recurring.
3. Verbal approval from the user will be needed once at the
   start of session N+1 to set `user_approved=true` (current
   harness behavior; (a) preserves it as a guardrail per spec
   Alternative D).
