# Spec: Unified React TopBar Widget

**Date:** 2026-03-22
**Type:** refactor / UI unification
**Bounded context:** UI/Frontend (pragmatic)

---

## Problem

Twig pages and React SPA pages have different topbar experiences:
- **Twig pages:** Full HTML topbar in `base.html.twig` (hamburger, search, lang switcher, notifications, user dropdown)
- **React SPA pages:** No topbar — only floating hamburger buttons in DualMenuShell for nav/data sidebars

Users experience a visual disconnect when navigating between Twig-rendered pages and React SPA pages.

## Goal

A single React `TopBar` component that renders identically in both Twig and React SPA contexts, providing a consistent navigation experience across the entire application.

## Design Decisions

1. **Single source of truth** — One React `TopBar` component used everywhere (widget in Twig + component in DualMenuShell)
2. **POST with CSRF for locale switch** — Semantically correct (modifies session state). New API endpoint `GET /api/csrf-token/{intention}` to provide CSRF tokens to React.
3. **Adaptable search** — `compact` prop: full search bar in Twig pages, icon-expandable in map/SPA pages
4. **No HTML skeleton** — Pure React, accepts brief flash on initial load as trade-off for clean architecture
5. **Reuse existing APIs** — `/api/search`, `/api/notifications/unread-count`, `/api/mercure-token`, `/api/me` already exist

## Approach Elegido (Approach B) — Trade-offs

**Ventaja:** Verdadera unificación — un componente React, cero divergencia entre Twig y SPA.
**Ventaja:** Cualquier cambio futuro se hace en un solo lugar.
**Desventaja:** Más archivos nuevos (~9 create, ~6 edit).
**Desventaja:** Alpine.js search/notification reescritas en React (esfuerzo one-time).
**Desventaja:** Brief flash sin topbar mientras React carga (aceptado como trade-off).

**Alternativas descartadas:**
- **Opcion A (TopBar solo en SPA):** Duplica topbar (HTML en Twig + React en SPA), diverge con el tiempo.
- **Opcion C (Twig layout wraps SPA):** Conflicto de layouts fullscreen maps + Twig padding, CSS hacks.

## Components

### TopBar.tsx (`frontend/src/components/layout/TopBar.tsx`)

Props:
```typescript
interface TopBarProps {
  compact?: boolean;        // true = search collapses to icon (for map views)
  onHamburgerClick: () => void;  // opens NavigationSidebar
}
```

Contains:
- **Hamburger button** — calls `onHamburgerClick`
- **SearchBar** — full or compact mode, hits `/api/search?q=...`
- **LanguageSwitcher** — POST to `/locale/{locale}` with CSRF token
- **NotificationBell** — fetches `/api/notifications/unread-count`, subscribes to Mercure SSE for live updates
- **UserDropdown** — shows email, avatar initial, logout link

### SearchBar.tsx (`frontend/src/components/layout/SearchBar.tsx`)

Props:
```typescript
interface SearchBarProps {
  compact?: boolean;  // icon-only that expands on click
}
```

- Debounced fetch to `/api/search?q=...` (300ms)
- Autocomplete dropdown with type icons (shipment, route, vehicle)
- Full form submit navigates to `/search?q=...`

### NotificationBell.tsx (`frontend/src/components/layout/NotificationBell.tsx`)

- Fetches initial count from `/api/notifications/unread-count`
- Subscribes to Mercure SSE via `/api/mercure-token` for live updates
- Badge with unread count (99+ cap)
- Links to `/notifications`

### LanguageSwitcher.tsx (`frontend/src/components/layout/LanguageSwitcher.tsx`)

- Fetches CSRF token from new `GET /api/csrf-token/locale` endpoint
- POST to `/locale/{locale}` with CSRF token
- Shows current locale, dropdown with ES/EN options

### UserDropdown.tsx (`frontend/src/components/layout/UserDropdown.tsx`)

- Uses `useMe()` hook for email and role
- Avatar initial circle
- Dropdown: email display + logout link

