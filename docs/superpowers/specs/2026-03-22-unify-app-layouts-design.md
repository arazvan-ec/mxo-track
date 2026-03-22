# Spec: Unify App Layouts — Single React TopBar Widget

**Date:** 2026-03-22
**Status:** Approved
**Bounded context:** Pragmático (UI/Frontend)

## Problema

El top bar está duplicado: Twig (`base.html.twig`) tiene su versión HTML con search, notifications, language switcher y user dropdown. Las páginas React SPA (`DualMenuShell`) tienen una versión reducida con solo hamburger + user avatar. Esto causa UX inconsistente y duplicación de código.

## Approach elegido: React TopBar Widget (Approach B)

Un único componente React `TopBar` sirve como source of truth tanto para páginas Twig como para páginas SPA.

### Alternativas descartadas

- **(A) Shared React TopBar solo para SPA:** Duplica lógica entre Twig HTML y React. Más simple pero no elimina duplicación.
- **(C) Alpine.js en SPA:** Mezcla frameworks (Alpine + React) en la misma vista. Inconsistente con la dirección del proyecto.

### Trade-offs del approach elegido

- **Ventaja:** Zero duplicación — un solo componente para todas las vistas
- **Ventaja:** Mantenimiento en un solo lugar — cambios al top bar aplican everywhere
- **Desventaja:** Requiere un nuevo widget entry point (como sidebar-widget)
- **Desventaja:** CSRF token para locale se inyecta como data attribute (puede expirar en sesiones muy largas)

## Diseño técnico

### Componentes

1. **`TopBar.tsx`** — Componente React con:
   - Hamburger button (abre NavigationSidebar overlay via `window.__mxoSidebarOpen`)
   - Search bar con autocompletado (`/api/search`)
   - Language switcher (POST a `/locale/{locale}` con CSRF token)
   - Notification bell con live count (`/api/notifications/unread-count` + Mercure SSE)
   - User dropdown (email + logout, via `/api/me`)
   - Prop `extraControls` para controles page-specific (e.g. data sidebar toggle)

2. **`topbar-widget.tsx`** — Entry point standalone:
   - Monta `TopBar` en `#react-topbar-root`
   - Lee data attributes del mount div: `data-csrf-locale`, `data-mercure-url`
   - Expone `window.__mxoSidebarOpen` al hamburger del TopBar

3. **`DualMenuShell.tsx`** — Actualización:
   - Importa `TopBar` directamente (no usa widget)
   - Pasa data sidebar toggle como `extraControls` prop

4. **`base.html.twig`** — Actualización:
   - Elimina todo el top bar HTML inline (search, lang, notifications, user dropdown, Alpine.js functions)
   - Añade `<div id="react-topbar-root" data-csrf-locale="..." data-mercure-url="..."></div>`
   - Añade `<script type="module" src="/app/assets/topbar-widget.js"></script>`

### Datos inyectados desde Twig

| Dato | Mecanismo | Fuente |
|------|-----------|--------|
| Mercure URL | `data-mercure-url` en mount div | `{{ mercure_public_url }}` |
| CSRF locale token | `data-csrf-locale` en mount div | `{{ csrf_token('locale') }}` |
| User info | API call `/api/me` | React hook `useMe()` |
| Locale actual | `document.documentElement.lang` | `<html lang="{{ app.request.locale }}">` |
| Search results | API call `/api/search?q=...` | Existing endpoint |
| Notification count | API call `/api/notifications/unread-count` | Existing endpoint |
| Mercure SSE token | API call `/api/mercure-token` | Existing endpoint |

### Limpieza

- Eliminar `AppShell.tsx` (sin rutas que lo usen)
- Eliminar `Sidebar.tsx` (sin rutas que lo usen)
- Eliminar funciones Alpine.js `searchAutocomplete()` y `notificationBell()` de `base.html.twig`
- Actualizar router.tsx (quitar import de AppShell)

## Opción futura

Si `window.__mxo*` globals exceden 2-3, migrar a un event bus ligero (ya documentado en ui-frontend.md).

## Success Criteria

- Top bar idéntico visual y funcionalmente en Twig y SPA pages
- Search con autocompletado funcional
- Notifications con live count via Mercure
- Language switch funcional
- Zero código duplicado de top bar
- AppShell y Sidebar eliminados sin regresiones
