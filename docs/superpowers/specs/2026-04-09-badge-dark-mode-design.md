# Spec: Theme-Aware Badge Colors for Dark Mode

**Fecha:** 2026-04-09
**Tipo:** Code change (UI/CSS)
**Branch:** `claude/improve-views-claude-md-NiE60`

## Problema

Todas las vistas Twig usan badges de estado con colores Tailwind hardcoded (`bg-blue-100 text-blue-800`, etc.). En dark mode, estos fondos claros (`*-100`) producen manchas blancas que no encajan con el tema oscuro. Afecta 31 templates con 180+ ocurrencias.

## Existing Functionality Inventory

| Elemento | Decisión | Justificación |
|----------|----------|---------------|
| CSS custom properties en `index.css` (`:root` + `.dark`) | Include — extender | Ya existe el patrón de tokens temáticos |
| `@source "../../backend/templates"` en index.css | Include — aprovechar | Tailwind v4 ya escanea templates Twig |
| `.theme-card` pattern | Include — patrón de referencia | Mismo patrón de CSS variables + clases |
| 12 colores de badge distintos | Transform — unificar en clases CSS | Reducir de ~180 líneas hardcoded a 12 clases |
| Toasts en `base.html.twig` | Transform | Patrón distinto (bg-*-50 + ring) |
| Notification badges | Transform | Patrón distinto (bg-*-50 + ring-inset) |
| Icon backgrounds (dashboard KPIs) | Omit | Decorativos, no badges de estado |
| Buttons (`bg-blue-600`) | Omit | Funcionales, no afectados por el problema |
| Progress bars (`bg-green-500`) | Omit | Barras sólidas, visualmente correctos en dark |

## Approach Chosen: CSS Custom Properties + Utility Classes (Opción A)

### Alternativas evaluadas

**Opción A (elegida):** CSS custom properties en `index.css` + clases `.badge-COLOR`
- Ventaja: Un solo punto de control, cambio mínimo en templates (solo reemplazar clases)
- Desventaja: ~31 templates a modificar

**Opción B (descartada):** Twig macro `_badge.html.twig`
- Ventaja: DRY máximo
- Desventaja: Requiere refactor más profundo de la estructura HTML de cada badge

### Design

12 colores estándar + 5 variantes soft + 4 clases toast:

**Estándar (reemplazan `bg-COLOR-100 text-COLOR-800`):**
- Light: fondo claro sólido + texto oscuro (idéntico al original)
- Dark: fondo semi-transparente `rgba(color, 0.15)` + texto claro `COLOR-300`

**Soft (reemplazan `bg-COLOR-50 text-COLOR-700`):**
- Light: fondo más claro + texto medio
- Dark: fondo más sutil `rgba(color, 0.08)` + texto claro

**Toast (reemplazan bg+text+ring combinados):**
- Composite class que incluye `--tw-ring-color` para integrar con Tailwind ring utilities

### Aprobación

Usuario aprobó Opción A el 2026-04-09.
