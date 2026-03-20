# Retrospective — Technical Debt Elimination Phase 1 & 2

**Period:** 2026-03-19 to 2026-03-20
**Reviewer:** Claude
**Branch:** `claude/start-session-a-RnjfK`
**Spec:** `docs/superpowers/specs/2026-03-19-technical-debt-elimination-design.md`
**Plan:** `docs/superpowers/plans/2026-03-19-technical-debt-elimination.md`

---

## Quantitative Summary

- **Tasks completed:** 31 (across 5 sub-phases)
- **Average estimate accuracy:** accurate (estimated ~4h, actual ~3h)
- **Blockers encountered:** 2 (Doctrine mapping prefix conflict, PublicIdTrait lifecycle dependency)
- **Plan deviation rate:** ~15% — projection architecture changed from single projector to separate listeners; RouteEvent kept simpler than planned
- **Files changed:** 140
- **Lines changed:** +1807 / -377
- **New tests added:** 5 (RouteApplyTest, extended RouteStopTest, extended RouteEventLogListenerTest)
- **Pre-existing test failures:** 11 (6 errors + 5 failures, all on main)
- **New test failures:** 0

## Estimate Calibration

| Complexity | Estimate | Actual | Ratio | Notes |
|------------|----------|--------|-------|-------|
| XL | ~4h | ~3h | 0.75x | Existing patterns (RouteSnapshot) accelerated Phase 1; mechanical import changes faster than expected |

## Recurring Blockers

| Blocker Category | Frequency | Root Cause | Proposed Fix |
|-----------------|-----------|------------|--------------|
| Doctrine mapping type conflict | 1 | Same namespace prefix cannot have two mapping types (attribute + xml) | Always use separate namespace for XML-mapped entities |
| ORM lifecycle callbacks in traits | 1 | `PublicIdTrait` uses `#[ORM\PrePersist]` — breaks when entity moves to POPO | Extract ULID generation to constructor; deprecate PrePersist pattern for new entities |

## Design Decision Outcomes

| Decision | Outcome | Lesson |
|----------|---------|--------|
| Interfaces in Domain layer following RouteSnapshot pattern | Smooth — autowiring via services.yaml aliases works cleanly | Following existing codebase patterns when they're correct accelerates delivery |
| Collect+dispatch instead of full event sourcing | Correct scope — avoids event store complexity while enabling state reconstruction | Full ES is a future phase; the intermediate step is valuable on its own |
| Separate projection listeners vs single projector | Better SRP, easier to test individual projections | Splitting by projection table is the natural boundary |
| Move to Domain\Route\Model namespace | Required by Doctrine mapping constraints; also correct DDD placement | Namespace migration is mechanical but must be done atomically (one commit) to avoid broken imports |
| Keep EntityManagerInterface in Application services for flush | Pragmatic — flush control stays at the boundary | Repository.save() = persist only; flush at Application boundary is a clean separation |

## Process Compliance Assessment

| CLAUDE.md Requirement | Status | Notes |
|----------------------|--------|-------|
| Brainstorming (Skill 2) | Partial | Design spec created, but formal brainstorming dialogue not executed per skill |
| Plan (Skill 3) | Done | Plan in docs/superpowers/plans/ |
| TDD (Skill 7) | Partial | Tests exist but committed alongside implementation, not red-green-refactor |
| Verification (Skill 9) | Done retroactively | Tests run, 0 new failures confirmed |
| Execution Log | Done retroactively | Created 2026-03-20 |
| Decision Log | Done retroactively | 4 entries added |
| Retrospective | Done | This file |
| Atomic Commits | Done | 6 well-scoped commits |
| make manifest | Pending | Should run before final push |

## Actions

- [ ] Run `make manifest` before final push to update codebase manifest
- [ ] Verify `doctrine:schema:validate` passes with actual database
- [ ] Fix pre-existing test failures (DemoSetupCommandTest, PostRouteAnalysisHandlerTest, GitLogReaderTest, SmokeTests) — these are on main, not introduced by this branch
- [ ] In future sessions: enforce strict TDD commit discipline (separate commits for failing test and passing implementation)
- [ ] Consider extracting a `DomainPublicIdTrait` that generates ULID in constructor without ORM dependency, for use in all future POPO entities
