---
name: consult
description: Execute the consult phase - read decision logs and execution logs
user_invocable: true
---

# Consult Phase

Read past decisions and execution logs to inform the current task. This phase prevents repeating mistakes and ensures continuity.

## What to Read

1. **Decision log** — Check the configured `decisions_log` path (default: `docs/decisions/log.md`)
2. **Recent execution logs** — Check the configured `execution_logs_path` directory for recent entries
3. **Session context** — Review `last_work_summary` in session-state.json if available

## Evidence Required

- `decisions_read = true` OR `logs_scanned = true` (HARD gate)

## Actions

1. Read the decision log file
2. Scan recent execution logs (last 3-5 entries)
3. Note any relevant decisions or lessons for the current task
4. Update session-state evidence (auto-evidence hook handles this automatically when you Read the files)
5. Transition to next phase:

```bash
REPO=$(git rev-parse --show-toplevel 2>/dev/null || pwd)
jq '.phase_history += ["consult"] | .current_phase = "brainstorming"' \
  "$REPO/.claude/session-state.json" > /tmp/ss.json && mv /tmp/ss.json "$REPO/.claude/session-state.json"
```

## Display

Start your response with:
```
✅ Consult (1/8) — [N] decision logs relevantes, [N] execution logs recientes
🔄 Brainstorm (2/8) — [next action]
```
