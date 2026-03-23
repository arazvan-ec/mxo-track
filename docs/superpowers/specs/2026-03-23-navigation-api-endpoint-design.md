# Spec: Navigation API Endpoint

**Date:** 2026-03-23
**Bounded Context:** Pragmatic (UI/Navigation)
**Approach:** A — API endpoint + React consume

---

## Problema

El `NavigationSidebar.tsx` tiene los items del menú hardcodeados por rol en 3 funciones (`getAdminNav`, `getCustomerNav`, `getDriverNav`). Esto duplica la lógica de navegación entre React y el template Twig `_sidebar_content.html.twig` (ya deprecado pero no eliminado). No hay un Single Source of Truth para el menú.

## Alternativas evaluadas

### Approach A: API endpoint + React consume (elegido)
- `GET /api/navigation` retorna sections filtradas por rol del usuario autenticado
- Labels traducidos server-side via `TranslatorInterface`
- React consume con hook `useNavigation()`; mapea icon keys a SVG
- **Ventaja:** SSoT real (server), roles/permisos en PHP, i18n server-side, funciona tanto en Twig pages (widget) como SPA (`/app/*`)
- **Desventaja:** 1 request extra (~<1KB, cacheable con `Cache-Control: private, max-age=3600`)

### Approach B: JSON incrustado en HTML (`window.__mxoNav`)
- Server genera JSON, lo inyecta en `base.html.twig` como `window.__mxoNavItems`
- **Ventaja:** Sin latencia
- **Desventaja:** Globals proliferan (ya hay 2), no funciona en SPA puro (`/app/*` no pasa por Twig)

### Approach C: Extender `/api/me` con navigation
- Añadir `navigation` al response de `/api/me`
- **Ventaja:** Un solo endpoint
- **Desventaja:** Viola SRP (me = identity, no navigation), response size crece, cacheo diferente

## Decisión

**Approach A** — endpoint dedicado, separación de concerns, funciona en ambos contextos (Twig widget + SPA).

---

## Existing Functionality Inventory

| # | Elemento | Ubicación | Descripción |
|---|----------|-----------|-------------|
| 1 | `_sidebar_content.html.twig` | `backend/templates/` | Sidebar Twig con items por rol (admin/customer/driver), active state highlighting via `app.request.attributes.get('_route')`, i18n via `trans` filter |
| 2 | `NavigationSidebar.tsx` | `frontend/src/components/layout/` | Componente React con 3 funciones hardcodeadas: `getAdminNav()`, `getCustomerNav()`, `getDriverNav()`. 16 SVG icons inline. Modos overlay/inline. |
| 3 | `sidebar-widget.tsx` | `frontend/src/` | Monta NavigationSidebar en overlay mode para Twig pages. Expone `window.__mxoSidebarOpen()`. |
| 4 | `topbar-widget.tsx` | `frontend/src/` | Monta TopBar en Twig pages. Conecta hamburger a sidebar. |
| 5 | `MeController` (`/api/me`) | `backend/src/Controller/Api/` | Retorna user info: email, role, customerId, customerName, locale |
| 6 | `useMe()` hook | `frontend/src/api/hooks/` | React hook que consume `/api/me` |
| 7 | `base.html.twig` | `backend/templates/` | Monta `react-sidebar-root` + `react-topbar-root` divs |
| 8 | Active state (Twig) | inline en `_sidebar_content.html.twig` | Usa `app.request.attributes.get('_route')` para CSS class |
| 9 | i18n labels (Twig) | inline en `_sidebar_content.html.twig` | Usa `trans` filter: `sidebar.main`, `nav.dashboard`, etc. |
| 10 | `DualMenuShell.tsx` | `frontend/src/components/layout/` | Shell SPA que usa NavigationSidebar en inline mode |
| 11 | Icons SVG dict | inline en `NavigationSidebar.tsx` | 16 icon keys mapeados a JSX SVG |

