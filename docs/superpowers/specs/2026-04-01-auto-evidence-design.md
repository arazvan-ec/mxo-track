# Spec: Auto-Evidence Detection for Workflow Status

**Date:** 2026-04-01
**Type:** Enhancement (developer tooling)
**Approach:** A — Separate `auto-evidence.sh` + enhance `user-prompt-state.sh`

## Problem

The workflow status line system generates rich, detailed status messages, but only
when session-state.json evidence fields are populated. Currently, Claude must manually
run `jq` commands to update each of the ~12 evidence fields. In practice, Claude often
forgets or skips these updates, resulting in status lines with empty/minimal evidence.

## Design

### Component 1: `auto-evidence.sh` (new PostToolUse hook)

**Trigger:** PostToolUse, all tools (runs BEFORE `workflow-status-line.sh`)

**Detection rules:**

| Tool | Input pattern | Evidence update |
|------|--------------|-----------------|
| Read | `file_path` contains `docs/decisions/log.md` | `decisions_read = true` |
| Read | `file_path` contains `docs/superpowers/execution-logs/` | `logs_scanned = true` |
| Write/Edit | `file_path` matches `docs/superpowers/specs/*.md` | `spec_path = <file_path>` |
| Write/Edit | `file_path` matches `docs/superpowers/plans/*.md` (not `conversation/`) | `plan_path = <file_path>` |
| Write/Edit | `file_path` matches `docs/superpowers/execution-logs/*.md` | `execution_log_path = <file_path>` |
| Write/Edit | `file_path` matches `backend/tests/*` | `tests_written += 1` |
| Bash | `command` contains `phpunit` and tool succeeded | `tests_passed = true` |
| Bash | `command` contains `phpunit` and tool failed | `tests_passed = false` |
| Bash | `command` contains `make lint` or `php -l` and succeeded | `lint_clean = true` |
| Bash | `command` contains `make lint` or `php -l` and failed | `lint_clean = false` |

**Environment variables used:**
- `CLAUDE_TOOL_NAME` — tool name (Read, Write, Edit, Bash)
- `CLAUDE_TOOL_INPUT_FILE_PATH` — for Read/Write/Edit
- `CLAUDE_TOOL_INPUT_COMMAND` — for Bash
- `CLAUDE_TOOL_RESULT_EXIT_CODE` or hook exit context — for Bash success/failure

**Behavior:**
- Non-blocking: always exits 0
- Only updates fields that match — does not clear/reset anything
- Idempotent: setting `decisions_read = true` twice is harmless
- `tests_written` increments per unique file (tracks seen files in tmp to avoid double-counting)

### Component 2: Enhance `user-prompt-state.sh`

**Change:** During brainstorming phase, auto-increment `user_turns` each time the hook fires.

**Logic:**
```
if flow_type == "full" && current_phase == "brainstorming":
    user_turns += 1
    write back to session-state.json
```

**Guard:** Only increment when in brainstorming phase. No-op otherwise.

### Component 3: Update `settings.json`

Add `auto-evidence.sh` as first PostToolUse hook (before `workflow-status-line.sh`)
so evidence is updated before the status line reads it.

## Existing Functionality Inventory

| Element | Decision | Justification |
|---------|----------|---------------|
| `workflow-status-line.sh` | Include (unchanged) | Reads state, renders — no changes needed |
| `user-prompt-state.sh` | Transform | Add user_turns auto-increment |
| `workflow-engine.sh` | Omit | Unrelated (PreToolUse gate) |
| `post-commit-validator.sh` | Omit | Unrelated |
| `settings.json` hooks config | Transform | Add new hook entry |
| `test-status-line.sh` | Include | Add tests for auto-evidence |

## Non-auto-detectable fields (unchanged)

These still require manual `jq` by Claude:
- `alternatives_proposed` — requires judgment
- `user_approved` — requires judgment
- `root_cause_identified` / `pattern_wide_search_done` — debug flow analysis
- `branch_strategy` — explicit decision

## Risks

- **Hook ordering:** `auto-evidence.sh` must run before `workflow-status-line.sh`. Settings.json array order determines execution order.
- **Performance:** Each PostToolUse adds ~50ms. The script is simple jq reads/writes, should be fast.
- **Race conditions:** Only one hook runs at a time per tool call, so no concurrent writes.
