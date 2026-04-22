---
type: plan
date: 2026-04-22
feature: workflow-automation-phase2
spec: docs/superpowers/specs/2026-04-22-workflow-automation-phase2-design.md
tags: [workflow, automation, derivation, parallel-agents, worktree-isolation]
---

# Plan — Workflow Automation Phase 2

Reference: [design spec](../specs/2026-04-22-workflow-automation-phase2-design.md)

## Phase 1 (v0) — All 5 features working with tests

Goal: each feature blocks or derives as designed; all new tests pass;
existing harness does not regress (excepting documented flaky suites).

### [parallel] Wave 1 — 4 agents in worktrees

Each task is independent (disjoint files). Dispatch as background agents with
`isolation: "worktree"`. Wave 1 completes when all 4 agents return.

**A1 — Capture gate carve-out (Feature 1)**
- Files: `.claude/hooks/workflow-engine.sh`, `.claude/hooks/test-workflow-engine.sh`
- Change: in the capture/retrospective phase gate logic, add carve-out:
  if `tool_input.file_path == evidence.execution_log_path` AND file size is
  0 OR file does not exist → allow Write.
- Tests: +2 cases (allow with 0-byte file, block with non-matching path).
- → produces: no more `touch` workaround needed for first-time log creation.

**A2 — Task progress auto-advance (Feature 2a)**
- Files: `.claude/hooks/plan-progress.sh`, `.claude/hooks/test-plan-progress.sh`
- Change: on PostToolUse Edit/Write, if `evidence.task_progress.task_index`
  is populated, match `tool_input.file_path` against each task's `files:`
  declaration (substring match). On first match, set
  `task_progress.current = task_index + 1` and `label = task.label`. Never
  decrement.
- Tests: +2 cases (file matches task → current advances; no match → unchanged).
- → produces: status line auto-reflects plan execution progress.

**A3 — Test suite health tracking (Feature 3)**
- Files: `Makefile`, `docs/knowledge/test-suite-health.md` (new)
- Change:
  - Doc: seed table with 3 known-flaky suites (`test-self-gating` 7/14,
    `test-workflow-engine` 6/29, `test-status-line` unknown count) and expected
    fail counts as of 2026-04-22.
  - Makefile target `test-new-failures`: runs harness, extracts per-suite
    fail counts, compares against `test-suite-health.md`. Exit 0 if counts
    ≤ documented; exit 1 if any suite fails beyond documented threshold.
- Tests: manual smoke (no new regressions → 0; intentional regression → 1).
- → produces: CI-compatible noise-filtered test signal.

**A4 — Classification suggestion (Feature 4)**
- Files: `.claude/hooks/user-prompt-state.sh`, `.claude/hooks/test-status-line.sh`
- Change:
  - `user-prompt-state.sh`: at top of render (before flow branching), if
    `interaction_classification` is null AND `evidence.last_action.tool` is
    `Edit`/`Write` AND `evidence.last_action.file_path` matches framework
    regex, emit `💡 Sugerencia: clasificar como 'full' (edit a <path>)` line.
  - Requires `evidence.last_action` field populated by `auto-evidence.sh`
    (schema addition — done here since it's a single field; full derivation
    of auto-evidence happens in Wave 2).
- Tests: +3 cases in `test-status-line.sh` (framework + null → suggest;
  docs + null → silent; framework + full → silent). If test-status-line is
  known-flaky, isolate the new cases into `test-classify-suggestion.sh` (new).
- → produces: proactive classification prompt when model drifts.

### [serial] Wave 2 — auto-evidence.sh derivations (1 agent, 3 sub-tasks)

All three sub-tasks modify `.claude/hooks/auto-evidence.sh` and its test.
Serialized within one agent to avoid merge conflicts.

**2.1 — `tests_written` from `git diff --stat` (Feature 2b)**
- Files: `.claude/hooks/auto-evidence.sh`, `.claude/hooks/test-auto-evidence.sh`
- Change: in PostToolUse case Edit|Write, if `file_path` matches
  `^backend/tests/` OR `\.test\.` OR `\.spec\.`, recalculate:
  ```
  evidence.tests_written = count of (git diff --name-only + git ls-files --others --exclude-standard)
                           filtered by the test path patterns above
  ```
- Tests: +2 cases (edit to test file → count > 0, edit to src → unchanged).

