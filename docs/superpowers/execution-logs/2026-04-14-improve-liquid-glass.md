---
type: feature
tags: [glass-overlay]
files_touched: [docs/knowledge/design-patterns.md, docs/knowledge/ui-frontend.md, docs/superpowers/plans/2026-04-14-improve-liquid-glass.md, docs/superpowers/specs/2026-04-14-improve-liquid-glass-design.md, frontend/src/components/ui/ThemeSwitcher.tsx, frontend/src/context/ThemeProvider.tsx, frontend/src/index.css]
patterns: []
outcome: null
outcome_verified_at: null
regressions_later: []
pr_number: null
estimated_lines: null
actual_lines: null
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-04-14 — Improve iOS Liquid Glass Effect

**Type:** feature (UI enhancement)
**Branch:** `claude/improve-ios-glass-theme-4FChy`
**Spec:** `docs/superpowers/specs/2026-04-14-improve-liquid-glass-design.md`
**Plan:** `docs/superpowers/plans/2026-04-14-improve-liquid-glass.md`

## Brainstorming

**Alternatives considered:**
- **A: Enhanced CSS Liquid Glass (structural effects).** **Selected.** Noise texture, specular highlights, rim glow, enhanced shadows. Zero JS, ~80 líneas CSS.
- **B: Canvas/WebGL shader for real refraction.** Rejected. 200-300 líneas JS, competes with MapLibre WebGL, Apple doesn't use refraction on web either.
- **C: Toggleable intensity boost.** **Selected as add-on.** `.glass-enhanced` CSS class with pushed blur/saturate/opacity values. Toggle in ThemeSwitcher, persists in localStorage.

**User decision:** Combine A + C with toggle to disable C from the app.

## Implementation

### Files touched

| File | Δ lines | Change |
|---|---|---|
| `frontend/src/index.css` | +55 -8 | Noise `::before` + specular `::after` pseudo-elements, `--glass-noise`/`--glass-reflection` vars, enhanced card shadows (inset top+bottom), `.glass-enhanced` + `.glass-enhanced.dark` override blocks, `position: relative` on `.glass-overlay` |
| `frontend/src/context/ThemeProvider.tsx` | +20 | `glassEnhanced` state + localStorage + `glass-enhanced` class on `<html>`, exposed in context |
| `frontend/src/components/ui/ThemeSwitcher.tsx` | +22 | Toggle switch "Liquid Glass" visible only when `preset === 'ios'`, iOS-style toggle UI |

**Total:** ~97 líneas netas, 3 archivos, 0 nuevos archivos (code), 2 docs.

### Key design decisions

- **Noise via SVG feTurbulence inline data URI** — no external file, no canvas, works in all browsers with backdrop-filter support. Opacity 0.035 light / 0.07 dark.
- **Specular reflection at 168deg angle** — asymmetric gradient (top-left bright → center transparent → bottom-right subtle) mimics off-axis specular highlight like real glass under a light source.
- **Glass enhanced values:** blur 40px (vs base 28px), saturate 2.2 (vs 1.8), bg opacity 0.55 (vs 0.72). Dramatic enough to be visible but not unusable.
- **Toggle default: OFF** — conservative base values work universally; enhanced is opt-in.

## Verification

- **`cd frontend && npm run build`**: ✅ `tsc -b && vite build` OK in 6.77s, 237 modules, 0 TypeScript errors
- **CSS bundle delta:** 91.21 kB (from ~89.35 kB) — **+1.86 kB** for all new effects
- **PHPUnit:** skipped (vendor not installed; changes are 100% frontend)

## Lessons

- **Reverted commit 683dc49 was directionally correct** — noise + specular + enhanced shadows is the right approach. The revert was branch cleanup, not a quality issue. Reusing the pattern with refined values saved design time.
- **SVG feTurbulence as data URI** is the most portable noise solution for CSS — no build step, no external file, works as `background-image` in pseudo-elements. Key tuning: `baseFrequency='0.80'` + `numOctaves='4'` for fine grain without visible patterns.
- **Toggle pattern for sub-preset options** works well: CSS class on `<html>`, state in ThemeProvider, UI conditional on active preset. Reusable if other presets want similar sub-options.

## Retrospective

**Estimate accuracy:** ~125 líneas estimadas → ~97 netas (22% overestimate). **Root cause:** estimated by concept ("noise + specular + enhanced block + toggle") instead of by CSS selector blocks. Pseudo-elements for `.glass-overlay` and `.theme-card` consolidated into shared selectors, producing fewer blocks than the per-concept estimate assumed.

**Process fix:** Added CSS estimation rule to `docs/knowledge/ui-frontend.md` — count by selector blocks, not by concept. This prevents the ~20% overestimation pattern in CSS-heavy tasks.

**Emergent pattern — sub-preset toggle:** CSS class on `<html>` + state in ThemeProvider + UI conditional in ThemeSwitcher. **Occurrence count: 1.** If this pattern appears 2 more times, graduate to `docs/knowledge/design-patterns.md` as a documented pattern per the 3-occurrence rule in CLAUDE.md.
