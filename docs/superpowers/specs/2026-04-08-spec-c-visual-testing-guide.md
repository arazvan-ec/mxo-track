# Spec C — Visual Testing Guide for 5 Theme Presets

**Date:** 2026-04-08
**Type:** Documentation
**Branch:** `claude/innovative-dashboard-design-ddekk`
**Approach:** Structured checklist for manually testing all presets across key pages

## Existing Functionality Inventory

| Element | Decision | Justification |
|---------|----------|---------------|
| 5 theme presets (default/glass/command/bento/dense) | **Include** | All must be tested |
| Light + dark mode per preset | **Include** | 10 total combinations |
| ThemeSwitcher in TopBar | **Include** | Access point for switching |
| AdminDashboardPage | **Include** | Primary test target |
| OperatorDashboardPage (fleet map) | **Include** | Tests .theme-card-overlay on map |
| CustomerRouteDetailPage | **Include** | Tests widgets in bottom sheet |

## Omission Decisions

| Element | Decision | Justification |
|---------|----------|---------------|
| Automated visual regression tests | **Omit** | Requires Playwright/Chromatic setup, out of scope |
| Customer dashboard | **Omit** | Only if Spec B is implemented first |

## Design

### Deliverable

A markdown checklist at `docs/superpowers/visual-testing-guide.md` with:

1. **Setup instructions** — How to run frontend dev server, access each page
2. **10 test combinations** (5 presets x 2 modes) — Each with expected visual characteristics
3. **Per-page checklist** — What to verify on each page:
   - Cards have correct bg/blur/border/shadow
   - Text is readable (contrast check)
   - Animations play (counters, bars, gauges)
   - ThemeSwitcher dropdown works
   - No visual artifacts (text clipping, overflow)
4. **Known issues** — Command Center ignores light/dark toggle (by design)
5. **Screenshot reference** — Description of expected appearance per preset

**Estimated:** ~1 file, ~150 lines of documentation
