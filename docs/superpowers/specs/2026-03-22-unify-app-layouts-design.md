# Spec: Unify App Layouts — Top Bar & Hamburger Menu

**Date:** 2026-03-22
**Status:** Approved
**Bounded context:** Pragmático (UI/Frontend)

## Problem

The app has two different top bars:
1. **Twig pages** (43 pages via `base.html.twig`): hamburger + search + language switcher + notifications + user dropdown
2. **React SPA pages** (10 pages via `DualMenuShell`): hamburger + data toggle + basic user avatar only

This creates an inconsistent UX — users lose search, notifications, and language switcher when navigating to SPA pages.

## Design

### Approach: Extract shared TopBar React component

Create a `TopBar.tsx` component that mirrors the Twig top bar with all elements:
- Hamburger button (opens NavigationSidebar overlay)
- Search bar (calls `/api/search`)
- Language switcher (posts to `/locale/{locale}`)
- Notification bell with live count (via `/api/notifications/unread-count` + Mercure SSE)
- User dropdown menu

### Changes

1. **New:** `frontend/src/components/layout/TopBar.tsx` — shared top bar component
2. **Update:** `DualMenuShell.tsx` — replace inline top bar with `<TopBar>`, pass data sidebar toggle as `extraControls`
3. **Remove:** `AppShell.tsx` and `Sidebar.tsx` — unused components (no routes use them)

### Trade-offs

- **Chosen:** Single TopBar component used by DualMenuShell → consistent with Twig pages
- **Rejected:** Rendering Twig top bar over SPA pages → too complex, hydration issues
- **Rejected:** Moving everything to React SPA → too large scope, 43 Twig pages still needed

## Success Criteria

- SPA pages show the same top bar elements as Twig pages
- Hamburger menu works identically in both contexts
- Search, notifications, language switcher functional in SPA pages
