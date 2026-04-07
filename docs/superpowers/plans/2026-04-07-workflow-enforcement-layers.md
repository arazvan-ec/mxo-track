# Plan — Workflow Enforcement: 5 Capas Anti-Evasión

**Spec:** `docs/superpowers/specs/2026-04-07-workflow-enforcement-layers-design.md`

## Phase 1 (v0) — Implementación funcional

### Tarea 1 — Crear `phase-advance.sh` (CLI command)
- Crear `.claude/hooks/phase-advance.sh`
- Acepta arg: next phase name
- Valida transición legal (secuencia fija)
- Escribe `phase_history` con timestamps
- Actualiza `current_phase`
- Archivo: `.claude/hooks/phase-advance.sh` (NUEVO)
- TDD: crear test script `.claude/hooks/test-phase-advance.sh`, ejecutar, verificar pass

### Tarea 2 — Crear `phase-transition-controller.sh` (PostToolUse:Bash)
- Crear `.claude/hooks/phase-transition-controller.sh`
- Intercepta `jq` + `session-state.json`
- Compara state antes/después
- Valida: phase_history solo crece, no se reescribe
- Detecta y revierte escritura directa de `user_approved = true`
- Archivo: `.claude/hooks/phase-transition-controller.sh` (NUEVO)
- TDD: crear test script, verificar revert funciona

### [parallel] Tarea 3a + 3b — Endurecer validators + User approval

- **3a:** Endurecer validators SOFT→HARD
  - `consult-validator.sh`: exit 1 → exit 2
  - `brainstorm-validator.sh`: exit 1 → exit 2 (en checks críticos)
  - `planning-validator.sh`: exit 1 → exit 2
  - `implementation-validator.sh`: exit 1 → exit 2 para plan check (TDD queda SOFT)
  - Archivos: 4 validators en `.claude/hooks/validators/`

- **3b:** User approval detection en `user-prompt-state.sh`
  - Añadir regex de patrones approval/rejection en español+inglés
  - Leer `CLAUDE_USER_PROMPT` del input
  - Si match approval Y `current_phase = brainstorming` → set `user_approved = true`
  - Si match rejection → reset `user_approved = false`
  - Archivo: `.claude/hooks/user-prompt-state.sh` (MODIFICAR)

### Tarea 4 — TDD order tracking en `auto-evidence.sh`
- Añadir `tdd_tracker` al state
- En Edit/Write: registrar file type (test vs src) con timestamp
- En task_progress change: resetear tracker
- Archivo: `.claude/hooks/auto-evidence.sh` (MODIFICAR)

### Tarea 5 — Cross-validation en `pre-push-gate.sh`
- Verificar phase_history tiene formato timestamp (no strings planos)
- Verificar timestamps cronológicos con separación >30s
- Verificar manifest timestamp vs último commit
- Verificar decisions log tiene diff (SOFT warning)
- Archivo: `.claude/hooks/pre-push-gate.sh` (MODIFICAR)

### Tarea 6 — Registrar hooks en `settings.json`
- Añadir `phase-transition-controller.sh` en PostToolUse:Bash (antes de auto-evidence)
- Verificar orden de ejecución correcto
- Archivo: `.claude/settings.json` (MODIFICAR)

### Tarea 7 — Test de integración end-to-end
- Crear `.claude/hooks/test-enforcement-layers.sh`
- Simular: advance legal (pass), advance ilegal (block), fabricar phase_history (revert),
  write user_approved directamente (revert), push sin manifest (block)
- Ejecutar todos los tests
- Archivo: `.claude/hooks/test-enforcement-layers.sh` (NUEVO)

### Tarea 8 — Actualizar documentación
- Actualizar `.claude/README.md` con nuevo formato phase_history y phase-advance command
- Actualizar `docs/knowledge/superpowers-skills.md` con nuevas capas
- Actualizar `CLAUDE.md` con instrucciones de usar `phase-advance` en vez de `jq` directo
- Archivos: 3 docs (MODIFICAR)

## Archivos afectados (resumen)

| Archivo | Acción |
|---------|--------|
| `.claude/hooks/phase-advance.sh` | NUEVO |
| `.claude/hooks/phase-transition-controller.sh` | NUEVO |
| `.claude/hooks/test-phase-advance.sh` | NUEVO |
| `.claude/hooks/test-enforcement-layers.sh` | NUEVO |
| `.claude/hooks/auto-evidence.sh` | MODIFICAR |
| `.claude/hooks/user-prompt-state.sh` | MODIFICAR |
| `.claude/hooks/pre-push-gate.sh` | MODIFICAR |
| `.claude/hooks/validators/consult-validator.sh` | MODIFICAR |
| `.claude/hooks/validators/brainstorm-validator.sh` | MODIFICAR |
| `.claude/hooks/validators/planning-validator.sh` | MODIFICAR |
| `.claude/hooks/validators/implementation-validator.sh` | MODIFICAR |
| `.claude/settings.json` | MODIFICAR |
| `.claude/README.md` | MODIFICAR |
| `docs/knowledge/superpowers-skills.md` | MODIFICAR |
| `CLAUDE.md` | MODIFICAR |