## Omission Decisions

| Elemento | Decision | Justification |
|----------|----------|---------------|
| 1. `_sidebar_content.html.twig` | **Omit (delete)** | Ya no se incluye en `base.html.twig` desde 2026-03-22. Completamente reemplazado por React widget. |
| 2. `NavigationSidebar.tsx` | **Transform** | Eliminar funciones hardcodeadas (`getAdminNav`, etc.), consumir `useNavigation()` hook. Mantener icons dict, modos overlay/inline, user footer. |
| 3. `sidebar-widget.tsx` | **Include (keep as-is)** | Sigue siendo el bridge Twig-React, no requiere cambios. |
| 4. `topbar-widget.tsx` | **Include (keep as-is)** | No afectado por este cambio. |
| 5. `MeController` | **Include (keep as-is)** | Identity endpoint separado de navigation. No se modifica. |
| 6. `useMe()` | **Include (keep as-is)** | Sigue usándose para user footer en sidebar. |
| 7. `base.html.twig` | **Include (keep as-is)** | Mount points no cambian. |
| 8. Active state (Twig) | **Omit** | Se elimina con template #1. React implementará active state comparando `location.pathname` con item `href`. |
| 9. i18n labels (Twig) | **Transform** | API endpoint envía labels ya traducidos según locale del request. Translation keys se reusan. |
| 10. `DualMenuShell.tsx` | **Include (keep as-is)** | No cambia, sigue pasando NavigationSidebar como child. |
| 11. Icons SVG dict | **Include (keep in React)** | Server envía icon key string, React mapea a SVG. No tiene sentido enviar SVG markup por API. |

---

## Diseño detallado

### 1. API Endpoint: `GET /api/navigation`

**Controller:** `src/Controller/Api/NavigationController.php`

**Response shape:**
```json
{
  "sections": [
    {
      "title": "Principal",
      "items": [
        { "label": "Dashboard", "href": "/admin", "icon": "dashboard" },
        { "label": "Dashboard Live", "href": "/app/admin/operator-dashboard", "icon": "dashboardLive" }
      ]
    }
  ]
}
```

- `icon` is a string key matching React's icon dict
- Labels are pre-translated via `TranslatorInterface` using request locale
- Sections/items filtered by user's primary role (`ROLE_ADMIN`, `ROLE_CUSTOMER`, `ROLE_DRIVER`)
- `Cache-Control: private, max-age=3600` (menu changes only on role/locale change)
- `#[IsGranted('ROLE_USER')]` — same as MeController

### 2. React: `useNavigation()` hook

**File:** `frontend/src/api/hooks/useNavigation.ts`

- Fetches `GET /api/navigation`
- Returns `{ data: NavSection[] | undefined, isLoading, error }`
- Uses same fetch/cache pattern as `useMe()`

### 3. NavigationSidebar.tsx changes

- Remove `getAdminNav()`, `getCustomerNav()`, `getDriverNav()`, `getNavSections()`
- Keep `icons` dict and `NavItem`/`NavSection` interfaces (now matching API shape)
- Consume `useNavigation()` instead of deriving from `useMe().role`
- Add active state: compare `location.pathname` with item `href` for CSS highlight
- Keep `useMe()` for user footer (email, role label)

### 4. Cleanup

- Delete `backend/templates/_sidebar_content.html.twig`

---

## Trade-offs

| Aspect | Pro | Con |
|--------|-----|-----|
| Extra HTTP request | SSoT, role-filtered server-side | ~1 extra request per page load |
| Server-side labels | i18n centralized, translation keys reused | Locale change requires refetch |
| Icon as string key | Small payload, SVG stays in React | Must keep icon dict in sync |

## Risks

- **Low:** If `/api/navigation` fails, sidebar shows empty/loading. Mitigated by `useMe()` fallback role still available.
- **Low:** Icon key mismatch between server and React dict. Mitigated by using same keys already in NavigationSidebar.
