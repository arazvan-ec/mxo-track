# Plan: Widget Minimize — Migrar AdminDashboardPage al Widget System

**Fecha:** 2026-04-08
**Spec:** `docs/superpowers/specs/2026-04-08-widget-minimize-design.md`
**Branch:** `claude/add-widget-minimize-Epswo`

## Phase 1 (v0): Working implementation

### [parallel] Wave 1: Theme widgets con CSS variables (6 tareas)

**1a: CollapsibleWidget.tsx → CSS variables**
- Reemplazar `bg-white`, `text-gray-500`, `hover:bg-gray-50`, `ring-gray-900/5` por inline styles con CSS vars
- Verificar: TypeScript clean
- → produce: CollapsibleWidget compatible con dark theme

**1b: SystemHealthWidget.tsx → CSS variables**
- Reemplazar `bg-white`, `text-gray-900`, `ring-gray-900/5` por CSS vars
- Mantener colores funcionales semánticos (emerald/red para status)
- → produce: SystemHealthWidget compatible con dark theme

**1c: InfrastructureMetricsWidget.tsx → CSS variables**
- Reemplazar `bg-white`, `text-gray-900`, `ring-gray-900/5` por CSS vars
- Mantener colores funcionales (amber/blue/teal/purple para iconos)
- → produce: InfrastructureMetricsWidget compatible con dark theme

**1d: DashboardKpisWidget.tsx → CSS variables**
- Reemplazar `bg-white`, `text-gray-900`, `text-gray-500`, `ring-gray-900/5` por CSS vars
- Mantener colores funcionales (indigo/orange/violet/cyan para KPI bars e iconos)
- → produce: DashboardKpisWidget compatible con dark theme

**1e: MiniReportsWidget.tsx → CSS variables**
- Reemplazar `bg-white`, `text-gray-900`, `text-gray-500`, `text-gray-400`, `ring-gray-900/5`, `divide-gray-100`, `border-gray-100` por CSS vars
- Mantener colores funcionales (emerald para chart bars, medal colors)
- → produce: MiniReportsWidget compatible con dark theme

**1f: ActivityFeedWidget.tsx → CSS variables**
- Reemplazar `bg-white`, `text-gray-900`, `text-gray-500`, `text-gray-400`, `text-gray-300`, `ring-gray-900/5`, `divide-gray-100`, `hover:bg-gray-50` por CSS vars
- Mantener colores funcionales (emerald/red para status, cyan para icons)
- → produce: ActivityFeedWidget compatible con dark theme

### [parallel] Wave 2: Backend + Frontend types + nuevo widget (4 tareas)

**2a: Backend WidgetType.php → añadir REPORTS_BANNER**
- Añadir `case REPORTS_BANNER = 'reports_banner';`
- → produce: backend enum actualizado

**2b: Frontend layout.ts → añadir 'reports_banner'**
- Añadir `| 'reports_banner'` al tipo WidgetType
- → produce: frontend type actualizado

**2c: Crear ReportsBannerWidget.tsx**
- Extraer banner de reportes de AdminDashboardPage como widget standalone
- Usar CSS variables para theming (gradient se mantiene — es branding)
- Implementar WidgetProps interface
- → produce: nuevo widget component

**2d: Nueva migración → reports_banner + actualizar layout a 'full'**
- Insertar widget_definition para reports_banner
- Actualizar 5 entries existentes de sheet_state='half' a 'full' (admin_dashboard es full page)
- Insertar page_layout_widget para reports_banner en 'full' state, position 5
- → produce: layout completo con 6 widgets en 'full' state

### Wave 3: Wire up (2 tareas, depende de W1+W2)

**3a: Actualizar registry.ts**
- Importar y registrar ReportsBannerWidget con collapsible: true, sectionTitle: 'Reportes y Analítica'
- → produce: registry con 17 widgets

**3b: Reescribir AdminDashboardPage.tsx**
- Eliminar sub-componentes inline (ServiceHealthCard, KpiCard, MiniBarChart, TopDriversList, InfrastructureMetrics, formatSecondsAgo, SERVICE_CONFIG)
- Añadir imports: usePageLayout, WidgetRenderer
- Llamar usePageLayout('admin_dashboard')
- Componer pageData con datos de useAdminDashboard + mercurePublicUrl
- Renderizar: header + WidgetRenderer con layout, sheetState="full", pageData, mode="page"
- → produce: AdminDashboardPage migrado al widget system con minimize en todas las secciones

## Verificación

- TypeScript: `npx tsc --noEmit` clean
- Build: `npm run build` success
- Visual: 6 secciones colapsables, localStorage persistence, dark theme correcto
