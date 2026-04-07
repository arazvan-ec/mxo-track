# Plan — Workflow Improvements

**Spec:** `specs/2026-04-07-workflow-improvements-design.md`
**Branch:** `claude/unify-menu-implementation-Yo8np`

## Phase 1 (v0)

### [parallel] Task 1-6

- **Task 1:** Crear `test-full-flow-e2e.sh` — simula consult→finalize con artefactos reales (spec ≥500B, plan ≥300B con Task keywords, log con Lessons ≥100 chars). Verifica que phase-advance pasa todas las transiciones.

- **Task 2:** Crear `test-finalize-validator.sh` — tests dedicados: (a) sin branch_strategy → warn, (b) con branch_strategy → pass, (c) knowledge mapping con pocos archivos → no warn, (d) knowledge mapping con muchos archivos → warn.

- **Task 3:** Modificar `test-phase-advance.sh` Test 8 — el full walk ahora necesita crear spec, plan, execution log con retrospectiva, y setear evidence (decisions_read, tests_passed, lint_clean, execution_log_path, branch_strategy) para que todos los validators pasen.

- **Task 4:** Modificar `planning-validator.sh` — después de pasar sus propios checks, llamar a `spec-compliance-validator.sh` como sub-check. Si retorna exit 1, emitir warning pero no bloquear (exit 0 del planning-validator).

- **Task 5:** Modificar `finalize-validator.sh` — añadir threshold: solo sugerir knowledge module update si ≥5 archivos matchean el patrón, O si hay archivos nuevos (git diff --diff-filter=A).

- **Task 6:** Modificar `user-prompt-state.sh` — ampliar regex de aprobación con: `prefiero|me parece|suena bien|hazlo|implementa|proceed`.

### Task 7 — Verificar
- Ejecutar los 7 test suites: test-full-flow-e2e, test-finalize-validator, test-phase-advance, test-enforcement-layers, test-retrospective-validator, test-phase-transition-controller, test-auto-evidence
- Todos deben pasar con 0 failures

## Phase 2 (Mature): N/A — pure testing + small refactors
