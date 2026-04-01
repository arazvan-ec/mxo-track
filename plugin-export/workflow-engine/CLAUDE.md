# workflow-engine — Behavioral Instructions

This plugin enforces structured workflows for every Claude Code interaction.
Every interaction has structure. The depth scales with the type.

---

## Classify First (before any response)

| Type | Signal | Flow |
|------|--------|------|
| **micro** | "what does X do?", "explain Y" | Consult docs → answer → capture gaps |
| **light** | Edit docs, config | Check overlap → propose → execute → verify |
| **debug** | Error, test failure, unexpected behavior | Consult → root cause → pattern-wide → TDD fix |
| **full** | New feature, refactor, enhancement | Consult → brainstorm → plan → implement → verify → capture → retrospective → finalize |
| **explore** | "audit X", "how does Z work?" | Consult → explore → answer → capture findings |

After classifying, update session-state:
```bash
REPO=$(git rev-parse --show-toplevel 2>/dev/null || pwd)
jq '.flow_type = "<type>"' "$REPO/.claude/session-state.json" > /tmp/ss.json && mv /tmp/ss.json "$REPO/.claude/session-state.json"
```

---

## Full-Flow: The 8 Phases

```
consult → brainstorm → plan → implement → verify → capture → retrospective → finalize
```

Each phase produces something that feeds the next. The workflow engine (hooks) blocks code edits if prior phases aren't completed.

| Phase | Produces | Evidence |
|-------|----------|----------|
| Consult | Past decisions context | decisions_read OR logs_scanned |
| Brainstorm | Spec with design approval | user_turns >= 1, alternatives, approval, spec file |
| Plan | Task breakdown with TDD | plan file with task keywords |
| Implement | Working code via TDD | tests_written > 0 |
| Verify | Green tests + clean lint | tests_passed + lint_clean |
| Capture | Execution log | execution_log_path |
| Retrospective | Decision log entries | (soft) |
| Finalize | Branch strategy | branch_strategy declared |

**Scope change detection:** If the user requests something NOT in the current plan, it's a new interaction. Increment `interaction_id`, reclassify, restart the flow.

---

## Debug-Flow: 4 Phases

```
consult → root_cause → pattern_search → fix
```

Must identify root cause AND search for the pattern across the codebase before writing any fix.

---

## Evidence Before Claims

Before claiming anything is done:
1. **Identify** what command proves the claim
2. **Run** the full command (fresh, not cached)
3. **Read** complete output, check exit code
4. **Only then** make the claim

---

## Display Rules

**Every message must include a progress indicator.**

Messages communicate RESULTS, not process. No narrating intentions ("I'm going to...", "I need to see...").

**Each message includes:** (1) what was completed with concrete data, (2) what's next.

### Progress Headers by Flow

```
💬 [concise answer with concrete data]
📝 Light — [what was completed]
🐛 Debug (phase) — [root cause or fix applied]
🔍 Explore — [what was found]
✅✅🔄⬚⬚⬚⬚⬚ Phase (N/8) — [what was completed]
```

Use emoji prefixes: ✅ completed, 🔄 in progress, ⬚ pending, ❌ failed.

### Status Line

Read `.claude/workflow-status-line.txt` before composing each response. Display its content as the FIRST line.

---

## Commits and Push

### When to commit
- After each file that works (compiles, doesn't break tests)
- After each completed task in a plan
- After writing a test (even if it fails)
- After making a test pass

### When to push
- After each commit (or max 2-3 if part of same logical step)
- **Always** before launching subagents

### Commit format
Prefixes: `feat:`, `fix:`, `refactor:`, `test:`, `docs:`, `chore:`
Short, descriptive. One logical change per commit.

---

## Session State

`.claude/session-state.json` is external memory — it survives context compaction. Update it with `jq` after each phase transition:

```bash
REPO=$(git rev-parse --show-toplevel 2>/dev/null || pwd)
jq '.phase_history += ["<phase>"] | .current_phase = "<next_phase>"' \
  "$REPO/.claude/session-state.json" > /tmp/ss.json && mv /tmp/ss.json "$REPO/.claude/session-state.json"
```

### Task Progress

During implementation, update task_progress for granular status:
```bash
jq '.evidence.task_progress.completed_labels += [.evidence.task_progress.label] | .evidence.task_progress.current = N | .evidence.task_progress.label = "next task"' \
  "$REPO/.claude/session-state.json" > /tmp/ss.json && mv /tmp/ss.json "$REPO/.claude/session-state.json"
```

---

## Deviation Mode

For genuine emergencies only. Requires explicit user confirmation. Converts HARD gates to warnings.

---

## Configuration

The plugin reads `workflow.json` (at repo root or `.claude/workflow.json`) for project-specific settings:

```json
{
  "src_paths": ["src"],
  "test_paths": ["tests"],
  "test_command": "npm test",
  "lint_command": "npm run lint",
  "specs_path": "docs/specs",
  "plans_path": "docs/plans",
  "execution_logs_path": "docs/execution-logs",
  "decisions_log": "docs/decisions/log.md"
}
```

All settings have sensible defaults. Works zero-config for standard project layouts.

---

## Workflow Gates Summary

| Flow | Can edit src/tests | Gate |
|------|-------------------|------|
| micro/light/explore | DENY (must reclassify) | — |
| debug | HARD: needs root_cause + pattern-wide | — |
| full | HARD: needs consult + brainstorm + plan | — |

| Gate level | Behavior |
|-----------|----------|
| HARD | Blocks the action (exit 2) |
| SOFT | Warning, allows continuation (exit 1) |
| DENY | Blocks and asks to reclassify |
