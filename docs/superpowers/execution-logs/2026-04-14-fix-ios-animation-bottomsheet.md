# Execution Log — 2026-04-14 — Fix iOS Preset Breaking BottomSheet + ThemeSwitcher

**Type:** bug fix (2 root causes, same file)
**Branch:** `claude/fix-theme-switcher-bottomsheet-ZWCi1`
**Files changed:** 1 (frontend/src/index.css)

## Bug 1: BottomSheet Disappears on iOS Preset

### Root Cause

`@keyframes ios-push-in` ended with `transform: translateX(0)` and `@keyframes ios-scale-in` ended with `transform: scale(1)`. With `animation-fill-mode: both`, these transforms persisted after animation completion.

Per CSS spec, **any `transform` value (even identity like `translateX(0)`) creates a new containing block** for `position: fixed` descendants. This made the BottomSheet's `position: fixed` behave like `position: absolute` relative to `IOSPageTransition`, instead of the viewport.

Combined with `overflow-hidden` on the content area parent (`AppLayout.tsx:38`), the BottomSheet was clipped and effectively hidden.

### Fix

Removed `transform` from the `to` keyframe of `ios-push-in` and `ios-scale-in`. The browser interpolates from the `from` transform to the element's underlying value (`none`), producing identical visual animation without retaining a containing block after completion.

## Bug 2: ThemeSwitcher Dropdown Mispositioned on iOS Preset

### Root Cause

`.preset-ios .theme-card { position: relative }` had CSS specificity (0,2,0) which overrode Tailwind's `.absolute { position: absolute }` at (0,1,0). The ThemeSwitcher dropdown uses both `theme-card` (for visual styling) and `absolute` (for positioning). In iOS preset, it lost `position: absolute` and fell into normal document flow, causing items to render at wrong position with top items clipped off-screen.

### Fix

Added `:not(.absolute):not(.fixed)` to the selector so the `position: relative` rule only applies to theme-cards in normal flow (where it's needed for `::before`/`::after` pseudo-elements), not to positioned overlays.

```css
/* Before */
.preset-ios .theme-card {
  position: relative;
}

/* After */
.preset-ios .theme-card:not(.absolute):not(.fixed) {
  position: relative;
}
```

## Pattern-Wide Search

| Animation | Has transform in `to`? | Used in TSX? | Action |
|-----------|----------------------|--------------|--------|
| `ios-push-in` | Yes → `translateX(0)` | Yes (IOSPageTransition) | **Fixed** |
| `ios-scale-in` | Yes → `scale(1)` | No (unused) | **Fixed preventively** |
| `ios-sheet-rise` | Yes → `translateY(0)` | No (unused) | Left (intentional for sheet positioning) |
| `ios-fade-in` | No (opacity only) | No (unused) | Safe |

## Verification

- Frontend `npm run build` (tsc -b + vite): ✅
- Backend `php -l src/`: ✅
- PHPUnit: 672 tests, 0 failures ✅

## Lessons

1. **CSS `transform` identity values** (`translateX(0)`, `scale(1)`) are visually neutral but create containing blocks and stacking contexts. Omit `transform` from final animation keyframes when `animation-fill-mode` retains values.

2. **CSS specificity conflicts with utility frameworks**: When custom CSS targets a class (`.preset-X .component-class`), it gets higher specificity than single-class utilities (`.absolute`). Always check if a CSS rule could override Tailwind positioning. Use `:not()` to exclude utility classes that must not be overridden.

3. **iOS preset as a recurring source of layout bugs**: This is the 3rd bug caused by iOS preset CSS interfering with layout (overflow:hidden → PR#254, transform identity → this PR, specificity override → this PR). Consider an iOS preset audit checklist before adding new CSS rules.
