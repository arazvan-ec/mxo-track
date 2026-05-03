---
type: spec
tags: [harness, meta-spec, critique, mitigations, 4-test, hipertrofia, poda, governance, manus]
files_touched:
  - docs/superpowers/specs/2026-05-03-harness-critique-and-mitigations-design.md
  - docs/superpowers/plans/2026-05-03-harness-critique-and-mitigations.md
  - docs/decisions/log.md
patterns: [docs-only-meta-spec, retrospective-conditional-adoption, prune-before-add]
outcome: success
outcome_verified_at: 2026-05-03
regressions_later: []
pr_number: null
estimated_lines: 350
actual_lines: 347
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-05-03 — Harness Critique & Mitigations Spec

**Branch:** `claude/harness-critique-and-mitigations-spec-kXDx4`
**Spec:** `docs/superpowers/specs/2026-05-03-harness-critique-and-mitigations-design.md`
**Plan:** `docs/superpowers/plans/2026-05-03-harness-critique-and-mitigations.md`

## Summary

Procesar con el 4-test de `CLAUDE.md` las 12 estrategias propuestas para
mitigar problemas del harness (9 de Manus AI + 3 derivadas del grafo
conceptual IA estándar) y producir un único spec consolidado que sirva como
input para futuras interacciones full. Cada ADOPCIÓN será su propia
implementación futura; este artefacto es exclusivamente de gobernanza, sin
modificaciones a `.claude/`, hooks, ni `CLAUDE.md`.

**Decisión final:** 9 ADOPTAR · 2 DESCARTAR · 1 APLAZAR, todas condicionadas
a un Hito 0 reductor (poda + paridad doc) como prerrequisito HARD.

## Approach

Una tarea única (T1: escribir spec). Sin código, sin tests, sin lint
relevante. La interacción es deliberadamente meta: ejecuta el flujo full
para diagnosticar que el flujo full produce hipertrofia.

## Phases

- **Consult:** leídos `docs/decisions/log.md` (entradas 2026-04 completas), `docs/superpowers/execution-logs/2026-04-30-cross-session-resume-hardening.md`, inventario de validators (14), wc -l hooks (12.362 LOC), wc -l CLAUDE.md (1.268), comparación con el resumen de Manus.
- **Brainstorming:** alternativas A/B/C de estructura del spec; usuario aprobó A (spec único consolidado). Ronda 1 produjo el draft completo con 8 secciones + Adversarial Review (4 Q/A) + Observación estructural.
- **Planning:** un plan trivial con tarea única (T1). plan_session_date NO stamped (post-commit-session-stamp.sh no auto-wired — heredado de execution log 2026-04-30).
- **Implementation:** spec escrito en un solo Write. Sin blockers de contenido.
- **Verification:** `make lint` (PHP) clean. PHPUnit ausente del entorno; tests=skipped honesto. Bypass `SKIP_PHASE_EXIT_GATE=1` con entrada decision log.
- **Capture:** este log.
- **Retrospective:** sigue.
- **Finalize:** push pendiente al final.

## Verification results

- `make lint` (PHP): exit 0, sin errores de sintaxis.
- `make lint-shell`: no ejecutado (sin cambios shell).
- PHPUnit: no instalado (`vendor/bin/phpunit` ausente).
- Sync gate: el commit `23e6634` toca exclusivamente el spec; plan + log + decision log se añaden en commits subsiguientes — todos dentro de `WORKFLOW_ARTIFACTS_PATHS` exempt.

## Blockers / corrections during implementation

1. **Regex `user-prompt-state.sh` no captura "Confirmó A" (con tilde).** El regex acepta `confirmo|confirm` pero no `confirmó`. Workaround: pedir al usuario re-confirmar con "Apruebo". Follow-up: ampliar regex a `confirm[oóáa]?(do)?`. 1ª ocurrencia.

2. **Cabeceras numeradas (`## 8. Norms`, `## 9. Safeguards`, `## 3. Existing Functionality Inventory`) no fueron reconocidas por el validator** que usa `^## Norms` literal. Corregido eliminando los prefijos numéricos en esas tres secciones. Follow-up: validator podría aceptar prefijo numérico opcional. 1ª ocurrencia.

3. **`verification → capture` bloqueado por `tests_passed=skipped` en flow=full.** Bypass `SKIP_PHASE_EXIT_GATE=1` con entrada decision log (artefacto docs-only, PHPUnit no disponible). Heurística post-bypass: 3+ ocurrencias → graduar a aceptación automática cuando `git diff --name-only` solo contiene `docs/**.md`.

4. **`plan_session_date` no stamped automáticamente.** Heredado del follow-up del execution log 2026-04-30. B3 session-cut gate (`planning → implementation`) salió con WARN, lo cual en este caso es lo deseado.

