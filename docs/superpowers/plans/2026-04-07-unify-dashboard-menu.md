# Plan: Fase 0 + Fase 1 — Shell unificado + Dashboard React

**Spec:** `docs/superpowers/specs/2026-04-07-twig-to-react-migration-design.md`
**Branch:** `claude/unify-dashboard-menu-6mHcn`

## Fase 0: Shell Widget Unificado

### Wave 1 (parallel): Tarea 1a + 1b

**Tarea 1a — Crear `app-shell-widget.tsx`**
Un widget React que provee el mismo chrome que AppLayout (ThemeProvider + TopBar + NavigationSidebar) para páginas Twig.
- Crea `frontend/src/app-shell-widget.tsx`
- Monta en `#react-shell-root` (reemplaza los dos divs separados)
- Incluye ThemeProvider, TopBar (compact=true), NavigationSidebar (overlay)
- El contenido Twig existente se mantiene dentro de un `<main>` que el shell no toca
- Importa `index.css` para CSS variables del theme system
- Test: build con `npx tsc --noEmit` + `npx vite build`
- Produce: shell widget funcional

**Tarea 1b — Agregar entry point a Vite config**
- Edita `frontend/vite.config.ts` para incluir `app-shell-widget.tsx` como entry point adicional
- Produce: `app-shell-widget.js` disponible en `/app/assets/`

### Wave 2: Tarea 2

**Tarea 2 — Actualizar `base.html.twig` para usar shell widget**
- Reemplaza los dos divs (`react-sidebar-root` + `react-topbar-root`) y sus scripts por un solo `#react-shell-root` + `app-shell-widget.js`
- Elimina las `<script>` de sidebar-widget.js y topbar-widget.js
- Ajusta layout classes: `flex flex-col h-screen` en el body/wrapper para que coincida con AppLayout
- El contenido del Twig (`{% block content %}`) queda dentro del main content area
- El shell provee TopBar + Sidebar; el Twig solo renderiza contenido
- Test: verificar que alguna pagina Twig carga con el nuevo shell (no hay test automatico; verificar build)

### Wave 3: Tarea 3

**Tarea 3 — Cleanup: eliminar topbar-widget.tsx y sidebar-widget.tsx**
- Elimina `frontend/src/topbar-widget.tsx` y `frontend/src/sidebar-widget.tsx`
- Elimina `frontend/src/sidebar-widget.css`
- Elimina entry points de Vite config si estaban listados
- Test: `npx tsc --noEmit` + `npx vite build` limpios

---

## Fase 1: Dashboard Admin React

### Wave 4: Tarea 4

**Tarea 4 — API endpoint `GET /api/admin/dashboard`**
- Crea `backend/src/Controller/Api/AdminDashboardController.php`
- Ruta: `GET /api/admin/dashboard`, requiere `ROLE_ADMIN`
- Retorna JSON combinando: SystemHealthService::check(), SystemHealthService::checkLive(), AdminMetricsService::collect(), ReportingService::getDailyDeliveries(7), ReportingService::getTopDrivers(5,7)
- Estructura:
  ```json
  {
    "health": {...},
    "live": {...},
    "metrics": {...},
    "daily_deliveries": [...],
    "top_drivers": [...],
    "generated_at": "ISO8601"
  }
  ```
- Produce: endpoint funcional

### Wave 5 (parallel): Tarea 5a + 5b

**Tarea 5a — Hook `useAdminDashboard`**
- Crea `frontend/src/api/hooks/useAdminDashboard.ts`
- Usa `useQuery` para `GET /api/admin/dashboard`
- Refetch interval: 30s (misma cadencia que el Alpine.js original)
- Exporta datos tipados
- Produce: hook funcional

**Tarea 5b — Tipos TypeScript para dashboard data**
- Agrega tipos en `frontend/src/api/types.ts` o archivo dedicado
- Tipos para: HealthStatus, LiveData, DashboardMetrics, DailyDelivery, TopDriver, AdminDashboardResponse
- Produce: tipos exportados

### Wave 6: Tarea 6

**Tarea 6 — Crear `AdminDashboardPage.tsx`**
- Crea `frontend/src/pages/admin/AdminDashboardPage.tsx`
- Secciones (mismo orden que Twig):
  1. System Health Cards (6 servicios con latencias)
  2. Infrastructure Metrics (posiciones tabla, DB size, ultima ingestion)
  3. KPI Cards (rutas activas, paradas pendientes, imports CSV, posiciones/hora)
  4. Mini Reports (chart entregas 7 dias + top 5 drivers)
  5. Reports banner link
  6. Live Activity Feed (posiciones via Mercure SSE — simplificado, sin Chart.js por ahora)
- Usa `useAdminDashboard` + `useFleetKpi`
- Layout: scroll vertical con padding, similar al dashboard Twig original pero con theme system
- Para el chart de entregas: placeholder simple con barras CSS (sin Chart.js dependency)
- Produce: pagina funcional

### Wave 7 (parallel): Tarea 7a + 7b + 7c

**Tarea 7a — Agregar ruta al router**
- Agrega `{ path: 'admin/dashboard', element: <AdminDashboardPage /> }` en `router.tsx`
- Cambia el index redirect de `admin/fleet-map` a `admin/dashboard`
- Produce: ruta funcional en SPA

**Tarea 7b — Actualizar NavigationController**
- En `backend/src/Controller/Api/NavigationController.php`:
  - Cambia URL de "Dashboard" de `/admin` a `/app/admin/dashboard`
  - Elimina entrada "Dashboard Live" (ya no necesario, todo es una sola vista)
- Produce: menu con un solo "Dashboard" apuntando al SPA

**Tarea 7c — Redirect `/admin` → `/app/admin/dashboard`**
- En `AdminController.php`: cambia `dashboard()` para hacer redirect a `/app/admin/dashboard`
- Mantiene `/admin/health` y `/admin/health/live` como JSON endpoints (no cambian)
- Produce: backward compatibility para bookmarks

### Wave 8: Tarea 8

**Tarea 8 — Verificacion final**
- `npx tsc --noEmit` — 0 errores
- `npx vite build` — success
- Verificar que NavigationController devuelve URLs correctas
- Test: `php bin/console router:match /admin` muestra redirect
