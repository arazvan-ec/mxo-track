# UI & Frontend

**Última actualización:** 2026-03-22 (updated: React sidebar widget pattern)
**Estado:** Vigente

## Tech Stack

| Layer | Tech | Version | Notes |
|-------|------|---------|-------|
| Server-side rendering | Twig | via Symfony 7.4 | `backend/templates/` |
| Interactivity (inline) | Alpine.js | 3.14.8 (CDN) | `x-data`, `x-show`, `x-transition` |
| Styling | Tailwind CSS | CDN (TODO: npm build) | Inline config in `base.html.twig` |
| Turbo | Symfony UX Turbo | via controllers.json | Turbo Drive enabled, Mercure stream disabled |
| Stimulus | Symfony UX | 2 controllers | `csrf_protection_controller.js`, `hello_controller.js` |
| Frontend SPA | React | `frontend/src/` | Separate React app (pages, components, API hooks) |

## Layout Architecture

**`backend/templates/base.html.twig`** — Master layout. All pages extend this.
- Tailwind config (brand colors, sidebar palette) defined inline in `<script>`
- Alpine.js loaded via CDN defer
- Includes sidebar, topbar, flash messages
- Twig blocks: `title`, `styles`, `body`, `body_attrs`

**Top Bar** — Unified across Twig and React SPA pages:
- **Twig pages:** Top bar defined inline in `base.html.twig` with hamburger, search, language switcher, notifications, user dropdown
- **React SPA pages:** `frontend/src/components/layout/TopBar.tsx` — shared React component matching the Twig top bar (search, language switcher, notifications with Mercure SSE, user dropdown)
- `TopBar` accepts `extraControls` prop for page-specific buttons (e.g. data sidebar toggle in `DualMenuShell`)

**Navigation Sidebar** — Unified React `NavigationSidebar` widget (replaces old Twig `_sidebar_content.html.twig`)
- **Data source:** `NavigationController::getNavigation()` (`/api/navigation`) — returns sections, items, icons per role. Cached 1h (`Cache-Control: max-age=3600`)
- **Renderer:** `frontend/src/components/layout/NavigationSidebar.tsx` renders all menu items for all roles
- **Icons:** SVG icons defined in `NavigationSidebar.tsx` `icons` object — must match `icon` field from API response
- **Mounted in Twig via widget:** `frontend/src/sidebar-widget.tsx` → standalone entry point, loaded in `base.html.twig` as `<script src="sidebar-widget.js">`
- **Twig integration:** Hamburger button in topbar calls `window.__mxoSidebarOpen()` to open React overlay drawer
- **SPA integration:** Same `NavigationSidebar` used inside `DualMenuShell` for React SPA pages
- Role-based rendering handled inside React component (reads user role from context)
- Old `_sidebar_content.html.twig` still exists but is **no longer included** in `base.html.twig` (candidate for deletion)
- **To add a menu item:** 1) Add icon SVG in `NavigationSidebar.tsx` icons object, 2) Add `$this->item()` call in `NavigationController.php`, 3) Add translation key in `messages.{es,en}.yaml`

## Template Organization

| Directory | Purpose |
|-----------|---------|
| `templates/admin/` | Admin panel — CRUD pages, dashboards, forms (largest section) |
| `templates/customer/` | Customer portal — dashboard, routes, shipments, reports |
| `templates/driver/` | Driver portal — route list |
| `templates/operator/` | Operator portal — dashboard |
| `templates/components/` | Reusable partials (prefix `_`) |
| `templates/email/` | Email notification templates |
| `templates/security/` | Login, registration, auth |
| `templates/tracking/` | Public tracking pages |
| `templates/export/` | Export/download views |
| `templates/notification/` | Notification templates |
| `templates/search/` | Search UI |

Template counts available in `docs/codebase-manifest.md` → Twig Template Map.

## Reusable Components (`templates/components/`)

| Component | Params | Purpose |
|-----------|--------|---------|
| `route/_route_card.html.twig` | `route`, `showMetrics`, `showTiming`, `showValidation`, `showOriginalOrder`, `routeIndex`, `mapId` | Route card with collapse/expand (Alpine) |
| `route/_metrics.html.twig` | `route` | Route metrics display |
| `route/_stop_list.html.twig` | `route`, `stops` | Stop list within a route |
| `_optimization_log_panel.html.twig` | `logs` | Optimization log viewer |

