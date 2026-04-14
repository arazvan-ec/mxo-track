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

## Layout Architecture — Unified System

**Última actualización:** 2026-04-08

**Principle:** SPA and Twig pages share the SAME layout structure so the menu system behaves identically everywhere.

**Unified layout structure** (both `AppLayout.tsx` and `base.html.twig`):
```
flex flex-col h-screen w-full
  ├── TopBar (shrink-0, sticky top-0)
  ├── NavigationSidebar (fixed overlay, z-50 + backdrop z-40)
  └── Content area (flex-1 overflow-auto)
        └── Page content (scrolls within this area)
```

**`backend/templates/base.html.twig`** — Master layout. All Twig pages extend this.
- Layout: `flex flex-col h-screen w-full` (matches `AppLayout.tsx`)
- Content area: `flex-1 overflow-auto` (content scrolls, TopBar stays fixed)
- React App Shell CSS loaded in `<head>`: `<link rel="stylesheet" href="/app/assets/index.css">`
- React App Shell widget: `app-shell-widget.js` renders TopBar + NavigationSidebar
- Tailwind CDN for Twig-specific utility classes
- Alpine.js loaded via CDN defer
- Twig blocks: `title`, `styles`, `body`, `body_attrs`, `content`

**`frontend/src/components/layout/AppLayout.tsx`** — SPA layout. React pages use this.
- Identical structure: `flex flex-col h-screen w-full` → TopBar → `flex-1 overflow-hidden` → Outlet
- Uses `overflow-hidden` (React pages manage own scroll) vs Twig's `overflow-auto`

**Top Bar** — Single React component used everywhere:
- `frontend/src/components/layout/TopBar.tsx` — hamburger, search, theme switcher, language, notifications, user dropdown
- `TopBar` accepts `extraControls` prop for page-specific buttons (e.g. data sidebar toggle)
- Sticky `top-0 z-20` with glass backdrop blur

**Navigation Sidebar** — Single React component used everywhere:
- **Data source:** `NavigationController` (`/api/navigation`) — sections, items, icons per role. Cached 1h
- **Renderer:** `frontend/src/components/layout/NavigationSidebar.tsx`
- **Overlay mode:** `fixed z-50` + backdrop `fixed inset-0 z-40` + body scroll lock
- **Overlay glass:** `--color-surface-glass` + `backdrop-filter: blur(24px) brightness() saturate(0.3)` — adaptive glass via `useAdaptiveOpacity` hook
- **Inline mode:** Solid `--color-surface-elevated` (no glass, no blur)
- **Responsive width:** `w-[85vw] max-w-[18rem]` on mobile
- **Icons:** SVG icons in `NavigationSidebar.tsx` `icons` object — must match `icon` field from API
- **To add a menu item:** 1) Add icon SVG in `NavigationSidebar.tsx`, 2) Add `$this->item()` in `NavigationController.php`, 3) Add translation key in `messages.{es,en}.yaml`

**Glass Overlay Pattern** — `.glass-overlay` utility class in `index.css`:
- CSS custom properties: `--glass-blur` (default 16px), `--glass-brightness` (1), `--glass-saturate` (1), `--glass-bg`, `--glass-border`
- All overlay components use this class; override via inline `style={{ '--glass-blur': '24px' } as React.CSSProperties}`
- `.theme-card-overlay` uses same custom property namespace (default blur 12px)

| Component | Class | blur | brightness | saturate | border |
|-----------|-------|------|-----------|----------|--------|
| TopBar | `.glass-overlay` | 16px (default) | 1 | 1 | `--color-border` (inline) |
| NavigationSidebar (overlay) | `.glass-overlay` | 24px | adaptive (0.15–0.30) | 0.3 | `--color-border-accent` |
| BottomSheet | `.glass-overlay` | 20px | 1 | 1 | `--color-border-accent` |
| FleetSidebar | `.glass-overlay` | 16px (default) | 1 | 1 | `--color-border-subtle` (default) |
| `.theme-card-overlay` | standalone | 12px (default) | 1 | 1 | `--color-border-subtle` |
| MapLibre popups/controls | standalone CSS | 12px | — | — | accent/subtle |

**CSS Architecture:**
- Theme CSS variables defined in `frontend/src/index.css` (loaded on ALL pages via `<link>`)
- `index.css` includes: design tokens (`:root`, `.dark`), presets (glass, command, bento, dense, ios), `theme-card` class, animations
- Vite builds CSS with predictable name (`index.css`, no hash) so `base.html.twig` can link it
- Tailwind CDN on Twig pages for utility classes; Tailwind v4 via `@tailwindcss/vite` in React build

**CSS Estimation Rule:**
When estimating CSS line counts, count by **selector blocks**, not by concept. Pseudo-elements (`::before`/`::after`) that share the same structure across multiple selectors consolidate into fewer blocks than it seems. Example: "noise texture + specular reflection" sounds like 2 large features but produces ~4 shared selector blocks, not 8 separate ones. This prevents overestimation (~20% typical).

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

Common card pattern: `.theme-card` class (uses CSS variables: `--card-bg`, `--card-border`, `--card-radius`, `--card-shadow`)

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

## Collapsible Components UX

When designing a component that can collapse (e.g. widgets wrapped in
`CollapsibleWidget`), explicitly enumerate what information disappears in the
collapsed state. For each piece of lost info, decide:

1. **Keep visible** — promote to the summary/header slot (`summary` prop on
   `CollapsibleWidget`, `summaryComponent` in `WidgetRegistryEntry`).
2. **Accept loss** — the info is non-critical and discoverable by expanding.
3. **Don't collapse** — if too much critical info would vanish, the component
   shouldn't be collapsible at all.

**Anti-pattern:** shipping a collapsible widget where the collapsed state shows
only the title. Users lose all actionable info by clicking collapse — worse
than no-collapse-at-all.

**Infrastructure available:**
- `CollapsibleWidget` accepts `summary?: ReactNode` — rendered inline in the
  header next to the title, visible regardless of expanded state
  (`components/widgets/CollapsibleWidget.tsx`).
- `WidgetRegistryEntry` accepts `summaryComponent?: ComponentType<WidgetProps>`
  — auto-wired by `WidgetRenderer` when `mode='page'` and the widget is
  collapsible (`widgets/registry.ts`, `components/bottom-sheet/WidgetRenderer.tsx`).

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
