---
name: verify
description: Execute the verification phase - run tests and linter
user_invocable: true
---

# Verification Phase

Evidence before claims. "Should work" is not evidence.

## Actions

1. **Run tests** — Use the configured test command
2. **Read complete output** — Check exit code, don't just grep for "OK"
3. **Run linter** — Use the configured lint command
4. **Update evidence** — The auto-evidence hook handles this automatically when Bash commands complete

## Evidence Required

- `tests_passed = true` (HARD gate)
- `lint_clean = true` (HARD gate)

## Transition

```bash
REPO=$(git rev-parse --show-toplevel 2>/dev/null || pwd)
jq '.phase_history += ["verification"] | .current_phase = "capture"' \
  "$REPO/.claude/session-state.json" > /tmp/ss.json && mv /tmp/ss.json "$REPO/.claude/session-state.json"
```

## Display

```
🧪 Verification — Tests: ✅ | Lint: ✅ | [N] tests, 0 new failures
```
