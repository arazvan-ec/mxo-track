# Spec: Enforce Valid Flow Types + DRY Protected Path Lists

**Fecha:** 2026-04-09
**Tipo:** Code change (hooks infrastructure)
**Branch:** `claude/improve-views-claude-md-NiE60`

## Problema

1. **Flow type no validado:** El modelo puede setear `flow_type = "code_change"` (inválido) en session-state.json. `get_validators_for_flow("code_change", ...)` cae a `*) echo ""` → cero validators → edits a código pasan sin spec ni plan.
2. **Paths duplicados:** `pre-push-gate.sh` tiene `protected_patterns` array (L68-80) separado de `classify_file()` en `workflow-engine.sh`. Al extender uno, el otro se desincroniza.

## Existing Functionality Inventory

| Elemento | Decisión | Justificación |
|----------|----------|---------------|
| `classify_file()` en workflow-engine.sh | Transform — extraer a librería | Compartir con pre-push-gate |
| Gate 1 (flow required) en workflow-engine.sh | Transform — agregar validación | Rechazar flow types inválidos |
| `protected_patterns` en pre-push-gate.sh | Transform — reemplazar con classify_file | Eliminar duplicación |
| `get_validators_for_flow()` | Include — sin cambios | Ya maneja correctamente los 5 flow types |
| Validators individuales | Include — sin cambios | Ya verifican spec/plan correctamente |
| `has_protected_changes()` en pre-push-gate.sh | Transform — usar classify_file | Única fuente de verdad |

## Omission Decisions

| Elemento | Decisión | Justificación |
|----------|----------|---------------|
| Cambios en phase-advance.sh | Omit | Redundante si flow_type es válido |
| Cambios en validators | Omit | Ya funcionan correctamente para flow types válidos |
| Cambios en session-start.sh | Omit | No afectado |

## Approach

**Cambio 1:** Validar flow_type. Valores válidos: `micro`, `light`, `debug`, `full`, `explore`, `null`. Cualquier otro → DENY para code/test, WARN para otros.

**Cambio 2:** Extraer `classify_file()` a `.claude/hooks/lib/classify-file.sh`. Importar desde `workflow-engine.sh` y `pre-push-gate.sh`. En pre-push-gate, reemplazar `protected_patterns` array con llamadas a classify_file prepending `$REPO/` a paths relativos del diff.

### Aprobación

Usuario aprobó solo estos 2 cambios el 2026-04-09.
