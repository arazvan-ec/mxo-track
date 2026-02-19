# Session Continuity Guide

**Version**: 3.0 (Provider-aware, checkpoint-based)
**Last Updated**: 2026-02-15

---

## How State Persists

Session continuity relies on four persistence mechanisms:

| Mechanism | What It Stores | When Updated |
|-----------|---------------|-------------|
| **tasks.md** | Workflow State, role statuses, task progress, resume point, decision log | After every task and phase |
| **Git commits** | Code changes, checkpoints | At each checkpoint via `/multi-agent-workflow:checkpoint` |
| **OpenSpec files** | Specs, design docs, proposal | After each planning phase |
| **scratchpad.md** | Working notes, hypotheses, blockers, context breadcrumbs | During active work (ephemeral, per-feature) |

All four are durable — they survive session ends, crashes, and context compaction.

### Scratchpad Pattern

Each feature can have an optional `scratchpad.md` in `openspec/changes/${FEATURE_ID}/`:

- **Purpose**: External memory for the active role. Prevents losing context during long sessions or after compaction.
- **Template**: `core/templates/scratchpad-template.md`
- **When to create**: At the start of any phase expected to be complex or multi-session
- **When to read**: Always read on session resume (Step 1 of Resuming a Session)
- **Lifecycle**: Created during work, reviewed during self-review, archived by `/workflows:compound`

The scratchpad is NOT a deliverable — it's a thinking aid. It replaces the need for non-existent snapshot/restore commands by providing persistent context breadcrumbs.

---

## Provider-Aware Thresholds

Thresholds depend on the active `context_management` provider (see `core/providers.yaml`):

| Signal | Standard (Opus 4.5) | Advanced (Opus 4.6+) |
|--------|---------------------|----------------------|
| Compact at capacity | 70% | 85% |
| Max files read | 20 | 50 |
| Max session duration | 2 hours | 4 hours |
| Max messages | 50 | 150 |
| Checkpoint frequency | Every 30-45 min | At milestones only |

**Provider detection**: Read `core/providers.yaml` → `providers.context_management`. If `auto`, detect tier from model identity (advanced = Opus 4.6+, standard = everything else).

### The Capacity Rule

**Don't wait for auto-compact at 95%.** When context reaches the threshold:

1. **Option A**: Run `/compact` to summarize and reduce context
2. **Option B**: Create a checkpoint and start a fresh session

With the **compaction-aware provider** (Opus 4.6+), the Compaction API auto-summarizes server-side, so checkpoints are primarily for milestones and role handoffs, not context exhaustion.

---

## Resuming a Session

When starting a new session to continue previous work:

```
1. Read tasks.md → identify current Workflow State
2. Read scratchpad.md (if exists) → re-orient with context breadcrumbs
3. Read git log --oneline -10 → understand recent progress
4. Read the resume point in tasks.md → know which task/phase is next
5. Read the relevant openspec/changes/<feature>/ files for context
6. Continue from where the previous session left off
```

If tasks.md shows a role as `IN_PROGRESS`, check the Resume Point section for:
- Last completed task
- Currently working on (may be partially done)
- Next task after current
- Files to read on resume

---

## Creating a Checkpoint

Use `/multi-agent-workflow:checkpoint` to persist state at milestones:

```bash
/multi-agent-workflow:checkpoint ${ROLE} ${FEATURE_ID} "Completed ${UNIT}"
```

### When to Checkpoint

| Trigger | Rationale |
|---------|-----------|
| Completing a task | Atomic progress marker |
| Before major changes | Safe rollback point |
| End of work session | Resume tomorrow without loss |
| Before role handoff | Enable smooth transitions |
| Context getting heavy | Create save point before `/compact` |

### What Gets Saved

A checkpoint creates a git commit containing:
- Updated tasks.md (Workflow State + task progress)
- All code changes for the completed task
- Updated openspec/ files if applicable

---

## Token-Efficient Habits

- Use `grep` to find what you need before reading full files
- Read specific line ranges when possible
- Filter command outputs (`--oneline`, `--stat`)
- Avoid re-reading files already in context
- Disable unused MCP servers (they consume context even idle)

---

## Cross-Role Handoffs

When one role completes and another begins:

1. Completing role creates a checkpoint with descriptive message
2. New role reads tasks.md to understand current state
3. New role reads relevant openspec/ files for context
4. New role continues from the resume point in tasks.md

---

## What to Do When Resuming Fails

**tasks.md missing or corrupted:**
- Check `git log` for the last checkpoint commit
- Reconstruct state from git history and openspec/ files
- Re-create tasks.md manually if needed

**Mid-task interruption (task was IN_PROGRESS):**
- Read the partially completed code via `git diff`
- Decide: continue from partial state or revert to last checkpoint
- If reverting: `git checkout -- .` to discard uncommitted changes

**Stale state (days old):**
- Run `git log --since="<last-session-date>"` to see what changed
- Re-read current state files before continuing
- Create a fresh checkpoint after re-orientation

---

## Quick Reference

```bash
# Context management
/context              # Check current token usage
/compact              # Summarize and reduce context
/clear                # Fresh start (loses current context)

# Checkpoints
/multi-agent-workflow:checkpoint ${ROLE} ${FEATURE} "message"

# Status check
/workflows:status ${FEATURE}

# Resume flow
# 1. Read tasks.md
# 2. Read git log
# 3. Continue with /workflows:work or /workflows:plan
```

---

**Related Documentation:**
- `CONTEXT_ENGINEERING.md` - Context management strategies
- `CAPABILITY_PROVIDERS.md` - Provider detection and thresholds
- `core/rules/git-rules.md` - Git practices for multi-agent work
