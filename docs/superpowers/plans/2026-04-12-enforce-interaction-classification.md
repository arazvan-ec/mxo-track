# Plan — Enforce interaction_classification via warning

**Spec:** `docs/superpowers/specs/2026-04-12-enforce-interaction-classification-design.md`

## Phase 1 (single wave)

- **Task 1:** Agregar warning en `user-prompt-state.sh` cuando `flow_type` seteado + `interaction_classification` vacío (~5 líneas)
- **Task 2:** Verificar con estado simulado: flow_type=debug, interaction_classification=null → warning aparece
- **Task 3:** Verificar que con interaction_classification seteado → sin warning
