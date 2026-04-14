# UI Layout Contracts

**Ultima actualizacion:** 2026-04-14
**Estado:** Vigente
**Consultar cuando:** Cualquier cambio que toque CSS, layout, posicionamiento, animaciones, o presets visuales.

## Por que existe este modulo

Los ultimos 4 bugs de regresion (PR#252, PR#254, y 2 en esta rama) fueron CSS que rompio layout existente. Todos se habrian prevenido consultando estas reglas **antes** de escribir codigo. Este modulo se lee en brainstorming (paso 3: inventory existing functionality) cuando el cambio toca CSS/layout.

---

## Contrato 1: Positioning Hierarchy

**Regla:** Nunca aplicar `position: relative` con especificidad > (0,1,0) a una clase que tambien se usa con `position: absolute` o `position: fixed`.

**Contexto:** Tailwind genera `.absolute { position: absolute }` con especificidad (0,1,0). Cualquier regla custom con mayor especificidad (e.g. `.preset-X .mi-clase`) sobrescribe la utilidad y el elemento pierde su posicionamiento.

**Clases afectadas:**

| Clase | Uso con `absolute` | Uso con `fixed` | Uso en flow normal |
|-------|--------------------|-----------------|--------------------|
| `.glass-overlay` | No | Si (BottomSheet, NavigationSidebar) | Si (TopBar, cards) |
| `.theme-card` | Si (ThemeSwitcher dropdown, SearchBar results) | No | Si (dashboard widgets, cards) |

**Patron seguro:** Usar `:not(.absolute):not(.fixed)` cuando una regla CSS custom necesita `position: relative` en una clase compartida:

```css
/* Correcto */
.preset-ios .theme-card:not(.absolute):not(.fixed) {
  position: relative;
}

/* Incorrecto — rompe dropdowns */
.preset-ios .theme-card {
  position: relative;
}
```

**Antes de anadir `position` a una regla CSS custom:** Buscar todos los usos de la clase target con `Grep` y verificar que ninguno usa `.absolute` o `.fixed`.

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
| 2026-04-14 | ThemeSwitcher mal posicionado | `position: relative` en `.theme-card` sobrescribia `absolute` | Anadido `:not(.absolute):not(.fixed)` |

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
