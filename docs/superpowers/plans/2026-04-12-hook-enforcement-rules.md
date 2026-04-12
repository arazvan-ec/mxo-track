# Plan — Hook Enforcement Rules

**Date:** 2026-04-12
**Branch:** `claude/add-customer-filters-ev8cG`

## Phase 1 (v0)

### [parallel] Wave 1: Quick wins (5-8) — extensiones simples

**Task 5 — Manifest check in pre-push-gate.sh**
- Add manifest modified check after existing HARD gates
- SOFT warning (not blocking) if `codebase-manifest.md` not in diff
- File: `.claude/hooks/pre-push-gate.sh`

**Task 6 — Uncommitted changes check for Agent dispatch**
- Add Agent check in `workflow-engine.sh` (new matcher section)
- `git status --porcelain` → DENY if output non-empty
- Exclude `subagent_type: Explore` from block
- File: `.claude/hooks/workflow-engine.sh`

**Task 7 — Ephemeral artifact warning in auto-evidence.sh**
- Detect Write to `/tmp/` or ephemeral paths with spec/plan keywords
- SOFT warning via systemMessage
- File: `.claude/hooks/auto-evidence.sh`

**Task 8 — Deploy command tracking in auto-evidence.sh + pre-push-gate.sh**
- auto-evidence: track `npm run build`, `make lint` in `.evidence.verified_commands[]`
- pre-push: SOFT warning if canonical commands missing from `verified_commands`
- Files: `.claude/hooks/auto-evidence.sh`, `.claude/hooks/pre-push-gate.sh`

### [parallel] Wave 2: Critical gates (2-4)

**Task 2 — Fresh evidence timestamps**
- auto-evidence: save `.evidence.tests_ran_at` / `.evidence.lint_ran_at` with ISO timestamp
- pre-push: verify timestamps exist and are from current session date
- Files: `.claude/hooks/auto-evidence.sh`, `.claude/hooks/pre-push-gate.sh`

**Task 3 — Deviation criteria validation**
- In phase-advance.sh: when deviation activation detected, run programmatic checks
- Count changed lines via `git diff --stat`
- Scan for new Route attributes in changed files
- Verify file:line reference in deviation reason
- File: `.claude/hooks/phase-advance.sh`

**Task 4 — TDD task isolation check**
- In brainstorm-validator.sh: scan plan for standalone test tasks
- SOFT warning (exit 1) for isolated "add tests" tasks
- File: `.claude/hooks/validators/brainstorm-validator.sh`

### Wave 3: Verification
- `bash -n` on all modified hook scripts
- Test each enforcement manually with mock state
