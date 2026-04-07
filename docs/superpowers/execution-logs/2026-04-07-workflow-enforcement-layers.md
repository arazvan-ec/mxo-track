# Execution Log — 2026-04-07 — Workflow Enforcement: 5 Capas Anti-Evasión

**Type:** feature (infrastructure — workflow engine)
**Branch:** `claude/unify-map-route-filter-b9Flc`
**Spec:** `docs/superpowers/specs/2026-04-07-workflow-enforcement-layers-design.md`
**Plan:** `docs/superpowers/plans/2026-04-07-workflow-enforcement-layers.md`

## Brainstorming

- **Problema:** Claude fabricaba phase_history, seteaba user_approved sin consentimiento, saltaba TDD. Solo un enforcement point (pre-push gate).
- **Alternatives:** Arquitectura monolítica de un solo gate reforzado vs 5 capas independientes
- **Chosen:** 5 capas — cada una cierra un vector de evasión específico. Sin cualquiera, queda un hueco.
- **Complexity estimate:** Alta — 15 archivos, 8 tareas, hooks interdependientes

## Planning

- 8 tareas, 15 archivos (4 nuevos, 11 modificados)
- Tareas 3a+3b paralelas (validators + user approval)

## Implementation

- **Capa 1:** `phase-advance.sh` (CLI) + `phase-transition-controller.sh` (PostToolUse:Bash)
  - phase_history usa timestamps: `{"phase": "consult", "at": "..."}`
  - Controller detecta escrituras directas y revierte
  - phase-advance.sh valida secuencia legal (no saltos, no retrocesos)

- **Capa 2:** Validators SOFT→HARD
  - consult, brainstorm, planning: exit 1 → exit 2 (DENY en vez de WARN)
  - implementation: HARD para plan, SOFT para TDD

- **Capa 3:** User approval en UserPromptSubmit
  - Regex ES+EN detecta "sí", "aprobado", "go ahead", etc.
  - Rechazo detecta "no", "cambia", "diferente"
  - Escrituras directas de user_approved revertidas por controller

- **Capa 4:** TDD order tracking en auto-evidence
  - `tdd_tracker` en session-state: registra edits test/src por tarea
  - Se resetea al cambiar task_progress.current

- **Capa 5:** Cross-validation en pre-push
  - Detecta strings planos en phase_history (formato antiguo)
  - Verifica timestamps cronológicos
  - Verifica spec/plan existen con tamaño mínimo
  - Soporta nuevo formato phase_history en phase_completed helper

- **Tarea 6:** Registrado phase-transition-controller en settings.json (PostToolUse:Bash, antes de auto-evidence)
- **Tarea 7:** 30 tests: 11 phase-advance + 7 controller + 12 integración
- **Tarea 8:** Docs actualizados: README, CLAUDE.md, decision log

## Verification

| Check | Resultado |
|-------|-----------|
| TypeScript (`tsc --noEmit`) | ✅ Sin errores |
| test-phase-advance.sh | ✅ 11/11 |
| test-phase-transition-controller.sh | ✅ 7/7 |
| test-enforcement-layers.sh | ✅ 12/12 |

## Retrospective

- **Estimación:** Alta complejidad estimada, alta complejidad real. 8 tareas ejecutadas sin blockers.
- **Lección principal:** La confianza en honor system no funciona. Los gates deben verificar realidad, no strings en JSON.
- **Test flaky:** El test del controller falló inicialmente por JSON con quotes anidados — usar `jq -n` para construir JSON seguro en tests.
- **Decisión sobre TDD tracking:** Dejado como SOFT porque no hay infraestructura de tests frontend para componentes visuales. Endurecer cuando exista.
- **Patrón para futuro:** Cualquier nueva evidencia auto-detectable debería seguir el patrón de auto-evidence.sh (PostToolUse, no bloqueante, atómico).
