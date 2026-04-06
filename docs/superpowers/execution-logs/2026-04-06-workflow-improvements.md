# Execution Log — 2026-04-06 — Workflow Improvements

**Type:** tooling improvement
**Branch:** `claude/center-map-on-stop-hnC0V`

## Brainstorming

- **Problem 1:** Retrospective phase skipped twice in same session — SOFT warning insufficient
- **Problem 2:** Wiring-only changes (<30 lines, 0 design decisions) forced through full brainstorm+plan
- **Problem 3:** No calibration data for estimating future tasks of similar type
- **Approach:** 4 targeted fixes — promote gate, document deviation criteria, add calibration

## Implementation

| # | Improvement | File | Lines |
|---|------------|------|-------|
| 1 | Retrospective promoted from SOFT to HARD in pre-push gate | `.claude/hooks/pre-push-gate.sh` | ~15 changed |
| 2 | Deviation criteria for wiring-only changes | `CLAUDE.md` | +20 |
| 3 | Calibration data: wiring tasks (~15 lines, <5 min) | `docs/knowledge/superpowers-skills.md` | +30 |
| 4 | Calibration data: boilerplate migrations (~120 lines, <10 min) | `docs/knowledge/superpowers-skills.md` | (same section) |

Also:
- Auto-reset of workflow state when finalize completes (`user-prompt-state.sh`)
- Updated `.claude/README.md` validators table to reflect HARD status

## Verification

- Bash syntax: clean
- TypeScript: 0 errors

## Lessons

- Detect patterns at 2 occurrences, not 3 — the cost of implementing a fix early is low
- SOFT gates are effectively no gates — if the model skips a phase, a warning after the fact doesn't prevent the skip
- Workflow overhead compounds: for 3 wiring tasks in one session, full brainstorm+plan added ~30 min of ceremony with zero design value
