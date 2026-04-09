# Spec: Glass Overlay CSS Utility

**Fecha:** 2026-04-09
**Enfoque:** A — CSS utility class con custom properties

## Problema

4 componentes JSX duplican inline el mismo patrón glass:
`backgroundColor: var(--color-surface-glass)` + `backdropFilter: blur(Npx)` + `-webkit-` prefix + border.
Cada uno con blur diferente (16-24px). Cambiar el patrón glass requiere editar 4 archivos.

## Existing Functionality Inventory

| Element | Decision | Justification |
|---------|----------|---------------|
| TopBar inline glass (blur 16px) | **Transform** | Migrar a `.glass-overlay` class |
| NavigationSidebar inline glass (blur 24px, adaptive) | **Transform** | Migrar a class + custom property overrides |
| BottomSheet inline glass (blur 20px) | **Transform** | Migrar a class + custom property override |
| FleetSidebar inline glass (blur 16px) | **Transform** | Migrar a class (default blur matches) |
| FleetSidebar search input bg | **Omit** | Solo usa `--color-surface-glass` como bg, no es glass overlay |
| `.theme-card-overlay` (CSS class, blur 12px) | **Transform** | Adoptar mismas custom properties para consistencia |
| MapLibre popup/controls (CSS, blur 12px) | **Omit** | Usan `!important` + selectores de terceros, no componen bien con utility |
| `useAdaptiveOpacity` hook | **Omit** | Se mantiene — ahora setea `--glass-brightness` via inline style |

## Diseño

### CSS Utility: `.glass-overlay`

```css
.glass-overlay {
  background: var(--glass-bg, var(--color-surface-glass));
  backdrop-filter: blur(var(--glass-blur, 16px)) brightness(var(--glass-brightness, 1)) saturate(var(--glass-saturate, 1));
  -webkit-backdrop-filter: blur(var(--glass-blur, 16px)) brightness(var(--glass-brightness, 1)) saturate(var(--glass-saturate, 1));
  border: 1px solid var(--glass-border, var(--color-border-subtle));
}
```

Defaults: blur 16px, brightness/saturate 1 (passthrough), border-subtle.

### Component Migration

| Component | Class | Inline overrides |
|-----------|-------|-----------------|
| TopBar | `glass-overlay` | ninguno (defaults match) |
| FleetSidebar | `glass-overlay` | ninguno (defaults match) |
| BottomSheet | `glass-overlay` | `--glass-blur: 20px`, `--glass-border: var(--color-border-accent)` |
| NavigationSidebar | `glass-overlay` | `--glass-blur: 24px`, `--glass-brightness: {dynamic}`, `--glass-saturate: 0.3`, `--glass-border: var(--color-border-accent)` |

### `.theme-card-overlay` refactor

Adopta custom properties con su propio default (12px):
```css
.theme-card-overlay {
  background: var(--glass-bg, var(--color-surface-glass));
  backdrop-filter: blur(var(--glass-blur, 12px)) brightness(var(--glass-brightness, 1)) saturate(var(--glass-saturate, 1));
  -webkit-backdrop-filter: ...same...
  border: 1px solid var(--glass-border, var(--color-border-subtle));
  /* card-specific: radius, shadow, transition — unchanged */
}
```

### Archivos afectados

| Archivo | Cambio |
|---------|--------|
| `frontend/src/index.css` | Add `.glass-overlay`, refactor `.theme-card-overlay` |
| `frontend/src/components/layout/TopBar.tsx` | Inline → class |
| `frontend/src/components/layout/NavigationSidebar.tsx` | Inline → class + overrides |
| `frontend/src/components/bottom-sheet/BottomSheet.tsx` | Inline → class + overrides |
| `frontend/src/components/fleet/FleetSidebar.tsx` | Inline → class |
