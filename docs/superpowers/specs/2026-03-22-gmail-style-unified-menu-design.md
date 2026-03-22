# Spec: Menú unificado React estilo Gmail

**Fecha:** 2026-03-22
**Estado:** Aprobado
**Bounded context:** Pragmático (UI/Frontend)

## Problema

Existen DOS sidebars duplicados:
1. **Twig** (`_sidebar_content.html.twig` + Alpine.js) — para páginas server-rendered (`/admin/*`, `/customer/*`)
2. **React** (`NavigationSidebar.tsx`) — para páginas SPA (`/app/admin/*`)

Los ítems del menú están duplicados y pueden desincronizarse. En mobile, el sidebar Twig ocupa 64px permanentemente (icons-only) en vez de usar un drawer overlay estilo Gmail.

## Decisión

Unificar en un solo menú React (`NavigationSidebar.tsx`) que se monta como widget standalone en páginas Twig. Patrón hamburger + drawer overlay en todas las vistas.

## Diseño

### Nuevo entry point: `sidebar-widget.tsx`

Widget React standalone que:
- Se monta en `<div id="react-sidebar-root">` dentro de `base.html.twig`
- Expone `window.__mxoSidebarOpen()` para que el hamburger Twig lo abra
- Usa `NavigationSidebar` en modo `overlay` (backdrop + drawer animado)
- Incluye `QueryClientProvider` para que `useMe()` funcione

### Cambios en `base.html.twig`

- Eliminar sidebar Twig completo (el `<div class="fixed inset-y-0 z-30 flex w-16 lg:w-64">`)
- Eliminar `pl-16 lg:pl-64` del main content
- Añadir `<div id="react-sidebar-root"></div>` + `<script>` del widget
- Añadir botón hamburger en top bar que llama a `window.__mxoSidebarOpen()`

### Vite multi-page build

Añadir `sidebar-widget.html` como segundo input en `vite.config.ts` para generar un bundle separado con nombre fijo (`sidebar-widget.js`).

### `_sidebar_content.html.twig`

Se mantiene en el repo (no se elimina) pero ya no se incluye en `base.html.twig`.

## Archivos afectados

| Archivo | Acción |
|---------|--------|
| `frontend/sidebar-widget.html` | Crear |
| `frontend/src/sidebar-widget.tsx` | Crear |
| `frontend/src/sidebar-widget.css` | Crear |
| `frontend/vite.config.ts` | Modificar (multi-page) |
| `backend/templates/base.html.twig` | Modificar (eliminar sidebar Twig, añadir React) |

## Navegación entre contextos

Los links en `NavigationSidebar.tsx` usan `<a href>` nativo (no React Router), por lo que:
- Click en "Dashboard" (`/admin`) → full page navigation a Symfony
- Click en "Fleet Map" (`/app/admin/fleet-map`) → full page navigation a SPA
- Funciona bidireccionalmente sin problemas

## Riesgos y mitigaciones

| Riesgo | Mitigación |
|--------|------------|
| Build React no disponible → hamburger no funciona | El botón simplemente no hace nada (graceful degradation) |
| Bundle size adicional en páginas Twig | ~50-80KB gzipped (React+ReactDOM+widget). Aceptable para UX gain |
| Cache del bundle | Vite genera hashes; usaremos nombre fijo via rollupOptions para simplificar integración Twig |
