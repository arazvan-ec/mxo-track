# Execution Log — 2026-04-08 — Consolidate PostToolUse Hooks

**Type:** code change (hooks infrastructure)
**Branch:** `claude/add-status-line-feedback-Jc4yX`

## Brainstorming
- Problem: Claude Code web UI showed 3-4 hook notifications per tool call (Edit: 3 PostToolUse + 1 PreToolUse)
- Two approaches evaluated: A) consolidate into single dispatcher, B) just improve statusMessage
- User chose A — consolidation follows pattern from commit 2773ab9 (Bash consolidation)

## Planning
- 3 tasks: create dispatcher script, update settings.json, integration test
- All sequential (each depends on previous)

## Implementation
- Created `post-tool-handler.sh` — dispatcher that calls existing scripts in sequence:
  auto-evidence → plan-persistence → workflow-status-line
- Updated settings.json: replaced 3 PostToolUse entries with 1 (matcher: Read|Write|Edit|Agent)
- Kept post-bash-validator.sh separate (different responsibility)
- statusMessage changed to "📍 Registrando progreso..." (descriptive, unique)

## Verification
- settings.json: valid JSON ✅
- post-tool-handler.sh: executable ✅
- Edit/Read/Agent tool simulations: all exit 0 ✅
- PostToolUse entries reduced: 4 → 2 ✅

## Retrospective

### What worked
- Dispatcher pattern (call existing scripts) was much simpler than full merge (~45 lines vs ~750)
- Pattern from commit 2773ab9 transferred directly

### Lessons
- Consolidating hooks via dispatcher is the right pattern for this repo — keeps individual scripts testable while reducing UI noise
- The stdin-once problem (each script reads stdin) is solved by saving input and piping to each sub-script

### Metrics
- Files changed: 4 (1 new script, 1 settings update, 1 spec, 1 plan)
- UI notifications per Edit: 4 → 2 (50% reduction)
- UI notifications per Read: 2 → 1 (50% reduction)
