# Spec: Extend Workflow Gates to All Business Folders

**Fecha:** 2026-04-09
**Tipo:** Code change (hooks infrastructure)
**Branch:** `claude/improve-views-claude-md-NiE60`

## Problema

`workflow-engine.sh` solo protege `backend/src/*` y `frontend/src/*` como "code". Los demás paths de negocio (`templates/`, `config/`, `migrations/`, `assets/`, `ml-service/`, `docker/`, `scripts/`, `openspec/`) caen a `"other"` y pasan sin validators. Esto permite editar templates, configuración y infraestructura sin brainstorming ni planning — exactamente lo que ocurrió con el cambio de badges (interacción anterior).

## Existing Functionality Inventory

| Elemento | Decisión | Justificación |
|----------|----------|---------------|
| `classify_file()` en workflow-engine.sh L62-74 | Transform — extender patterns | Agregar paths de negocio al case "code" |
| Gate 1 (flow required) L78-88 | Transform — extender DENY | Bloquear HARD para todos los paths de negocio |
| Gate 3 (scope change) L96-105 | Transform — extender detection | Detectar scope change en todos los paths |
| `get_validators_for_flow()` L112-183 | Include — sin cambios | Ya rutea "code" correctamente |
| `pre-push-gate.sh` protected_patterns | Include — como referencia | Lista de paths ya definida ahí |
| Exclusiones L44-50 | Include — sin cambios | `.claude/`, `node_modules/`, etc. siguen excluidos |

## Omission Decisions

| Elemento | Decisión | Justificación |
|----------|----------|---------------|
| Categorías granulares (ui/infra/config) | Omit | Mismos validators en práctica, complejidad sin beneficio |
| Cambios en validators individuales | Omit | Los validators existentes funcionan correctamente |
| Cambios en pre-push-gate.sh | Omit | Ya protege estos paths |

## Approach: Extender "code" (Opción A)

- Agregar 8 paths al case `"code"` en `classify_file()`
- Actualizar Gate 1 y Gate 3 para cubrir los mismos paths
- ~15 líneas modificadas en 1 archivo

### Aprobación

Usuario aprobó Opción A el 2026-04-09.
