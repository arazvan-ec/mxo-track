---
type: spec
tags: [harness, addendum, reorder, llm-judge, manus, governance, 4-test, recursivity, grounding, consult-saves-rework]
files_touched:
  - docs/superpowers/specs/2026-05-06-harness-mitigations-reorder-addendum-design.md
  - docs/superpowers/plans/2026-05-06-harness-mitigations-reorder-addendum.md
  - docs/decisions/log.md
patterns: [docs-only-meta-spec, addendum-not-supersession, consult-saves-rework, prune-before-add]
outcome: success
outcome_verified_at: 2026-05-06
regressions_later: []
pr_number: null
estimated_lines: 250
actual_lines: 248
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-05-06 — Harness Mitigations Reorder Addendum

**Branch:** `claude/review-manus-analysis-olj1I`
**Spec:** `docs/superpowers/specs/2026-05-06-harness-mitigations-reorder-addendum-design.md`
**Plan:** `docs/superpowers/plans/2026-05-06-harness-mitigations-reorder-addendum.md`

## Summary

Re-lectura del análisis externo Manus AI motivó reabrir el brainstorming del
spec del 2026-05-03. Output: addendum que reordena la ejecución para atacar
primero §2.2 (recursividad) y §3.5 (grounding) y añade estrategia #13
(LLM-as-judge) para §2.3 (satisfacción estructural vs rigor). El addendum NO
sustituye al parent — lo extiende (patrón ADR acumulativo).

