# Execution Log — 2026-04-08 — Enhance Status Line

**Type:** infrastructure improvement
**Branch:** `claude/enhance-status-line-Oq6BG`

## Problem
`workflow-status-line.sh` generated all status info on a single line (~200+ chars in
late phases), which the Claude Code web/mobile UI truncated with "...". Actionable
info (needs, pending phases) was lost to truncation.

## Solution (v1 → v2)

**v1 (Hybrid A+C):** adaptive multi-line (1-3 lines) with priority inversion.

**v2 (5-line expanded):** User requested more info. Added:
- Line 1: flow + phase + emoji bar + task progress + `🔀 branch` + deviation
- Line 2: `Evidence: ...` — phase-specific evidence fields (decisions, turns, tests, etc.)
- Line 3: `Next: ...` — next action to take
- Line 4: completed phase chain with evidence (only when ≥3 phases done)
- Line 5: `· ToolName target` — last tool used (when available)

## Alternatives Considered
- **A (pure multi-line):** Always multi-line — verbose in early phases
- **B (compact, no history):** Drop phase evidence — loses useful context
- **C (character-count adaptive):** Fragile, unpredictable format changes
- **Hybrid A+C (selected):** Adaptive by phase count (semantic threshold)

## Changes
- `.claude/hooks/workflow-status-line.sh`: full-flow + debug-flow refactored to 5-line format,
  added `current_evidence()`, `next_action()`, `yn()` helpers, branch via `git branch --show-current`
- `.claude/hooks/test-status-line.sh`: expanded from 16 to 21 tests (41 assertions total)

## Test Results
41/41 assertions passing, 0 failures.

## Lessons
- Status line truncation is a real UX problem on mobile — always put actionable info
  first in any output that might be displayed in constrained UIs.
- The emoji progress bar (✅🔄⬚) is a more compact representation of phase progress
  than explicit "Pendiente: phase1, phase2" text.
- Evidence + Next lines replicate what `user-prompt-state.sh` injects, but visible to
  the user in the PostToolUse output — different audience (model vs user).

## Retrospectiva
- El usuario rechazó la desviación en v1, forzando flujo completo. Fue valioso: la spec
  documentó enfoques y el diseño se iteró de 3 a 5 líneas en v2.
- Dos iteraciones en una sesión: v1 (3 líneas) → feedback → v2 (5 líneas). El flujo
  completo se aplicó a ambas interacciones sin overhead excesivo.
- Patrón recurrente: cuando un output tiene restricciones de display, priorizar info
  accionable al inicio. Aplica a status lines, error messages, notifications.
