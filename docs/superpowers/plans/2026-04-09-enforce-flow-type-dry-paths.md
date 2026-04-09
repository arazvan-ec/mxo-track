# Plan: Enforce Valid Flow Types + DRY Protected Path Lists

**Fecha:** 2026-04-09
**Spec:** `docs/superpowers/specs/2026-04-09-enforce-flow-type-dry-paths-design.md`

## Phase 1 (v0)

### Wave 1: Extract shared library
- **1a:** Crear `.claude/hooks/lib/classify-file.sh` con la función `classify_file()`
  → produce: función reutilizable por ambos scripts

### Wave 2: Update consumers (paralelo)
- **2a:** Modificar `workflow-engine.sh` — reemplazar classify_file inline con `source lib/classify-file.sh`; agregar validación de flow_type después de leer FLOW_TYPE (~8 líneas)
  → produce: flow types inválidos bloqueados
- **2b:** Modificar `pre-push-gate.sh` — importar `classify-file.sh`; reemplazar `has_protected_changes()` para usar classify_file en vez de protected_patterns array
  → produce: única fuente de verdad para paths protegidos

### Wave 3: Verification
- **3a:** Test classify_file desde la librería compartida
- **3b:** Test flow_type validation (valid → pass, invalid → deny)
- **3c:** Test has_protected_changes con classify_file
- **3d:** Frontend build (no roto)
