# Plan — Retrospective Auto-Propose Backlog Candidates (P2)

**Spec:** `docs/superpowers/specs/2026-05-20-retrospective-backlog-candidates-design.md`
**Date:** 2026-05-20
**Branch:** `claude/compare-claude-workflows-yrl2P`

## Task DAG

### Wave 1: TDD red — extend test

- **1a:** Extend `.claude/hooks/test-retrospective-validator.sh` with 4 new cases:
  - Test A: retro log without `## Backlog candidates` section → validator exit 2
  - Test B: retro log with section + 2 bullets, backlog NOT in git diff → exit 2
  - Test C: retro with section + bullets + backlog modified → exit 0
  - Test D: retro with literal "Backlog candidates: 0 — no surfaced improvements" + backlog NOT modified → exit 0
  → produces: 4 failing tests
  → files: `.claude/hooks/test-retrospective-validator.sh` (modified, ~80 lines added)

### Wave 2: TDD green — implement validator + docs

Tasks 2a and 2b are **independent** (touch disjoint files) → can run in parallel.

- **2a:** Edit `docs/CLAUDE.md` § Retrospective visibility rule: add 4th obligatory point (Backlog candidates analysis) + heuristic for model to ask before finalize
  → produces: rule documented
  → files: `docs/CLAUDE.md` (modified, ~15 lines added)

- **2b:** Extend `.claude/hooks/validators/retrospective-validator.sh`:
  - At `retrospective → finalize` exit, parse execution log at `evidence.execution_log_path`
  - Detect `## Backlog candidates` heading + bullets OR literal "0 — no surfaced improvements" line
  - If bullets exist, verify `docs/backlog.md` in `git diff $PLAN_COMMIT...HEAD` OR `git status --short`
  - Exit 2 with explicit message on failure
  → produces: HARD enforcement
  → files: `.claude/hooks/validators/retrospective-validator.sh` (modified, ~40 lines added)

### Wave 3: Verification (parallel) — needs Wave 2

- **3a:** Run `test-retrospective-validator.sh` — all 4 new cases pass + existing pass
- **3b:** Manual dry-run against this interaction's eventual retrospective — confirm validator accepts current pattern
- **3c:** `make lint` clean

## Estimated artifacts

- Source: 2 files modified (`docs/CLAUDE.md` ~15 lines, `retrospective-validator.sh` ~40 lines)
- Tests: 1 extended (`test-retrospective-validator.sh` ~80 lines)
- Shared interaction log + decision log entry with P1 + P3

## Risks

- Validator misses heading variants (e.g., `### Backlog candidates` instead of `## Backlog candidates`) — mitigation: anchor regex strictly at `^## Backlog candidates`; document the exact heading in CLAUDE.md rule
- "0 candidates" literal string typos break detection — mitigation: case-insensitive match on key tokens ("Backlog candidates" + "0" + "no surfaced")
- HARD validator blocks legitimate retros — mitigation: bypass available `SKIP_PHASE_EXIT_GATE=1`; first 3 bypasses graduate to WARNING per existing pattern
- Plan commit reference for git diff — reuse same logic as sync-validator (DRY via existing helper or inline)

## Commit cadence

- Commit 1 after 1a (TDD red — 4 new test cases failing)
- Commit 2 after 2a + 2b (parallel completion — green)
- Wave 3 no commit
