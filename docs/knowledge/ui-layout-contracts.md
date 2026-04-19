# UI Layout Contracts

**Ultima actualizacion:** 2026-04-19
**Estado:** Vigente
**Consultar cuando:** Cualquier cambio que toque CSS, layout, posicionamiento, animaciones, o presets visuales.

## Por que existe este modulo

Los ultimos 5 bugs de regresion (PR#252, PR#254, PR#258, PR#259, y esta rama) fueron CSS que rompio layout existente. Todos se habrian prevenido consultando estas reglas **antes** de escribir codigo. Este modulo se lee en brainstorming (paso 3: inventory existing functionality) cuando el cambio toca CSS/layout.

---

## Contrato 1: Positioning Hierarchy

**Regla:** Nunca aplicar `position: relative` (ni en **regla base** ni en overrides de preset) a una clase que tambien se usa con `position: absolute`/`fixed`/`sticky`, sin guard `:not(.absolute):not(.fixed):not(.sticky)`.

**Contexto:** Tailwind genera `.absolute`/`.fixed`/`.sticky` con especificidad (0,1,0). Cualquier regla custom que toque la misma clase con igual o mayor especificidad sobrescribe la utilidad.

**Dos modos de fallo, ambos reales:**

1. **Override con mayor especificidad** (e.g. `.preset-X .mi-clase` — especificidad 0,2,0 > 0,1,0): gana siempre.
2. **Empate de especificidad resuelto por orden** (e.g. `.mi-clase` base — especificidad 0,1,0 == 0,1,0): gana la declaracion posterior. Como `@import "tailwindcss"` esta en linea 1 de `index.css`, **cualquier regla custom en el mismo archivo gana el empate sobre las utilidades de Tailwind**.

**Clases afectadas:**

| Clase | Uso con `absolute` | Uso con `fixed` | Uso en flow normal |
|-------|--------------------|-----------------|--------------------|
| `.glass-overlay` | No | Si (BottomSheet, NavigationSidebar) | Si (TopBar, cards) |
| `.theme-card` | Si (ThemeSwitcher dropdown, SearchBar results) | No | Si (dashboard widgets, cards) |

**Patron seguro: guard en TODAS las declaraciones de `position: relative`, no solo en presets:**

```css
/* Correcto — regla base separada con guard */
.glass-overlay {
  background: ...;
  backdrop-filter: ...;
  border: ...;
  /* SIN position: relative aqui */
}

.glass-overlay:not(.absolute):not(.fixed):not(.sticky) {
  position: relative;
}

/* Correcto — override de preset con guard */
.preset-ios .theme-card:not(.absolute):not(.fixed):not(.sticky) {
  position: relative;
}

/* Incorrecto — regla base sin guard, rompe fixed/absolute por empate de especificidad */
.glass-overlay {
  position: relative;
}

/* Incorrecto — override de preset sin guard, rompe por mayor especificidad */
.preset-ios .theme-card {
  position: relative;
}
```

**Antes de anadir `position: relative` a una regla CSS custom (base o override):**
1. Buscar todos los usos de la clase target con `Grep` — verificar que ninguno usa `.absolute`/`.fixed`/`.sticky`.
2. Si al menos uno lo usa → separar `position: relative` en su propia regla con guard `:not(.absolute):not(.fixed):not(.sticky)`.
3. **Aplicar el guard tanto en la regla base como en cualquier override de preset** — ambos modos de fallo son reales.

---

## Contrato 2: Containing Block Safety

**Regla:** Las siguientes propiedades CSS crean un **containing block** para `position: fixed` descendants, rompiendo su posicionamiento respecto al viewport:

| Propiedad | Ejemplo | Efecto |
|-----------|---------|--------|
| `transform` (cualquier valor != none) | `transform: translateX(0)` | fixed → relativo al ancestor |
| `filter` | `filter: blur(5px)` | fixed → relativo al ancestor |
| `backdrop-filter` | `backdrop-filter: blur(16px)` | fixed → relativo al ancestor |
| `will-change: transform` | `will-change: transform` | fixed → relativo al ancestor |
| `contain: paint/layout/strict` | `contain: paint` | fixed → relativo al ancestor |

**Elementos con `position: fixed` en el codebase:**

| Componente | Archivo | Criticidad |
|-----------|---------|------------|
| BottomSheet | `components/bottom-sheet/BottomSheet.tsx` | Alta — presente en todas las map pages |
| NavigationSidebar (overlay) | `components/layout/NavigationSidebar.tsx` | Alta — menu principal |
| Backdrop del sidebar | `components/layout/NavigationSidebar.tsx` | Media |

**Regla para animaciones:** Nunca retener `transform` en el keyframe final cuando `animation-fill-mode: both/forwards`. Omitir `transform` del `to` keyframe — el browser interpola al valor base (`none`):

```css
/* Correcto — no retiene transform */
@keyframes ios-push-in {
  from { transform: translateX(16px); opacity: 0; }
  to   { opacity: 1; }
}

/* Incorrecto — retiene transform, crea containing block */
@keyframes ios-push-in {
  from { transform: translateX(16px); opacity: 0; }
  to   { transform: translateX(0); opacity: 1; }
}
```

**Antes de anadir transform/filter/backdrop-filter a un ancestor:** Verificar que no contiene hijos o descendientes con `position: fixed`.

---

## Contrato 3: Flex Scroll Pattern

**Regla:** Todo `overflow-y-auto` dentro de un flex column necesita `min-h-0` en el mismo elemento.

**Por que:** El valor por defecto `min-height: auto` en flex items impide que el elemento sea mas pequeno que su contenido. Sin `min-h-0`, `overflow-y-auto` nunca se activa porque la altura del elemento = altura del contenido.

**Patron obligatorio:**
```tsx
<div className="flex flex-col h-screen">
  <header className="shrink-0">...</header>
  <main className="flex-1 min-h-0 overflow-y-auto">  {/* min-h-0 es OBLIGATORIO */}
    ...contenido largo...
  </main>
  <footer className="shrink-0">...</footer>
</div>
```

**Instancias actuales en el codebase:**

| Componente | Archivo:linea | Estado |
|-----------|--------------|--------|
| NavigationSidebar nav | `NavigationSidebar.tsx:211` | OK (min-h-0 presente) |
| AppLayout content area | `AppLayout.tsx:38` | overflow-hidden (correcto, pages gestionan su propio scroll) |
| BottomSheet content | `BottomSheet.tsx:42` | maxHeight inline (correcto, no usa flex) |

**Antes de crear un flex column con scroll:** Siempre incluir `min-h-0` junto a `overflow-y-auto`.

---

## Contrato 4: Preset Independence

**Regla:** Cambiar de preset visual no debe romper layout, posicionamiento, ni interactividad de ningun componente.

**Presets actuales:** default, glass, command, bento, dense, ios

**Zona de riesgo: preset iOS.** El preset iOS anade:
- Pseudo-elements `::before`/`::after` en `.glass-overlay` y `.theme-card` (noise texture, specular reflection)
- Animaciones con `transform` (push-in, scale-in, sheet-rise)
- `button:active { transform: scale(0.97) }` (micro-interaccion)

**Bugs historicos del preset iOS:**

| Fecha | Bug | Root cause | Fix |
|-------|-----|-----------|-----|
| 2026-04-14 | Dropdowns clipeados | `overflow: hidden` en `.glass-overlay` para contener pseudo-elements | Eliminado overflow:hidden (pseudo-elements ya usan `inset:0` + `border-radius:inherit`) |
| 2026-04-14 | BottomSheet desaparece | `transform: translateX(0)` retenido en animacion | Eliminado transform del keyframe `to` |
| 2026-04-14 | ThemeSwitcher mal posicionado | `position: relative` en `.theme-card` preset iOS sobrescribia `absolute` | Anadido `:not(.absolute):not(.fixed)` en preset |
| 2026-04-19 | Sidebar sin scroll (PR #259) | `position: relative` en `.preset-ios .glass-overlay` sobrescribia Tailwind `.fixed` (especificidad 0,2,0 > 0,1,0) | Anadido `:not(.absolute):not(.fixed)` en preset |
| 2026-04-19 | Sidebar sin scroll (rama actual) | `position: relative` en **regla base** `.glass-overlay` empataba con `.fixed` y ganaba por orden de declaracion | Separado en regla aparte con `:not(.absolute):not(.fixed):not(.sticky)` |

**Checklist al modificar CSS del preset iOS:**
1. La regla usa `overflow: hidden`? → Verificar que no clipea hijos absolute/fixed
2. La regla anade `position`? → Verificar especificidad vs utilidades Tailwind
3. La regla o animacion usa `transform`? → Verificar que no hay `position: fixed` en descendants
4. La regla usa `filter`/`backdrop-filter`? → Mismo check que transform

---

## Contrato 5: Mobile Viewport

**Regla:** Todo contenido interactivo debe ser accesible en un viewport de 360x640px (minimo Android comun).

**Checklist:**
- Dropdowns: no se cortan por los bordes del viewport
- Sidebar: scroll funciona, footer visible
- BottomSheet: handle visible en estado collapsed
- Inputs/forms: teclado virtual no oculta campos activos

---

## Como consultar este modulo

Este modulo se consulta en **brainstorming** (paso 3: inventory) cuando el cambio toca:
- CSS global (`index.css`)
- Clases compartidas (`.glass-overlay`, `.theme-card`, `.theme-card-overlay`)
- Positioning (`absolute`, `fixed`, `sticky`, `relative`)
- Animaciones CSS
- Presets visuales
- Layout flex/grid

El formato de consulta es: "Mi cambio toca X → Contrato Y aplica → debo verificar Z antes de implementar."