**Decisión final:** A2 + (i) — re-priorización A2 (Hito 0 → 1' #3+#9 → 2 #1
→ 3 #11→#12 → 4 #10 → 5 #8+#7 → 6 #13) + Hito 0 en sesión separada humana.

## Approach

Tres tareas: T1 (spec — completado en brainstorming, commit `9278c3f`),
T2 (decision log entry — finalize), T3 (este execution log — capture).

## Phases

- **Consult:** Leídos `docs/decisions/log.md` (271 líneas), parent spec del 2026-05-03 (348 líneas), su execution log + plan, 24 logs harness via `consult.sh tag harness`. **Hallazgo crítico:** el spec del 2026-05-03 ya procesó las 12 estrategias con el 4-test — la propuesta inicial duplicaba 3 de 4 sin saberlo (el usuario re-proponía estrategias ya 4-tested + 1 nueva). Pivot a opción B (reabrir brainstorming) tras superficie del hallazgo.
- **Brainstorming:** 3 alternativas A1 (orden 2026-05-03 intacto + addendum), A2 (re-priorizar §2.2+§3.5 al frente), A3 (Hito 0 ahora, reorden después). Usuario eligió A2. Sub-pregunta sobre Hito 0 (timing): elegida (i) — Hito 0 en sesión separada humana, coherente con el espíritu de la mejora #1. 4 Q/A en Adversarial Review cubriendo tech-debt, coupling, pattern, architecture.
- **Planning:** Plan trivial con T1 ya completado, T2/T3 pendientes. 50 líneas.
- **Implementation:** Bloqueado al intentar escribir T2 (decision log entry) — gate retrospectiva fired correctamente: decision log es artefacto "de cierre" y exige retrospectiva visible previa. Pivot: T2 movido a finalize, T3 (execution log) ahora en capture.
- **Verification:** `make lint` (PHP) clean (sin cambios PHP, default lint pasa). PHPUnit ausente (mismo entorno que parent). `tests_passed=skipped` rechazado en full → `SKIP_PHASE_EXIT_GATE=1` (2ª ocurrencia post parent — graduación a auto-accept en docs-only triggered).
- **Capture:** este log.
- **Retrospective:** sigue.
- **Finalize:** push pendiente al final + decision log entry.

## Verification results

- `make lint` (PHP): exit 0, sin errores de sintaxis (sin cambios PHP).
- `make lint-shell`: N/A (sin cambios shell).
- PHPUnit: `vendor/bin/phpunit` ausente, `tests_passed=skipped`.
- Sync gate: `git diff` desde commit `706119e` (parent merge) cubre solo
  `docs/superpowers/specs/`, `docs/superpowers/plans/`, `docs/codebase-manifest.md` (auto-commit) — todos en `WORKFLOW_ARTIFACTS_PATHS`, exempt.

## Blockers / corrections during implementation

1. **Brainstorm-validator rechazó cabeceras numeradas** (`## 2. Existing Functionality Inventory`, `## 3. Omission Decisions`, `## 4. Prior Art Audit`). 2ª ocurrencia del mismo blocker (1ª: 2026-05-03 parent spec). Corregido eliminando prefijos numéricos. Heurística post-bypass del parent registró follow-up "validator podría aceptar prefijo numérico opcional" — sigue sin implementar; ahora 2/3 ocurrencias hacia graduación.

2. **Gate retrospectiva bloqueó edit de decision log durante implementation.** Comportamiento correcto del gate: el decision log es artefacto de cierre, no de implementation. Reordenamiento del plan: T2 (decision log entry) movido de implementation a finalize. Mismo patrón que parent 2026-05-03 — graduación a documentación: la fase canónica para decision log entries es **finalize**, no implementation. Si recurre 1 vez más, graduar regla a CLAUDE.md o al plan template.

3. **`tests_passed=skipped` no aceptable en full + PHPUnit ausente.** 2ª ocurrencia post parent. `SKIP_PHASE_EXIT_GATE=1` con decisión registrada en finalize. Heurística post-bypass del parent (3+ ocurrencias → graduar a auto-accept en `docs/**.md` only) ahora a 2/3.

4. **Consult crítico evitó re-implementación duplicada.** El status line declaraba "logs_scanned=true" desde SessionStart pero `consult.sh tag harness` reveló el spec del 2026-05-03 que el usuario no recordaba conscientemente. Sin ese consult, la sesión hubiera arrancado a re-especificar 3 estrategias ya 4-tested + descartado la 4ª. Justifica el coste del consult phase de forma directa. Patrón nuevo: `consult-saves-rework`.

## Patterns (graduation candidates)

- **docs-only-meta-spec.** 2ª ocurrencia (1ª: 2026-05-03). 1 más → graduar regla a CLAUDE.md "ningún spec del harness sin plan de reducción neta cuantificada".
- **addendum-not-supersession.** 1ª ocurrencia. Patrón: spec posterior que extiende sin modificar al parent (ADR-style). Si recurre, graduar como convención explícita en `docs/CLAUDE.md`.
- **consult-saves-rework.** 1ª ocurrencia explícita en logs. Patrón: el consult fase encuentra trabajo previo que el usuario no recordaba, evitando re-litigación. Si recurre, fortalecer narrativa pro-consult en CLAUDE.md.
- **prune-before-add.** 3ª aplicación (1ª: I/J removal 2026-04-26; 2ª: parent spec 2026-05-03). Graduación lista — el parent ya documentaba el patrón; este addendum lo respeta por coherencia (no añade gates implementables esta sesión).
- **decision-log-in-finalize.** 2ª ocurrencia (1ª: parent 2026-05-03). Patrón: el decision log entry corresponde a finalize, no implementation. Graduación: actualizar plan templates / CLAUDE.md.

## Decisions

- **Addendum NO modifica parent.** Pattern ADR acumulativo, mismo que `docs/decisions/log.md`. Coste: dos documentos a leer en futuras consults; beneficio: trazabilidad histórica preservada.
- **#3+#9 antes de #1.** HARD dependencies del parent permitían el reorden; el agrupamiento temático original era estético, no técnico.
- **Mini-paridad doc per-PR obligatoria** para validators de #3 y #9 — Norm explícita en addendum, sin esperar al gate global de #1 en Hito 2.
- **Hito 0 en sesión separada humana.** Coherente con el espíritu de la mejora #1 (anti-recursividad humano-led) que el usuario priorizó como la más urgente.
- **#13 LLM-judge como asesor, no bloqueante.** Output del judge se inyecta en brainstorming; usuario decide; kill-switch a 30% falsos positivos.

## Bypass usage

- `SKIP_PHASE_EXIT_GATE=1` para `verification → capture`. Justificación idéntica al parent del 2026-05-03: cambio docs-only, PHPUnit ausente, lint=true honesto. Decision log entry pendiente en finalize (entrada combinada con la del reorden A2). Heurística post-bypass: 2/3 ocurrencias hacia graduación de auto-accept en docs-only.

## Follow-ups

- **Brainstorm-validator: aceptar prefijo numérico en cabeceras.** 2ª ocurrencia documentada. 1 más → graduar implementación.
- **Verification: auto-accept `tests_passed=skipped` cuando `git diff --name-only` solo cubre `docs/**.md`.** 2ª ocurrencia. 1 más → graduar.
- **Auto-wire `post-commit-session-stamp.sh`.** Sigue sin ejecutar (heredado de 2026-04-30 y 2026-05-03). Re-registrado, 3ª ocurrencia.
- **Hito 0 (poda + paridad doc) sigue pendiente.** Sin ejecución 4 días post parent spec. Si en 14 días no hay sesión de Hito 0, re-evaluar plan completo según Safeguard del addendum.
- **#13 LLM-judge: spec de implementación pendiente.** Cuando llegue a Hito 6, definir umbral mensual de coste de tokens y mecanismo de instrumentación.
- **Plan templates: documentar que decision log entries van en finalize, no implementation.** 2ª ocurrencia del re-orden manual; trivial añadir al template.

## Retrospectiva

(Presentada al usuario antes de escribir aquí.)

### Estimate accuracy

| Métrica | Estimado | Real | Δ |
|---|---|---|---|
| Líneas spec | ~250 | 248 | -1% |
| Archivos | 1 (spec) | 4 (spec + plan + log + decision-log) | +300% |
| Bypass usados | 0 esperados | 1 (`SKIP_PHASE_EXIT_GATE` en verification) | +1 |
| Aprobaciones de usuario | 2 | 4 (camino A/B/C, alternativa A1/A2/A3, sub-(i)/(ii)/(iii), aprobar spec) | +100% |
| Re-orden de plan | 0 esperado | 1 (T2 movido implementation→finalize) | +1 |

Sub-estimación de archivos sigue siendo el patrón dominante (3ª vez consecutiva en interacciones meta). El conteo de archivos del flujo full incluye 4 artefactos baseline (spec/plan/log/decision-log) que el modelo continúa olvidando contar al estimar — este patrón explícitamente documentado en parent log y aún no graduado a regla en CLAUDE.md.

### Process gap

- **Falta de check anti-duplicación en consult.** El `consult.sh tag harness` reveló trabajo de hace 3 días que el usuario no recordaba. Si el modelo hubiera saltado consult o el usuario hubiera presionado "implementa", la sesión hubiera duplicado spec del parent. Sugerencia: alerta automática en `consult.sh` cuando query devuelve execution log con `outcome: success` y fecha < 7 días — indicador de potencial duplicación que merece pausa explícita.
- **Cabeceras numeradas — 2ª vez.** Mismo blocker que el parent. Heurística post-bypass del parent registró follow-up que sigue sin implementar. La recurrencia confirma que sin priorizar el follow-up, el patrón persistirá.
- **Plan template no documenta fase canónica de decision log entry.** El re-orden manual de T2 (implementation → finalize) ahora ha ocurrido 2 veces. Trivial documentar en el template del plan o en CLAUDE.md § Closing the Cycle.

### Emergent patterns

- `addendum-not-supersession` (1ª).
- `consult-saves-rework` (1ª explícita).
- `decision-log-in-finalize` (2ª — graduación lista).
- `docs-only-meta-spec` (2ª).
- `prune-before-add` (3ª — graduación lista).
- **Anti-patrón meta:** el harness atrae specs sobre sí mismo a un ritmo que excede su capacidad de implementación. Hito 0 lleva 4 días sin ejecutarse; hoy se añadió un addendum que lo retrasa indirectamente al añadir #13 a la cola. Si el ratio de specs:implementaciones del harness se mantiene > 1, graduar a regla "no se permiten nuevos specs harness hasta cerrar el backlog actual".
