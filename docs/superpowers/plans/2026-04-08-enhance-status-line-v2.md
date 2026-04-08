# Plan — Enhance Status Line v2 (5-line expanded)

**Date:** 2026-04-08
**Spec:** `docs/superpowers/specs/2026-04-08-enhance-status-line-v2-design.md`

## Wave 1: Add evidence + next + branch to full-flow (1 task)

**Task 1: Add evidence, next, and branch lines to full-flow section**
- Add `git branch --show-current` call near top of script for branch name
- Add evidence computation (reuse logic from `user-prompt-state.sh` lines 215-248)
- Add next action computation (reuse logic from `user-prompt-state.sh` lines 252-294)
- Restructure full-flow output to 5 lines:
  - Line 1: existing + `| 🔀 branch`
  - Line 2: `  Evidence: ...`
  - Line 3: `  Next: ...`
  - Line 4: completed chain (if CURRENT_INDEX > 2)
  - Line 5: tool suffix (if present)

## Wave 2: Add same to debug-flow + simple flows (1 task)

**Task 2: Add evidence, next, branch to debug and simple flows**
- Debug: add evidence line, next line, branch suffix
- Simple flows (micro/light/explore): add branch suffix to line 1

## Wave 3: Update tests + verify (1 task)

**Task 3: Update tests and run full verification**
- Update `test-status-line.sh` assertions for new format
- Run test suite, verify 0 failures
- Commit and push
