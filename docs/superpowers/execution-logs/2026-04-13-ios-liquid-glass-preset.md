---
type: feature
tags: [glass-overlay, ios-preset]
files_touched: [docs/superpowers/plans/2026-04-13-ios-liquid-glass-preset.md, docs/superpowers/specs/2026-04-13-ios-liquid-glass-preset-design.md, frontend/src/components/bottom-sheet/useBottomSheet.ts, frontend/src/components/layout/AppLayout.tsx, frontend/src/components/transitions/IOSPageTransition.tsx, frontend/src/components/ui/ThemeSwitcher.tsx, frontend/src/context/ThemeProvider.tsx, frontend/src/index.css]
patterns: []
outcome: success
outcome_verified_at: null
regressions_later: []
pr_number: 247
estimated_lines: null
actual_lines: null
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-04-13 — iOS Liquid Glass Preset

**Type:** feature (UI redesign)
**Branch:** `claude/ios-style-transfers-whTh4`
**Spec:** `docs/superpowers/specs/2026-04-13-ios-liquid-glass-preset-design.md`
**Plan:** `docs/superpowers/plans/2026-04-13-ios-liquid-glass-preset.md`

## Brainstorming

**Alternatives considered:**
- **A: Preset `ios` completo (visual + motion)** — **Selected.** Cero breaking changes, aprovecha `.glass-overlay` y arquitectura de presets existente, motion hace la identidad iOS auténtica. Estimado ~200 líneas netas.
- **B: Solo preset visual, sin motion** — Rejected. Liquid Glass sin springs se siente genérico; pierde identidad iOS.
- **C: Reemplazar preset `default`** — Rejected. Breaking change sin beneficio real.

**Decisiones cerradas con usuario:** acento = iOS System Blue `#007AFF` (vs. teal de marca), alcance = SPA + Twig, motion incluido, variante dark incluida como nice-to-have.

## Planning

- 7 tareas distribuidas en 4 waves efectivas (descomposición paralela)
- Wave 1 combinó 1a+1b tras detectar conflicto de archivo (ambas tocaban `index.css`)
- Estimado total: ~210 líneas
- **Actual: ~215 líneas** (muy cerca del estimado)

## Implementation

### Archivos tocados

| Archivo | Δ líneas | Cambio |
|---|---|---|
| `frontend/src/index.css` | +176 | Preset `.preset-ios` light+dark, 4 keyframes iOS, `.ios-hairline`, microinteraction button scale, badges, maplibre overrides. Migración de `animate-slide-in-left` a CSS vars con fallback |
| `frontend/src/context/ThemeProvider.tsx` | +2 | `'ios'` añadido al tipo `ThemePreset` y al array `ALL_PRESETS` |
| `frontend/src/components/ui/ThemeSwitcher.tsx` | +1 | Entrada `ios` en `PRESET_META` con swatch de colores |
| `frontend/src/components/transitions/IOSPageTransition.tsx` | +28 (nuevo) | Wrapper que aplica `animate-ios-push-in` cuando preset activo; usa `location.key` para remontar y retriggerear la animación |
| `frontend/src/components/layout/AppLayout.tsx` | +3 | Import + wrap `<Outlet/>` con `IOSPageTransition` |
| `frontend/src/components/bottom-sheet/useBottomSheet.ts` | +4 -2 | `SPRING_EASING` y `TRANSITION_MS_FALLBACK` ahora via `var(--ease-ios, ...)` + `var(--dur-sheet, ...)` con fallback al comportamiento previo |

**Total:** 215 líneas netas, 6 archivos (1 nuevo), 0 backend.

### Blockers

- **Conflicto wave planning:** 1b y 1c inicialmente separadas, ambas modificaban `index.css` → combinadas en Wave 1 tarea 1b única (ambas son append al final del archivo).
- **Dependencias npm no instaladas:** `npm install` requirió 15s. Sin ello el build fallaba por `Cannot find type definition for 'vite/client'` y `'node'`. Resuelto instalando.
- **Backend PHPUnit no disponible:** `backend/vendor/` no instalado en el entorno. Tests marcados **skipped** (decisión [2026-04-07]). Impacto nulo — cambios son 100% frontend.

### Decisiones no planeadas (durante implementación)

- **NavigationSidebar (3d):** en lugar de editar el componente JSX, se modificó la regla `.animate-slide-in-left` en `index.css` para usar `var(--ease-ios, ease-out)` + `var(--dur-std, 0.2s)`. Mismo efecto, zero JS touch, retrocompatible.
- **BottomSheet (3c):** igual idea — el `SPRING_EASING` del hook ya usaba la curva `cubic-bezier(0.32, 0.72, 0, 1)` (idéntica al `--ease-ios`). El cambio es cosmético del código para permitir que preset-ios ajuste duración (`--dur-sheet: 500ms` vs. default 300ms).
- **IOSPageTransition placement:** plan decía `router.tsx`, implementación lo puso en `AppLayout.tsx` envolviendo `<Outlet/>`. Más natural y respeta SRP — router sigue siendo solo definición de rutas.

## Verification

