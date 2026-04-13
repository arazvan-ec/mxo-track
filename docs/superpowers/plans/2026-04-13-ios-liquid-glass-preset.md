# Plan — iOS Liquid Glass Preset

**Fecha:** 2026-04-13
**Spec:** `docs/superpowers/specs/2026-04-13-ios-liquid-glass-preset-design.md`
**Branch:** `claude/ios-style-transfers-whTh4`

## Fase 1 (v0) — Preset funcional mínimo

Preset visible en `ThemeSwitcher`, aplicable, se ve Liquid Glass y aplica transiciones a los componentes clave. Sin pulir tokens finos ni edge cases dark.

### [parallel] Wave 1 — Fundaciones CSS + tipos
Tareas independientes sobre archivos disjuntos.

- **1a:** Extender tipo `ThemePreset` con `'ios'` y añadir al array `ALL_PRESETS`
  - Archivo: `frontend/src/context/ThemeProvider.tsx:5,20`
  - → produce: tipo soporta el nuevo preset

- **1b:** Añadir bloque `.preset-ios` (light tokens, glass tuning, motion vars, hairlines) + `.preset-ios.dark` en `index.css`
  - Archivo: `frontend/src/index.css` (append al final, junto con los otros presets)
  - → produce: tokens CSS disponibles cuando clase `.preset-ios` esté en `<html>`

- **1c:** Añadir keyframes `ios-sheet-rise`, `ios-scale-in`, `ios-push-in`, `ios-fade-in` + utility `.ios-hairline` en `index.css`
  - Archivo: `frontend/src/index.css` (sección animations)
  - → produce: animaciones CSS disponibles globalmente (no dependen del preset activo)

> **Verificación disjoint files:** 1a toca `ThemeProvider.tsx`, 1b y 1c tocan `index.css`. **Conflicto 1b↔1c en el mismo archivo.** → mover 1c a Wave 2 o combinar 1b+1c.

**Resolución:** combinar 1b + 1c en una sola tarea `1b` (mismo archivo, mismas líneas añadidas al final).

### [parallel] Wave 1 (revisado) — 1a + 1b

- **1a:** Extender `ThemePreset` type + `ALL_PRESETS` array
  - Archivo: `frontend/src/context/ThemeProvider.tsx`
  - TDD: no hay test unitario existente para ThemeProvider → verificar manualmente que build pasa
  - → produce: tipo y constante

- **1b:** Añadir TODO en `index.css`: bloque `.preset-ios` + `.preset-ios.dark` + keyframes iOS + utility `.ios-hairline` + overrides badges/maplibre dentro del preset
  - Archivo: `frontend/src/index.css`
  - TDD: no aplica (CSS puro) — verificar visualmente después
  - → produce: preset CSS completo

### Wave 2 — Integración UI (depende de Wave 1)

- **2:** Añadir entrada `ios` a `PRESET_META` en `ThemeSwitcher`
  - Archivo: `frontend/src/components/ui/ThemeSwitcher.tsx`
  - TDD: componente sin tests existentes → verificar manualmente que el swatch aparece
  - Depende de: **1a** (el tipo debe aceptar `'ios'`)
  - → produce: UI funcional — usuario puede elegir preset iOS

### [parallel] Wave 3 — Motion wrappers (dependen de Wave 1 tokens)

Tareas independientes sobre archivos disjuntos.

- **3a:** Crear componente `IOSPageTransition` que envuelve `<Outlet/>`
  - Archivo: **nuevo** `frontend/src/components/transitions/IOSPageTransition.tsx`
  - Comportamiento: detecta `location.key` change; si `preset === 'ios'` aplica `animation: ios-push-in var(--dur-std) var(--ease-ios)` al children; sino renderiza sin wrapper
  - TDD: test unitario — dos casos: (a) preset `ios` → animación aplicada, (b) preset `default` → sin animación. Tests en `frontend/src/components/transitions/__tests__/IOSPageTransition.test.tsx`. Si no existe infra de tests para React → skip test, verificar manual
  - → produce: componente reutilizable

- **3b:** Integrar `IOSPageTransition` en `router.tsx` wrapeando `<Outlet/>`
  - Archivo: `frontend/src/router.tsx`
  - Depende de: **3a** (necesita el componente)
  - → produce: page transitions activas cuando preset `ios`

