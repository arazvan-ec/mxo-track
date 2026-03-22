# Workflow Verification System — Design Spec

**Date:** 2026-03-22
**Bounded Context:** Pragmático (tooling/infrastructure)
**Status:** Approved

## Problema

El sistema actual de hooks tiene limitaciones que afectan la efectividad del enforcement del flujo de trabajo:

1. **Sin modelo de fases:** `full-flow-gate.sh` verifica condiciones binarias (done/not done) pero no entiende la progresión natural del flujo (consult → brainstorm → plan → implement → verify → capture → retrospective → finalize)
2. **Sin deviation tracking:** Cuando Claude necesita saltar una fase (bug urgente), no hay mecanismo para registrar la desviación y forzar el retorno
3. **Sin visibilidad:** No existe un archivo vivo que muestre el estado actual del workflow en cualquier momento
4. **Hooks no composables:** Cada hook es monolítico. Añadir un nuevo check requiere modificar un script existente o crear uno nuevo con su propia lógica de bypass

## Approach A — Refactor incremental de hooks existentes

Modificar `full-flow-gate.sh` y `tdd-gate.sh` para añadir phase tracking y deviation.

**Ventaja:** Menos archivos nuevos, cambio gradual.
**Desventaja:** Aumenta la complejidad de scripts ya complejos. No resuelve la composabilidad. Mezcla responsabilidades.

## Alternativa B — Workflow Engine + Phase Validators (elegida)

Reemplazar los hooks monolíticos con un engine central que delega a validators por fase.

**Ventaja:** Cada validator tiene una sola responsabilidad. Añadir nuevas fases = nuevo archivo. El engine maneja la lógica de transición. Escala mejor.
**Desventaja:** Más archivos inicialmente. Requiere migrar la lógica existente.

## Alternativa C — State machine formal con transiciones explícitas

Implementar un FSM completo con estados, transiciones válidas y eventos.

**Ventaja:** Máxima rigurosidad.
**Desventaja:** Over-engineering para hooks de shell. La complejidad no justifica el beneficio sobre la Alternativa B.

## Opcion elegida: Alternativa B

Trade-off aceptable: más archivos pero mejor mantenibilidad y extensibilidad. La lógica de cada validator es simple y testeable de forma independiente.

## Arquitectura

### Estructura de archivos

```
.claude/hooks/
├── workflow-engine.sh              # Motor central (PreToolUse Edit|Write|Bash)
├── validators/
│   ├── consult-validator.sh        # Gate suave
│   ├── brainstorm-validator.sh     # Gate duro
│   ├── planning-validator.sh       # Gate duro
│   ├── implementation-validator.sh # Gate duro (migra tdd-gate.sh)
│   ├── verification-validator.sh   # Gate duro
│   ├── capture-validator.sh        # Gate suave
│   ├── retrospective-validator.sh  # Gate suave
│   └── finalize-validator.sh       # Gate suave
├── post-commit-validator.sh        # PostToolUse (fusiona commit-msg-lint + post-commit-reminder)
├── post-push-validator.sh          # PostToolUse (migra manifest-auto-run + workflow-status)
└── workflow-status.sh              # Genera .claude/workflow-status.md
```

### Session State Model (session-state.json)

```json
{
  "session_date": "2026-03-22",
  "flow_type": null,
  "current_phase": null,
  "interaction_classification": null,
  "phase_history": [],
  "evidence": {
    "decisions_read": false,
    "logs_scanned": false,
    "user_turns": 0,
    "alternatives_proposed": false,
    "user_approved": false,
    "spec_path": null,
    "plan_path": null,
    "tests_written": 0,
    "tests_passed": null,
    "lint_clean": null,
    "execution_log_path": null,
    "branch_strategy": null
  },
  "deviation": {
    "active": false,
    "reason": null,
    "skipped_phases": [],
    "return_to_phase": null,
    "acknowledged_by_user": false
  }
}
```

### Workflow Engine Flow

1. Lee `session-state.json`
2. Si `flow_type=null` y archivo es `src/` o `tests/` → BLOQUEA
3. Si `deviation.active=true` → WARNING
4. Determina fase requerida por file path
5. Invoca validator de la fase
6. Gate duro falla → BLOQUEA; Gate suave falla → WARNING

### Phase Transition Rules

| De → A | Pre-condición |
|---|---|
| consult → brainstorming | `decisions_read` OR `logs_scanned` |
| brainstorming → planning | `user_turns` ≥ 3 AND `alternatives_proposed` AND `user_approved` AND spec ≥500B |
| planning → implementation | plan existe con `- [ ]` tasks |
| implementation → verification | `tests_written` > 0 |
| verification → capture | `tests_passed` AND `lint_clean` |
| capture → retrospective | `execution_log_path` existe |
| retrospective → finalize | Siempre permitido |

### Deviation Tracking

- Claude declara deviation con reason
- Engine permite acción pero registra
- Después de la acción urgente, engine bloquea hasta retomar fase saltada
- Máximo 1 deviation activa, no anidable

### Workflow Status (.claude/workflow-status.md)

Archivo generado automáticamente (en `.gitignore`), muestra estado actual de fases, deviations, y salud de hooks. Se regenera después de cada push y bajo demanda con `make workflow-status`.

### Migration Map

| Hook actual | Destino | Acción |
|---|---|---|
| `full-flow-gate.sh` | `workflow-engine.sh` + validators | Reemplazado |
| `tdd-gate.sh` | `implementation-validator.sh` | Migrado |
| `commit-msg-lint.sh` | `post-commit-validator.sh` | Fusionado |
| `post-commit-reminder.sh` | `post-commit-validator.sh` | Fusionado |
| `manifest-auto-run.sh` | `post-push-validator.sh` | Migrado + ampliado |
| `session-start.sh` | Adaptado (nuevo modelo de estado) | Adaptado |
| `test-self-gating.sh` | Nuevo test suite para validators | Reescrito |