- **`cd frontend && npm run build`** (comando exacto de deploy): ✅ `tsc -b && vite build` OK en 8.53s, 237 módulos transformados, 0 errores TypeScript
- **CSS bundle size:** 88.87 kB (antes ~83 kB) — **+5.8 kB por preset iOS**, dentro del budget razonable
- **`make lint`** (root): ✅ exit 0, `No syntax errors detected` en todos los archivos PHP
- **`php vendor/bin/phpunit`**: **skipped** (vendor no instalado; cambios son 100% frontend sin impacto PHP)
- **Sanity grep:** `preset-ios` aparece 6 veces en `index.css`, `'ios'` en ambos lugares de `ThemeProvider`, meta en `ThemeSwitcher`, IOSPageTransition exportado y wrapeando Outlet, CSS vars cableadas en `useBottomSheet`

## Lessons

- **Pattern: CSS vars con fallback doble** (`var(--ease-ios, ease-out)`) permite que un preset específico sobreescriba una animación global sin tocar el componente — la regla keyframe vive una vez en CSS, el preset redefine solo las vars, y el fallback preserva comportamiento previo para otros presets. Esta técnica evitó tocar `NavigationSidebar.tsx` y mantuvo la PR más pequeña de lo planeado.
- **Spring easing iOS ya estaba en el código** (`useBottomSheet.ts:12` con la curva exacta `0.32, 0.72, 0, 1`). El trabajo fue más de **alinear el código a un sistema de tokens** que de inventar animaciones. Decisión: siempre grep por curvas de cubic-bezier antes de añadir nuevas — probablemente ya existe la que quieres.
- **Deploy command vs. approximations:** como avisa CLAUDE.md, corrí `npm run build` (el exacto), no `tsc --noEmit` separado. El primer intento falló por vendor vacío — un `tsc --noEmit` local no habría detectado eso porque vite es el que carga los tipos via `vite/client`. Verification exacta atrapa problemas de entorno reales.

## Retrospective

**Estimate accuracy:** 210 → 215 líneas (error 2.4%). Muy precisa. La descomposición parallel-first con detección de conflicto de archivo (1b+1c → 1b) evitó sorpresas en implementación.

**Process gap detectado:** Ninguno crítico. La pregunta inicial del usuario incluía el término ambiguo "transferencias" (transfer vs. transition). La step 4 de brainstorming (clarifying questions con multiple choice) capturó la ambigüedad antes de llegar a código. Sin ese paso, habría construido algo diferente.

**Patrón emergente para knowledge module:** El patrón "**CSS var con doble fallback**" (`var(--ease-ios, cubic-bezier(...))`) para permitir override por preset sin tocar el JSX del componente es la tercera vez que aparece en este codebase (ya usado en `.glass-overlay` con `--glass-blur` y en `.theme-card` con `--card-*`). Si vuelve a aparecer, graduar a `design-patterns.md` como pattern explícito.

**Lección para futuras sesiones:** Antes de añadir `cubic-bezier(...)` nuevos, grep el codebase — muy probablemente la curva ya existe (pasó en `useBottomSheet.ts`). Esto debería ir como regla en `ui-frontend.md` si vuelve a pasar.

---

## Fase 2 — Pulido

### Resumen

4 tareas netas (3 ya se habían adelantado accidentalmente a Fase 1: M1b badges, M1c maplibre, M2a button scale).

| Tarea | Resultado |
|---|---|
| M1a — WCAG AA audit dark/light | Auditoría con algoritmo WCAG: primary 18-20:1 ✅, secondary light 3.41:1 ❌ → bump alpha 0.60→0.75 (5.05:1 ✅). Muted se deja en 0.30 intencionalmente (iOS tertiary behavior, large text only) |
| M2b — Toasts con ios-fade-in | Nuevo keyframe `ios-toast-in` (opacity + translateY 8px + scale 0.98). Aplicado a `.toast-success/error/warning/info` dentro de `.preset-ios` con blur 20px y radio 14px |
| M2c — Alpine.js x-show scale-in | Regla `.preset-ios [x-show]:not([style*="display: none"])` aplica `animate-ios-scale-in` a dropdowns/modales Twig. Duración `--dur-fast` (250ms) |
| V-final — Build + lint + manifest | `npm run build` ✅ 8.47s 0 errores. Lint ✅. Manifest regen ✅. CSS +0.48 KB → 89.35 KB total |

### Decisión deliberada: desviación de iOS oficial en secondary label

iOS usa `secondaryLabel = rgba(60,60,67,0.60)` en light mode. Esto da 3.41:1 contra elevated surface — falla WCAG AA (4.5:1). Bump a 0.75 → 5.05:1 ✅. La diferencia visual es mínima (128 → 108 luminancia) pero cumple accesibilidad. Comentario en el CSS documenta la decisión.

### Retrospective Fase 2

- **Auto-descubrimiento:** al empezar Fase 2 revisé cada tarea contra el código y encontré que 3 de 6 ya estaban shipeadas. Esto ahorró tiempo pero también es una señal de que el plan original sobredescribió Fase 2 vs. lo que fluía naturalmente durante Fase 1. Lección: escribir el preset incluyendo overrides de componentes relacionados (badges, maplibre) es más eficiente que separarlos en waves posteriores — el contexto mental ya está cargado.
- **WCAG vs. autenticidad iOS:** surgió un trade-off explícito (0.60 iOS puro vs. 0.75 WCAG compliant). Elegimos compliance con comentario; esto debería graduar a decision log si vuelve a aparecer en otros presets.
- **Estimate accuracy:** 4 tareas estimadas, 4 completadas. +20 líneas netas en `index.css` (vs. el +215 de Fase 1). Ratio de esfuerzo Fase 1 / Fase 2 ≈ 10:1 — el preset base absorbe la mayor parte del trabajo.
