---
type: refactor
tags: [menu]
files_touched: []
patterns: []
outcome: success
outcome_verified_at: null
regressions_later: []
pr_number: 225
estimated_lines: null
actual_lines: null
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-04-08 — Unified Menu/Layout System

**Type:** refactor (layout unification)
**Branch:** `claude/unify-menu-styling-eDVus`

## Problem

The sidebar menu behaved differently between React SPA pages (`AppLayout.tsx`) and
Twig server-rendered pages (`base.html.twig`) because the surrounding layout structure
differed:
- SPA: `h-screen` + `flex-1 overflow-hidden` (viewport-bound, content scrolls within area)
- Twig: `min-h-screen` + plain `<div>` (page grows beyond viewport, whole page scrolls)

Additionally, the React CSS bundle (`index.css`) containing theme variables was not
linked in Twig pages, making the sidebar backdrop transparent.

## Solution

### Phase 1: CSS fix (bug fix)
1. **vite.config.ts**: Predictable CSS filenames (no hash) for Twig reference
2. **base.html.twig**: Link `index.css` in `<head>` (theme variables + Tailwind utilities)
3. **NavigationSidebar.tsx**: Body scroll lock + responsive width (`w-[85vw] max-w-[18rem]`)
4. **base.html.twig**: Flash messages z-[60] > sidebar z-50
5. **base.html.twig**: Remove hardcoded `bg-gray-50` from `<html>`

### Phase 2: Layout unification (refactor)
1. **base.html.twig**: `min-h-screen` → `h-screen w-full` (match AppLayout)
2. **base.html.twig**: Content wrapper `<div>` → `<div class="flex-1 overflow-auto">`
3. Result: identical layout structure between SPA and Twig

## Brainstorming

- **Rejected:** CSS-only color migration (hybrid inline styles) — user wanted unified system, not color patches
- **Rejected:** Custom CSS utility classes — adds infrastructure without solving the layout problem
- **Approved:** Structural alignment — make Twig layout match AppLayout, zero new components

## Verification

- Twig lint: ✅ (59 files)
- TypeScript: ✅ (0 errors)
- Vite build: ✅
- PHPUnit: 602 tests, 0 new failures (6 errors + 5 failures pre-existing)

## Lessons

- The menu "looking different" was a layout problem, not a styling problem — fixing
  the container structure (`h-screen` + `flex-1 overflow-auto`) was the real fix
- CSS variables being undefined (transparent backdrop) was caused by missing `<link>`
  to the React CSS bundle — Vite doesn't auto-load extracted CSS from JS entry points
- 53 Twig templates still use hardcoded Tailwind colors — separate tech debt from layout
