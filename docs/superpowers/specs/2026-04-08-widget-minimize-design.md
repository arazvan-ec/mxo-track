# Spec: Widget Minimize — Migrar AdminDashboardPage al Widget System

**Fecha:** 2026-04-08
**Branch:** `claude/add-widget-minimize-Epswo`

## Problema

AdminDashboardPage renderiza 5 secciones hardcoded sin capacidad de minimizar/colapsar. El widget system (WidgetRenderer + CollapsibleWidget + registry) ya existe pero no se usa en esta página. Los widgets existentes usan colores hardcoded light-theme que no funcionan en dark mode.

## Approaches Evaluados

### Approach A: Adaptar CollapsibleWidget existente (wrapping manual)
- **Ventaja:** Mínimos cambios (~35 líneas), rápido
- **Desventaja:** No escalable — añadir widgets requiere editar AdminDashboardPage
- **Trade-off:** Velocidad vs escalabilidad

### Approach B: Crear ThemedCollapsibleWidget separado
- **Ventaja:** Zero riesgo de regresión en WidgetRenderer
- **Desventaja:** Duplicación de código, doble mantenimiento
- **Trade-off:** Aislamiento vs DRY

### Approach C: Colapsar inline sin componente
- **Ventaja:** Autocontenido
- **Desventaja:** Sin reutilización, sin localStorage, viola DRY

### Approach D: Migrar AdminDashboardPage al Widget System (ELEGIDO)
- **Ventaja:** Escalable — añadir widget = 1 entrada en layout. Per-customer overrides gratis. Alineado con arquitectura existente.
- **Desventaja:** Más cambios (~80-100 líneas), requiere tematizar 6 widgets
- **Trade-off:** Más trabajo inicial, pero costo marginal cero para widgets futuros
- **Alternativa descartada:** A, B, C — no escalan

**Decisión del usuario:** Opción D aprobada (prioriza escalabilidad)

## Existing Functionality Inventory

| Elemento | Decisión | Justificación |
|----------|----------|---------------|
| `CollapsibleWidget.tsx` | **Transform** | Existe pero usa colores hardcoded light-theme, necesita CSS vars |
| `SystemHealthWidget.tsx` | **Transform** | Usa colores hardcoded, necesita CSS vars |
| `InfrastructureMetricsWidget.tsx` | **Transform** | Usa colores hardcoded, necesita CSS vars |
| `DashboardKpisWidget.tsx` | **Transform** | Usa colores hardcoded, necesita CSS vars |
| `MiniReportsWidget.tsx` | **Transform** | Usa colores hardcoded, necesita CSS vars |
| `ActivityFeedWidget.tsx` | **Transform** | Usa colores hardcoded, necesita CSS vars |
| `AdminDashboardPage.tsx` inline sub-components | **Eliminar** | Reemplazados por widgets del registry |
| `AdminDashboardPage.tsx` formatSecondsAgo | **Eliminar** | Ya existe en InfrastructureMetricsWidget |
| `WidgetRenderer.tsx` mode='page' | **Include** | Core del approach, sin cambios |
| `usePageLayout` hook | **Include** | Obtiene layout del backend |
| `useAdminDashboard` hook | **Include** | Fuente de datos, se pasa como pageData |
| Widget registry metadata (collapsible, sectionTitle) | **Include** | Ya configurado para 5 widgets |
| Backend PageKey::ADMIN_DASHBOARD | **Include** | Ya existe en enum |
| Backend migration Version20260407000100 (seeding) | **Include** | 5 widgets + layout ya seeded (estado 'half') |
| Reports banner (gradient CTA link) | **Transform** | Convertir a widget collapsible (Opción C del usuario) |

## Omission Decisions

| Elemento | Decisión | Justificación |
|----------|----------|---------------|
| OperatorDashboardPage | Omit | Usa bottom-sheet mode, no afectado |
| Page header (título + descripción) | Omit | Siempre visible, no colapsable |
| Bottom sheet states (collapsed/half) | Omit | Admin dashboard es full page, no bottom sheet |
| Admin CRUD de layouts | Omit | Ya existe, no requiere cambios |

## Alcance

| Archivo | Cambio |
|---------|--------|
| `CollapsibleWidget.tsx` | CSS vars para dark theme |
| `SystemHealthWidget.tsx` | CSS vars |
| `InfrastructureMetricsWidget.tsx` | CSS vars |
| `DashboardKpisWidget.tsx` | CSS vars |
| `MiniReportsWidget.tsx` | CSS vars |
| `ActivityFeedWidget.tsx` | CSS vars |
| `AdminDashboardPage.tsx` | Reescribir con usePageLayout + WidgetRenderer mode='page' |
| **Nuevo:** `ReportsBannerWidget.tsx` | Widget CTA reportes (collapsible) |
| `registry.ts` | Registrar reports_banner |
| `layout.ts` | Añadir 'reports_banner' al tipo WidgetType |
| Backend `WidgetType.php` | Añadir REPORTS_BANNER |
| **Nueva migración** | WidgetDefinition + layout entry para reports_banner + update a 'full' state |

## Data Flow

```
useAdminDashboard() → adminData
usePageLayout('admin_dashboard') → layout

pageData = { health, live, metrics, daily_deliveries, top_drivers, mercurePublicUrl }

<WidgetRenderer layout={layout} sheetState="full" pageData={pageData} mode="page" />
  → Para cada widget con collapsible=true:
    → <CollapsibleWidget title={sectionTitle}>
        <WidgetComponent data={pageData} />
      </CollapsibleWidget>
```

## CSS Variable Mapping

- `bg-white` → `var(--color-surface-elevated)`
- `text-gray-900` → `var(--color-text-primary)`
- `text-gray-500` → `var(--color-text-secondary)`
- `text-gray-400` → `var(--color-text-muted)`
- `ring-gray-900/5` → `var(--color-border)`
- `divide-gray-100` / `border-gray-100` → `var(--color-border)`
- `hover:bg-gray-50` → hover con opacity sobre surface
- Colores funcionales (emerald, amber, indigo, etc.) se mantienen — son semánticos
