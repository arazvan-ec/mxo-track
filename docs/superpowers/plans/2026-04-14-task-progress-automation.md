# Plan — Auto-populate task_progress

**Spec:** `docs/superpowers/specs/2026-04-14-task-progress-automation-design.md`
**Branch:** `claude/enhance-dashboard-widgets-sxseH`

## Fase 1 (v0)

### Wave 1 — Modificaciones paralelas (archivos disjuntos)

#### **1a — phase-advance.sh: auto-init cuando entra a implementation**

Editar `.claude/hooks/phase-advance.sh`: tras escritura exitosa de phase_history,
si target == implementation y task_progress.total == 0 y plan_path existe, llamar
a `plan-progress.sh init` (non-blocking).

→ produce: hook extendido que auto-inicializa task_progress

#### **1b — todowrite-mirror.sh: escribir task_progress si no hay plan**

Editar `.claude/hooks/todowrite-mirror.sh`: tras escribir todo_progress, si
`task_progress.task_index` está vacío y TOTAL > 0, escribir task_progress.

→ produce: hook extendido que espeja TodoWrite a task_progress

### Wave 2 — Verificación (depende de Wave 1)

#### **2a — Verificación del hook de fase**

Test manual:
1. Snapshot de session-state actual
2. Reset task_progress a ceros, mantener plan_path
3. Ejecutar `phase-advance.sh verification` y volver a `implementation` (trampa)
   — mejor: crear un state file temporal con phase=planning y ejecutar advance
4. Verificar que task_progress.total > 0 tras el advance

#### **2b — Verificación del hook de TodoWrite**

Test manual:
1. Crear un state file temporal sin task_index
2. Pipe un JSON con todos simulados a todowrite-mirror.sh
3. Verificar que task_progress.label == in_progress label

#### **2c — Regresión**

Ejecutar los test-*.sh relevantes:
- `bash .claude/hooks/test-phase-advance.sh`
- `bash .claude/hooks/test-workflow-engine.sh`

### Wave 3 — Documentación y publicación

#### **3a — Actualizar CLAUDE.md**

Añadir referencia a la auto-inicialización en la sección "Task Progress Tracking":
mencionar que `phase-advance.sh implementation` ahora auto-inicializa, y que TodoWrite
se espeja automáticamente si no hay plan.

#### **3b — Commit + push**

Commit mensaje: `feat: auto-populate task_progress from plan/todos`
Push a `claude/enhance-dashboard-widgets-sxseH`.

## Fase 2 (Mature)

N/A.
