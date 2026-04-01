---
name: classify
description: Classify the current interaction type and set the workflow flow_type
user_invocable: true
---

# Classify Interaction

Classify the current user request into one of these flow types and update session-state accordingly.

## Flow Types

| Type | Signal | Flow |
|------|--------|------|
| **micro** | "what does X do?", "explain Y" | Consult docs → answer → capture gaps |
| **light** | Edit docs, knowledge modules | Check overlap → propose → execute → verify |
| **debug** | Error, test failure, unexpected behavior | Consult → root cause → pattern-wide → TDD fix |
| **full** | New feature, refactor, enhancement | Consult → brainstorm → plan → implement → verify → capture → retrospective → finalize |
| **explore** | "audit X", "how does Z work?" | Consult manifest → explore → answer → capture findings |

## Actions

1. Read the user's message and classify the interaction
2. Update session-state.json:

```bash
REPO=$(git rev-parse --show-toplevel 2>/dev/null || pwd)
jq '.flow_type = "<type>" | .current_phase = "<initial_phase>" | .interaction_id += 1 | .evidence.interaction_id = (.interaction_id + 1)' \
  "$REPO/.claude/session-state.json" > /tmp/ss.json && mv /tmp/ss.json "$REPO/.claude/session-state.json"
```

Initial phases by flow:
- **micro**: `null` (no phases)
- **light**: `null` (no phases)
- **explore**: `null` (no phases)
- **debug**: `"consult"`
- **full**: `"consult"`

3. Communicate the classification to the user with the appropriate display header
