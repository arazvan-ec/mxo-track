# Spec — Unified Layout System (Twig ↔ React SPA)

**Fecha:** 2026-04-08
**Tipo:** Refactor (layout unification)
**Branch:** `claude/unify-menu-styling-eDVus`

## Problema

El layout de páginas Twig (`base.html.twig`) difiere estructuralmente del layout React SPA (`AppLayout.tsx`). Esto causa que el mismo NavigationSidebar + TopBar se comporte distinto entre vistas:

| Aspecto | SPA (AppLayout) | Twig (base.html.twig) |
|---------|-----------------|----------------------|
| Container | `flex flex-col h-screen w-full` | `flex flex-col min-h-screen` |
| Content wrapper | `flex-1 relative overflow-hidden` | `<div>` sin clases |
| Scroll | Content area (TopBar fijo) | Página entera |
| Sidebar overlay | Correcto | Backdrop transparente (CSS faltante) |

## Solución aprobada

Alinear `base.html.twig` para que sea estructuralmente idéntico a `AppLayout.tsx`. Zero componentes nuevos, zero clases CSS nuevas — solo alinear la estructura HTML.

### Cambios en `base.html.twig`

```html
<!-- ANTES -->
<div class="flex flex-col min-h-screen" style="...">
  <div id="react-shell-root"></div>
  <div>
    <!-- flash messages (fixed) -->
    <main class="py-8">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        {% block content %}{% endblock %}
      </div>
    </main>
  </div>
</div>

<!-- DESPUÉS -->
<div class="flex flex-col h-screen w-full" style="...">
  <div id="react-shell-root"></div>
  <div class="flex-1 overflow-auto">
    <!-- flash messages (fixed) -->
    <main class="py-8">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        {% block content %}{% endblock %}
      </div>
    </main>
  </div>
</div>
```

### Resultado

- TopBar queda fijo en el viewport (sticky dentro de h-screen)
- Content scrollea dentro de `flex-1 overflow-auto`
- Sidebar overlay funciona idéntico al SPA (backdrop cubre content area)
- Mismos componentes React, mismo layout, mismo comportamiento

## Existing Functionality Inventory

| Elemento | Decisión | Justificación |
|----------|----------|---------------|
| `AppLayout.tsx` | Include (referencia) | Es el layout target — Twig debe igualarlo |
| `AppShellWidget` (app-shell-widget.tsx) | Include (sin cambios) | Ya renderiza TopBar + Sidebar correctamente |
| `NavigationSidebar.tsx` | Include (ya modificado) | Scroll lock + responsive width del fix anterior |
| `TopBar.tsx` | Include (sin cambios) | Sticky top-0, funciona en ambos layouts |
| `base.html.twig` | Transform | Alinear estructura a AppLayout |
| Flash messages (toasts) | Include (sin cambios) | `fixed` positioning, independiente del layout |
| 53 templates con colores hardcoded | Omit | Scope separado, no afecta layout |

## Omission Decisions

| Elemento | Decisión | Justificación |
|----------|----------|---------------|
| Migración de colores en 53 templates | Omit | Es deuda técnica de theming, no de layout. Scope separado. |
| `DualMenuShell` | Omit | Solo usado por páginas SPA legacy, no afectado |
| Tailwind CDN → npm build | Omit | Marcado como TODO en base.html.twig, scope separado |
