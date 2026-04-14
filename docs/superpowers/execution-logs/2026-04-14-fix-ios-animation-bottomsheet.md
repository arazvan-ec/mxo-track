# Execution Log — 2026-04-14 — Fix iOS Animation Breaking BottomSheet

**Type:** bug fix
**Branch:** `claude/fix-theme-switcher-bottomsheet-ZWCi1`
**Files changed:** 1 (frontend/src/index.css)

## Root Cause

`@keyframes ios-push-in` ended with `transform: translateX(0)` and `@keyframes ios-scale-in` ended with `transform: scale(1)`. With `animation-fill-mode: both`, these transforms persisted after animation completion.

Per CSS spec, **any `transform` value (even identity like `translateX(0)`) creates a new containing block** for `position: fixed` descendants. This made the BottomSheet's `position: fixed` behave like `position: absolute` relative to `IOSPageTransition`, instead of the viewport.

Combined with `overflow-hidden` on the content area parent (`AppLayout.tsx:38`), the BottomSheet was clipped and effectively hidden.

## Why It Wasn't Caught Earlier

The `IOSPageTransition` wrapper was added for the iOS preset page transitions. The animation visually completes correctly — `translateX(0)` looks identical to no transform. The containing-block side effect is invisible unless you have `position: fixed` children, which only happens on map pages with BottomSheet.

## Fix

Removed `transform` from the `to` keyframe of `ios-push-in` and `ios-scale-in`. The browser interpolates from the `from` transform to the element's underlying value (`none`), producing identical visual animation without retaining a containing block after completion.

```css
/* Before */
@keyframes ios-push-in {
  from { transform: translateX(16px); opacity: 0; }
  to   { transform: translateX(0); opacity: 1; }
}

/* After */
@keyframes ios-push-in {
  from { transform: translateX(16px); opacity: 0; }
  to   { opacity: 1; }
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
- PHPUnit: 672 tests, 2803 assertions, 0 failures ✅

## Lesson

CSS `transform` identity values (`translateX(0)`, `scale(1)`, `rotate(0)`) are visually neutral but have real side effects: they create containing blocks and stacking contexts. Always prefer omitting `transform` from final animation keyframes when `animation-fill-mode` retains values. If a persistent transform is needed, use `transform: none` explicitly.
