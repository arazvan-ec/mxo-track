---
name: plan
description: Execute the planning phase - create v0 and mature implementation plan
user_invocable: true
---

# Planning Phase

Create a concrete implementation plan with two phases: v0 (simplest working implementation) and Phase 2 (mature architecture).

## Plan Structure

```markdown
# Plan — [Feature Name]

**Spec:** [path to spec]

## Phase 1 (v0): Working implementation

### [parallel] Tarea 1a + 1b — [Description]
- **1a:** [Task with file paths]
- **1b:** [Task with file paths]

### Tarea 2 (depends on 1a + 1b)
- [Task with file paths]

## Phase 2 (Mature): Refinements
- [Refactoring tasks]
```

## Rules

- **Two phases:** v0 proves the solution works. Phase 2 refactors toward production quality.
- **TDD per task:** Write test → verify fail → implement → verify pass → commit
- **Never create a separate "add tests" task.** Tests are integral to each task via TDD.
- **Parallel execution:** Identify which tasks can run in parallel. Group them in `[parallel]` blocks.
- **Specific file paths:** Every task must reference exact files to create/modify.

## Evidence Required

- `plan_path` set to a file >= 300B with task keywords

## Transition

```bash
REPO=$(git rev-parse --show-toplevel 2>/dev/null || pwd)
jq '.phase_history += ["planning"] | .current_phase = "implementation"' \
  "$REPO/.claude/session-state.json" > /tmp/ss.json && mv /tmp/ss.json "$REPO/.claude/session-state.json"
```

Initialize task progress:
```bash
jq '.evidence.task_progress = {"current": 1, "total": N, "label": "first task name", "completed_labels": []}' \
  "$REPO/.claude/session-state.json" > /tmp/ss.json && mv /tmp/ss.json "$REPO/.claude/session-state.json"
```
