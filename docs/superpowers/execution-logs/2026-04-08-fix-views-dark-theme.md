---
type: bugfix
tags: []
files_touched: [frontend/src/index.css]
patterns: []
outcome: success
outcome_verified_at: null
regressions_later: []
pr_number: 228
estimated_lines: null
actual_lines: null
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-04-08 — Fix Views Dark Theme + Consolidate Hooks

**Type:** bug fix
**Branch:** `claude/fix-views-header-styling-YykOG`

## Root Cause

51 Twig templates used hardcoded light-mode Tailwind classes (`bg-white`, `text-gray-900`, `bg-gray-50`, `divide-gray-200`, `border-gray-300`) instead of CSS variables from `frontend/src/index.css`. The React SPA pages used CSS variables correctly, but Twig pages didn't.

Additionally, PostToolUse hooks fired 2-3 times per tool call (auto-evidence + workflow-status-line separately), causing excessive UI noise.

## Fix

1. **Hook consolidation**: Merged `auto-evidence.sh` + `workflow-status-line.sh` into single `post-rwe-hook.sh` for Read/Write/Edit/Agent. Reduces events from 2-3 to 1 per tool call.

2. **51 Twig templates**: Replaced hardcoded colors with CSS variables using 6 parallel agents:
   - `bg-white shadow rounded-lg` → `theme-card` class
   - `bg-white` → `background: var(--color-surface-elevated)`
   - `text-gray-900` → `color: var(--color-text-primary)`
   - `text-gray-500/700` → `color: var(--color-text-secondary)`
   - `text-gray-400` → `color: var(--color-text-muted)`
   - `bg-gray-50` → `background: var(--color-surface)`
   - `divide-gray-200` → `border-color: var(--color-border)`
   - Semantic status badges (green/red/yellow) preserved intentionally

## Verification

- Grep confirms 0 remaining `bg-white`, `text-gray-900`, `bg-gray-50`, `divide-gray-200` in templates
- Only 2 intentional semantic badges remain (`bg-gray-100 text-gray-700` for neutral status)
- Tests/lint skipped (Twig-only changes, no PHP modified)

## Lessons

- Parallel agents (6x) effective for bulk template changes — completed 51 files in ~8 minutes
- CSS variable approach using `style=""` attributes is verbose but reliable for Twig + Tailwind CDN setup
