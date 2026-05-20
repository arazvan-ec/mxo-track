# Plan — Verification `lint_clean=skipped` Smart Acceptance (P3)

**Spec:** `docs/superpowers/specs/2026-05-20-verification-lint-skipped-smart-acceptance-design.md`
**Date:** 2026-05-20
**Branch:** `claude/compare-claude-workflows-yrl2P`

## Task DAG

### Wave 1: Extract shared helper (foundation for P3 + future sync use)

- **1a:** Create `.claude/hooks/lib/git-refs.sh` with `get_plan_commit_parent()` function
  - Read `evidence.plan_path` from session-state
  - `git log --diff-filter=A --follow --format=%H -- "$plan_path" | tail -1` to find the commit that introduced the plan
  - Fallback: `git merge-base HEAD origin/main`
  - Return parent of plan-introduction commit (so diff includes the plan creation commit)
  → produces: shared helper
  → files: `.claude/hooks/lib/git-refs.sh` (new, ~25 lines)

### Wave 2: TDD red — write test

- **2a:** Create `.claude/hooks/test-verification-validator.sh` covering:
  - Test 1: shellcheck missing + diff has 0 shell files → accept, `lint_skip_reason=no_shell_files_in_diff`
  - Test 2: shellcheck missing + diff has shell file → accept, `lint_skip_reason=shellcheck_missing`
  - Test 3: shellcheck present + lint=skipped → block (no auto-acceptance with available tool)
  - Test 4: `lint_clean=true` → accept (regression)
  - Test 5: `lint_clean=null` → block (regression)
  - Test 6: `lint_clean=false` → block (regression)
  - Test 7: helper `lib/git-refs.sh::get_plan_commit_parent()` returns valid commit ref
  → produces: failing tests (1-3, 7)
  → files: `.claude/hooks/test-verification-validator.sh` (new, ~150 lines)

### Wave 3: TDD green — implement validator extension

- **3a:** Extend `.claude/hooks/validators/verification-validator.sh`:
  - Source `lib/git-refs.sh`
  - When `lint_clean=skipped`, evaluate scenarios:
    - Scenario 1: `command -v shellcheck` → not found → ACCEPT_SHELL_SKIP=1
    - Scenario 2: `git diff --name-only $(get_plan_commit_parent)...HEAD` + `git status --short` contains 0 shell files → ACCEPT_SHELL_SKIP=1
  - If accepted, write `evidence.lint_skip_reason` and exit 0
  - Otherwise, preserve existing block
  → produces: smart acceptance logic
  → files: `.claude/hooks/validators/verification-validator.sh` (modified, ~30 lines added)

- **3b:** Extend `.claude/hooks/pre-push-gate.sh` to propagate `lint_skip_reason` as ⚠ marker
  → produces: visible skip rationale in PR/push context
  → files: `.claude/hooks/pre-push-gate.sh` (modified, ~10 lines added)

Tasks 3a and 3b are **sequential** (3b depends on 3a setting `evidence.lint_skip_reason`).

### Wave 4: Verification (parallel) — needs Wave 3

- **4a:** Run `test-verification-validator.sh` — all 7 pass
- **4b:** Integration — for this interaction's verification phase, set `lint_clean=skipped`, run advance → smart acceptance should fire (shellcheck missing in sandbox)
- **4c:** `make lint` clean (PHP files in `verification-validator.sh` not affected, but contractual)

## Estimated artifacts

- Source: 3 files (1 new helper, 2 modified validators)
- Tests: 1 new (`test-verification-validator.sh` ~150 lines)
- Shared interaction log + decision log entry with P1 + P2

## Risks

- `get_plan_commit_parent()` returns wrong commit when plan was amended — mitigation: fallback to `merge-base HEAD origin/main`; test 7 covers
- Shellcheck binary check (`command -v`) may give false-negative on aliased shells — mitigation: also check `which shellcheck` as secondary
- Diff regex `\.(sh|bash)$` misses extensionless scripts — mitigation: audited in spec; if found, extend later
- `lint_skip_reason` leaks into commits — mitigation: session-state.json gitignored

## Commit cadence

- Commit 1 after 1a (helper standalone, mergeable for P3 and future sync DRY)
- Commit 2 after 2a (TDD red)
- Commit 3 after 3a + 3b (TDD green + propagation)
- Wave 4 no commit
