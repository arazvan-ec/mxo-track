# Plan — Enhance Status Line (Hybrid A+C)

**Date:** 2026-04-08
**Spec:** `docs/superpowers/specs/2026-04-08-enhance-status-line-design.md`

## Phase 1 (v0): Adaptive multi-line status line

### Wave 1: Refactor full-flow output (1 task)

**Task 1: Refactor `workflow-status-line.sh` full-flow section to adaptive multi-line**
- File: `.claude/hooks/workflow-status-line.sh` (lines 232-318)
- Change the full-flow output block:
  - Line 1: `📍 full | Phase (idx/total) | emoji_bar [task_progress]`
  - Line 2 (if needs or tool suffix): `  Need: ... · tool_suffix`
  - Line 3 (if CURRENT_INDEX > 2): `  ✅ completed_chain_with_evidence`
- Move `DEVIATION_SUFFIX` to line 1
- Move `TOOL_SUFFIX` to line 2
- Move `NEEDS` to line 2 (prefix with "Need:")
- Move completed phase chain to line 3 (only if ≥2 phases completed)
- Test: run script manually with mock session-state at phases 1, 4, 6

### Wave 2: Refactor debug-flow output (1 task)

**Task 2: Refactor debug-flow section to same adaptive format**
- File: `.claude/hooks/workflow-status-line.sh` (lines 321-414)
- Same pattern: line 1 = status + bar, line 2 = needs + tool, line 3 = history
- Debug has 4 phases, so threshold is CURRENT_INDEX > 1

### Wave 3: Verify (1 task)

**Task 3: Run existing test suite + manual verification**
- Run `.claude/hooks/test-status-line.sh` if it exists
- Manual test: set session-state to phase 2/8, run script, verify compact output
- Manual test: set session-state to phase 6/8, run script, verify expanded output
- Commit and push

## Phase 2: Not needed — single script change, no architectural refactor required.
