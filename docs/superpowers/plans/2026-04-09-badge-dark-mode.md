# Plan: Theme-Aware Badge Colors for Dark Mode

**Fecha:** 2026-04-09
**Spec:** `docs/superpowers/specs/2026-04-09-badge-dark-mode-design.md`

## Phase 1 (v0): Implementación directa

### Wave 1: CSS Infrastructure
- **1a:** Agregar badge color tokens (`:root` + `.dark`) a `frontend/src/index.css`
  → produce: 12 pares de variables `--badge-COLOR-bg` / `--badge-COLOR-text`
- **1b:** Agregar clases utility `.badge-COLOR` que usan las variables
  → produce: 12 clases + 5 soft + 4 toast

### Wave 2: Template Bulk Replacement (paralelo)
- **2a:** `perl` con negative lookbehind para reemplazar `bg-COLOR-100` → `badge-COLOR`
  → produce: todos los fondos de badge migrados (no toca `group-hover:bg-COLOR-100`)
- **2b:** `perl` condicional para eliminar `text-COLOR-{500-800}` solo en líneas con `badge-COLOR`
  → produce: texto de badge eliminado (el color ahora viene de la clase CSS)

### Wave 3: Edge Cases (secuencial)
- **3a:** Toasts en `base.html.twig` — reemplazar con `.toast-*` classes
- **3b:** Notification badges — reemplazar con `.badge-soft-*`
- **3c:** Driver soft badges — reemplazar `bg-COLOR-50 text-COLOR-700` con `.badge-soft-*`
- **3d:** Import/route_template badges — mismo patrón soft

### Wave 4: Verification
- **4a:** `npm run build` — TypeScript + Vite build
- **4b:** Verificar que no quedan `bg-COLOR-100` hardcoded en contextos badge

## Phase 2 (Mature): No aplica
Cambio cosmético, no requiere refactor arquitectónico posterior.
