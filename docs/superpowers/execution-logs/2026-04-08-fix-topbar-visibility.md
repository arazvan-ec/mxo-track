# Execution Log — 2026-04-08 — Fix Topbar Visibility

**Type:** Bug fix
**Branch:** `claude/fix-topbar-visibility-sMyDa`

## Root Cause

Two issues caused the topbar to render differently between Twig and SPA pages:

1. **Tailwind version conflict**: `base.html.twig` loaded Tailwind **v3 CDN** alongside the Vite-built Tailwind **v4** CSS. Different CSS definitions for the same utility classes caused inconsistent rendering.

2. **Stale JS cache**: `app-shell-widget.js` had a **stable filename** (no content hash). Browsers cached old versions across deploys, showing outdated topbar components on Twig pages while SPA pages loaded fresh hashed bundles.

## Fix

- Removed Tailwind v3 CDN from `base.html.twig`
- Added `@source "../../backend/templates"` to `index.css` so Tailwind v4 generates utilities for Twig templates
- Added `@theme` with brand colors (previously only in CDN config)
- Enabled Vite manifest generation (`build.manifest: true`)
- Hashed all entry filenames including `app-shell-widget.js`
- Created `ViteAssetExtension` Twig extension to resolve hashed paths from manifest
- Updated `base.html.twig` to use `vite_entry_script()` / `vite_entry_styles()`

## Verification

- PHP lint: clean (0 errors)
- Twig lint: 59/59 valid
- PHPUnit: 602 tests, 0 new failures (pre-existing: 6 errors + 5 failures unrelated)
- Vite build: manifest generated, CSS includes brand colors + Twig utilities
- Both entry points share same CSS chunk (`index-[hash].css`) and React chunk

## Lessons

- Tailwind CDN (v3) should never coexist with Tailwind v4 built CSS — the class definitions conflict silently
- All JS entry points should use content-hashed filenames for cache busting
- Vite manifest + Twig extension is the proper bridge for Symfony + Vite
