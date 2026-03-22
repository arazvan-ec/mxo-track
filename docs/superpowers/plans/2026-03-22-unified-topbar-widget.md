# Plan: Unified React TopBar Widget

**Spec:** `docs/superpowers/specs/2026-03-22-unified-topbar-widget-design.md`
**Branch:** `claude/workflow-verification-system-qhgKX`
**Estimated tasks:** 10

---

## Task 1: Backend — CsrfTokenController

Create `backend/src/Controller/Api/CsrfTokenController.php`:
- `GET /api/csrf-token/{intention}` returns `{ "token": "..." }`
- Inject `CsrfTokenManagerInterface`, call `getToken($intention)->getValue()`
- `#[IsGranted('ROLE_USER')]`

**Files:** `backend/src/Controller/Api/CsrfTokenController.php` (CREATE)

- [ ] Create controller
- [ ] Verify with `make lint`

## Task 2: Backend — Add locale to MeController

Edit `backend/src/Controller/Api/MeController.php`:
- Add `Request $request` parameter
- Add `'locale' => $request->getLocale()` to JSON response

Edit `frontend/src/api/types.ts`:
- Add `locale?: string` to `MeResponse`

**Files:** `MeController.php` (EDIT), `types.ts` (EDIT)

- [ ] Edit MeController
- [ ] Edit MeResponse type
- [ ] Verify with `make lint`

## Task 3: Frontend — useSearch hook

Create `frontend/src/api/hooks/useSearch.ts`:
- Debounced search function hitting `/api/search?q=...`
- Returns `{ query, setQuery, results, isOpen, setIsOpen }`
- 300ms debounce via `useRef` + `setTimeout`

**Files:** `frontend/src/api/hooks/useSearch.ts` (CREATE)

- [ ] Create hook

## Task 4: Frontend — useNotifications hook

Create `frontend/src/api/hooks/useNotifications.ts`:
- Fetch initial count from `/api/notifications/unread-count`
- Subscribe to Mercure SSE via `/api/mercure-token`
- Returns `{ unreadCount }`
- Cleanup EventSource on unmount

**Files:** `frontend/src/api/hooks/useNotifications.ts` (CREATE)

- [ ] Create hook

## Task 5: Frontend — SearchBar component

Create `frontend/src/components/layout/SearchBar.tsx`:
- Props: `compact?: boolean`
- Uses `useSearch` hook
- Full mode: input with autocomplete dropdown (shipment/route/vehicle icons)
- Compact mode: magnifying glass icon, expands to input on click
- Form submit navigates to `/search?q=...`

**Files:** `frontend/src/components/layout/SearchBar.tsx` (CREATE)

- [ ] Create component

## Task 6: Frontend — NotificationBell component

Create `frontend/src/components/layout/NotificationBell.tsx`:
- Uses `useNotifications` hook
- Bell icon with badge (unread count, 99+ cap)
- Links to `/notifications`

**Files:** `frontend/src/components/layout/NotificationBell.tsx` (CREATE)

- [ ] Create component

## Task 7: Frontend — LanguageSwitcher + UserDropdown components

Create `frontend/src/components/layout/LanguageSwitcher.tsx`:
- Fetches CSRF token from `/api/csrf-token/locale`
- Shows current locale from `useMe()`, dropdown with ES/EN
- POST to `/locale/{locale}` then `window.location.reload()`

Create `frontend/src/components/layout/UserDropdown.tsx`:
- Uses `useMe()` hook
- Avatar initial, email display, role label
- Dropdown with logout link

**Files:** `LanguageSwitcher.tsx` (CREATE), `UserDropdown.tsx` (CREATE)

- [ ] Create LanguageSwitcher
- [ ] Create UserDropdown

## Task 8: Frontend — TopBar component

Create `frontend/src/components/layout/TopBar.tsx`:
- Props: `compact?: boolean`, `onHamburgerClick: () => void`
- Composes: Hamburger button + SearchBar + LanguageSwitcher + NotificationBell + UserDropdown
- Same visual style as current Twig topbar: `sticky top-0 z-20 h-16 bg-white border-b shadow-sm`
- In DualMenuShell context (dark bg): adapt colors via `variant` prop or Tailwind dark classes

**Files:** `frontend/src/components/layout/TopBar.tsx` (CREATE)

- [ ] Create component

## Task 9: Frontend — topbar-widget entry point + Vite config

Create `frontend/topbar-widget.html`:
- Minimal HTML with `<div id="react-topbar-root">` and script tag

Create `frontend/src/topbar-widget.tsx`:
- Mount TopBar in `#react-topbar-root`
- `compact={false}`, `onHamburgerClick` calls `window.__mxoSidebarOpen()`
- Wrapped in QueryClientProvider

Edit `frontend/vite.config.ts`:
- Add `'topbar-widget'` to rollup inputs
- Add fixed filename for `topbar-widget`

**Files:** `topbar-widget.html` (CREATE), `topbar-widget.tsx` (CREATE), `vite.config.ts` (EDIT)

- [ ] Create HTML entry
- [ ] Create widget entry point
- [ ] Update Vite config
- [ ] Verify `npm run build` succeeds

## Task 10: Integration — base.html.twig + DualMenuShell

Edit `backend/templates/base.html.twig`:
- Remove HTML topbar (lines 60-215)
- Remove Alpine.js `searchAutocomplete()` and `notificationBell()` functions
- Add `<div id="react-topbar-root"></div>` + `<script type="module" src="/app/assets/topbar-widget.js">`
- Keep sidebar widget, flash messages, and page content structure

Edit `frontend/src/components/layout/DualMenuShell.tsx`:
- Add `<TopBar compact={true} onHamburgerClick={() => setNavOpen(!navOpen)} />` at top
- Remove floating nav hamburger button from main area
- Keep data sidebar expand button

**Files:** `base.html.twig` (EDIT), `DualMenuShell.tsx` (EDIT)

- [ ] Edit base.html.twig
- [ ] Edit DualMenuShell
- [ ] `npm run build` succeeds
- [ ] `make lint` clean
