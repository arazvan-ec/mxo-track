# Plan — 2026-04-13 — Status Line: Plan Hierarchy + TodoWrite Mirror

**Branch:** `claude/enhance-dashboard-widgets-R46AJ` (side-task)
**Approved alternative:** C (auto-parser del plan + mirror de TodoWrite)

## Objetivo

Que el status line muestre jerarquía completa **siempre que haya un plan activo**:
problema (si 2+) → fase → wave → tarea → todos del modelo. Auto-poblado desde el plan
markdown (sin `jq` manual) + reflejo en vivo de la lista de TodoWrite.

## Diseño

### Schema extendido en session-state.json

Añadir a `evidence`:

```json
"todo_progress": {
  "total": 0,
  "completed": 0,
  "in_progress_label": null,
  "items": []   // [{content, status}]
}
```

`task_progress` y `work_context.wave` ya existen — solo se aprovechan más.

### 1. `.claude/hooks/plan-progress.sh` (nuevo)

API:
- `plan-progress.sh init` — lee `evidence.plan_path`, parsea waves y tareas:
  - Wave: regex `^### Wave ([0-9]+)\b.*$`
  - Tarea: regex `^#### \*\*([0-9]+[a-z]?) [—-] (.+)\*\*$`
  - Escribe en session-state:
    - `evidence.task_progress.total` = total tareas
    - `evidence.task_progress.task_index` = `[{id:"1a", wave:1, label:"Backend..."}, ...]`
    - `evidence.work_context.wave.total` = total waves
    - `evidence.work_context.wave.labels` = `["Fundaciones", "Widgets refactor", ...]`
- `plan-progress.sh advance <task_id>` — busca `task_id` (ej. `2a`) en el index, fija:
  - `evidence.task_progress.current` (1-based ordinal)
  - `evidence.task_progress.label` (texto de la tarea)
  - `evidence.work_context.wave.current` (wave de la tarea)
  - `evidence.work_context.wave.label` (label de la wave)
- `plan-progress.sh complete` — incrementa `completed_labels` con la actual; si todas
  done, resetea `current` a 0

### 2. `.claude/hooks/todowrite-mirror.sh` (nuevo)

PostToolUse hook que matchea `TodoWrite`:
- Lee stdin del hook (`tool_input.todos`)
- Cuenta `completed`, `in_progress`, `pending`
- Extrae `content` del todo `in_progress`
- Escribe en `evidence.todo_progress`

Non-blocking; siempre exit 0.

### 3. Modificar `.claude/settings.json`

Añadir matcher PostToolUse para `TodoWrite`:

```json
{
  "matcher": "TodoWrite",
  "hooks": [
    { "type": "command", "command": "/home/user/mxo-track/.claude/hooks/todowrite-mirror.sh", "timeout": 2 }
  ]
}
```

### 4. Modificar `.claude/hooks/user-prompt-state.sh`

a) Añadir `evidence.todo_progress` al reset block daily (línea 150-151) inicializado vacío.

b) Mover el bloque "wave display" (líneas 311-316) fuera de la condición `implementation` —
   mostrar wave/tarea siempre que `task_progress.total > 0` durante: planning,
   implementation, verification, capture.

c) Añadir nueva línea "Plan: ✅✅⬚⬚⬚ N/T — Tarea actual: 2a Label" cuando hay plan parseado.

d) Añadir línea "Todos: 🔄 X (3/8 completados)" cuando `todo_progress.total > 0`.

### 5. `.claude/hooks/session-start.sh`

Añadir `evidence.todo_progress` al reset diario (mismo schema vacío que en user-prompt-state).

## Tareas (4 waves, mayoría secuencial por dependencia de archivos)

### Wave 1 [parallel: 2 tareas]

- **1a — Crear `plan-progress.sh`** (archivo nuevo) con tests inline en `test-plan-progress.sh`
- **1b — Crear `todowrite-mirror.sh`** (archivo nuevo) con tests inline en `test-todowrite-mirror.sh`

### Wave 2 [secuencial]

- **2 — Modificar `settings.json` + `user-prompt-state.sh` + `session-start.sh`**
  Tres archivos, cambios pequeños y relacionados — una sola edición coherente:
  - settings.json: añadir matcher TodoWrite
  - user-prompt-state.sh: mostrar wave/task fuera de implementation, añadir línea Plan + Todos, añadir todo_progress al reset
  - session-start.sh: añadir todo_progress al reset diario

### Wave 3 [secuencial]

- **3 — Verificación manual:**
  - Ejecutar `plan-progress.sh init` con el plan del dashboard → verificar JSON correcto
  - Disparar TodoWrite → verificar mirror en session-state
  - Inspeccionar status line salida en próximo prompt

### Wave 4 [secuencial]

- **4 — Capture + commit + push** (sin retrospective formal — side task)

## Verificación esperada

Tras implementar y ejecutar `plan-progress.sh init` con el plan del dashboard,
el status line en el siguiente prompt debe mostrar:

```
📍 Planning (3/8) — Wave —/4
  ✅ consult → ✅ brainstorm → 🔄 planning → ⬚ impl → ⬚ verify → ⬚ capture → ⬚ retro → ⬚ finalize
  Plan: ⬚⬚⬚⬚⬚⬚⬚⬚⬚⬚ 0/10 tareas (10 tareas en 4 waves)
  Estado: spec, plan
  Siguiente: → implementation
```

Y tras `plan-progress.sh advance 2a`:

```
📍 Implementation (4/8) — Wave 2/4: Widgets refactor
  ...
  Plan: ✅✅✅✅⬚⬚⬚⬚⬚⬚ 4/10 — Tarea 2a: DashboardKpisWidget detailed
  Todos: 🔄 Implementando 2a (3/8 completados)
```

## LOC estimado

- plan-progress.sh: ~120 LOC
- todowrite-mirror.sh: ~40 LOC
- user-prompt-state.sh: +25 LOC, -0
- settings.json: +6 LOC
- session-start.sh: +1 LOC

Total: ~190 LOC nuevo, 25 LOC modificado.
