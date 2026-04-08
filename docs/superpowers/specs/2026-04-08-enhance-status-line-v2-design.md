# Spec — Enhance Status Line v2 (5-line expanded)

**Date:** 2026-04-08
**Type:** Infrastructure improvement (iteration on v1)
**File:** `.claude/hooks/workflow-status-line.sh`

## Problem

v1 improved the status line from 1 line to 3 lines max. User wants even more
information visible — specifically evidence, next actions, and branch context
that are currently only in `user-prompt-state.sh` (invisible to user).

## Approach: 5-line adaptive format

### Alternatives Considered

**Approach A: Add evidence + next to existing 3-line format**
Add 2 more lines (evidence, next) between line 1 and the history line.
Trade-off: always 5 lines might be too verbose in early phases.
Ventaja: consistent, all info always visible.
Desventaja: 5 lines for phase 1/8 when there's little to show.

**Approach B: Adaptive 3-5 lines based on available data**
Show evidence and next only when they have meaningful content (not "N/A").
Trade-off: variable line count.
Ventaja: compact when little info, rich when lots.
Desventaja: slightly more complex logic.

**Selected: Approach A** — Consistency wins. Even early phases have useful evidence
(decisions_read=N) and next actions. The user explicitly wants more info.

## Design

### Line Layout (priority order)

| Line | Content | When shown |
|------|---------|------------|
| 1 | `📍 flow \| Phase (idx/total) \| emoji_bar [task_progress] \| 🔀 branch` | Always |
| 2 | `  Evidence: key=val key=val ...` | Always (phase-specific evidence) |
| 3 | `  Next: action description` | Always (what to do next) |
| 4 | `  ✅ completed_chain_with_evidence` | Only if CURRENT_INDEX > 2 |
| 5 | `  · ToolName target` | Only if tool context available |

### Format Examples

```
# Phase 1 (consult) — 4 lines:
📍 full | Consult (1/8) | 🔄⬚⬚⬚⬚⬚⬚⬚ | 🔀 claude/fix-bug
  Evidence: decisions_read=N logs_scanned=N
  Next: read decisions/logs
  · Grep: route

# Phase 2 (brainstorming) — 4 lines:
📍 full | Brainstorming (2/8) | ✅🔄⬚⬚⬚⬚⬚⬚ | 🔀 claude/feature-x
  Evidence: user_turns=3 alternatives=Y approved=N spec=N
  Next: get approval, write spec
  · Read specs/design.md

# Phase 4 (implementation) — 5 lines:
📍 full | Implementation (4/8) | ✅✅✅🔄⬚⬚⬚⬚ t2/5: Add toggle button | 🔀 claude/feature-x
  Evidence: plan=Y tests_written=2 task=2/5 (Add toggle button)
  Next: task 2/5: Add toggle button (TDD, commit after each task)
  ✅ consult(dec+log) → brainstorm(t4,alt,ok) → planning(plan-file)
  · Read Entity/Vehicle.php

# Phase 8 (finalize) — 5 lines:
📍 full | Finalize (8/8) | ✅✅✅✅✅✅✅🔄 | 🔀 claude/feature-x
  Evidence: branch_strategy=pr
  Next: execute branch strategy
  ✅ consult(dec+log) → brainstorm(ok) → plan → impl(t7/7) → verif(tests✓,lint✓) → capture(log) → retro
  · Bash: git push

# Debug — 4 lines:
📍 debug | Root_cause (2/4) | ✅🔄⬚⬚ | 🔀 claude/fix-xyz
  Evidence: decisions=Y root_cause=N pattern_wide=N
  Next: identify root cause (Skill 8)
  · Grep: ErrorHandler

# Simple flows — 2 lines max:
📍 micro | Responder | 🔀 main
  · Read Entity/Vehicle.php
```

### What Changes vs v1

| Aspect | v1 (current) | v2 |
|--------|-------------|-----|
| Max lines | 3 | 5 |
| Evidence | Not shown | Line 2 (phase-specific) |
| Next action | Embedded in "Need:" | Dedicated line 3 |
| Branch | Not shown | Line 1 suffix |
| Tool suffix | Line 2 mixed with needs | Dedicated line 5 |

## Existing Functionality Inventory

| Element | Decision | Justification |
|---------|----------|---------------|
| `workflow-status-line.sh` full-flow block | Transform | Add evidence, next, branch lines |
| `workflow-status-line.sh` debug-flow block | Transform | Same additions |
| `workflow-status-line.sh` simple flows | Transform | Add branch suffix |
| Evidence computation logic | Reuse from `user-prompt-state.sh` | Same `case` structure |
| Next action computation logic | Reuse from `user-prompt-state.sh` | Same `case` structure |
| Test file `test-status-line.sh` | Transform | Update assertions for new format |

## Omission Decisions

| Element | Decision | Justification |
|---------|----------|---------------|
| `user-prompt-state.sh` | Omit | Separate concern, already multi-line |
| Interaction ID | Omit | Low value for user visibility |
| Spec/plan paths | Omit | Already captured in evidence (spec=Y/N) |
