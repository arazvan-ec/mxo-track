---
name: capture
description: Execute the capture phase - write execution log
user_invocable: true
---

# Capture Phase

Write an execution log documenting what was done, decisions made, and lessons learned.

## Execution Log Template

Save to the configured execution logs path:

```markdown
# Execution Log — YYYY-MM-DD — [Feature Name]

**Type:** [feature/bug fix/refactor]
**Branch:** `branch-name`
**Spec:** `path/to/spec`
**Plan:** `path/to/plan`

## Summary
[1-3 sentences of what was accomplished]

## Phases
- **Consult:** [what was found in decision/execution logs]
- **Brainstorm:** [alternatives considered, design chosen]
- **Plan:** [task breakdown summary]
- **Implementation:** [key decisions, blockers, deviations]
- **Verification:** [test results, lint results]

## Lessons Learned
- [What worked well]
- [What to do differently next time]

## Metrics
- Tasks: N completed / N planned
- Tests: N written, N passing
- Files modified: N
```

## Evidence Required

- `execution_log_path` exists (SOFT gate)

## Transition

```bash
REPO=$(git rev-parse --show-toplevel 2>/dev/null || pwd)
jq '.phase_history += ["capture"] | .current_phase = "retrospective"' \
  "$REPO/.claude/session-state.json" > /tmp/ss.json && mv /tmp/ss.json "$REPO/.claude/session-state.json"
```
