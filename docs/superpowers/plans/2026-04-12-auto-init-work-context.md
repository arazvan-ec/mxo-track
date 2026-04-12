# Plan — Auto-init work_context.description

**Spec:** `docs/superpowers/specs/2026-04-12-auto-init-work-context-design.md`

## Wave 1 (single wave — too small to parallelize)

- **Task 1a:** Leer `interaction_classification` en user-prompt-state.sh (1 línea)
- **Task 1b:** Agregar auto-init block después de lectura de work_context vars (~8 líneas)
- **Task 1c:** Verificar con estado simulado (interaction_classification set, work_context.description vacío)
- **Task 1d:** Verificar que no sobrescribe description ya seteado manualmente
