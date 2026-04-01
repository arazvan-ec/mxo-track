---
name: brainstorm
description: Execute the brainstorm phase - explore, propose alternatives, write spec
user_invocable: true
---

# Brainstorm Phase

Brainstorming is preventive QA. Every code change goes through this, no matter how "simple."

## Checklist

1. **Consult past decisions** — Declare what you found in the consult phase
2. **Classify bounded context** — Is this critical (DDD pure) or pragmatic? Declare it.
3. **Explore project context** — Check files, docs, recent commits
4. **Inventory existing functionality** — Enumerate what exists in the affected area. Every element gets an explicit decision: Include / Omit / Transform. No silent omissions.
5. **Ask clarifying questions** — One at a time. Multiple choice when possible.
6. **Propose 2-3 approaches** — With trade-offs and recommendation
7. **Present design, get approval** — Section by section
8. **Write spec** — Save to the configured specs path

## Spec Template

```markdown
# Spec — [Feature Name]

**Date:** YYYY-MM-DD
**Type:** [feature/bug fix/refactor]

## Problem
[What needs solving]

## Existing Functionality Inventory
[List of existing elements]

## Omission Decisions
| Element | Decision | Justification |

## Design
[Proposed solution]

## Risks
[What could go wrong]
```

## Evidence Required

- `user_turns >= 1` (HARD) — at least 1 round of dialog
- `user_turns >= 3` (SOFT warning) — recommended depth
- `alternatives_proposed = true`
- `user_approved = true`
- `spec_path` set to a file >= 500B

## Transition

```bash
REPO=$(git rev-parse --show-toplevel 2>/dev/null || pwd)
jq '.phase_history += ["brainstorming"] | .current_phase = "planning"' \
  "$REPO/.claude/session-state.json" > /tmp/ss.json && mv /tmp/ss.json "$REPO/.claude/session-state.json"
```
