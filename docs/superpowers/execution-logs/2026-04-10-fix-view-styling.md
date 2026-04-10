# Execution Log — 2026-04-10 — Fix List View Styling Consistency

**Type:** bug fix
**Branch:** `claude/fix-view-styling-ZCnNC`

## Root Cause

Three admin list templates (`customer`, `user`, `integration`) used hardcoded
Tailwind utilities (`shadow ring-1 sm:rounded-lg`) with inline `style` attributes
for background/border, instead of the `.theme-card` CSS class used by all other
list views (shipments, drivers, vehicles, routes).

This caused visible white borders and missing theme support on those pages.

## Pattern-Wide Search

Searched all templates for `shadow ring-1` — found 3 table wrappers affected:
- `backend/templates/admin/customer/index.html.twig:14`
- `backend/templates/admin/integration/index.html.twig:14`
- `backend/templates/admin/user/index.html.twig:14`

Two other hits were filter panels (route) and summary cards (customer/shipment),
not table wrappers — different context, not part of this fix.

## Fix

Replaced `class="overflow-x-auto shadow ring-1 sm:rounded-lg" style="background: var(--color-surface-elevated); border-color: var(--color-border);"` with `class="theme-card overflow-x-auto"` on all 3 templates.

## Verification

- PHP lint: clean
- Frontend build (`tsc -b && vite build`): clean
- PHPUnit: 602 tests, 6 pre-existing errors (driver_routes HTTP 500), 0 new failures

## Lessons

- Templates created before the `.theme-card` pattern was standardized used ad-hoc
  inline styles. When adding new theme classes, do a pattern-wide search for the
  old pattern and migrate all instances — not just the one currently visible.
