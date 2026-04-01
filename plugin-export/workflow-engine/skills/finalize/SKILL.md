---
name: finalize
description: Execute the finalize phase - declare branch strategy and close
user_invocable: true
---

# Finalize Phase

Close the workflow cycle by declaring a branch strategy and executing it.

## Branch Strategies

| Strategy | When | Action |
|----------|------|--------|
| **merge** | Feature complete, reviewed | Merge to main |
| **pr** | Needs review from others | Create pull request |
| **keep** | Work in progress, will continue | Keep branch, push |
| **discard** | Experiment, not needed | Delete branch |

## Actions

1. Verify all prior phases are complete (tests pass, lint clean, execution log written)
2. Ask the user which strategy to use
3. Update evidence:

```bash
REPO=$(git rev-parse --show-toplevel 2>/dev/null || pwd)
jq '.evidence.branch_strategy = "<strategy>" | .phase_history += ["finalize"] | .current_phase = "finalize"' \
  "$REPO/.claude/session-state.json" > /tmp/ss.json && mv /tmp/ss.json "$REPO/.claude/session-state.json"
```

4. Execute the strategy

## Evidence Required

- `branch_strategy` declared (one of: merge, pr, keep, discard)
