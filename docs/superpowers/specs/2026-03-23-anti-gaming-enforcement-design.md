# Spec — Anti-Gaming Workflow Enforcement

**Date:** 2026-03-23
**Type:** Enhancement to workflow enforcement hooks
**Bounded context:** Pragmatic (tooling/hooks, not production code)

## Problem

Evidence flags in `session-state.json` are "honor system" — Claude can set `tests_written = 1`, `root_cause_identified = true`, etc. without actually doing the work. The workflow engine checks flags but not real artifacts.

## Existing Functionality Inventory

| Element | Location | Status |
|---------|----------|--------|
| workflow-engine.sh | PreToolUse hook for Edit/Write | Active — routes to validators |
| implementation-validator.sh | validators/ | Active — checks plan + TDD via git diff |
| brainstorm-validator.sh | validators/ | Active — checks turns, spec size, keywords |
| debug-validator.sh | validators/ | Active — checks 3 boolean flags |
| post-commit-validator.sh | PostToolUse Bash | Active — commit msg lint + exec log reminder |
| post-push-validator.sh | PostToolUse Bash | Active — manifest auto-run |
| tdd-gate.sh.bak | Disabled | Superseded by implementation-validator |
| full-flow-gate.sh.bak | Disabled | Superseded by workflow-engine + validators |
| commit-msg-lint.sh.bak | Disabled | Superseded by post-commit-validator |
| manifest-auto-run.sh.bak | Disabled | Superseded by post-commit-validator |
| post-commit-reminder.sh.bak | Disabled | Superseded by post-commit-validator |

## Omission Decisions

| Element | Decision | Justification |
|---------|----------|---------------|
| .bak files | Delete | Already superseded, cause confusion |
| workflow-engine.sh | Keep as-is | Routing logic is correct |
| brainstorm-validator.sh | Keep as-is | Already has spec size + keywords + anti-omission checks |
| consult-validator.sh | Keep | Simple flag check is acceptable here |

## Approach (approved by user): Triple-layer enforcement

### Capa 1: Anti-gaming in existing validators

**implementation-validator.sh** — Always verify test files in git, regardless of `tests_written` flag:
- Check git log for test file commits since interaction started (not just working tree)
- If `tests_written > 0` but zero test files changed in git diff + working tree + recent commits → BLOCK with contradiction message
- Allow bypass for test files themselves (already implemented)

**debug-validator.sh** — Require textual evidence, not just boolean:
- `root_cause_identified`: Require `evidence.root_cause_description` (non-empty string, min 20 chars)
- `pattern_wide_search_done`: Require `evidence.pattern_wide_description` (non-empty string, min 20 chars)
- Boolean flags alone are no longer sufficient

### Capa 2: Pre-push gate

New **PreToolUse** hook on Bash that detects `git push` commands and blocks if:
- flow_type is `full` or `debug`
- `tests_passed` is not true in session-state → BLOCK
- No execution log for today exists → WARN (soft, since early pushes of WIP are OK)

### Capa 3: Cleanup

- Delete all 5 `.bak` files (confirmed superseded by reading each one)

## Trade-offs

- **Pro:** Mechanically harder to bypass workflow
- **Pro:** Root cause descriptions create audit trail
- **Con:** Slightly more friction on every debug/full flow (requires writing descriptions)
- **Con:** Pre-push gate adds ~1s to every push command
- **Risk:** False positives if test files are in unusual locations → mitigate with broad glob patterns

## Alternativas descartadas

1. **Full state machine with cryptographic proof** — Over-engineering for bash hooks
2. **Only re-enable .bak files** — They're redundant with current system, would cause double-gating
3. **Post-push only warnings (no pre-push block)** — Too late, push already happened; no teeth
