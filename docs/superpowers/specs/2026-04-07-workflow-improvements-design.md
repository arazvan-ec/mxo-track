# Spec — Workflow Improvements (Tests + Integrations)

**Date:** 2026-04-07
**Type:** Testing + refactor
**Branch:** `claude/unify-menu-implementation-Yo8np`

## Problem

Autodiscovery activó 5 validators dormidos revelando: finalize-validator con false positives, spec-compliance-validator invisible (dead code), user_approved no detecta "prefiero", y 0 tests e2e del flujo completo. Alternativa A aprobada en sesión anterior.

## Existing Functionality Inventory

| Element | Decision | Justification |
|---------|----------|---------------|
| `test-phase-advance.sh` (Test 8 full walk) | **Modificar** | Crear artefactos reales para satisfacer validators |
| `test-enforcement-layers.sh` | **No tocar** | Ya actualizado en sesión anterior |
| `finalize-validator.sh` | **Modificar** | Threshold para reducir false positives |
| `spec-compliance-validator.sh` | **Integrar** | Llamarlo desde planning-validator como sub-check |
| `planning-validator.sh` | **Modificar** | Añadir call a spec-compliance |
| `user-prompt-state.sh` | **Modificar** | Ampliar keywords de aprobación |
| `retrospective-validator.sh` | **No tocar** | Funciona correctamente |

## Omission Decisions

| Element | Decision | Justification |
|---------|----------|---------------|
| Rename `*-gate.sh` convention | Omitir | Requiere actualizar workflow-engine.sh que referencia los validators — riesgo alto, beneficio bajo |
| debug-validator.sh | No tocar | Funciona correctamente en su contexto (PreToolUse), no es dead code |
| Semantic validation | Omitir | No factible en bash sin LLM |

## Alternativa A — Tests + integrations en paralelo (CHOSEN)

6 tareas independientes ejecutadas en paralelo. Ventaja: máxima velocidad. Trade-off: cada agente necesita contexto completo de su tarea.

## Alternativa B — Secuencial con verificación entre cada tarea

Más seguro pero ~3x más lento. Desventaja: no aprovecha independencia entre tareas.

## Architecture

### spec-compliance integration
`planning-validator.sh` llama a `spec-compliance-validator.sh` como sub-check después de sus propias validaciones. Si spec-compliance retorna exit 1, planning-validator emite warning pero no bloquea (preserva soft gate behavior).

### finalize-validator threshold
Solo sugerir knowledge module update si ≥5 archivos cambiaron matching un patrón, O si se crearon archivos nuevos (no solo modificaciones).

### user_approved keywords
Añadir al regex existente: `prefiero|me parece|suena bien|hazlo|implementa|proceed`.
