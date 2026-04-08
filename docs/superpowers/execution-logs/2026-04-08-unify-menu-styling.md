# Execution Log — 2026-04-08 — Unify Menu Styling

**Type:** bug fix
**Branch:** `claude/unify-menu-styling-eDVus`

## Root Cause

The React app shell CSS (`index.css`) was NOT linked in `base.html.twig`. This file
contains all theme CSS variables (`--color-surface-overlay`, `--color-surface-elevated`,
etc.) and shared Tailwind utilities. Without it:

1. The sidebar backdrop had `backgroundColor: var(--color-surface-overlay)` which was
   **undefined** → transparent backdrop → page content visible behind sidebar
2. The sidebar panel had `backgroundColor: var(--color-surface-elevated)` also undefined
3. Z-index classes from React CSS were absent (though Tailwind CDN partially compensated)

The Vite build extracts CSS into `index-[hash].css` but `base.html.twig` only loaded
the JS entry point. The built `app-shell-widget.html` correctly links the CSS, but
Twig pages never consumed that reference.

## Pattern-Wide Search

- 54 of 59 Twig templates use hardcoded Tailwind colors (bg-white, text-gray-900, etc.)
- No duplicate sidebar/navigation implementations found — single React NavigationSidebar
- Z-index conflict: flash messages (z-50) same level as sidebar (z-50)

## Fix Applied

1. **vite.config.ts**: Added `assetFileNames` to produce predictable CSS names (no hash)
2. **base.html.twig**: Added `<link rel="stylesheet" href="/app/assets/index.css">` in `<head>`
3. **base.html.twig**: Removed hardcoded `bg-gray-50` from `<html>`, use `var(--color-surface)`
4. **base.html.twig**: Flash messages z-index raised to `z-[60]` to avoid sidebar conflict
5. **NavigationSidebar.tsx**: Added body scroll lock (`overflow: hidden`) when overlay open
6. **NavigationSidebar.tsx**: Responsive sidebar width (`w-[85vw] max-w-[18rem]`) for mobile
7. **admin/report/index.html.twig**: Migrated from hardcoded colors to theme CSS variables

## Verification

- TypeScript: clean (0 errors)
- PHP lint: clean
- Twig lint: clean
- PHPUnit: 602 tests, pre-existing failures only (DemoSetupCommand, PostRouteAnalysisHandler, PageSmokeTest)
- Vite build: successful

## Lessons

- When adding React widgets to server-rendered pages, the CSS bundle must be explicitly
  linked — Vite's JS entry point does NOT auto-load extracted CSS
- Predictable asset names (no hash) are needed when server templates reference build outputs
- The 54 remaining templates with hardcoded colors are tech debt for a future migration
