# Implementation Plan — Knowledge Update Soft Gate

**Date:** 2026-03-23
**Spec:** `docs/superpowers/specs/2026-03-23-knowledge-update-soft-gate-design.md`
**Complexity:** S (Small)
**Files affected:** 2

---

## Tasks

### Task 1: Extend finalize-validator.sh

- [ ] Edit `.claude/hooks/validators/finalize-validator.sh`
- Add knowledge module check after existing `branch_strategy` check:
  1. Get changed files: `git diff --name-only origin/main...HEAD`
  2. Map directories to knowledge modules using associative array
  3. Collect unique modules that may need updating
  4. If any found, emit SOFT warning (exit 1) listing them
- Keep existing `branch_strategy` check unchanged
- Both checks are SOFT (exit 1 = warn)

### Task 2: Update CLAUDE.md documentation

- [ ] Edit `CLAUDE.md` — In the "Validators" table, update the `finalize` row to mention knowledge module check
- [ ] Edit `CLAUDE.md` — In the finalize phase description, add note about SOFT knowledge gate

### Task 3: Verify and push

- [ ] Test the hook with current branch (should warn about ui-frontend.md since we touched frontend/src/)
- [ ] Commit and push