5. **Edición a `docs/decisions/log.md` bloqueada por gate `retrospective`** antes de presentar retrospectiva visible. Reordené: capture → write execution log → present retrospective → user approves → write decision log entry → finalize. Comportamiento esperado del gate.

## Patterns (graduation candidates)

- **docs-only-meta-spec.** 1ª ocurrencia explícita. Patrón: spec full sin código que evalúa o gobierna el harness. Si recurre, graduar regla a CLAUDE.md "ningún spec del harness sin plan de reducción neta cuantificada".
- **retrospective-conditional-adoption.** 1ª ocurrencia. Patrón: ADOPTAR estrategia condicionada a Hito 0 reductor previo; sin reducción neta el plan se aborta. Si recurre, graduar.
- **prune-before-add.** 2ª aplicación (la 1ª fue remoción de Layers I/J, 2026-04-26). 1 más → graduar a CLAUDE.md.

## Decisions

- **Documentar las 2 estrategias DESCARTADAS y la 1 APLAZADA con criterio de re-evaluación explícito** en lugar de elidirlas. Coste ~30 líneas; beneficio: el próximo análisis externo que las re-proponga ve el razonamiento previo y debe justificar obsolescencia para re-adopción.
- **Adoptar 9 a pesar de la observación de hipertrofia.** Razón en Adversarial Review Q4: las 9 atacan problemas que la poda no resuelve por sí sola; condicionarlas a Hito 0 evita agravar la hipertrofia. Tradeoff explícito.
- **Confirmación retroactiva del 4-test sobre layers existentes K/N/S/Sync/Agent/F/H** queda incluida en Hito 0 (Adversarial Review Q1) y no en este spec.

## Bypass usage

- `SKIP_PHASE_EXIT_GATE=1` para `verification → capture`. Justificación: cambio docs-only, PHPUnit ausente, lint=true honesto. Decision log entry: 2026-05-03.

## Follow-ups

- **Ampliar regex de aprobación verbal en `user-prompt-state.sh`** para capturar tildes y formas conjugadas: `confirm[oóáa]?(do)?`. (1ª ocurrencia.)
- **Aceptar prefijo numérico en cabeceras del spec** en `brainstorm-validator.sh`: `^## (\d+\. )?Norms` y similares. (1ª ocurrencia.)
- **Auto-aceptar `tests_passed=skipped` en docs-only changes**: cuando `git diff --name-only` solo contiene `docs/**.md`, el verification validator podría rebajar a soft-warn. Heurística: 3+ ocurrencias.
- **Auto-wire `post-commit-session-stamp.sh`** sigue sin ejecutar (heredado de 2026-04-30). Re-registrado aquí.
- **Hito 0 propuesto en el spec**: poda dedicada (reducción ≥15% LOC + paridad doc) es la siguiente acción natural sobre el harness. No ejecutada aquí — interacción aparte.

## Retrospectiva

(Presentada al usuario antes de escribir aquí.)

### Estimate accuracy

| Métrica | Estimado | Real | Δ |
|---|---|---|---|
| Líneas spec | ~600-800 | 347 (post-edits) | -50% |
| Archivos | 1 (spec) | 4 (spec + plan + log + decision-log) | +300% |
| Bypass usados | 0 esperados | 1 (`SKIP_PHASE_EXIT_GATE` verification) | +1 |
| Aprobaciones de usuario | 1 | 2 (regex no capturó "Confirmó") | +1 |

El conteo de líneas del spec (347) quedó por debajo del estimado porque la tabla resumen del 4-test condensa información en lugar de desplegar cada test por estrategia. **Sub-estimación de archivos** es típica del flujo full y patrón ya conocido (CLAUDE.md § Planning lo prescribe pero el modelo sigue sub-estimando — sugiere reforzar la regla o moverla a zona de mayor atención).

### Process gap

- **Regex de aprobación verbal no captura formas conjugadas con tilde.** Falso negativo: usuario aprobó y el sistema no avanzó. Fix: ampliar regex.
- **Cabeceras numeradas no las reconoce el validator** — inconsistencia entre formato natural de spec largo (`## 8. Norms`) y regex literal (`^## Norms`). Aceptar prefijo numérico opcional.
- **Cadena de bypass auto-justificable.** El bypass se usó en el contexto exacto que el spec recién escrito intenta gobernar (estrategia 9 — grounding factual). Meta-ironía registrada; heurística post-bypass del CLAUDE.md activa para 3rd ocurrencia.

### Emergent patterns

- `docs-only-meta-spec` (1ª).
- `retrospective-conditional-adoption` (1ª).
- `prune-before-add` (2ª).
- **Anti-patrón meta:** spec full sobre el harness usa el harness para diagnosticar la hipertrofia del harness. Si se repite, graduar regla "ningún spec del harness sin plan de reducción neta cuantificada".
