# Plan: Extend Workflow Gates to All Business Folders

**Fecha:** 2026-04-09
**Spec:** `docs/superpowers/specs/2026-04-09-extend-workflow-gates-design.md`

## Phase 1 (v0): Implementación

### Wave 1: Modify workflow-engine.sh
- **1a:** Extender `classify_file()` (L62-74) — agregar patterns para templates, config, migrations, assets, ml-service, docker, scripts, openspec al case "code"
  → produce: todos los paths de negocio clasificados como "code"
- **1b:** Extender Gate 1 (L78-88) — agregar paths de negocio al case DENY
  → produce: HARD block para edits sin flow_type en paths de negocio
- **1c:** Extender Gate 3 (L96-105) — agregar paths al scope change detection
  → produce: scope change warning para todos los paths de negocio

### Wave 2: Update documentation
- **2a:** Actualizar `.claude/README.md` — documentar paths protegidos
- **2b:** Actualizar `CLAUDE.md` — tabla de gates con paths extendidos

### Wave 3: Verification
- **3a:** Verificar con test manual que classify_file retorna "code" para cada path nuevo
- **3b:** Verificar que paths excluidos (.claude/, node_modules/) siguen pasando
- **3c:** Verificar build frontend (no roto por cambios en hooks)

## Phase 2 (Mature): No aplica
Cambio de infraestructura de hooks, no requiere refactor posterior.
