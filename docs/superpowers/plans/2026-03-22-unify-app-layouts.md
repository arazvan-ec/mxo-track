# Plan: Unify App Layouts — React TopBar Widget

**Date:** 2026-03-22
**Spec:** `docs/superpowers/specs/2026-03-22-unify-app-layouts-design.md`
**Goal:** Replace Twig inline top bar (Alpine.js) with a React TopBar widget, achieving zero duplication.
**Complexity:** S (small — 4 files changed, 2 new files)

## File Structure

```
frontend/
  topbar-widget.html          # NEW — Vite entry HTML
  src/
    topbar-widget.tsx          # NEW — Standalone React mount
    components/layout/
      TopBar.tsx               # EXISTS — No changes needed
      DualMenuShell.tsx        # EXISTS — No changes needed

backend/templates/
  base.html.twig              # UPDATE — Replace top bar HTML with React mount
```

## Tasks

- [ ] **Task 1:** Create `frontend/topbar-widget.html` Vite entry page
  - Copy pattern from `sidebar-widget.html`
  - Mount div: `<div id="react-topbar-root"></div>`
  - Script: `<script type="module" src="/src/topbar-widget.tsx"></script>`

- [ ] **Task 2:** Create `frontend/src/topbar-widget.tsx` entry point
  - Import TopBar, NavigationSidebar, QueryClient
  - Mount TopBar with `onMenuClick` toggling NavigationSidebar overlay
  - Include NavigationSidebar overlay (same as sidebar-widget pattern)
  - Mount on `#react-topbar-root`

- [ ] **Task 3:** Add `topbar-widget` entry to `frontend/vite.config.ts`
  - Add to `rollupOptions.input`
  - Add stable filename in `entryFileNames`

- [ ] **Task 4:** Update `backend/templates/base.html.twig`
  - Add `<meta name="mercure-url" content="{{ mercure_public_url }}">` to `<head>`
  - Replace top bar div (lines 61-215) with `<div id="react-topbar-root"></div>` + script tag
  - Remove `searchAutocomplete()` and `notificationBell()` Alpine.js functions (lines 278-350)
  - Remove sidebar-widget mount point and script (now included in topbar-widget)
  - Keep: flash messages, main content area

- [ ] **Task 5:** Build frontend and verify no errors
  - `cd frontend && npm run build`
  - Verify `topbar-widget.js` appears in `backend/public/app/assets/`
