# Harness Mitigations Reorder Addendum (A2) + Strategy #13 LLM-as-Judge

**Fecha:** 2026-05-06
**Tipo:** Addendum al spec consolidado del 2026-05-03
**Parent:** `docs/superpowers/specs/2026-05-03-harness-critique-and-mitigations-design.md`
**Branch:** `claude/review-manus-analysis-olj1I`
**No-modifica:** `.claude/`, hooks, `CLAUDE.md`. Único artefacto de esta interacción.

---

## 1. Origen

El usuario re-leyó el análisis externo de Manus AI (2026-04-30) el 2026-05-06
y solicitó reabrir el brainstorming del plan del 2026-05-03 con dos objetivos:

- **(a)** Reordenar la ejecución para abordar primero §2.2 (recursividad
  agente↔agente) y §3.5 (grounding factual) — los problemas que el análisis
  identifica como más graves y que el plan parental difirió a Hitos 4–5.
- **(b)** Incorporar una estrategia ausente en las 12 originales: **#13
  LLM-as-Judge** para evaluación semántica de specs del harness, abordando
  §2.3 (satisfacción estructural vs rigor intelectual).

El parent spec evaluó las 12 estrategias con el 4-test y produjo el veredicto
9 ADOPTAR · 2 DESCARTAR · 1 APLAZAR, todas condicionadas al Hito 0 reductor.
Este addendum **NO reabre las decisiones de adopción** — solo cambia el orden
de ejecución y añade una estrategia nueva (#13) sometida a su propio 4-test.

Norm parental respetada (§ 6 parent): «Jamás se permitirá ampliar el alcance
de las 12 estrategias en este documento sin reabrir brainstorming.» El
brainstorming fue reabierto, alternativas A1/A2/A3 propuestas, A2 elegida —
este addendum es su artefacto.

---

## Existing Functionality Inventory

| Elemento | Decisión | Justificación |
|---|---|---|
| Spec parent 2026-05-03 (12 estrategias + 4-test + Hito 0–5) | Include (referenciado, no re-litigado) | Foundation; el 4-test sobre las 12 no se repite. |
| Hito 0 reductor (poda + paridad doc + activar #2 con thresholds calibrados) | Include (sin cambios) | Sigue siendo prerrequisito HARD bajo A2; el reorden no lo toca. |
| Estrategia #3 (session-cut sub-domain harness) | Transform (sube de Hito 4 a Hito 1') | Sin HARD dependency previa que lo bloquee; aborda P2 en su origen. |
| Estrategia #9 (grounding validator extendido de Layer Sync) | Transform (sube de Hito 5 a Hito 1') | Sin HARD dependency; cierra la brecha más explotable hoy (`tests_passed=true` falsificable sin ejecución). |
| Estrategia #1 (paridad doc enforced) | Transform (baja de Hito 1 a Hito 2) | Sigue siendo importante; la mini-paridad per-PR de #3+#9 actúa como red intermedia. |
| Estrategias #11→#12, #10, #8+#7 | Transform (corrimiento de hito sin cambio en HARD dependencies) | Reordenamiento estético; las HARD `#11→#12` y `#8 después de #1+#11` se preservan. |
| Estrategias DESCARTADAS (#4 token cripto, #6 chaos monkey) | Omit | Sin evidencia nueva que invalide el rechazo; criterios de re-adopción del parent siguen vigentes. |
| Estrategia APLAZADA (#5 granular approval) | Omit | Criterio de re-evaluación trimestral del parent sigue vigente. |

## Omission Decisions

| Elemento | Decisión | Justificación |
|---|---|---|
| Re-litigar las 12 decisiones de adopción del parent | Omit | Sin evidencia empírica nueva (3 días de delta) que justifique reabrir el 4-test sobre estrategias ya evaluadas. |
| Modificar Hito 0 (umbrales, criterios de poda, métrica ≥15% LOC) | Omit | Hito 0 no es objeto del reorden; mantenerlo intocado evita cascadas. |
| Adelantar Hito 0 a esta sesión | Omit | Decisión explícita del usuario: Hito 0 lo ejecuta sesión separada con auditoría humana — coherente con el espíritu de la propia estrategia #3 (anti-recursividad humano-led). |

---

## Prior Art Audit

| Element | Status | Notes |
|---|---|---|
| Spec parent 2026-05-03 — 4-test sobre 12 estrategias | ✅ endorsed | Fundación; addendum referencia, no sustituye. |
| Layer B3 (session-cut gate) en `CLAUDE.md` | ✅ endorsed | Precedente directo de #3; sub-dominio harness es especialización natural. |
| Layer Sync en `sync-validator.sh` | ✅ endorsed | Precedente directo de #9; "Grounding Validator" extiende su mecánica de exit-code capture. |
| Skill 15 (Learning Review mensual) | ✅ endorsed | Canal análogo de revisión; #13 LLM-judge es complementario (per-spec automatizado vs mensual humano agregado), no redundante. |
| ADR pattern (decision logs acumulativos con referencias cruzadas) | ✅ endorsed | Precedente para "addendum NO sustituye parent"; la convención del repo en `docs/decisions/log.md` ya sigue este patrón acumulativo. |
| Documentación `workflow-engine.md` ↔ Layers K/N/S/Sync/Agent | ❌ tech-debt | Brecha P1 cerrada por Hito 0.b del parent — fuera de scope de este addendum. |
| Bypass `SKIP_PHASE_EXIT_GATE=1` recurrente (P6 parent) | ❌ tech-debt | Pendiente; no abordado por el reorden ni por #13. Re-evaluación al cerrar Hito 0. |
| Sampling humano random (#6 chaos monkey, descartado) | ❌ rechazado | Diferente mecánica de #13 (ver § 6.3); las críticas T1/T3 contra #6 no aplican. |

---

## 5. Re-priorización (A2)

### 5.1 HARD dependencies del parent — análisis

El parent spec § 5.7 declara explícitamente las únicas dependencias técnicas:

```
Hito 0 → todo
#11 → #12
#8 después de #1+#11
```

Resto del orden es **agrupamiento temático, no técnico.** Esto deja libertad
para promover #3 y #9 sin romper invariantes:

- **#3** (session-cut sub-domain harness) no depende de #1, #11, ni #10. Su
  implementación es self-contained: extender `session-cut-validator.sh` con
  detección de sub-dominio + bloquear `planning → implementation` cuando los
  archivos del plan tocan `.claude/hooks/` o `workflow-engine.md` y
  `plan_session_date == today`.
- **#9** (Grounding Validator) extiende `sync-validator.sh` capturando
  exit-codes auditables; no depende de #1, #11, ni #10. Self-contained.

### 5.2 Justificación de la re-priorización

Tres razones convergen:

1. **Severidad relativa según evidencia externa.** Manus identifica §2.2
   (recursividad) y §3.5 (grounding) como las brechas más graves. El parent
   spec las difirió a Hitos 4–5 por agrupamiento temático; con la severidad
   declarada explícitamente, ese diferimiento es estética sobre rigor.
2. **Coste de cambio bajo.** Las HARD dependencies no se rompen; el cambio se
   limita a § 5 del parent + entrada en decision log. Sin re-litigación del
   4-test sobre estrategias ya adoptadas.
3. **Standstill empírico.** El parent spec lleva 3 días sin avanzar a Hito 0
   (commit `706119e` mergeado 2026-05-03; sin commits de poda desde).
   Indicador de que el orden actual no genera urgencia; reordenar para que las
   primeras estrategias post-Hito-0 ataquen los problemas más visibles puede
   mejorar la tracción.

### 5.3 Plan de adopción actualizado (A2)

```
Hito 0 (gate-keeper #2 + poda + paridad doc retroactiva)  ──┐
                                                            │
                    ┌──────────────────────────────────────┘
                    v
         Hito 1' (#3 + #9)                    ← anti-recursividad + grounding
                    │
                    v
         Hito 2  (#1)                         ← paridad doc enforced
                    │
                    v
         Hito 3  (#11 → #12)                  ← matriz + conceptual locus
                    │
                    v
         Hito 4  (#10)                        ← vocabulario meta-trabajo
                    │
                    v
         Hito 5  (#8 + #7)                    ← inyección dinámica + checkpoint
                    │
                    v
         Hito 6  (#13 LLM-judge)              ← nuevo, ver § 6
```

**Dependencias HARD preservadas:** `0 → todo`, `#11 → #12`, `#8 después de
#1+#11`. No se introducen nuevas dependencias HARD.

**Mini-paridad doc per-PR.** Cada PR de #3 y #9 (Hito 1') DEBE incluir su
entrada en `docs/knowledge/workflow-engine.md` en el mismo commit, sin esperar
al gate de #1 en Hito 2. Esto evita reproducir P1 (brecha de paridad) sobre
los validators nuevos mientras el gate global aún no opera.

---

## 6. Estrategia #13 — LLM-as-Judge para specs del harness

### 6.1 Definición operativa

Un segundo modelo (separado del modelo de la sesión activa) evalúa
**semánticamente** la calidad del razonamiento en specs que tocan paths
críticos del harness. El juicio no se invoca sobre cada spec — solo sobre los
que declaran tocar `.claude/hooks/`, `docs/knowledge/workflow-engine.md`, o
`CLAUDE.md` en sus `→ files:` (criterio de trigger análogo a Layer F).

Output esperado: una crítica estructurada (3–5 puntos) que identifica
debilidades del razonamiento — no presencia/ausencia de secciones. La crítica
se inyecta en la fase de brainstorming para que el usuario decida si acepta
el spec, lo refina, o reabre alternativas. **El judge es asesor, no
bloqueante.**

### 6.2 4-test

| Criterio | Evaluación | Notas |
|---|---|---|
| **T1** (práctica que el LLM no haría espontáneamente) | ✓ | Ningún validator existente evalúa calidad semántica del razonamiento; los regex F/H/K/N/S verifican presencia, no rigor. La revisión humana es el único mecanismo actual y sufre §2.3 (satisfacción estructural por aprobación rápida). |
| **T2** (fase correcta) | ✓ | Spec-exit gate, mismo punto que Layer C (Adversarial Review) — donde la calidad del razonamiento aún tiene impacto antes de planning. |
| **T3** (coste/valor proporcional) | ~ | Coste real (tokens del segundo modelo + latencia). Mitigación: aplicación selectiva por trigger (solo specs harness); instrumentar coste por invocación; revisión a 3 meses. Si el coste mensual excede umbral X (a definir en spec de implementación), reducir frecuencia o desactivar. |
| **T4** (fundamento) | ✓ | §2.3 Manus (satisfacción estructural vs rigor); precedente de "automated check ≠ human review" en grafo conceptual IA estándar; complementario a Skill 15 (mensual humano agregado). |

**Decisión:** ADOPTAR condicionado a Hito 0 + a su propio Hito 6.

### 6.3 Diferenciación de #6 (chaos monkey, descartado)

| Eje | #6 (descartado) | #13 (adoptado) |
|---|---|---|
| Mecanismo | Sampling random 1/10 interacciones | Trigger por path crítico, determinista |
| Evaluador | Usuario humano (calificación 1–5) | Segundo modelo (crítica estructurada) |
| Fricción UX | Alta (interrumpe flujo, espera input) | Cero (asíncrono, paralelizable) |
| Solapamiento | Duplica retrospective + Skill 15 | Complementario (semántica per-spec vs estimate-accuracy mensual) |
| Coste | Tiempo del usuario | Tokens (medible, ajustable) |

Las críticas T1/T3 que tumbaron #6 no aplican a #13.

---

## Norms

> Imperativos sobre el propio addendum y sus consumidores.

- Toda interacción que IMPLEMENTE las estrategias re-priorizadas (#3, #9, o el resto en su nuevo orden) DEBE referenciar este addendum en su sección Consult, además del spec parent.
- El reorden A2 NO INVALIDA las decisiones de adopción del parent — únicamente cambia el orden de ejecución. Cualquier intento de re-litigar las 12 adopciones requiere brainstorming nuevo, no este addendum.
- Hito 0 SHALL preceder cualquier estrategia, sin excepción. El addendum NO modifica esta precondición.
- Cada PR de #3 y #9 (Hito 1') DEBE incluir su entrada en `workflow-engine.md` en el mismo commit (mini-paridad per-PR), sin esperar a #1.
- La estrategia #13 (LLM-as-judge) JAMÁS se invocará sobre specs que no toquen paths críticos del harness — aplicación selectiva por trigger, no universal.
- Cualquier futura solicitud de re-reorden requiere su propio addendum o brainstorming nuevo. No se permiten parches verbales sobre este documento.

---

## Safeguards

| Risk | Mitigation |
|---|---|
| Re-reorden invalida razonamiento del parent y crea confusión sobre cuál documento es la verdad. | Norm explícita "addendum NO sustituye, extiende"; cada referencia futura debe leer ambos; el parent permanece como fuente del 4-test. |
| Subir #3+#9 antes del gate global de paridad doc (#1) genera validators nuevos no documentados en `workflow-engine.md`. | Mini-paridad per-PR obligatoria (Norm 4): cada PR de #3 o #9 actualiza `workflow-engine.md` en el mismo commit. El gate global de #1 (Hito 2) opera como red de seguridad para validators legados K/N/S/Sync/Agent. |
| Coste de tokens del LLM-judge (#13) crece sin control. | Aplicación selectiva por trigger de path; instrumentar coste por invocación; revisión trimestral con umbral mensual a definir en spec de implementación de #13. |
| #13 produce críticas de baja calidad (modelo evaluador alucina) y entorpece brainstorming legítimo. | Output del judge es asesor, no bloqueante; el usuario decide si acepta, refina o ignora. Si el judge demuestra ratio de falsos-positivos > 30% en primer mes, desactivar y reabrir spec de #13. |
| El addendum mismo cae en P3 (estructura sin rigor): cumple Norms/Safeguards/Adversarial por regex pero el análisis es superficial. | Usuario revisa contenido manualmente antes de aprobar; retrospective de esta interacción debe nominar como process gap cualquier sección detectada como ceremonial. |
| Re-reordenamientos sucesivos (un addendum cada vez que un análisis externo llega) generan inflación documental. | Norm 6 obliga a brainstorming completo para re-reorden; el coste de fricción del brainstorming actúa como freno natural. Si emerge un 2º addendum en < 30 días, graduar a regla CLAUDE.md "ningún re-reorden sin justificación de evidencia empírica nueva". |
| Hito 0 sigue postergándose y este addendum se vuelve letra muerta. | Sesión separada para Hito 0 ya pactada con el usuario; si no se ejecuta en 14 días, re-evaluar plan completo (addendum + parent) en sesión de auditoría dedicada. |

---

## Architectural Adversarial Review

Mínimo 3 Q/A; ≥30 caracteres cada una; ≥1 con keyword arquitectónica
(endorsed, boundary, DDD, tech-debt, architecture, coupling, pattern, tradeoff).

**Q1.** ¿Re-priorizar contradice la decisión documentada del 2026-05-03 y por tanto crea **tech-debt** documental que debe re-leerse cada vez que se consulte el plan?

A: Riesgo real, pero mitigado por convención. El addendum NO sustituye al parent — lo extiende, mismo **pattern** que ADRs acumulativos ya endorsed en `docs/decisions/log.md`. La Norm explícita "el reorden NO invalida las adopciones; solo cambia orden de ejecución" delimita el scope del cambio. Tradeoff aceptado: dos documentos a mantener vs un único documento mutante con historia interna que confunde — la práctica endorsed favorece acumulación con referencias cruzadas (la edición destructiva del parent perdería trazabilidad de qué se decidió cuándo y por qué).

**Q2.** ¿Subir #3+#9 antes del Hito 1 (paridad doc) introduce **coupling** indeseable: validators nuevos que no estarán en `workflow-engine.md` hasta el gate global de #1 opere?

A: Sí, y por eso el addendum exige mini-paridad per-PR (Norm 4): cada PR de #3 o #9 actualiza `workflow-engine.md` en el mismo commit. El gate global de #1 (Hito 2) sigue operando como red de seguridad para validators legados. Tradeoff: doble disciplina (per-PR + global), pero captura el problema en su origen sin esperar a Hito 2. La mini-paridad es **boundary-respecting** — el contrato es local al PR, no global al harness, y por tanto no requiere infraestructura nueva (solo un Norm verificable por revisión humana del PR).

**Q3.** ¿La nueva estrategia #13 (LLM-as-judge) no es exactamente el **pattern** que el spec parent rechazó como #6 (chaos monkey) — auditoría externa de calidad por sampling — y por tanto debería heredar el rechazo?

A: No. #6 era sampling humano random (1/10 interacciones, calificación 1–5) con coste UX alto y solapamiento con retrospective + Skill 15. #13 es evaluación automatizada por modelo separado, sin interrupción al usuario, aplicado selectivamente a specs que tocan paths críticos del harness. Mecánica distinta, target distinto (semántica del razonamiento per-spec vs estimate-accuracy mensual humano). Las críticas T1/T3 que tumbaron #6 (duplicar canal humano, fricción UX) no aplican a #13 (canal nuevo automatizado, sin fricción al usuario más allá de coste de tokens). Tradeoff aceptado: #13 introduce dependencia de un segundo modelo y coste recurrente, mitigado por aplicación selectiva.

**Q4.** ¿No es contradictorio que el addendum se escriba en la misma sesión que el brainstorming que lo originó, dada la propia recomendación de mejora #1 ("agente no debe diseñar e implementar reglas sobre sí mismo en flujo continuo")?

A: Aparente contradicción, mitigada por dos hechos estructurales: (a) el addendum es **spec**, no implementación. La Norm parental "ninguna estrategia DEBE implementarse en la misma sesión que su spec" se respeta — la implementación de #3, #9, ..., #13 ocurre en sesiones futuras separadas. (b) La política humano-led sobre cambios al harness (Hito 0 separado, ejecutado por humano con auditoría) ya rige sobre lo crítico — la ejecución de Hito 0 es lo que la mejora #1 protege. Escribir un spec/addendum es trabajo deliberativo, no ejecutivo; la separación session-cut aplica al par spec↔impl, no al par brainstorming↔spec dentro de la misma fase de la **architecture** del flow.

---

## 8. Anexo — Referencias cruzadas

- `docs/superpowers/specs/2026-05-03-harness-critique-and-mitigations-design.md` — parent spec (4-test sobre 12 estrategias).
- `docs/superpowers/execution-logs/2026-05-03-harness-critique-and-mitigations-spec.md` — execution log del parent.
- `docs/superpowers/plans/2026-05-03-harness-critique-and-mitigations.md` — plan del parent.
- `docs/decisions/log.md` § 2026-05-03 — decisiones de adopción del parent.
- `CLAUDE.md` § The 4-Test for Workflow Changes — criterio aplicado a #13.
- `CLAUDE.md` § Session-cut gates (B3) — precedente de #3.
- `.claude/hooks/sync-validator.sh` — base de extensión para #9.
- Análisis externo Manus AI 2026-04-30 (input usuario 2026-05-06) — origen de la priorización §2.2 + §3.5 al frente.
