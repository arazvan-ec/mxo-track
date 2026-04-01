---
name: retrospective
description: Execute the retrospective phase - update decision log
user_invocable: true
---

# Retrospective Phase

Reflect on design decisions made during this interaction. If non-trivial decisions were made, add them to the decision log.

## Decision Log Entry Format

```markdown
### [YYYY-MM-DD] Brief context
- **Problem:** What needed solving
- **Decision:** What was chosen and why
- **Alternatives discarded:** What else was evaluated
- **Result:** (fill post-implementation) Did it work? What was learned?
```

## Rules

- Only add entries for non-trivial decisions (new abstraction, new pattern, architecture trade-off)
- When the same lesson appears 3+ times, update the relevant knowledge module
- This is a SOFT gate — the system warns but doesn't block

## Transition

```bash
REPO=$(git rev-parse --show-toplevel 2>/dev/null || pwd)
jq '.phase_history += ["retrospective"] | .current_phase = "finalize"' \
  "$REPO/.claude/session-state.json" > /tmp/ss.json && mv /tmp/ss.json "$REPO/.claude/session-state.json"
```
