# Spec: Migrar todas las vistas Twig a React SPA

**Fecha:** 2026-04-07
**Tipo:** Migración de stack (Twig+Alpine.js → React SPA)
**Motivación:** El usuario ve "dos menús diferentes" entre páginas Twig y React SPA. Quiere un único sistema de menú React en todas las vistas.

## Problema

Las páginas Twig usan `base.html.twig` que monta widgets React independientes (topbar-widget + sidebar-widget). Las páginas React SPA usan `AppLayout` con TopBar + NavigationSidebar integrados. El resultado visual difiere: layout, theme, compact mode, padding, etc.

## Alternativas evaluadas

**Opcion A) Alinear widgets existentes** — Hacer que topbar-widget y base.html.twig repliquen la estructura de AppLayout. ~3 archivos. Ventaja: cambio minimo. Desventaja: dos sistemas paralelos que divergen con el tiempo.

**Opcion B) Shell Widget unificado** — Un solo app-shell-widget.tsx que monte el mismo AppLayout alrededor del contenido Twig. ~5 archivos. Ventaja: un solo punto de verdad. Desventaja: sigue siendo Twig dentro de React.

**Opcion C) Migrar todo a React SPA** — Reescribir las ~35 páginas Twig como componentes React. Ventaja: stack unificado al 100%. Desventaja: semanas de trabajo pero elimina la dualidad permanentemente.

**Trade-off principal:** Velocidad de implementación vs unificación real del stack. A y B son parches; C es la solución definitiva.

**Decisión:** C, con Fase 0 (opcion B) como fundamento inmediato para que las vistas no-migradas se vean bien durante la transición.

## Plan de fases

### Fase 0: Shell unificado (fundamento)
**Objetivo:** Que las páginas Twig existentes usen el mismo chrome React mientras se migran.
**Qué:** Un `app-shell-widget.tsx` que envuelve el contenido Twig con el mismo AppLayout (TopBar + Sidebar + ThemeProvider).
**Valor:** Validación inmediata — todas las páginas se ven igual desde día 1. Safety net durante la migración.

### Fase 1: Dashboard admin
**Objetivo:** Primera página 100% React. Valida el patrón completo.
**Qué:** `AdminDashboardPage.tsx` en `/app/admin/dashboard` con system health, KPIs, infrastructure metrics, mini reports, activity feed.
**Endpoints necesarios:** Nuevo `GET /api/admin/dashboard` para health+metrics+reports. Reutiliza `/api/fleet/summary`.
**Valor:** Resuelve el trigger original del usuario. Establece patrón de migración.

### Fase 2: Páginas de listado (~12 páginas)
**Objetivo:** Migrar todas las tablas CRUD (index).
**Qué:** Componente `DataTable` reutilizable + API endpoints por entidad.
**Patrón:** Cada página = `useQuery` + `DataTable` + filtros + paginación.
**Valor:** Altamente paralelizable — mismo patrón repetido 12 veces.

### Fase 3: Páginas de formulario (~12 páginas)
**Objetivo:** Migrar todos los CRUD forms.
**Qué:** Sistema de forms React con validación + CSRF via API.
**Patrón:** Cada página = `useForm` hook + API endpoint POST/PUT + validación server-side.
**Valor:** Elimina dependencia de Symfony Forms en frontend.

### Fase 4: Páginas especiales
**Objetivo:** Las que no encajan en listado ni form.
**Qué:** Reports, billing, AI assistant, commit_story, fixtures, customer/driver dashboards.
**Valor:** Completa la migración al 100%.

### Fase 5: Cleanup
**Objetivo:** Eliminar código muerto.
**Qué:** Borrar base.html.twig, templates Twig, topbar-widget, sidebar-widget, Alpine.js CDN, Tailwind CDN.
**Valor:** Stack unificado, zero deuda técnica de dual-rendering.

## Páginas públicas (fuera de scope)
- `security/login.html.twig` — layout propio sin menú
- `tracking/*.html.twig` — públicas, sin auth
- `export/commit_story.html.twig` — vista de impresión

## Existing Functionality Inventory

### React SPA (ya migrado) — 11 páginas
FleetMapPage, OperatorDashboardPage, ExceptionMapPage, RoutePlannerPage, TestRoutingPage, DebugRoutingPage, ShipmentMapPage, CustomerPortalPage, CustomerRoutesPage, DriverRoutesPage, WidgetGalleryPage.

### Twig (por migrar) — ~35 páginas
Agrupadas por fase en el plan arriba.

## Omission Decisions

| Elemento | Decisión | Justificación |
|----------|----------|---------------|
| login.html.twig | Omitir | Layout propio sin menú |
| tracking/*.html.twig | Omitir | Páginas públicas sin auth |
| export/commit_story.html.twig | Omitir | Vista de impresión |
| _delete_form, _pagination, components/* | Omitir | Partials que desaparecen con vistas padre |

## Scope de esta sesión

**Fase 0 + Fase 1** solamente. El resto se planifica en sesiones futuras.
