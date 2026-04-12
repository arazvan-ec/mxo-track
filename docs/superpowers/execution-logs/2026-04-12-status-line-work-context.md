# Execution Log — 2026-04-12 — Status Line Work Context

**Type:** enhancement
**Branch:** `claude/status-message-problem-id-gd83L`

## Brainstorming

- **Problema:** El status line no indicaba qué problema/wave/tarea se estaba trabajando. En debug multi-problema, el usuario veía acciones sin saber a cuál pertenecían.
- **Alcance expandido:** El usuario pidió no solo debug sino todos los flujos.
- **Alternativas:** (A) Modelo actualiza manualmente, (B) Reutilizar task_progress, (C) Campo dedicado `work_context`. Se eligió C por separación limpia de conceptos.
- **Diseño aprobado:** Nuevo campo `evidence.work_context` con `description`, `problems` (debug), `wave` (full impl).

## Planning

- 3 waves: Schema + initial state → Display logic (5 subtareas) → Verificación
- Archivos: `.claude/README.md`, `.claude/hooks/user-prompt-state.sh`, `.claude/session-state.json`

## Implementation

- Wave 1: Schema documentado en README.md, campo inicializado en session-state.json
- Wave 2: 5 cambios en user-prompt-state.sh:
  - Variables de lectura de work_context con truncado a 40 chars
  - Flujos simples (micro/light/explore): muestran `description`
  - Debug: muestra `Problema N/M: label` si hay múltiples problemas, o `description` si hay uno solo
  - Full: muestra `Wave N/M: label` durante implementation, o `description` en otras fases
  - Auto-reset: limpia work_context al finalizar flujo
- Backwards compatible: si work_context no existe en session-state, el hook funciona igual que antes
- Total: +236 líneas, 4 archivos

## Verification

- 6 estados simulados manualmente:
  1. Debug multi-problema → `📍 Debug: Fix (4/4) — Problema 1/2: phase-advance.sh solo define full` ✅
  2. Debug single-problema → `📍 Debug: Root_cause (2/4) — Endpoint 404 en /api/routes` ✅
  3. Full implementation con wave → `📍 Implementation (4/8) — Wave 2/4: Fase 1: Rutas + Vehiculos` ✅
  4. Full brainstorm con description → `📍 Brainstorming (2/8) — Mejorar status line para todos los fl...` ✅
  5. Micro con description → `📍 micro | Endpoint devuelve 404 en /api/routes` ✅
  6. Sin work_context (backwards compat) → `📍 Brainstorming (2/8)` sin error ✅
- Tests/lint: skipped (scripts bash sin framework de test)

## Blockers

- Rebase sobre main: conflicto en `codebase-manifest.md` (archivo generado). Resuelto tomando versión de main.

## Lessons

- El campo `work_context` es puramente informativo (no gate-blocking). Esto simplifica la implementación pero depende de que Claude recuerde actualizarlo. Si se detectan omisiones frecuentes, considerar agregar detección automática en auto-evidence.
- La truncación a 40 chars previene que descripciones largas rompan el formato compacto del status line.