Convention: Reusable components use `_` prefix and document params with `{# Param: #}` comments.

## Tailwind Configuration

Brand palette defined inline in `base.html.twig`:
- `brand-50` to `brand-900` (blue scale, primary: `brand-500` = `#3b82f6`)
- `sidebar` (DEFAULT: `#1e293b`, hover: `#334155`, active: `#0f172a`)

Breakpoints: Mobile-first. `lg:` = desktop (sidebar expands, text shown).

Common card pattern: `.rounded-lg.border.border-gray-200.bg-white.shadow-sm`

## Alpine.js Patterns

Global functions defined inline in `base.html.twig`:
- `adminDashboard()` — Admin dashboard state
- `searchAutocomplete()` — Debounced search with fetch
- `notificationBell()` — Mercure SSE connection + notification state

Pattern: `x-data="{ open: false }"` + `x-show` + `x-transition` for dropdowns/modals.

## Frontend React App (`frontend/src/`)

| Directory | Purpose |
|-----------|---------|
| `pages/` | Page-level components (admin, customer, driver) |
| `components/` | Reusable React components (fleet, layout, maps, panels) |
| `components/layout/` | Layout shells: `NavigationSidebar`, `DualMenuShell`, `TopBar` (AppShell/Sidebar removed) |
| `components/bottom-sheet/` | `BottomSheet` — draggable panel (collapsed/half/full states) + `useBottomSheet` hook. Used by `TestRoutingPage` |
| `components/metrics/` | `MetricPairs` — 3 logical metric groups (scope/distance/time) with hero+delta display |
| `api/` | API client + hooks |
| `hooks/` | Custom React hooks |
| `assets/` | Static assets |
| `router.tsx` | Route definitions |
| `sidebar-widget.tsx` | Standalone entry point — mounts `NavigationSidebar` in Twig pages |
| `sidebar-widget.css` | Minimal styles for widget container + hamburger button |

## Common Patterns

**Adding a new admin page:**
1. Create controller in `src/Controller/Admin/`
2. Create template in `templates/admin/{section}/`
3. Extend `base.html.twig` with `{% extends 'base.html.twig' %}`
4. Add nav item in `NavigationSidebar.tsx` (React component — single source of truth for all navigation)

**Adding a reusable component:**
1. Create `templates/components/_name.html.twig`
2. Document params with `{# Param: description #}`
3. Include via `{{ include('components/_name.html.twig', {param: value}) }}`

**Adding interactivity:**
- Simple toggle/dropdown → Alpine.js `x-data`
- Form CSRF → Stimulus `csrf_protection_controller`
- Real-time updates → Mercure SSE (see `docs/knowledge/realtime.md`)
- Complex interactive widget in Twig → React widget pattern (see below)

**Mounting a React widget in Twig pages (pattern):**

Use when a React component needs to render inside Twig-served pages (not just the SPA).

1. Create standalone entry point: `frontend/src/{widget-name}.tsx`
   - Import the React component, create a mount div, render with `createRoot`
   - Expose a global function on `window` for Twig to trigger (e.g., `window.__mxoWidgetOpen`)
2. Create minimal CSS: `frontend/src/{widget-name}.css` (only what the widget container needs)
3. Add HTML entry: `frontend/{widget-name}.html` (Vite needs an HTML entry per page)
4. Register in `frontend/vite.config.ts` → `build.rollupOptions.input` with fixed filename via `entryFileNames`
5. Include in `base.html.twig`: `<script src="{{ asset('app/assets/{widget-name}.js') }}"></script>`
6. Trigger from Twig: `<button onclick="window.__mxo{WidgetName}()">`

**Key decisions:**
- Fixed entry filename (no hash) simplifies Twig `<script>` tag — no manifest.json parsing needed
- Chunked dependencies retain hash-based cache busting (only the tiny entry loses it, acceptable for <1 kB)
- If `window.__mxo*` globals exceed 2-3, consider replacing with a lightweight event bus
