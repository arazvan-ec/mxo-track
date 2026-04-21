---
type: bugfix
tags: []
files_touched: [frontend/src/index.css]
patterns: []
outcome: success
outcome_verified_at: null
regressions_later: []
pr_number: 260
estimated_lines: null
actual_lines: 12
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-04-19 — Menu Scroll + Overlay Transparency

**Type:** debug (bug fix)
**Branch:** `claude/fix-menu-scroll-overlay-DsIBw`
**Report:** El menú mobile sigue sin scroll vertical (items del footer invisibles) y el backdrop del overlay se percibe opaco.

## Root cause

### Problema 1: Scroll no funciona

`frontend/src/index.css:312-318` (antes del fix):

```css
.glass-overlay {
  background: var(--glass-bg, var(--color-surface-glass));
  backdrop-filter: ...;
  border: ...;
  position: relative;  /* ← sin guard */
}
```

La regla **base** aplica `position: relative` a TODOS los `.glass-overlay`. El fix anterior
(`2026-04-19-sidebar-scroll-fix.md`) añadió guard solo a la regla del preset iOS (línea 550),
pero la regla base seguía rompiendo.

Cadena:
1. `<aside className="glass-overlay fixed top-0 left-0 bottom-0 ...">` debería ser `fixed`.
2. Tailwind `.fixed` (especificidad 0,1,0) empata con `.glass-overlay` (0,1,0).
3. En empate gana la declaración posterior. `@import "tailwindcss"` está en línea 1, `.glass-overlay` en 317 → gana el custom CSS.
4. Aside pasa a `position: relative` → `bottom: 0` no ancla al viewport → altura indefinida.
5. `<nav className="flex-1 min-h-0 overflow-y-auto">` no tiene altura finita del padre → scroll desactivado.
6. Items del footer quedan off-screen e inaccesibles.

### Problema 2: Overlay opaco

Backdrop usa `var(--color-surface-overlay)`:
- light default: `rgba(0, 0, 0, 0.50)`
- dark default: `rgba(0, 0, 0, 0.70)`
- iOS light: `rgba(0, 0, 0, 0.35)`
- iOS dark: `rgba(0, 0, 0, 0.60)`

Valores demasiado altos — usuario no ve contenido detrás del menú.

## Pattern-wide

`grep "position: relative"` en reglas base de componentes utility (`.glass-overlay`,
`.theme-card`, `.theme-card-overlay`):

- `.theme-card-overlay` (línea 296) — no tiene `position: relative` en base (OK).
- `.theme-card` (línea 285) — no tiene `position: relative` en base (OK).
- `.glass-overlay` (línea 317) — **la única afectada**.

Solo una regla base causa el problema. El preset iOS replica el patrón (ya corregido).

## Fix

### Fix 1: Guard en regla base (`frontend/src/index.css:312-324`)

```css
.glass-overlay {
  background: var(--glass-bg, var(--color-surface-glass));
  backdrop-filter: ...;
  border: ...;
  /* position: relative movido a regla separada con guard */
}

.glass-overlay:not(.absolute):not(.fixed):not(.sticky) {
  position: relative;
}
```

Separar `position: relative` en una regla propia con `:not(.absolute):not(.fixed):not(.sticky)`
preserva el contexto de positioning para pseudo-elementos (::before/::after del preset iOS)
cuando no hay Tailwind positioning utility, y deja pasar el positioning de Tailwind cuando sí lo hay.

### Fix 2: Opacidad del overlay

- light default `.50 → .30`
- dark default `.70 → .50`
- iOS light `.35 → .20`
- iOS dark `.60 → .45`

### Fix 3: Consistencia guard iOS preset

Añadir `:not(.sticky)` al guard existente (línea 550) para simetría con la regla base.

## Files changed

- `frontend/src/index.css` (+12 / -6)

## Verification

`cd frontend && npm run build`: ✅ built in 9.46s, sin errores TS ni Vite.

## Lessons

1. **Guard inconsistente entre regla base y preset.** El fix anterior cubrió el preset iOS pero
   dejó la regla base con el mismo patrón roto. Cuando una regla de preset replica el guard de
   una regla base, ambas deben estar sincronizadas. Si la base no tiene guard, el preset solo
   enmascara parte del problema.

2. **Cuarta ocurrencia del patrón `position: relative` overriding Tailwind.** Los tres previos
   (e5cc076, a029e14, sidebar-scroll-fix) atacaron síntomas del preset iOS. Este es el fix
   definitivo: la regla base, no el preset. Graduar a knowledge module — `ui-layout-contracts.md`
   necesita nota explícita: **cualquier utility class que aplique `position: relative` debe
   usar `:not(.absolute):not(.fixed):not(.sticky)` en la regla base, no solo en overrides de preset.**

3. **Empates de especificidad dependen del orden de declaración.** `.glass-overlay` (0,1,0) vs
   `.fixed` (0,1,0) — el ganador depende de quién aparece después en el CSS compilado. Con
   `@import "tailwindcss"` al inicio, el custom CSS siempre gana el empate. Esto es silencioso:
   no hay warning, solo un bug de layout difícil de diagnosticar.

## Antecedentes

Cadena de fixes del mismo patrón:
1. `e5cc076` — remove overflow:hidden iOS preset
2. `a029e14` — prevent iOS preset overriding theme-card absolute
3. `2026-04-19-sidebar-scroll-fix.md` — iOS preset glass-overlay guard
4. **Este fix** — base rule glass-overlay guard (definitivo)
