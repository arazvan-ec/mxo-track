# Plan: Automatic Status Line in Every Response

**Spec:** `docs/superpowers/specs/2026-03-23-automatic-status-line-design.md`
**Branch:** `claude/fix-fleet-map-routing-Kllk0`

---

## Goal

Add automatic workflow status visibility in every Claude response via a PostToolUse hook that generates a one-line status file + CLAUDE.md instruction that mandates displaying it.

## File Structure

```
.claude/hooks/workflow-status-line.sh    # NEW — generates status line
.claude/hooks/test-status-line.sh        # NEW — unit tests for the hook
.claude/settings.json                    # EDIT — add PostToolUse matcher
CLAUDE.md                                # EDIT — add mandatory section
backend/.gitignore                       # EDIT — add status-line.txt
```

## Tasks

### Task 1: Create `workflow-status-line.sh` hook

- [ ] Create `.claude/hooks/workflow-status-line.sh`
- Read `session-state.json`
- Generate one-line status to `.claude/workflow-status-line.txt`
- Handle all flows: null, micro, light, explore, debug, full
- Handle deviation active flag
- Always exit 0 (non-blocking)
- Make executable

**Full-flow output format:**
```
📍 full | Brainstorming (2/8) | ✅ consult → 🔄 brainstorm | Pendiente: planning, implementation, verification, capture, retrospective, finalize
```

**Debug-flow phases:** consult → root_cause → pattern_search → fix
Detection via evidence fields: `root_cause_identified`, `pattern_wide_search_done`, `current_phase`

**Simple flows:** `📍 micro | Responder`, `📍 light | Documentar`, `📍 explore | Investigar`

**Deviation:** append ` | ⚠ DESVÍO` when `deviation.active = true`

### Task 2: Create unit tests for the hook

- [ ] Create `.claude/hooks/test-status-line.sh`
- Test null flow → "📍 no flow declared"
- Test micro/light/explore → correct labels
- Test full-flow at each phase (consult through finalize)
- Test debug-flow at each phase
- Test deviation active appends warning
- Test missing state file → graceful fallback
- Run and verify all pass

### Task 3: Register hook in `settings.json`

- [ ] Add PostToolUse entry with empty matcher (matches all tools)
- Timeout: 2 seconds
- Command: absolute path to `workflow-status-line.sh`

### Task 4: Add mandatory instruction to CLAUDE.md

- [ ] Add "Automatic Status Line (mandatory)" section
- Place it after "Automatic Session Context" section (behavioral instruction, inline)
- Include rules: always show, two levels, never skip, fallback text
- Include examples for all flow types

### Task 5: Update .gitignore

- [ ] Add `.claude/workflow-status-line.txt` to `.gitignore`

### Task 6: Verify and commit

- [ ] Run tests
- [ ] Verify hook generates correct output manually
- [ ] Commit all changes
- [ ] Push
