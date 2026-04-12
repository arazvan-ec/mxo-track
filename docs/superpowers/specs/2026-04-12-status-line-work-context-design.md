# Spec — Status Line Work Context

**Fecha:** 2026-04-12
**Tipo:** Enhancement — hooks de infraestructura
**Branch:** `claude/status-message-problem-id-gd83L`

## Problema

El status line del hook `UserPromptSubmit` muestra la fase actual pero no indica:
- En debug multi-problema: cuál problema se está trabajando (1/2, 2/2)
- En full flow: cuál es la descripción de la interacción, wave actual, etc.
- En flujos simples (micro/light/explore): qué se está investigando/documentando

El usuario ve acciones pero no sabe a qué contexto de trabajo pertenecen.

## Diseño aprobado

### Nuevo campo `work_context` en session-state.json

Ubicación: `evidence.work_context`

```json
"work_context": {
  "description": "Mejorar status line multi-problema",
  "problems": {
    "total": 2,
    "current": 1,
    "labels": ["phase-advance.sh solo define full", "Tests pre-existentes"]
  },
  "wave": {
    "total": 4,
    "current": 2,
    "label": "Fase 1: Rutas + Vehículos"
  }
}
```

### Campos

| Campo | Tipo | Cuándo se usa | Quién lo escribe |
|-------|------|---------------|------------------|
| `description` | string\|null | Todos los flujos | Claude al clasificar |
| `problems.total` | int | Debug multi-problema | Claude al diagnosticar |
| `problems.current` | int | Debug multi-problema | Claude al cambiar de problema |
| `problems.labels` | string[] | Debug multi-problema | Claude al diagnosticar |
| `wave.total` | int | Full implementation con waves | Claude al entrar a impl |
| `wave.current` | int | Full implementation | Claude al cambiar de wave |
| `wave.label` | string\|null | Full implementation | Claude al cambiar de wave |

### Reglas de display

- `description` siempre se muestra si existe (truncado a ~40 chars)
- `problems` solo si `total > 1` (un solo problema no necesita numeración)
- `wave` solo durante implementation y si `total > 0`
- `task_progress` existente se mantiene sin cambios en la línea "Estado"

### Status line por flujo

```
# micro/light/explore — agregan descripción
📍 micro | Endpoint devuelve 404 en /api/routes

# debug con múltiples problemas
📍 Debug: Fix (4/4) — Problema 1/2: phase-advance.sh
  ✅ consult → ✅ root_cause → ✅ pattern_search → 🔄 fix

# debug sin sub-problemas (problems omitido)
📍 Debug: Root_cause (2/4)
  🔄 consult → ⬚ root_cause → ⬚ pattern_search → ⬚ fix

# full implementation con wave + tarea
📍 Implementation (4/8) — Wave 2/4 · t3/5: RouteListApiController
  ✅ consult → ✅ brainstorm → ✅ planning → 🔄 impl → ⬚ verify...

# full otras fases — solo descripción
📍 Brainstorming (2/8) — Mejorar status line
  ✅ consult → 🔄 brainstorm → ⬚ planning...
```

## Existing Functionality Inventory

| Elemento | Decisión | Justificación |
|----------|----------|---------------|
| `session-state.json` schema | Transform | Agregar `work_context` |
| `user-prompt-state.sh` micro/light/explore | Transform | Mostrar description |
| `user-prompt-state.sh` debug section | Transform | Mostrar problema N/M |
| `user-prompt-state.sh` full impl | Transform | Mostrar wave |
| `user-prompt-state.sh` full general | Transform | Mostrar description |
| `.claude/README.md` schema docs | Transform | Documentar work_context |
| `task_progress` | Include | Se mantiene, complementado por work_context |
| `phase-advance.sh` | Omit | No lee work_context |
| Validators | Omit | No validan work_context |

## Omission Decisions

| Element | Decision | Justification |
|---------|----------|---------------|
| `phase-advance.sh` | Omit | No necesita leer work_context — solo gestiona transiciones de fase |
| Validators | Omit | work_context es informativo, no gate-blocking |
| `auto-evidence.sh` | Omit | No puede detectar problemas automáticamente |
| `session-start.sh` | Omit | Ya resetea evidence completa; work_context se inicializa en null |

## Archivos a modificar

1. `.claude/hooks/user-prompt-state.sh` — display logic (~40 líneas)
2. `.claude/README.md` — schema documentation (~15 líneas)
3. `.claude/session-state.json` — initial state (añadir work_context null)
