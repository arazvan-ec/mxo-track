---
name: workflows:quick
description: "Lightweight execution path for simple tasks. Skips full planning pipeline, maintains TDD and atomic commits."
argument_hint: <task-description> [--no-tdd]
---

# Multi-Agent Workflow: Quick Mode

Fast-track execution for simple, well-understood tasks that don't need the full planning pipeline.

## Philosophy

> "Not every nail needs a sledgehammer."
> — Inspired by GSD Quick Mode

The full workflow (ROUTE→PLAN→WORK→VALIDATE→REVIEW→COMPOUND) is designed for features with architectural impact. But many tasks are simple: fix a typo, rename a variable, add a field, update a dependency. Quick Mode provides a lightweight path that maintains quality guarantees (TDD, atomic commits, state tracking) without the overhead of multi-phase planning.

## When to Use Quick Mode

```
USE QUICK MODE when ALL of these are true:
  ✅ Task affects ≤ 3 files
  ✅ No architectural decisions required
  ✅ No new entities, endpoints, or business rules
  ✅ No dimensional complexity increase (doesn't add new external APIs, consumers, or async requirements)
  ✅ Clear "done" criteria (you know when it's finished)
  ✅ Low risk (not auth, security, payment, data migration)

DO NOT use Quick Mode for:
  ❌ New features with multiple components
  ❌ Tasks touching auth/, security/, payment/ paths
  ❌ Tasks requiring multi-agent coordination
  ❌ Tasks where scope is unclear
  ❌ Refactoring that changes architecture
  ❌ Tasks that change the project's dimensional profile (adding external API, new consumer platform, introducing async)
```

## How Quick Mode Is Triggered

1. **Direct invocation**: User runs `/workflows:quick "fix email validation regex"`
2. **Route suggestion**: `/workflows:route` classifies task as "simple" and suggests Quick Mode
3. **User override**: User requests Quick Mode for any task they consider simple

## Execution Flow

```
/workflows:quick "task description"
    │
    ├── Step 1: Inline Assessment (no separate files)
    │   ├── Identify files to modify (max 3)
    │   ├── Define success criteria (1-3 items)
    │   └── Verify Quick Mode is appropriate
    │
    ├── Step 2: TDD Execution
    │   ├── Write/update test FIRST (unless --no-tdd for non-code tasks)
    │   ├── Implement change
    │   ├── Run tests
    │   └── Bounded Correction Protocol if tests fail (max 5 iterations for quick)
    │
    ├── Step 3: Atomic Commit
    │   ├── Stage changed files
    │   ├── Commit with descriptive message
    │   └── Tag as quick-mode in commit
    │
    └── Step 4: State Update
        ├── Update tasks.md (minimal entry)
        └── Done
```

## Step 1: Inline Assessment

No separate planning files. Assessment happens in-context:

```markdown
## Quick Mode Assessment

**Task**: ${TASK_DESCRIPTION}
**Files to modify**: [list, max 3]
**Success criteria**:
  1. [criterion 1]
  2. [criterion 2]
**Quick Mode appropriate?**: YES/NO

IF NO (scope too large, architectural impact, sensitive area):
  STOP. Recommend: /workflows:route → /workflows:plan
  Explain WHY Quick Mode is not appropriate.
```

## Step 2: TDD Execution

```
QUICK TDD CYCLE:

1. Write/update test for the change
2. Run test → confirm it fails (Red)
3. Implement the minimum change
4. Run test → confirm it passes (Green)
5. Quick refactor if needed

BOUNDED CORRECTION PROTOCOL (max 5 iterations for quick):
  Max 5 iterations (not the standard scale-adaptive limits — quick tasks should resolve fast)
  If stuck after 5: STOP, suggest full workflow instead
```

**Flag `--no-tdd`**: For non-code tasks (documentation, config changes, dependency updates) where tests don't apply.

## Step 3: Atomic Commit

```bash
git add ${CHANGED_FILES}
git commit -m "quick: ${TASK_DESCRIPTION}

Files: ${FILE_LIST}
Mode: quick-mode"
```

## Step 4: State Update (Minimal)

If a `tasks.md` exists for the current feature, add a quick-mode entry:

```markdown
### Quick Mode Tasks
| Task | Date | Files | Status |
|------|------|-------|--------|
| Fix email validation regex | 2026-02-11 | src/Domain/ValueObject/Email.php | COMPLETED |
```

If no feature context exists, no state file is created (truly ad-hoc task).

## Flags

| Flag | Default | Description |
|------|---------|-------------|
| `--no-tdd` | `false` | Skip TDD cycle (for non-code tasks: docs, config, deps) |

## Integration with Full Workflow

- Quick Mode tasks are logged in `tasks.md` if a feature context exists
- Quick Mode tasks can be captured in `/workflows:compound` if they reveal patterns
- If Quick Mode assessment determines task is too complex → redirect to full workflow
- Quick Mode does NOT trigger multi-agent review (task is too small)

## Escape to Full Workflow

```
IF during Quick Mode execution:
  - Scope grows beyond 3 files
  - Unexpected architectural impact discovered
  - Tests reveal deeper issues

THEN:
  1. Commit what you have so far (partial progress)
  2. NOTIFY user: "This task is more complex than expected. Recommend full workflow."
  3. Suggest: /workflows:route → /workflows:plan
```

## Examples

```bash
# Fix a bug
/workflows:quick "Fix null pointer in UserService.getEmail() when user has no email set"

# Update a dependency
/workflows:quick "Update symfony/security-bundle from 6.3 to 6.4" --no-tdd

# Add a field
/workflows:quick "Add 'phone' optional field to User entity and migration"

# Documentation
/workflows:quick "Update API docs for POST /api/users to include phone field" --no-tdd
```

## Error Recovery

- **Scope exceeds Quick Mode limits**: If more than 3 files need changes or architectural impact is discovered, commit partial progress and escalate to `/workflows:route` → `/workflows:plan`.
- **BCP exhausted (5 iterations)**: Quick tasks should resolve fast. If stuck after 5 iterations, the task is likely more complex than estimated. STOP, commit what works, and recommend full workflow.
- **No test infrastructure for TDD**: Use `--no-tdd` flag only for non-code tasks. For code tasks without test infrastructure, document the gap and recommend setting up tests before proceeding.
- **Task assessment unclear**: If Quick Mode assessment cannot determine whether the task is appropriate, default to full workflow (safer choice).

---

## Compound Effect

Quick Mode tasks, while small, contribute to compound engineering:
- Patterns from quick fixes inform future planning
- Recurring quick fixes may indicate a deeper architectural issue
- Quick fixes that escalate to full workflow reveal scope estimation gaps
