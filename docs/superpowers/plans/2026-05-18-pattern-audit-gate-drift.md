# Plan — `pattern-audit.sh` Gate-Drift Detection

**Spec:** `docs/superpowers/specs/2026-05-18-pattern-audit-gate-drift-design.md`
**Date:** 2026-05-18
**Branch:** `claude/compare-claude-workflows-yrl2P`

## Task DAG

### Wave 1: TDD red+green (sequential)

- **1a:** Write fixture decision log + test harness `tests/hooks/test-pattern-audit-gate-drift.sh`
  → produces: failing test
  → files: `tests/hooks/test-pattern-audit-gate-drift.sh` (new), `tests/hooks/fixtures/decision-log-3-bypasses.md` (new), `tests/hooks/fixtures/decision-log-2-bypasses.md` (new)

- **1b:** Extend `.claude/hooks/pattern-audit.sh` with gate-drift detection block (appended after deprecated-alias scan)
  → produces: test 1a passes
  → files: `.claude/hooks/pattern-audit.sh` (modified)

Sequential within Wave 1 (1a → 1b) per TDD.

### Wave 2: Integration verification (needs Wave 1) — parallel internally

- **2a:** Run `pattern-audit.sh` against current `docs/decisions/log.md` — must flag `SKIP_PHASE_EXIT_GATE` (3 entries: 2026-04-22, 2026-05-03, 2026-05-06) with `[TUNE]`/`[LEGITIMIZE]`
- **2b:** Run with `PATTERN_AUDIT_BYPASS_WINDOW_DAYS=7` — must NOT flag (count=1 in window)
- **2c:** `make lint-shell` on modified `pattern-audit.sh`

## Estimated artifacts

- 1 file modified (`pattern-audit.sh` +~40 lines)
- 3 new test files (~80 lines)
- Shared execution log + decision log entry

## Risks

- Decision log heading format drift — fixture pins format as contract
- Date arithmetic portability — use BSD-compatible bash date math or day-count approach
- Gate-name extraction regex must handle multi-underscore names (e.g., `SKIP_DDD_BOUNDARY_GATE`) — fixture covers edge case

## Commit cadence

- Commit 1 after 1a (TDD red)
- Commit 2 after 1b (TDD green)
