# Plan — Workflow Hook Tests & Autodiscovery

**Spec:** `specs/2026-04-07-workflow-hook-tests-design.md`
**Branch:** `claude/unify-menu-implementation-Yo8np`

## Phase 1 (v0)

### Task 1 — Crear test-retrospective-validator.sh
- Crear `.claude/hooks/test-retrospective-validator.sh`
- Scenarios: no log, log sin seccion, seccion corta (<100 chars), seccion valida
- Test: script passes (exit 0)
- Commit after

### Task 2 — Actualizar test-phase-advance.sh con validator tests
- Modificar `.claude/hooks/test-phase-advance.sh`
- Add: brainstorming→planning blocked without spec file
- Add: planning→implementation blocked without plan file
- Add: retrospective→finalize blocked without retrospective section
- Fix Test 8 (full walk) to create required artifacts before advancing
- Test: script passes (exit 0)
- Commit after

### Task 3 — Actualizar test-enforcement-layers.sh
- Modificar `.claude/hooks/test-enforcement-layers.sh`
- Add: retrospective-validator blocks finalize without lessons section
- Add: brainstorm-validator blocks when spec_path set but file missing
- Add: planning-validator accepts "Tarea" keyword
- Test: script passes (exit 0)
- Commit after

### Task 4 — Refactorizar phase-advance.sh autodiscovery
- Modificar `.claude/hooks/phase-advance.sh`
- Replace hardcoded case with convention: `${CURRENT_PHASE}-validator.sh`
- Handle brainstorming→brainstorm alias (strip "ing" suffix as fallback)
- Test: all 3 test files still pass
- Commit after

### Task 5 — Verificar todo
- Run all 3 modified/new test files
- Run existing test-phase-transition-controller.sh (regression)
- Commit + push

## Phase 2 (Mature): N/A
