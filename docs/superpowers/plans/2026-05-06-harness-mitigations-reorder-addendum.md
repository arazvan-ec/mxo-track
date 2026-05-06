# Plan — Harness Mitigations Reorder Addendum (A2)

**Spec:** `docs/superpowers/specs/2026-05-06-harness-mitigations-reorder-addendum-design.md`
**Branch:** `claude/review-manus-analysis-olj1I`
**Tipo:** docs-only. Sin modificaciones a `.claude/`, hooks, ni `CLAUDE.md`.

## Scope

Producir el addendum al spec parental (T1 — ya completado en brainstorming,
commit `9278c3f` en remote) más artefactos de cierre (decision log entry,
execution log). Las implementaciones de Hitos 0–6 (incluido #13) son
interacciones futuras — fuera de scope.

## Tareas

- [x] **T1: Escribir addendum spec** — completado en brainstorming
  - → produces: addendum 248 líneas con re-priorización A2 + estrategia #13 + Norms/Safeguards/Adversarial Review/Prior Art Audit.
  - → files: `docs/superpowers/specs/2026-05-06-harness-mitigations-reorder-addendum-design.md`

- [ ] **T2: Entrada en decision log** — añadir `[2026-05-06] Reorden A2 + estrategia #13 LLM-as-judge`
  - → produces: registro de la decisión de reorden + adopción de #13, alternativas A1/A3 descartadas, criterio de re-evaluación.
  - → files: `docs/decisions/log.md`

- [ ] **T3: Execution log** — capturar la interacción
  - → produces: log con frontmatter (tags: harness, addendum, reorder, llm-judge, manus, governance), summary, phases, blockers, retrospective.
  - → files: `docs/superpowers/execution-logs/2026-05-06-harness-mitigations-reorder-addendum.md`

## Verificación

- Plan-bound: los commits tocan exclusivamente `docs/`.
- `make lint` (PHP): N/A (solo Markdown — sin cambios a PHP).
- PHPUnit: skipped (sin cambios a backend).
- Sync gate: `git diff` desde commit de plan introduction debería cubrir solo
  `docs/superpowers/specs/`, `docs/superpowers/execution-logs/`,
  `docs/superpowers/plans/`, `docs/decisions/log.md` — todos en
  `WORKFLOW_ARTIFACTS_PATHS`, exempt.

## Notas

- T1 se completó durante la fase brainstorming (spec escrito como output de
  alternativas A1/A2/A3, commit `9278c3f`). El plan lo refleja como hecho
  para mantener consistencia con `task_progress`.
- Plan_session_date == today (heredado del follow-up del execution log
  2026-04-30: post-commit-session-stamp.sh sigue sin auto-wire). B3
  session-cut gate (`planning → implementation`) puede dispararse — el
  contenido restante (T2 + T3) es escritura docs-only sin riesgo
  arquitectónico, justifica bypass `SKIP_SESSION_CUT_GATE=1` con entrada
  decision log si el gate bloquea.
- Hito 0 (poda) y Hitos 1'–6 son interacciones futuras separadas, no
  ejecutadas aquí.