- **3c:** `BottomSheet` — migrar transition `ease` hardcoded a `var(--ease-ios, cubic-bezier(0.4, 0, 0.2, 1))` con fallback al valor actual
  - Archivo: `frontend/src/components/bottom-sheet/BottomSheet.tsx`
  - TDD: test existente no debe romperse
  - → produce: sheet usa spring iOS cuando preset activo, sin cambios visuales en otros presets

- **3d:** `NavigationSidebar` — mismo swap de easing para la animación overlay drawer
  - Archivo: `frontend/src/components/layout/NavigationSidebar.tsx`
  - → produce: sidebar mobile con feel iOS cuando preset activo

> **Verificación disjoint files:** 3a (nuevo), 3b (router.tsx), 3c (BottomSheet.tsx), 3d (NavigationSidebar.tsx). **3b depende de 3a** → 3b va en Wave 3.5. **3a + 3c + 3d en paralelo** en Wave 3. **3b en Wave 4**.

### Wave 3 (revisado) — 3a + 3c + 3d en paralelo

Archivos disjuntos, sin dependencias entre sí.

### Wave 4 — Integración router

- **3b:** Wrap `<Outlet/>` con `IOSPageTransition` en `router.tsx`
  - Depende de: **3a**
  - → produce: page transitions activas

### Wave 5 — Verificación Fase 1

- **V1:** Ejecutar comando de deploy exacto:
  ```
  cd frontend && npm run build
  ```
  Debe pasar sin errores TypeScript ni Vite.
- **V2:** `make lint` en backend (aunque no tocamos PHP, por disciplina).
- **V3:** `php vendor/bin/phpunit` (solo para confirmar que no hay regresiones — cambios son 100% frontend).
- **V4:** Manual smoke — abrir SPA, activar preset iOS, verificar:
  - Superficies translúcidas con blur
  - Acento azul #007AFF
  - BottomSheet con spring easing
  - Popover ThemeSwitcher con scale-in

**Commit al cerrar Fase 1:** `feat: add iOS Liquid Glass preset with motion system`

---

## Fase 2 (Mature) — Pulido y edge cases

Aplica después de Fase 1 pasar verificación.

### Wave M1 — Pulido visual

- **M1a:** Tuning fino de dark variant — validar contraste WCAG AA en `.preset-ios.dark`, ajustar `--color-text-secondary` si necesario
- **M1b:** Overrides de badges dentro de `.preset-ios` (tonos translúcidos) — revisar los 12 colores en contexto
- **M1c:** MapLibre popups/controls bajo `.preset-ios` — border-radius 14px, blur 24px, hairlines 0.5px

### Wave M2 — Motion granular

- **M2a:** Button active state global: regla `button:active { transform: scale(0.97); transition: transform 120ms ease-out; }` dentro de `.preset-ios`
- **M2b:** Toast/flash messages — aplicar `ios-fade-in` + translateY cuando preset activo
- **M2c:** Dropdowns/popovers de Alpine.js en Twig — añadir class `ios-scale-in` vía CSS `.preset-ios [x-show]` selector si es posible (evaluar; si no, documentar como limitación)

### Wave M3 — Verificación final

- **V-final:** Repetir V1-V3. Regenerar manifest (`make manifest`). Confirmar 0 nuevos test failures.

**Commit al cerrar Fase 2:** `feat: polish iOS preset dark variant and micro-interactions`

---

## Resumen ejecución

| Wave | Tareas | Paralelas | Archivos |
|---|---|---|---|
| W1 | 1a + 1b | sí (2) | ThemeProvider.tsx, index.css |
| W2 | 2 | — | ThemeSwitcher.tsx |
| W3 | 3a + 3c + 3d | sí (3) | IOSPageTransition.tsx (nuevo), BottomSheet.tsx, NavigationSidebar.tsx |
| W4 | 3b | — | router.tsx |
| W5 | V1..V4 | — | — |
| M1..M3 | pulido | — | — |

**Total Fase 1:** 7 tareas, 4 waves efectivas, ~210 líneas.

## Criterios de éxito

- [ ] `npm run build` limpio
- [ ] PHPUnit 0 nuevos fallos
- [ ] ThemeSwitcher muestra opción "iOS"
- [ ] Activar preset → superficies translúcidas visibles, acento azul
- [ ] Navegación entre páginas SPA muestra `ios-push-in` (fade + slide horizontal)
- [ ] BottomSheet abre/cierra con spring iOS
- [ ] Cambiar a otro preset → comportamiento anterior intacto (zero regression)
