# Implementation Plan — Session Context Persistence

**Goal:** Enhance `session-start.sh` to output detailed context at startup and persist `last_work_summary` across day resets.
**Spec:** `docs/superpowers/specs/2026-03-23-session-context-persistence-design.md`
**Files affected:** 2 — `.claude/hooks/session-start.sh`, `CLAUDE.md`

---

## Task 1: Add context output function to session-start.sh

**File:** `.claude/hooks/session-start.sh`

Add a `output_context()` function that gathers and prints:
- Current date + resume status
- Current branch (`git branch --show-current`)
- Previous session info from `session-state.json` (date, flow, phase)
- Last 10 commits (`git log --oneline -10`)
- Merged `claude/*` branches (`git branch --merged main 2>/dev/null | grep 'claude/'`)
- Most recent execution log name + first 6 lines preview

Output format:
```
=== SESSION CONTEXT ===
...
=== END CONTEXT ===
```

Edge cases: handle missing git, no execution logs, no merged branches, no previous state.

- [ ] Implement `output_context()` function
- [ ] Verify output manually

## Task 2: Add last_work_summary preservation to session-start.sh

**File:** `.claude/hooks/session-start.sh`

Before resetting state on new day:
1. Extract previous state info: `session_date`, `flow_type`, `current_phase`
2. Build `last_work_summary` JSON object with git context
3. Include it in the fresh state JSON

On same day (resume):
1. Do NOT reset state (existing behavior)
2. DO call `output_context()` (new behavior — currently exits silently)

The `last_work_summary` object:
```json
{
  "previous_date": "...",
  "previous_branch": "...",
  "previous_flow": "...",
  "previous_phase": "...",
  "recent_commits": ["...", "..."],
  "merged_branches": ["..."],
  "last_execution_log": { "file": "...", "preview": "..." }
}
```

- [ ] Add preservation logic before state reset
- [ ] Modify same-day flow to output context instead of silent exit
- [ ] Test new day scenario (change session_date to trigger reset)
- [ ] Test resume scenario (same date, verify output appears)

## Task 3: Update CLAUDE.md — On-Demand Session Context section

**File:** `CLAUDE.md`

Update the "On-Demand Session Context" section:
- Note that SessionStart hook now outputs context automatically
- Change "Primera interacción de la sesión" row: context is provided by the hook, manual consultation is fallback
- Add `last_work_summary` field documentation to the session-state.json fields section

- [ ] Update On-Demand Session Context section
- [ ] Update session-state.json fields documentation
- [ ] Verify no contradictions with other CLAUDE.md sections

## Task 4: Verification

- [ ] Run hook manually: `bash .claude/hooks/session-start.sh` — verify output format
- [ ] Test new-day: set `session_date` to yesterday, run hook, verify reset + summary preserved + output
- [ ] Test resume: run hook again (same day), verify no reset + output still appears
- [ ] Test edge case: delete `session-state.json`, run hook, verify graceful handling
- [ ] Test edge case: no execution logs exist, verify section omitted cleanly
