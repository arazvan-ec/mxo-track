---
type: refactor
tags: [workflow]
files_touched: []
patterns: []
outcome: null
outcome_verified_at: null
regressions_later: []
pr_number: null
estimated_lines: null
actual_lines: null
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-04-07 — Workflow Improvements

**Type:** testing + refactor
**Branch:** `claude/unify-menu-implementation-Yo8np`

## Brainstorming

- **Problem:** 5 validators dormidos activados por autodiscovery, finalize-validator con false positives, spec-compliance invisible, user_approved no detecta "prefiero"
- **Chosen:** 6 tareas en paralelo: 2 test suites nuevos + 3 refactors + 1 keyword expansion

## Implementation

- **test-full-flow-e2e.sh:** 24 tests — walk completo con gates bloqueados + walk exitoso + verificación de estado final + verificación de artefactos
- **test-finalize-validator.sh:** 6 tests — branch_strategy, knowledge mapping, already-updated exclusion
- **planning-validator.sh:** +9 líneas — integra spec-compliance-validator como SOFT sub-check
- **finalize-validator.sh:** Refactored con threshold (≥5 archivos O archivos nuevos) usando helper functions check_pattern/check_pattern_i
- **user-prompt-state.sh:** +8 keywords (suena bien, hazlo, implementa, proceed, me gusta, está bien, esta bien, correcto)

## Verification

82 tests across 7 suites, 0 failures:
- test-full-flow-e2e: 24 ✅
- test-finalize-validator: 6 ✅
- test-phase-advance: 14 ✅
- test-enforcement-layers: 15 ✅
- test-retrospective-validator: 6 ✅
- test-auto-evidence: 17 ✅

## Retrospectiva

### Estimación vs realidad
6 tareas en paralelo con agentes: 3 completaron correctamente (e2e, finalize-test, planning-validator), 3 no escribieron los archivos a tiempo. Completé las restantes manualmente en ~3 min. Overhead de agentes vs beneficio: mixto — el e2e test (el más complejo, 24 tests) fue creado correctamente por agente, ahorrando tiempo. Los cambios simples (1-2 líneas) fueron más rápidos hacerlos directamente.

### Qué funcionó bien
- **El test e2e es el más valioso de todo el esfuerzo** — simula el flujo completo que un usuario real ejecuta. Cualquier cambio futuro en validators se verifica automáticamente.
- **Threshold en finalize-validator** reduce ruido significativamente — un cambio de 1 archivo en frontend ya no genera false positive.
- **spec-compliance como sub-check** preserva su naturaleza SOFT sin añadir complejidad al flujo.

### Qué falló
- **3 de 6 agentes no completaron a tiempo** — los agentes para tareas simples (1-2 líneas de cambio) tienen más overhead de setup que beneficio. Regla: solo usar agentes para tareas que requieran >20 líneas de código nuevo.
- **user_approved sigue requiriendo workaround** — el hook detecta "Apruebo" pero el phase-transition-controller puede revertirlo si un jq posterior toca session-state.json. El fix de keywords ayuda con la detección, pero el problema de fondo (race condition con controller) persiste.

### Lessons

- Tests e2e para flujos stateful son el ROI más alto — atrapan problemas de integración que los unit tests no ven
- Threshold-based validation (≥5 files) es mejor que boolean (any file) para reducir noise en gates SOFT
- Agentes paralelos: usar solo para tareas complejas (>20 líneas), no para 1-liner edits
