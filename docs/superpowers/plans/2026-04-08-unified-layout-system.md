# Plan — Unified Layout System

**Spec:** `docs/superpowers/specs/2026-04-08-unified-layout-system-design.md`
**Branch:** `claude/unify-menu-styling-eDVus`

## Phase 1 (v0): Align Twig layout to AppLayout

### Wave 1: Core layout change

**Tarea 1 — Alinear `base.html.twig` con `AppLayout.tsx`**

Archivo: `backend/templates/base.html.twig`

Cambios:
1. Container: `flex flex-col min-h-screen` → `flex flex-col h-screen w-full`
2. Content wrapper: `<div>` → `<div class="flex-1 overflow-auto">`
3. Mover flash messages DENTRO del content wrapper (ya están, solo verificar)

TDD:
- Test: verificar que `base.html.twig` lint pasa
- Test: verificar que las páginas admin siguen cargando (smoke test existente)

Commit: `refactor: align Twig layout to match React AppLayout structure`

### Wave 2: Verificación cross-view

**Tarea 2 — Verificar que templates existentes funcionan con el nuevo layout**

- Lint todas las templates Twig
- Verificar smoke tests existentes
- Verificar que TypeScript + Vite build siguen pasando
- Verificar que el scroll funciona correctamente en content largo

Commit: no (solo verificación)

### Wave 3: Cleanup

**Tarea 3 — Actualizar `ui-frontend.md` knowledge module**

Actualizar la documentación del layout en `docs/knowledge/ui-frontend.md` para reflejar el layout unificado.

Commit: `docs: update ui-frontend knowledge module with unified layout`

## Phase 2 (Mature): N/A

El cambio es minimal (~3 líneas en un archivo). No hay refactor posterior.
