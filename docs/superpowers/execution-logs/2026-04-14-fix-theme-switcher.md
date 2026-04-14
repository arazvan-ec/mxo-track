# Execution Log — 2026-04-14 — Fix Theme Switcher (iOS Preset)

**Type:** bug fix
**Branch:** `claude/fix-theme-switcher-7fURH`
**Flow:** debug

## Root Cause

Commit `03685a5` ("enhance iOS Liquid Glass with noise texture, specular highlights") added `overflow: hidden` to `.preset-ios .glass-overlay, .preset-ios .theme-card` in `frontend/src/index.css` (line 539-543).

This was intended to contain the `::before` (noise texture) and `::after` (specular reflection) pseudo-elements within rounded borders. However, `overflow: hidden` clips ALL absolutely-positioned children that extend beyond the element's bounds.

## Impact

When the iOS theme was active:
- **ThemeSwitcher dropdown** (`theme-card`, absolutely positioned below TopBar) — completely clipped → users trapped in iOS theme
- **LanguageSwitcher dropdown** (`theme-card`) — clipped
- **UserDropdown** (`theme-card`) — clipped
- **SearchBar results** (`theme-card`) — clipped
- **TopBar** (`glass-overlay`) — clipped all dropdown children

## Fix

Removed `overflow: hidden` from the rule. The pseudo-elements already use `inset: 0` + `border-radius: inherit`, which constrains them to the parent's bounds without needing overflow clipping. `position: relative` was kept (needed for pseudo-element positioning).

**1 line deleted**, 0 added.

## Verification

- Frontend build: ✅ (`npm run build`)
- Backend tests: ✅ (672 tests, 2915 assertions, 0 failures)

## Lessons

1. **`overflow: hidden` is dangerous on containers with dropdown children.** Before adding it, audit all elements matching the selector for absolutely-positioned descendants that extend outside.
2. **Pseudo-elements with `inset: 0` + `border-radius: inherit` are self-containing** — they don't need `overflow: hidden` on the parent to stay within rounded borders.
3. **Pattern to watch:** Any CSS rule targeting `.glass-overlay` or `.theme-card` has wide blast radius (60+ elements across the app).
