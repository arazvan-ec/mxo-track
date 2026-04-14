# Design Spec — Auto-populate task_progress for status line

**Fecha:** 2026-04-14
**Branch:** `claude/enhance-dashboard-widgets-sxseH`
**Origen:** gap meta identificado tras sesión de gap-closure — el status line no mostró
la tarea/gap activa porque `evidence.task_progress` nunca se pobló.

## Problema

El status line (`workflow-status-line.sh:101-109`) lee `evidence.task_progress.{current,
total,label,completed_labels}` para mostrar "Tarea X/Y — Label". Esos campos quedan
en ceros salvo que el modelo llame explícitamente a `plan-progress.sh init/advance`.
En las últimas dos sesiones (PR 1, gap closure) nunca los llamé, así que el usuario
sólo vio "Implementation 4/8" (fases) sin saber qué tarea concreta estaba en curso.

## Decisión

Automatizar la población de `task_progress` por dos caminos complementarios:

1. **Plan-driven (cuando hay plan con waves/tareas):** `phase-advance.sh`, al
   transicionar a `implementation`, auto-ejecuta `plan-progress.sh init` si
   `task_progress.total == 0` y `evidence.plan_path` está seteado.

2. **TodoWrite-driven (cuando el modelo trabaja con TodoWrite en vez de plan waves):**
   `todowrite-mirror.sh`, además de escribir `todo_progress`, también escribe
   `task_progress` cuando no existe un plan parseado (`task_index` vacío/ausente):
   - `task_progress.total` ← cantidad de todos
   - `task_progress.current` ← completados + 1 (posición del in_progress)
   - `task_progress.label` ← `in_progress_label`
   - `task_progress.completed_labels` ← labels de los completados

   Guard: si `task_progress.task_index` existe y no está vacío, no tocar
   `task_progress` — el plan es autoritativo.

## Existing Functionality Inventory

| Elemento | Decisión | Justificación |
|---|---|---|
| `.claude/hooks/plan-progress.sh` | Incluir (reusar) | Ya tiene init/advance/complete robustos |
| `.claude/hooks/todowrite-mirror.sh` | Transformar | Añadir escritura condicional a task_progress |
| `.claude/hooks/phase-advance.sh` | Transformar | Auto-init al entrar a implementation |
| `.claude/hooks/workflow-status-line.sh` | Omitir | Ya consume `task_progress`, no cambia |
| Tests de hooks existentes | Incluir (validar) | Ejecutar suite para no romper |

## Diseño

### Cambio 1 — `phase-advance.sh`

Al final del script, tras marcar phase avanzada a `implementation`:

```bash
# Auto-init plan progress if not already populated
if [ "$TARGET" = "implementation" ]; then
  CURRENT_TOTAL=$(jq -r '.evidence.task_progress.total // 0' "$STATE_FILE")
  PLAN_PATH=$(jq -r '.evidence.plan_path // ""' "$STATE_FILE")
  if [ "$CURRENT_TOTAL" = "0" ] && [ -n "$PLAN_PATH" ] && [ -f "$REPO/$PLAN_PATH" ]; then
    bash "$REPO/.claude/hooks/plan-progress.sh" init 2>&1 | sed 's/^/  [auto-init] /' || true
  fi
fi
```

Non-blocking: si falla, no bloquea la phase advance.

### Cambio 2 — `todowrite-mirror.sh`

Después del bloque actual que escribe `todo_progress`, añadir:

```bash
# Mirror into task_progress unless a plan has been parsed (plan is authoritative)
HAS_PLAN_INDEX=$(jq -r '(.evidence.task_progress.task_index // []) | length' "$STATE_FILE")
if [ "$HAS_PLAN_INDEX" = "0" ] && [ "$TOTAL" != "0" ]; then
  COMPLETED_LABELS=$(echo "$TODOS" | jq -c '[.[] | select(.status == "completed") | (.activeForm // .content)]')
  CURRENT_IDX=$((COMPLETED + 1))
  [ "$CURRENT_IDX" -gt "$TOTAL" ] && CURRENT_IDX="$TOTAL"
  jq --argjson total "$TOTAL" \
     --argjson cur "$CURRENT_IDX" \
     --arg lbl "$IN_PROGRESS_LABEL" \
     --argjson cl "$COMPLETED_LABELS" '
    .evidence.task_progress.total = $total |
    .evidence.task_progress.current = $cur |
    .evidence.task_progress.label = (if $lbl == "" then null else $lbl end) |
    .evidence.task_progress.completed_labels = $cl
  ' "$STATE_FILE" > /tmp/todo_task.json && mv /tmp/todo_task.json "$STATE_FILE"
fi
```

## Omission Decisions

| Elemento | Decisión | Justificación |
|---|---|---|
| Auto-advance en ToolUse de Edit/Write | Omitir | Demasiado acoplado, riesgo de false positives |
| UI de `plan-progress.sh` (colores, etc.) | Omitir | Fuera de alcance |
| Test unitarios con bats | Omitir | Existe `test-*.sh` estilo manual, seguir patrón |
| Hook SessionStart para init si retomamos implementation | Omitir | Session-start ya resetea; si retomas una sesión el modelo puede llamar init manualmente |

## Criterios de aceptación

1. Tras `phase-advance.sh implementation` con plan_path seteado, `task_progress.total > 0`
   sin intervención manual.
2. Tras un TodoWrite con 6 items (1 completed, 1 in_progress, 4 pending), el status line
   muestra "Tarea 2/6 — <label del in_progress>".
3. Si hay un plan parseado (task_index no vacío), TodoWrite no sobrescribe task_progress.
4. Los hooks existentes siguen funcionando (suite test-*.sh pasa).
