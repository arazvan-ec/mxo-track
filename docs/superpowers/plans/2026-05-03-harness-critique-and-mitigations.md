# Plan — Harness Critique & Mitigations Spec

**Spec:** `docs/superpowers/specs/2026-05-03-harness-critique-and-mitigations-design.md`
**Branch:** `claude/harness-critique-and-mitigations-spec-kXDx4`
**Tipo:** docs-only. Sin modificaciones a `.claude/`, hooks, ni `CLAUDE.md`.

## Scope

Producir un único artefacto consolidado (el spec) que evalúa con el 4-test las
12 estrategias de mitigación del harness y fija decisiones de adopción. Las
implementaciones de las estrategias ADOPTADAS son interacciones full futuras,
fuera de scope aquí.

## Tarea única

- [x] **T1: Escribir spec consolidado** — `docs/superpowers/specs/2026-05-03-harness-critique-and-mitigations-design.md`
  - → produces: spec con secciones Origen, Inventario problemas (5 + 4 detectados), Existing Functionality Inventory, Omission Decisions, tabla 12 estrategias con 4-test, plan de adopción por hitos, descartadas, aplazadas, Norms, Safeguards, Architectural Adversarial Review (4 Q/A), Observación estructural, Anexo referencias cruzadas.
  - → files: `docs/superpowers/specs/2026-05-03-harness-critique-and-mitigations-design.md`

## Verificación

- Plan-bound: el commit toca exclusivamente el spec. No hay tests/lint a
  ejecutar (artefacto Markdown único).
- Sync gate: comprobar que `git diff` desde el commit de plan introduction
  hasta HEAD solo cubre el spec + plan + execution log.

## Notas

- Esta interacción **dogfoodea** la regla "ningún spec del harness sin plan de
  reducción neta cuantificada" propuesta como graduación condicional en la
  Observación estructural — si el patrón se repite (specs sobre el harness
  que cuestan más de lo que ahorran), la regla graduará a `CLAUDE.md`.
- Plan_session_date == today; el spec NO modifica código, por lo que B3
  session-cut gate (`planning → implementation`) puede dispararse pero su
  semántica original (fresh-session review for code) no aplica a un artefacto
  docs-only. Si se dispara, justificar con `SKIP_SESSION_CUT_GATE=1` + entrada
  decision log.
