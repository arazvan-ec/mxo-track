# Plan — Status Line Work Context

**Spec:** `docs/superpowers/specs/2026-04-12-status-line-work-context-design.md`
**Branch:** `claude/status-message-problem-id-gd83L`

## Phase 1 (v0): Implementación funcional

### Wave 1: Schema + initial state (sin dependencias)

- **1a:** Agregar `work_context` al schema en `.claude/README.md`
  → produce: documentación del nuevo campo
- **1b:** Agregar `work_context` inicializado en `.claude/session-state.json`
  → produce: estado inicial con campo null

### Wave 2: Display logic en hook (necesita Wave 1 para saber el schema)

- **2a:** Leer `work_context` al inicio de la sección de lectura de estado en `user-prompt-state.sh`
  → produce: variables bash con description, problems, wave
- **2b:** Actualizar flujos simples (micro/light/explore) para mostrar `description`
  → produce: status line con contexto en flujos simples
- **2c:** Actualizar flujo debug para mostrar `problems.current/total + label`
  → produce: status line con problema actual en debug
- **2d:** Actualizar flujo full para mostrar `description` + `wave` en implementation
  → produce: status line con wave y descripción en full flow
- **2e:** Actualizar reset de auto-reset (L99-128) para limpiar `work_context`
  → produce: cleanup correcto al terminar flujo

### Wave 3: Verificación (necesita Wave 2)

- **3a:** Test manual — simular session-state con debug multi-problema, verificar output
- **3b:** Test manual — simular session-state con full + wave, verificar output
- **3c:** Test manual — simular session-state sin work_context (backwards compatibility)

## Tareas TDD

Nota: Los hooks son scripts bash sin framework de test unitario. La verificación
es por ejecución directa con diferentes estados simulados.

## Phase 2 (Mature): No aplica

El cambio es autónomo y no requiere refactoring posterior.
