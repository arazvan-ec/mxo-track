# Spec: Sidebar Adaptive Glass

**Fecha:** 2026-04-09
**Enfoque:** F (Hybrid — CSS adaptive glass + JS fine-tuning)

## Problema

El `NavigationSidebar` usa `backgroundColor: var(--color-surface-elevated)` sin `backdrop-filter`.
En el preset Glass dark, `--color-surface-elevated` es `rgba(15, 23, 42, 0.50)` — 50% transparente.
El mapa se ve nítidamente a través del sidebar, haciendo el texto difícil de leer.

El TopBar ya resuelve esto con `--color-surface-glass` + `backdrop-filter: blur(16px)`.
El BottomSheet usa `blur(20px)`. El sidebar no aplica ningún filtro.

## Existing Functionality Inventory

| Element | Decision | Justification |
|---------|----------|---------------|
| NavigationSidebar overlay mode | Transform | Problema raíz — sin blur, transparente en glass preset |
| NavigationSidebar inline mode | Omit | No overlays mapas |
| Backdrop overlay (z-40) | Include | Puede mejorarse opacidad |
| TopBar glass (`blur(16px)`) | Include | Patrón de referencia |
| BottomSheet glass (`blur(20px)`) | Include | Patrón de referencia |
| MapCanvas component | Transform | Necesita `preserveDrawingBuffer` para canvas reading |
| `index.css` variables | Omit | Los tokens existentes son suficientes |
| 5 presets | Include | La solución debe funcionar en todos |
| app-shell-widget.tsx | Include | Twig pages también usan NavigationSidebar |

## Omission Decisions

| Element | Decision | Justification |
|---------|----------|---------------|
| NavigationSidebar inline mode | Omit | Es child estático de layout, no overlay map |
| CSS variables in `index.css` | Omit | `--color-surface-glass` ya tiene los valores correctos por preset |
| ThemeSwitcher | Omit | No cambia — los presets ya definen los tokens |

## Diseño

### Capa 1: CSS Adaptive Glass (base)

Sidebar `<aside>` en overlay mode:
```
backgroundColor: var(--color-surface-glass)
backdropFilter: blur(24px) brightness(0.3) saturate(0.3)
```

- `blur(24px)` — más que TopBar (16) porque sidebar tiene >15 items de texto
- `brightness(0.3)` — oscurece dinámicamente lo que hay detrás (GPU, frame-a-frame)
- `saturate(0.3)` — desatura colores del mapa para no competir con texto
- `--color-surface-glass` — 80% opacidad en dark, adapta por preset

Backdrop overlay: subir de 0.60 a 0.70 opacidad para reducir distracción.

### Capa 2: JS Fine-tuning (extremos)

Hook `useAdaptiveOpacity`:
1. Al abrir sidebar, detecta si `.maplibregl-canvas` existe en DOM
2. Si existe, intenta leer pixels vía `drawImage` a canvas temporal
3. Calcula luminancia promedio muestreando cada 16 pixels
4. Si luminancia > umbral (fondo claro): reduce `brightness()` de 0.3 a 0.15
5. Si canvas no readable (sin `preserveDrawingBuffer`): CSS-only fallback (0 overhead)
6. Re-calcula en eventos `moveend` del mapa (debounced 300ms)

MapCanvas: añadir `preserveDrawingBuffer: true` para habilitar lectura del canvas WebGL.

### Archivos afectados

| Archivo | Cambio |
|---------|--------|
| `frontend/src/components/layout/NavigationSidebar.tsx` | Glass + blur + integrar hook |
| `frontend/src/components/maps/MapCanvas.tsx` | `preserveDrawingBuffer: true` |
| `frontend/src/hooks/useAdaptiveOpacity.ts` | Nuevo hook |
| `frontend/src/index.css` | Ajustar `--color-surface-overlay` a 0.70 en `.dark` |
