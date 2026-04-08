# Spec — Enhance Status Line (Hybrid A+C)

**Date:** 2026-04-08
**Type:** Infrastructure improvement
**File:** `.claude/hooks/workflow-status-line.sh`

## Problem

The `workflow-status-line.sh` (PostToolUse hook) generates the entire workflow status
on a **single line**. In later phases (4+/8), the completed phase chain with evidence
tags produces lines of 200+ characters that get truncated with "..." in the Claude Code
web/mobile UI. The truncation hides actionable information (needs, pending phases).

## Design: Hybrid A+C (Adaptive Multi-line with Priority Inversion)

### Core Principle

**Actionable info first, history last.** If truncation happens, it loses historical
data (already done), never current status or next actions.

### Format Rules

**Rule 1: Adaptive split based on completed phase count.**
- `CURRENT_INDEX <= 2` → compact (1-2 lines)
- `CURRENT_INDEX > 2` → expanded (2-3 lines)

**Rule 2: Line priority order.**
1. Line 1 (ALWAYS): Flow, phase, index, emoji bar, task progress
2. Line 2 (if needs or tool context): Need + tool suffix
3. Line 3 (if expanded): Completed phases with evidence

### Format Examples

```
# Early phase (0-1 completed) — compact:
📍 full | Brainstorming (2/8) | ✅🔄⬚⬚⬚⬚⬚⬚ | Need: alternatives, approval, spec
  · Grep: route

# Mid phase (3 completed) — expanded:
📍 full | Implementation (4/8) | ✅✅✅🔄⬚⬚⬚⬚ t2/5: Add toggle button
  Need: TDD · Read Entity/Vehicle.php
  ✅ consult(dec+log) → brainstorm(t4,alt,ok) → planning(plan-file)

# Late phase (5 completed) — expanded:
📍 full | Capture (6/8) | ✅✅✅✅✅🔄⬚⬚
  Need: execution log · Read hooks/workflow.sh
  ✅ consult(dec+log) → brainstorm(ok) → plan → impl(t7/7) → verif(tests✓,lint✓)

# Debug flow:
📍 debug | Root_cause (2/4) | ✅🔄⬚⬚ | Need: identify root cause
  · Grep: ErrorHandler

# Simple flows (unchanged):
📍 micro | Responder · Read Entity/Vehicle.php
📍 explore | Investigar · Grep: route
```

### Alternatives Considered

### Approach A: Multi-line with priority inversion (pure)
Always multi-line, history on separate line. Trade-off: uses 3 lines even in early
phases where 1 would suffice. Ventaja: consistent format. Desventaja: verbose in
early phases.

### Approach B: Compact 2-line (no history)
Drop completed phase chain entirely, rely on emoji bar. Trade-off: loses evidence
detail. Ventaja: always fits. Desventaja: no visibility into what evidence was
collected per phase.

### Approach C: Adaptive by length threshold
Split based on character count (>120 chars). Trade-off: unpredictable format changes.
Ventaja: optimal space usage. Desventaja: format depends on content length, not
semantic structure.

### Selected: Hybrid A+C
Adaptive split based on **phase count** (semantic, not character count). Multi-line
only when there's enough history to warrant it. Combines A's priority inversion with
C's adaptiveness, using a stable semantic threshold (phase index) instead of fragile
character counting.

## What Changes

| Aspect | Before | After |
|--------|--------|-------|
| Line count | Always 1 | 1-3 depending on phase |
| Info priority | History first, needs last | Needs first, history last |
| Truncation impact | Loses needs + pending | Loses only history detail |
| Tool suffix position | End of single line | End of line 2 (visible) |

## Existing Functionality Inventory

| Element | Decision | Justification |
|---------|----------|---------------|
| `workflow-status-line.sh` | Transform | Multi-line output with adaptive format |
| `user-prompt-state.sh` | No change | Already multi-line, separate concern |
| `.claude/workflow-status-line.txt` | Include | Transport mechanism unchanged |
| Emoji progress bar | Include | Compact visual summary |
| Tool suffix | Include | Moves to line 2 for visibility |
| Phase evidence tags | Transform | Move to line 3 (history line) |
| Phase needs | Transform | Move to line 2 (priority position) |
| Debug flow format | Transform | Same adaptive logic |
| Simple flows (micro/light/explore) | Include | Minimal change, add tool suffix after |

## Omission Decisions

| Element | Decision | Justification |
|---------|----------|---------------|
| `user-prompt-state.sh` changes | Omit | Already multi-line, works well |
| Deviation suffix | Include | Stays on line 1 as before |
