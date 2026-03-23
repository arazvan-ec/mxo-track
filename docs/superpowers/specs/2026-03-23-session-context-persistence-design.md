# Design Spec — Session Context Persistence

**Date:** 2026-03-23
**Bounded context:** Pragmatic (tooling/hooks)
**Problem:** When resuming or starting a new session, Claude has zero context about previous work. This causes lost continuity — as demonstrated on 2026-03-23 when Claude tried to merge a branch without knowing the original problem.

## Approach

**Approach C:** Output directo en `session-start.sh` + campo `last_work_summary` en `session-state.json`.

Combines immediate stdout context (Claude sees it at startup) with a persistent summary field that survives state resets across days.

## Existing Functionality Inventory

| Element | Current behavior |
|---------|-----------------|
| `session-start.sh` | Resets `session-state.json` if day changes. No context output. |
| `session-state.json` | Flow state only. No memory of previous sessions. |
| `workflow-engine.sh` | PreToolUse gate. Not involved. |
| `debug-validator.sh` | Debug-flow gate. Not involved. |
| CLAUDE.md "On-Demand Session Context" | Says to consult git log on first interaction — but Claude doesn't do it if unaware of prior work. |
| Execution logs | Exist in `docs/superpowers/execution-logs/` but not surfaced automatically. |

## Omission Decisions

| Element | Decision | Justification |
|---------|----------|---------------|
| `session-start.sh` | Transform | Add preservation logic + stdout output |
| `session-state.json` schema | Transform | New `last_work_summary` field |
| `workflow-engine.sh` | No change | Not involved |
| `debug-validator.sh` | No change | Not involved |
| CLAUDE.md "On-Demand Session Context" | Transform | Update to reflect hook now shows context |
| Execution logs | Read only | Hook references most recent by name and shows preview |

## Detailed Design

### 1. `session-start.sh` — New Day Flow

Before resetting state:

1. Read existing `session-state.json` — extract `flow_type`, `current_phase`, `session_date`
2. Capture git context:
   - `git log --oneline -10` (last 10 commits for full picture)
   - `git branch --show-current`
   - Recently merged branches to main: `git branch --merged main` filtered to `claude/*`
3. Find most recent execution log in `docs/superpowers/execution-logs/` and read first 5-6 lines (type, branch, phase info)
4. Build `last_work_summary` object
5. Reset state with `last_work_summary` preserved
6. Output detailed context summary to stdout

### 2. `session-start.sh` — Same Day Flow (Resume)

Currently exits immediately with no output. Change to:

1. Keep existing state (no reset)
2. **Output context summary to stdout** — same format as new day but marked as "Resume: yes"
3. This solves the exact problem from today: resuming mid-session with zero context

### 3. `last_work_summary` Schema

```json
{
  "last_work_summary": {
    "previous_date": "2026-03-22",
    "previous_branch": "claude/fix-admin-routing-gzlxm",
    "previous_flow": "full",
    "previous_phase": "implementation",
    "recent_commits": [
      "af76545 chore: update codebase manifest",
      "7639c65 feat: enforce workflow gates on all flows",
      "0fe3325 chore: update codebase manifest",
      "e8c8e19 docs: add Pattern-Wide Investigation",
      "185725d chore: update codebase manifest"
    ],
    "merged_branches": ["claude/fix-admin-routing-gzlxm"],
    "last_execution_log": {
      "file": "2026-03-22-gmail-unified-menu.md",
      "preview": "# Execution Log — 2026-03-22 — Gmail Unified Menu\n**Type:** feature\n**Branch:** `claude/...`"
    }
  }
}
```

### 4. Stdout Output Format (Detailed)

```
=== SESSION CONTEXT ===
Date: 2026-03-23 | Resume: no (new day)
Branch: claude/fix-admin-routing-gzlxm
Previous session: 2026-03-22, flow=full, phase=implementation

Recent commits (last 10):
  af76545 chore: update codebase manifest
  7639c65 feat: enforce workflow gates on all flows
  0fe3325 chore: update codebase manifest
  e8c8e19 docs: add Pattern-Wide Investigation
  185725d chore: update codebase manifest
  5f94bb3 fix: add missing index route to TestRoutingController
  7ba058d Merge pull request #141
  fc7950b chore: update codebase manifest
  979d74f chore: delete deprecated _sidebar_content.html.twig
  2baef99 refactor: NavigationSidebar consumes /api/navigation

Recently merged branches (claude/*):
  claude/fix-admin-routing-gzlxm

Last execution log: 2026-03-22-gmail-unified-menu.md
  # Execution Log — 2026-03-22 — Gmail Unified Menu
  **Type:** feature
  **Branch:** `claude/gmail-unified-menu-...`
  ---
  ### Phase: Brainstorming
=== END CONTEXT ===
```

~20 lines of actionable context. Claude sees branch, commits, what was completed, and what phase work was in.

### 5. CLAUDE.md Update

In "On-Demand Session Context" section:
- Note that SessionStart hook now outputs context automatically
- Change the "Primera interaccion de la sesion" row to say context is provided by the hook — manual consultation is a fallback only
- Add `last_work_summary` to the session-state.json field documentation

### 6. Edge Cases

- **No previous state file:** Skip preservation, output only git context
- **No execution logs exist:** Omit that section from output
- **No merged claude/* branches:** Omit that section
- **Git not available / not a repo:** Output warning, continue with state-only context
- **Very first session ever:** Full output with "(no previous session)" for the previous session line

## Alternatives Discarded

- **Approach A (output only):** Simpler but no memory between days — if hook runs but Claude's context is compressed, the summary is lost forever.
- **Approach B (file-based):** `.claude/session-context.md` adds a file that can desync. `session-state.json` is already the single source of truth for session data.
- **Concise output (5-8 lines):** Only branch + last commit. Insufficient — today's problem required seeing the 5th commit back to understand context. Detailed output is cheap and prevents this.

## Brainstorming Decisions

1. **Output volume:** Detailed (~20 lines) — concise would miss commits further back that provide essential context
2. **Execution log preview:** Show first 5-6 lines (type, branch, phase) — not just filename
3. **Branch filter:** Only `claude/*` branches merged to main — unmerged branches may be temporary/immature work
