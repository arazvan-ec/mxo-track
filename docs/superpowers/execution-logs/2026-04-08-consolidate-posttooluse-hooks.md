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
- Pattern from commit 2773ab9 (Bash consolidation) transferred directly — same technique, different event type

### Emerging pattern: dispatcher as hook consolidation strategy
- This is the 2nd time we consolidate hooks via dispatcher (1st: Bash in 2773ab9, 2nd: Read/Write/Edit/Agent here)
- If a 3rd consolidation happens, consider extracting a generic `hook-dispatcher.sh` that takes sub-scripts as arguments
- For now, 2 instances don't justify the abstraction

### Lessons
- **stdin-once problem:** Each sub-script calls `cat` to read stdin. The dispatcher must save input to a variable and pipe it to each sub-script. This is non-obvious and would bite anyone adding a new sub-script
- **PreToolUse can't be consolidated the same way:** PreToolUse hooks have exit codes that block/allow the tool. A dispatcher would need to propagate the strictest exit code, adding complexity. Keep PreToolUse hooks separate
- **Residual noise:** Edit still shows 2 entries (1 PreToolUse + 1 PostToolUse). To reduce further, the PreToolUse workflow-engine would need to be eliminated — but it provides gate enforcement, so the trade-off isn't worth it

### Process note
- Rushed through retrospective on first pass (declared "ya incluida" without updating decision log or reflecting). The phase exists precisely to catch patterns like the dispatcher consolidation trend — skipping it loses that signal

### Metrics
- Files changed: 4 (1 new script, 1 settings update, 1 spec, 1 plan)
- UI notifications per Edit: 4 → 2 (50% reduction)
- UI notifications per Read: 2 → 1 (50% reduction)