**2.2 — `spec_path` / `plan_path` autofill (Feature 2c)**
- Change: in PostToolUse case Write (creation), if `file_path` matches
  `docs/superpowers/specs/*.md` AND `evidence.spec_path` is null → set it.
  Idem for `plans/`.
- Tests: +2 cases (spec write → spec_path set; plan write when already set → unchanged).

**2.3 — `lint_clean` from Bash exit code (Feature 2d)**
- Change: in PostToolUse case Bash, if `tool_input.command` matches
  `make\s+(lint|lint-shell)`, read the tool result `exit_code` from the input
  and set `evidence.lint_clean = (exit_code == 0 ? true : false)`.
- Tests: +2 cases (make lint exit 0 → true; make lint exit 1 → false).

### Wave 3 — integration + PR

**3.1 — Live smoke tests**
- Touch an execution log path (simulate) → Wave 1 #1 allows it without
  workaround.
- Edit a task's file declared in the plan → `task_progress.current` advances.
- Edit a test file → `tests_written` increments.
- Write a new spec → `spec_path` auto-sets.
- Run `make lint` (expect 0) → `lint_clean = true`.
- Reset classification to null + edit a framework file → sugerencia aparece
  en el status line.

**3.2 — Pattern graduation verification (Feature 5)**
- Run `bash .claude/hooks/pattern-audit.sh`. Expect 0 candidates.
- If any appear (unlikely — audit was clean in consult), graduate via
  `scripts/graduate.sh <name> --module=<file> --section=<heading> --pattern`.

**3.3 — Full harness + test-new-failures**
- `make test-new-failures` → expect 0 (only known flaky).
- All new test files pass individually.

**3.4 — Commit + PR**
- `make manifest`
- Final commit chore: update manifest
- Create PR with comprehensive description.

## Task Execution Rules

- Each wave completes fully (all tasks + tests) before the next wave starts.
- Wave 1 dispatches 4 background agents concurrently with
  `isolation: "worktree"`. Each agent completes its feature + tests + commits
  inside its worktree. Orchestrator merges on return.
- Wave 2 runs as 1 agent with 3 sequential sub-tasks (same file). Each
  sub-task commits separately.
- Commit per task/sub-task. Push at end of Wave 1 (all 4 merged), Wave 2, and
  Wave 3.

## Task Counter Index

| Task ID | Wave | Files | Agent |
|---------|------|-------|-------|
| 1 | 1 | workflow-engine.sh + test | A1 |
| 2a | 1 | plan-progress.sh + test | A2 |
| 3 | 1 | Makefile + test-suite-health.md | A3 |
| 4 | 1 | user-prompt-state.sh + test | A4 |
| 2b | 2 | auto-evidence.sh + test | (serial in A2-next) |
| 2c | 2 | auto-evidence.sh + test | (serial) |
| 2d | 2 | auto-evidence.sh + test | (serial) |
| 5 | 3 | (audit verification only) | orchestrator |

Total: 7 code tasks + 1 verification step across 3 waves.
Parallel frontier: 4 concurrent (Wave 1).

## Acceptance (copied from spec)

1. Writing `docs/superpowers/execution-logs/<new>.md` for the first time does
   NOT require `touch` via Bash.
2. Editing a file listed in a task → `task_progress.current` advances.
3. Write to `backend/tests/**/*.php` → `tests_written` auto-increments.
4. Write to `docs/superpowers/specs/<new>.md` → `spec_path` auto-sets if null.
5. `make lint` exit 0 → `lint_clean = true` automatically.
6. `make test-new-failures`: 0 when only known flaky; 1 on new regression.
7. Edit to `.claude/hooks/foo.sh` with `classification=null` → status line
   shows `💡 Sugerencia: clasificar como 'full'`.
8. All new tests pass; `test-new-failures` shows no new regressions.

## Estimated Size

| Wave | Duration (wall-clock) | Lines (approx) |
|------|----------------------|----------------|
| 1 (4 parallel) | 30-40 min (bounded by slowest agent) | ~200 |
| 2 (3 serial) | 45-60 min | ~120 |
| 3 | 15-20 min | ~30 (mostly integration) |
| **Total** | **~1.5h - 2h** | **~350** |

Saved vs serial: ~65% wall clock.
