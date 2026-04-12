# Spec — Enforce CLAUDE.md Rules via Hooks (Critical + Quick Wins)

**Date:** 2026-04-12
**Branch:** `claude/add-customer-filters-ev8cG`
**Approved by user:** Yes

## Problema

18 reglas en CLAUDE.md existen solo como texto sin enforcement en hooks. El modelo las viola sin consecuencias visibles. Esta spec cubre las 8 de mayor impacto, divididas en critical gates (previenen fallos severos) y quick wins (bajo esfuerzo, alto valor).

## Existing Functionality Inventory

| Element | Decision | Justification |
|---------|----------|---------------|
| `pre-push-gate.sh` | Transform | Agregar checks #5 (manifest) y #8 (deploy command) |
| `auto-evidence.sh` | Transform | Agregar check #7 (ephemeral paths) y reforzar #2 (fresh evidence) |
| `workflow-engine.sh` | Transform | Agregar check #6 (uncommitted changes before Agent) |
| `brainstorm-validator.sh` | Transform | Agregar check #4 (TDD task isolation) |
| `phase-advance.sh` | Transform | Agregar check #3 (deviation criteria) |
| `post-tool-handler.sh` | Include (keep) | Dispatcher ya enruta a sub-scripts |
| `settings.json` | Include (keep) | Registros ya cubren los eventos necesarios |

## Omission Decisions

| Element | Decision | Justification |
|---------|----------|---------------|
| Wave concurrency (#1) | Omit for now | Requiere tracking de agentes background activos — complejidad alta, el modelo raramente despacha waves en paralelo aún |
| Response text scanning (narration) | Omit | No mechanically enforceable sin false positives |
| Session-state integrity checksum | Omit | Bajo impacto, corruption es rara |

## Approach Selection

### Opcion A (Seleccionada): Extensiones de hooks existentes
- **Ventaja:** No agrega archivos nuevos al dispatcher, mantiene la arquitectura simple
- **Ventaja:** Cada hook ya tiene el patrón de lectura de state, deny/warn, etc.
- **Ventaja:** 7 de 8 reglas son extensiones de hooks que ya existen
- **Trade-off:** Los hooks crecen en tamaño, pero cada check es independiente (fácil de leer)

### Alternativa B: Scripts nuevos por regla
- **Desventaja:** 8 scripts nuevos → 8 registros en settings.json → dispatcher más complejo
- **Desventaja:** Más hooks por evento = más latencia
- **Descartada:** Over-engineering para checks de 5-15 líneas cada uno

## Design — 7 Enforcement Rules

### Critical #2: Fresh evidence before claims
**File:** `auto-evidence.sh`
**Mechanism:** Al detectar `phpunit` o `make lint`, guardar timestamp del comando. En `pre-push-gate.sh`, verificar que `tests_passed` tiene timestamp reciente (misma sesión).
**State field:** `.evidence.tests_ran_at` (ISO timestamp), `.evidence.lint_ran_at`

### Critical #3: Deviation criteria validation
**File:** `phase-advance.sh`
**Mechanism:** Cuando `deviation.active` se intenta activar, ejecutar checks:
- `git diff --stat HEAD | tail -1` → extraer líneas cambiadas, bloquear si >= 30
- `grep -r '#\[Route\]' --include='*.php'` en archivos nuevos → bloquear si hay endpoints nuevos
- Verificar que `deviation.reason` contiene file:line reference
**Gate:** DENY si cualquier criterio falla

### Critical #4: TDD task isolation in plans
**File:** `brainstorm-validator.sh`
**Mechanism:** Leer plan file, buscar tareas que contengan solo "test" sin implementación asociada:
- Regex: líneas que matcheen `^[-*]\s.*(add|write|create)\s+test` sin contexto TDD
- SOFT warning (exit 1), no hard block — puede haber excepciones legítimas

### Quick Win #5: Manifest before final push
**File:** `pre-push-gate.sh`
**Mechanism:** Verificar que `docs/codebase-manifest.md` tiene cambios en los commits del branch:
- `git diff origin/main..HEAD --name-only | grep codebase-manifest`
- SOFT warning si no aparece (puede ser push intermedio legítimo)

### Quick Win #6: No agents with uncommitted changes
**File:** `workflow-engine.sh`
**Mechanism:** Cuando tool_name = "Agent", ejecutar `git status --porcelain`:
- Si hay cambios staged/unstaged → DENY con mensaje "Commit before dispatching agents"
- Excepción: si el agente es subagent_type=Explore (solo lectura)

### Quick Win #7: Artifacts in repo, not ephemeral
**File:** `auto-evidence.sh`
**Mechanism:** Cuando Write/Edit a paths efímeros (`/tmp/`, `/root/.claude/`):
- Si el contenido parece spec/plan/execution-log (por keywords), emit WARNING
- No bloquear — a veces los paths efímeros son legítimos (temp files)

### Quick Win #8: Exact deploy command verification
**File:** `auto-evidence.sh` + `pre-push-gate.sh`
**Mechanism:** 
- `auto-evidence.sh`: Al detectar `npm run build` o `make lint`, setear `.evidence.verified_commands[]`
- `pre-push-gate.sh`: Verificar que `verified_commands` contiene los comandos canónicos del proyecto
- Canonical commands: `npm run build` (frontend), `make lint` (backend)
- SOFT warning si se usaron aproximaciones (`tsc --noEmit`, `php -l`)
