---
name: deviate
description: Activate deviation mode for emergencies (hotfixes, production outages)
user_invocable: true
---

# Deviation Mode

Deviation mode exists for genuine emergencies (hotfixes, production outages) where waiting for the full flow would cause more harm than skipping it.

**Requires explicit user confirmation before activating.**

## When to Use

- Production is down and needs immediate fix
- Critical security vulnerability discovered
- Time-sensitive hotfix required

## Activation

Only activate after the user explicitly confirms:

```bash
REPO=$(git rev-parse --show-toplevel 2>/dev/null || pwd)
jq '.deviation.active = true
  | .deviation.reason = "<reason from user>"
  | .deviation.skipped_phases = ["<phases to skip>"]
  | .deviation.acknowledged_by_user = true' \
  "$REPO/.claude/session-state.json" > /tmp/ss.json && mv /tmp/ss.json "$REPO/.claude/session-state.json"
```

## Behavior

When `deviation.active = true`:
- HARD gates become SOFT warnings
- All phases can be skipped
- Every response still shows the deviation warning

## Deactivation

After the emergency is resolved, return to normal flow:

```bash
jq '.deviation.active = false | .deviation.reason = null | .deviation.skipped_phases = [] | .deviation.acknowledged_by_user = false' \
  "$REPO/.claude/session-state.json" > /tmp/ss.json && mv /tmp/ss.json "$REPO/.claude/session-state.json"
```
