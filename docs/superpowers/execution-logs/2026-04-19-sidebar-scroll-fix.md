---
type: bugfix
tags: []
files_touched: [docs/knowledge/ui-layout-contracts.md, frontend/src/index.css]
patterns: []
outcome: null
outcome_verified_at: null
regressions_later: []
pr_number: null
estimated_lines: null
actual_lines: 1
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-04-19 — Sidebar Scroll Fix (iOS Preset)

**Type:** debug (bug fix)
**Branch:** `claude/fix-ios-menu-background-LEV7f`
**Report:** Tras el fix anterior (restaurar glass values del tema), el usuario reporta que el menú mobile no permite scroll vertical y no se ven todos los items.

## Root cause

`frontend/src/index.css:550-553`:

```css
.preset-ios .glass-overlay,
.preset-ios .theme-card:not(.absolute):not(.fixed) {
  position: relative;
}
```

El guard `:not(.absolute):not(.fixed)` solo se aplicaba al segundo selector (`.theme-card`), no a `.glass-overlay`. Con tema iOS activo:

1. `<aside className="glass-overlay fixed top-0 left-0 bottom-0 ...">` debería ser `position: fixed` (Tailwind `.fixed`).
2. La regla CSS custom con especificidad (0,2,0) vence a Tailwind (0,1,0) → el aside pasa a `position: relative`.
3. `bottom: 0` en un elemento relative no lo ancla al viewport → el aside crece con su contenido.
4. Sin altura constreñida del padre, `flex-1 min-h-0 overflow-y-auto` en `<nav>` no tiene sobre qué aplicar overflow → scroll desactivado.
5. Items debajo del viewport quedan inaccesibles.

## Why it was hidden

Antes del fix de fondo translúcido, el sidebar tenía fondo blanco sólido que ocultaba el hecho de que el layout se extendía fuera del viewport. El footer (user info + logout) estaba off-screen pero invisible. Al restaurar la translucencia del Liquid Glass, la rotura de layout quedó expuesta.

## Pattern-wide

`grep "glass-overlay.*fixed\|glass-overlay.*absolute"` encontró 2 consumers:
- `NavigationSidebar.tsx:168` — `glass-overlay fixed top-0 left-0 bottom-0`
- `BottomSheet.tsx:22` — `glass-overlay fixed left-0 right-0 top-0`

Ambos afectados por la misma regla CSS. Un solo cambio los cubre.

## Fix

`frontend/src/index.css:550-553`:

```css
.preset-ios .glass-overlay:not(.absolute):not(.fixed),
.preset-ios .theme-card:not(.absolute):not(.fixed) {
  position: relative;
}
```

Añadir el guard al primer selector. Esto ya estaba documentado en `docs/knowledge/ui-layout-contracts.md` (Contrato 1 Positioning Hierarchy) — el código simplemente no cumplía el contrato documentado.

## Files changed

- `frontend/src/index.css` (+1 / -1)

## Verification

`cd frontend && npm run build`: ✅ 241 módulos, 6.43s, sin errores.

## Antecedentes y patrón emergente

Tercera ocurrencia del patrón **iOS preset overrides Tailwind utilities**:

1. `e5cc076 fix: remove overflow:hidden from iOS preset glass-overlay/theme-card`
2. `a029e14 fix: prevent iOS preset from overriding theme-card absolute positioning`
3. Este fix — prevent iOS preset from overriding glass-overlay fixed positioning

**Estado:** El knowledge module `docs/knowledge/ui-layout-contracts.md` ya documenta la regla (Contrato 1) y lista `.glass-overlay` como clase usada con `fixed` en BottomSheet y NavigationSidebar. El guard faltaba en el CSS pese a estar en la doc. No requiere actualizar el módulo — el contrato documentado ya cubre este caso; el CSS lo ignoró.

## Lessons

1. **Bugs ocultos tras bugs visuales.** El fondo opaco escondía la rotura de layout. Al arreglar un problema cosmético, síntomas previamente invisibles emergen. Corolario: tras cualquier fix de background/opacity, verificar que el layout subyacente respete los viewport bounds.
2. **Contratos documentados ≠ contratos aplicados.** El `ui-layout-contracts.md` describía exactamente este patrón pero el código CSS no cumplía. Gap: al editar reglas CSS que tocan clases listadas en `ui-layout-contracts.md`, validar que todos los selectores en la misma regla tengan el guard apropiado, no solo algunos.
3. **Pattern-wide incompleta en fixes anteriores.** El commit `a029e14` arregló `.theme-card` pero dejó `.glass-overlay` en la misma regla sin fix. La pattern-wide search debería haber detectado que ambos selectores comparten la misma propiedad y necesitan el mismo guard.
