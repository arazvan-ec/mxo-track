# Plan: Glass Overlay CSS Utility

**Spec:** `docs/superpowers/specs/2026-04-09-glass-overlay-utility-design.md`
**Branch:** `claude/improve-sidebar-transparency-1dGm0`

## Phase 1 (v0): Extract + Migrate

### [parallel] Wave 1: CSS Foundation (2 tasks)

**1a: Add `.glass-overlay` utility class**
- Add in `frontend/src/index.css` after `.theme-card-overlay:hover`
- Class with CSS custom properties: `--glass-blur`, `--glass-brightness`, `--glass-saturate`, `--glass-bg`, `--glass-border`
- Defaults: blur 16px, brightness 1, saturate 1, border-subtle
→ produces: reusable CSS class

**1b: Refactor `.theme-card-overlay` to use same custom properties**
- Replace hardcoded `blur(12px)` with `blur(var(--glass-blur, 12px))` etc.
- Keep card-specific properties (radius, shadow, transition) untouched
→ produces: `.theme-card-overlay` participates in same custom property system

### [parallel] Wave 2: Component Migration (4 tasks)

**2a: TopBar — inline → class**
- Replace inline `backgroundColor`, `backdropFilter`, `WebkitBackdropFilter` with `className` including `glass-overlay`
- Keep `borderColor` in style (controlled by existing class border-b)
→ produces: TopBar uses utility, 0 inline glass styles

**2b: FleetSidebar — inline → class**
- Replace inline glass styles on container div with `glass-overlay` class
→ produces: FleetSidebar uses utility, 0 inline glass styles

**2c: BottomSheet — inline → class + overrides**
- Add `glass-overlay` class, remove inline glass styles
- Override via style: `--glass-blur: 20px`, `--glass-border: var(--color-border-accent)`
- Keep `boxShadow` in inline style (component-specific)
→ produces: BottomSheet uses utility with overrides

**2d: NavigationSidebar — inline → class + adaptive overrides**
- Add `glass-overlay` class to overlay mode aside
- Override via style: `--glass-blur: 24px`, `--glass-brightness: ${brightnessValue}`, `--glass-saturate: 0.3`, `--glass-border: var(--color-border-accent)`
- `useAdaptiveOpacity` now sets `--glass-brightness` instead of rewriting full backdrop-filter
→ produces: NavigationSidebar uses utility with dynamic brightness override

### Wave 3: Verify + Capture (1 task)

**3: TypeScript check + build + update docs**
- `npx tsc --noEmit` + `npx vite build`
- Update `docs/knowledge/ui-frontend.md` glass overlay table
→ produces: verified build, updated docs

## Task Summary

| # | Task | Files | Lines est. |
|---|------|-------|------------|
| 1a | `.glass-overlay` class | index.css | ~8 |
| 1b | Refactor `.theme-card-overlay` | index.css | ~4 |
| 2a | TopBar migration | TopBar.tsx | ~5 |
| 2b | FleetSidebar migration | FleetSidebar.tsx | ~3 |
| 2c | BottomSheet migration | BottomSheet.tsx | ~5 |
| 2d | NavigationSidebar migration | NavigationSidebar.tsx | ~8 |
| 3 | Verify + docs | ui-frontend.md | ~5 |
| **Total** | | **5 files** | **~38 lines** |