## Backend Changes

### New endpoint: `GET /api/csrf-token/{intention}`

Controller: `CsrfTokenController`
- Returns `{ "token": "..." }` for the given CSRF intention
- Requires authentication
- Used by LanguageSwitcher to get token for locale POST

### MeResponse extension

Add `locale` field to `/api/me` response so React knows the current locale.

## Integration

### Twig (`base.html.twig`)

**Remove:** Entire topbar HTML (lines 60-215), Alpine.js functions `searchAutocomplete()` and `notificationBell()`

**Add:** `<div id="react-topbar-root"></div>` + `<script type="module" src="/app/assets/topbar-widget.js">`

The sidebar widget (`react-sidebar-root`) stays — TopBar's hamburger calls `window.__mxoSidebarOpen()` to open it.

### topbar-widget.tsx (`frontend/src/topbar-widget.tsx`)

New standalone entry point (like `sidebar-widget.tsx`):
- Mounts `<TopBar compact={false} onHamburgerClick={() => window.__mxoSidebarOpen?.()} />`
- Wrapped in QueryClientProvider
- Fixed filename in Vite build: `assets/topbar-widget.js`

### DualMenuShell.tsx

**Add:** `<TopBar compact={true} onHamburgerClick={() => setNavOpen(!navOpen)} />` at the top

**Remove:** Floating nav hamburger button (the TopBar's hamburger replaces it)

### Vite config

Add `'topbar-widget': path.resolve(__dirname, 'topbar-widget.html')` to rollup inputs.
Add fixed filename rule for `topbar-widget`.

## Visual Layout

### Twig pages (after)
```
+---------------------------------------------+
| [H] [Search.....................] [L] [N] [U]|  <- React TopBar (compact=false)
+---------------------------------------------+
|                                             |
|           Twig page content                 |
|                                             |
+---------------------------------------------+
  NavigationSidebar opens as overlay on H click
```

### React SPA pages (after)
```
+---------------------------------------------+
| [H] [S] [L] [N] [U]                         |  <- React TopBar (compact=true)
+----------+----------------------------------+
| Data     |                                  |
| sidebar  |         Map / Content            |
|          |                                  |
+----------+----------------------------------+
  NavigationSidebar opens as inline/overlay on H click
```

## File Summary

| File | Action | Description |
|------|--------|-------------|
| `frontend/src/components/layout/TopBar.tsx` | CREATE | Main topbar component |
| `frontend/src/components/layout/SearchBar.tsx` | CREATE | Search with autocomplete |
| `frontend/src/components/layout/NotificationBell.tsx` | CREATE | Notification bell with Mercure |
| `frontend/src/components/layout/LanguageSwitcher.tsx` | CREATE | Locale switcher with CSRF POST |
| `frontend/src/components/layout/UserDropdown.tsx` | CREATE | User menu dropdown |
| `frontend/src/topbar-widget.tsx` | CREATE | Standalone entry point for Twig |
| `frontend/topbar-widget.html` | CREATE | HTML entry for Vite build |
| `frontend/src/api/hooks/useNotifications.ts` | CREATE | Hook for notification count + Mercure |
| `frontend/src/api/hooks/useSearch.ts` | CREATE | Hook for search autocomplete |
| `backend/src/Controller/Api/CsrfTokenController.php` | CREATE | CSRF token API endpoint |
| `backend/src/Controller/Api/MeController.php` | EDIT | Add locale to response |
| `frontend/vite.config.ts` | EDIT | Add topbar-widget entry |
| `frontend/src/components/layout/DualMenuShell.tsx` | EDIT | Add TopBar, remove floating buttons |
| `backend/templates/base.html.twig` | EDIT | Replace HTML topbar with React mount |
| `frontend/src/api/types.ts` | EDIT | Add locale to MeResponse |

## Out of Scope

- Migrating flash messages to React (stays as Twig Alpine.js)
- Changing the NavigationSidebar component (already unified)
- Responsive breakpoints changes (keeps current mobile behavior)
