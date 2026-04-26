---
type: plan
feature: 4test-applied-FIJ
spec: docs/superpowers/specs/2026-04-26-4test-applied-FIJ-design.md
date: 2026-04-26
---

# Plan — Apply 4-Test to F/I/J

## Estimate

- 7 files modified, 1 new (`lib/ddd-boundaries.sh`)
- ~150 net lines (additions + removals balance close to net-zero)
- 1 wave (sequential where files overlap, parallel only for read-only analysis)

## Wave 1 — Analysis (parallel, read-only)

3 background agents apply the 4-test to F, I, J independently. Reports
written to `/tmp/layer-{f,i,j}-analysis.md`. No file changes. Already
completed when this plan was written; reports cited in spec.

## Wave 2 — Action 1 (foreground): F BLOCK + H YAML SoT

- Modify `.claude/hooks/ddd-boundary-check.sh` — promote WARNING to
  conditional BLOCK in full/debug when Prior Art Audit doesn't cover
  the file. TDD: existing 10/10 tests preserved; BLOCK branch
  exercised by production scenarios (follow-up: dedicated fixture).
- Add `.claude/hooks/lib/ddd-boundaries.sh` — `ddd_critical_regex()`
  helper reading `_ddd-boundaries.yaml`.
- Modify `.claude/hooks/validators/brainstorm-validator.sh` — Layer H
  sources the helper instead of hardcoded regex.
- Modify `.claude/hooks/validators/socratic-review-validator.sh` —
  same arch-keyword trigger now reads from helper.

Acceptance: 100/100 harness tests green, syntax clean.

## Wave 3 — Action 2 (foreground): drop Layer I

- Modify `.claude/hooks/validators/retrospective-validator.sh` —
  remove the architectural-keyword block + the
  `retrospective_no_architectural_concerns` flag read.
- Modify `.claude/hooks/test-retrospective-validator.sh` — drop the
  `no_arch_concerns` parameter from `setup_state` helper; replace
  Tests 7-8 with a single baseline confirming neutral lessons pass
  post-removal.

Acceptance: 7/7 tests in retrospective-validator suite (was 8/8).

## Wave 4 — Action 3 (foreground): drop Layer J

- Modify `.claude/hooks/validators/brainstorm-validator.sh` —
  remove the J block (graduation registry pattern extraction +
  warning emission). Replace with a comment explaining the removal
  and pointing at `/tmp/layer-j-analysis.md`.
- Modify `.claude/hooks/test-brainstorm-validator.sh` — drop the
  J fixture+helper section (run_j_scenario, SPEC_J1, SPEC_J2,
  J1/J2 assertions).

Acceptance: 11/11 cases in brainstorm-validator suite (was 13/13).

## Wave 5 — Doc updates

- `CLAUDE.md` shortcuts table: drop the J row, drop the I row,
  rewrite the F row to reflect conditional BLOCK, rewrite the H row
  to mention the YAML SoT.
- `.claude/README.md` evidence matrix: drop the
  `retrospective_no_architectural_concerns` reference; the
  brainstorming row drops the graduation-check mention; the F row
  describes the new BLOCK semantics.

## Wave 6 — Verification + capture + retro + finalize

- Run every test script; expect 97/97 green.
- Write execution log capturing the 4-test scores per layer and the
  decisions taken.
- Present retrospective.
- Commit (one commit per Action for clean history) + push.

## Acceptance checklist

- [ ] F BLOCKs in full-flow when Prior Art Audit doesn't cover file
- [ ] H reads critical paths from `_ddd-boundaries.yaml`
- [ ] Layer I removed; retrospective-validator preserves visibility +
      length checks
- [ ] Layer J removed; brainstorm-validator no longer scans for
      pattern names
- [ ] All harness tests 97/97 green
- [ ] CLAUDE.md + README reflect the new layout
- [ ] Execution log + retro committed; pushed

## Non-goals

- New fixture coverage for F's BLOCK branch (deferred)
- Re-introducing J in any form (its purpose is fulfilled by
  `pattern-audit.sh` post-hoc)
