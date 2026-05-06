# Plan — Harness Pruning Hito 0.b

**Fecha:** 2026-05-04
**Spec:** `docs/superpowers/specs/2026-05-03-harness-pruning-hito-0b-design.md`
**Branch:** `claude/prune-harness-poda-xEdZG`
**Baseline:** hooks 12,362 LOC · target ≤ 10,508 LOC (≥15% reducción)

---

## Existing Functionality Inventory

Espejo del spec (sección Inventory). No omisiones nuevas a nivel plan.

## Omission Decisions

No omissions — all spec inventory items addressed by waves.

## Phase 1 — eliminaciones independientes + doc

### [parallel] Wave 1: dead code + audits

- **1a:** Eliminar `test-self-gating.sh` (340 LOC).
  - Test: `ls .claude/hooks/full-flow-gate.sh` debe fallar (target inexistente).
  - Action: `git rm .claude/hooks/test-self-gating.sh`.
  - Verify: `bash .claude/hooks/test-enforcement-layers.sh` continúa cubriendo self-gating semantics.
  - → produces: −340 LOC.
  - → files: .claude/hooks/test-self-gating.sh

- **1b:** Auditar `test-pre-commit-deprecated-alias.sh` (56 LOC).
  - Test: `ls .claude/hooks/pre-commit-deprecated-alias.sh` y verificar uso real.
  - Action: si target activo y test pasa, mantener; si target inactivo, eliminar.
  - → produces: 0 ó −56 LOC.
  - → files: .claude/hooks/test-pre-commit-deprecated-alias.sh

- **1c:** Auditar `test-workflow-engine.sh` (513 LOC).
  - Test: capturar nombres de los 14 fallos en una tabla.
  - Action: por cada fallo, decidir delete/repair/keep. Si redundante con `test-full-flow-e2e.sh` o `test-enforcement-layers.sh`, eliminar.
  - → produces: −250 a −450 LOC estimado.
  - → files: .claude/hooks/test-workflow-engine.sh

### [parallel] Wave 3: doc rewrite (independent of Wave 2)

- **3a:** Reescribir `docs/knowledge/workflow-engine.md` con paridad completa: A, B (incl. B3), C (Adversarial Review), D, F, H, K [REMOVED 2026-05-04], N, S, Sync, Agent, spec-compliance. Tabla de bypasses completa. Mantiene precedente `[REMOVED]`.
  - → produces: workflow-engine.md actualizado.
  - → files: docs/knowledge/workflow-engine.md

## Phase 2 — refactor + verification

### Wave 2: brainstorm-validator pruning + table-driven (depends on Wave 1 only by LOC counting)

- **2a:** Eliminar Layer K en `brainstorm-validator.sh` (líneas 165-193) y lógica `extract_bullet` + rama `positive-signal` en `section-validator.sh` que sólo soportan K.
  - Test: tests no-K en `test-brainstorm-validator.sh` siguen pasando antes y después.
  - → produces: −~30 LOC validator + ~10 LOC lib.
  - → files: .claude/hooks/validators/brainstorm-validator.sh, .claude/hooks/lib/section-validator.sh

- **2b:** Eliminar tests de Layer K en `test-brainstorm-validator.sh`.
  - Action: borrar bloques que invocan Layer K.
  - → produces: ~−50 LOC.
  - → files: .claude/hooks/test-brainstorm-validator.sh

- **2c:** Refactor section-gates table-driven para N/S/H/C en `brainstorm-validator.sh`.
  - Test: snapshot pre/post comparable: `bash test-brainstorm-validator.sh > /tmp/pre.txt`; cualquier delta bloquea.
  - Action: introducir tabla `SECTION_GATES=(...)` con (name, trigger, classifier, error_msg) y reemplazar bloques if-else por loop.
  - → produces: ~−40 LOC.
  - → files: .claude/hooks/validators/brainstorm-validator.sh

### Wave 4: verification + recount

- **4a:** `make lint && make lint-shell`.
- **4b:** Suite total: `for t in .claude/hooks/test-*.sh; do bash "$t"; done` con conteo pre/post.
- **4c:** `wc -l` y reporte de % reducción sobre baseline 12,362.

---

## Estimación

| Wave | Reducción | Riesgo |
|---|---|---|
| 1a | −340 | Bajo |
| 1b | 0 ó −56 | Bajo |
| 1c | −250 a −450 | Medio |
| 2a | −40 | Bajo |
| 2b | −50 | Bajo |
| 2c | −40 | Medio |
| **Total** | **−720 a −976 (5.8% – 7.9%)** | El target ≥15% requiere que 1c rinda alto Y/o reducción adicional descubierta en runtime |

**Honest caveat:** la suma proyectada NO alcanza 15% por sí sola. Plan B contingente (segunda pasada): auditar también `test-status-line.sh` (220), `test-phase-advance.sh` (350), `test-brainstorm-validator.sh` (648) buscando redundancia con tests más específicos. Si tras todo eso aún <15%, documentar abort honesto.

---

## TDD per deletion

- **Test before delete:** capturar el comportamiento que el código eliminado supuestamente protege (red).
- **Delete:** remover código + tests asociados.
- **Verify:** suite global sin regresiones nuevas (green).

---

## Norms

- **Toda eliminación de test** debe ir acompañada de evidencia explícita de que el target no existe O que la cobertura está duplicada en otro test vivo.
- **Ningún commit** se hace con menos pasados que el baseline pre-cambio.
- **Siempre** corre `make lint && make lint-shell` antes de cada commit.

## Safeguards

| Risk | Mitigation |
|---|---|
| Wave 1c subestima/sobrevalora reducción al auditar test-workflow-engine. | Por cada uno de los 14 fallos: nombre, target referido, decisión (delete/repair/keep) en una tabla. Si 5+ son legítimos pero rotos, repararlos en lugar de eliminar. |
| Refactor 2c rompe tests N/S/H/C. | Snapshot pre/post obligatorio; cualquier delta bloquea. |
| Eliminación masiva crea PR enorme. | Un commit por wave (1a, 1b, 1c, 2, 3, 4); cada commit independientemente verificable. |
| Sub-target de 15% no se alcanza. | Documentar honestamente; escalar a Plan B contingente o abortar el plan maestro. No fabricar reducciones cosméticas. |
