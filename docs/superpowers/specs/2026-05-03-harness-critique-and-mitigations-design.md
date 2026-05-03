# Harness Critique & Mitigations — Consolidated Spec

**Fecha:** 2026-05-03
**Tipo:** Spec consolidado (input para futuras interacciones full)
**Branch:** `claude/harness-critique-and-mitigations-spec-kXDx4`
**No-modifica:** `.claude/`, hooks, `CLAUDE.md`. Único artefacto de esta interacción.

---

## 1. Origen y propósito

Análisis externo (Manus AI, 2026-04-30) identificó 5 problemas estructurales en
el workflow engine de mxo-track y propuso 9 mitigaciones; un análisis paralelo
desde el grafo conceptual estándar de agentes IA aportó 3 estrategias
complementarias (10–12). Este spec procesa las 12 propuestas con el 4-test de
`CLAUDE.md` y produce un único artefacto consultable que fija qué se ADOPTA,
qué se DESCARTA, qué se APLAZA y en qué orden. Cada ADOPCIÓN será su propia
interacción full futura; este documento es input para esas sesiones, no
implementación.

---

## 2. Inventario de problemas

### 2.1 Problemas reportados por Manus (5)

| # | Nombre | Verificación empírica en este repo |
|---|---|---|
| P1 | Expansión acelerada y brecha de documentación | **Confirmado.** `docs/knowledge/workflow-engine.md` (313 líneas) documenta hasta Layer H y marca I/J como removidos. NO documenta Layer K, N, S, Sync, Agent — todos introducidos entre 2026-04-28 y 04-29. |
| P2 | Recursividad: agente legislando al agente | **Confirmado.** Caso Layer K (`docs/superpowers/execution-logs/2026-04-28-layer-k-anti-reduction-validator.md`): el spec del propio validator activó la regla durante implementación (falso positivo recursivo documentado). |
| P3 | Satisfacción estructural vs rigor intelectual | **Parcialmente confirmado.** Layers F/H/K/N/S verifican presencia (regex/sección/tabla). No hay validator que evalúe calidad semántica del razonamiento; la única defensa actual es revisión humana en aprobación verbal. |
| P4 | Brechas conceptuales (autocompact, attention degradation, automated-vs-human review, sesgos paramétricos, grounding factual) | **Parcialmente confirmado.** El harness inyecta `session-state.json` cada turno pero no controla autocompact. CLAUDE.md (1268 líneas) se carga completo. No existe gate que verifique afirmaciones factuales (`tests_passed=true` puede setearse sin ejecutar tests). |
| P5 | Manus subestima la hipertrofia | **Cuantificado.** Hooks totalizan **12.362 LOC** (Manus reportó ~4.300 — error 3×). 14 validators en `validators/`. Si el threshold "natural" propuesto es 5.000 LOC, ya está excedido por factor 2.5. |

### 2.2 Problemas adicionales detectados leyendo execution-logs (últimos 30 días)

| # | Nombre | Evidencia |
|---|---|---|
| P6 | Bypass `SKIP_PHASE_EXIT_GATE=1` recurrente para cross-session continuation | execution-log 2026-04-30 documenta uso ≥3 veces; CLAUDE.md exige graduación a tuning del gate tras 3+ ocurrencias; aún sin tuning estructural — la mitigación (d) git-probe se shipped pero la heurística del bypass no se rebajó retroactivamente. |
| P7 | Auto-commits silenciosos del manifiesto pueden resetear session-state | execution-log 2026-04-30 follow-up: dos commits `chore: update codebase manifest` aparecieron post-implementación desde automatización; contrato no documentado en `.claude/README.md`. |
| P8 | Pre-existing baseline failures en 6 hook tests | No regresivos pero acumulados; nadie los repara; representan deuda técnica del propio sistema de auto-test. |
| P9 | Stream idle timeout durante `Write` largos | execution-log 2026-04-30: state machine sobrevivió pero el modelo no detectó proactivamente la interrupción; síntoma del mismo gap que P4 (state vs reality drift). |

---

## 3. Existing Functionality Inventory (sub-dominio harness)

Inventario explícito de elementos del harness que las 12 estrategias tocan o
podrían tocar. Cada item con decisión Include / Omit / Transform respecto a
este spec.

| Elemento | Decisión | Justificación |
|---|---|---|
| `.claude/hooks/validators/*` (14 validators) | Include (referenciado por P3, estrategias 1, 11, 12) | Subject of analysis, no se modifica aquí. |
| `docs/knowledge/workflow-engine.md` | Include (P1, estrategia 1) | Brecha de paridad central. |
| `CLAUDE.md` (1268 líneas) | Include (P4, estrategias 2, 8) | Vector principal del problema attention degradation. |
| `docs/knowledge/_vocabulary.yaml` (925 líneas) | Include (estrategia 10) | Infra ULS Phase A endorsed; estrategia 10 la extiende. |
| `_ddd-boundaries.yaml` | Include (regla F existente) | Modelo seguido para estrategia 11 (matriz como single source of truth). |
| `_graduations.yaml` | Include (referenciado por estrategia 11) | Patrón análogo de single-source-of-truth ya endorsed. |
| Layer B3 (session-cut gate) | Include (estrategia 3 lo extiende) | Patrón ya endorsed en CLAUDE.md § Session-cut gates; estrategia 3 lo especializa al sub-dominio harness. |
| `pre-push-gate.sh` | Include (estrategias 1, 2, 4) | Punto natural de enforcement final. |
| `pre-tool-freshness.sh` | Omit | Cubre subset de P9 implícitamente; estrategia 7 propone mecanismo distinto. |
| Subcommand `consult.sh vocab <term>` | Include (estrategia 10) | API de lectura ya establecida. |
| `pattern-audit.sh` y graduación blessed (`scripts/graduate.sh`) | Omit | Endorsed e independiente; ninguna estrategia lo modifica. |
| Layer K (anti-reduction) | Include (P2 caso paradigmático) | Sujeto de análisis; relevante para Adversarial Review Q1. |
| Bypass env vars (`SKIP_*_GATE=1`) | Include (P6) | Heurística post-bypass es una garantía arquitectónica ya documentada cuya enforcement empírica falla. |

## Omission Decisions

| Element | Decision | Justificación |
|---|---|---|
| Logs específicos de domain (logistics) | Omit | Fuera de scope; el spec es meta-trabajo, no dominio. |
| Subagent system (`AGENTS.md`, `pre-agent-check.sh`) | Omit | Las 12 estrategias no lo tocan; su Layer Agent ya existe documentado. |
| `_ddd-boundaries.yaml` extensión a paths del harness | Omit | Ninguna estrategia propone elevar `.claude/hooks/` a "critical context"; sería expansión de scope que el usuario explicitó como prohibida. |

---

## 4. Tabla de 12 estrategias — 4-test y decisión

Cada estrategia evaluada con los cuatro criterios de `CLAUDE.md` § The 4-Test
for Workflow Changes. Una estrategia ADOPTA solo si pasa los 4. ✓ = pasa,
✗ = falla, ~ = pasa con caveat.

| # | Estrategia (resumen 1 línea) | T1 práctica | T2 fase | T3 coste/valor | T4 fundamento | Decisión |
|---|---|---|---|---|---|---|
| 1 | Paridad estricta de documentación: pre-push hook cuenta layers en `validators/` y verifica paridad con `workflow-engine.md`. | ✓ | ✓ | ✓ | ✓ | **ADOPTAR** |
| 2 | Presupuesto de complejidad: thresholds (CLAUDE.md > 1.500, hooks > 5.000 LOC) bloquean nuevas features hasta poda. | ✓ | ✓ | ~ | ✓ | **ADOPTAR (con recalibración)** |
| 3 | Separación de sesiones para cambios al harness: cualquier edit a `.claude/hooks/` o `workflow-engine.md` divide spec/impl en dos sesiones. | ✓ | ✓ | ✓ | ✓ | **ADOPTAR** |
| 4 | Aprobación humana fuera de banda: token criptográfico vía `make approve-harness-change`. | ✗ | — | ✗ | ✗ | **DESCARTAR** |
| 5 | Aprobación granular de decisiones: usuario elige A/B/C explícitamente, registrado en `evidence.alternative_chosen`. | ~ | ✓ | ~ | ✓ | **APLAZAR** |
| 6 | Auditorías aleatorias de calidad (chaos monkey): 1/10 interacciones full pausan para calificación 1–5. | ✗ | ~ | ✗ | ✗ | **DESCARTAR** |
| 7 | Checkpoints de compactación: `last_compaction_checkpoint` con resumen de archivos modificados antes de cada wave. | ✓ | ✓ | ✓ | ✓ | **ADOPTAR** |
| 8 | Inyección dinámica de instrucciones: `UserPromptSubmit` inyecta 3–5 reglas críticas para la fase activa. | ✓ | ✓ | ~ | ✓ | **ADOPTAR** |
| 9 | Grounding factual: extender Layer Sync a "Grounding Validator" verificando exit-codes de tests antes de `tests_passed=true`. | ✓ | ✓ | ~ | ✓ | **ADOPTAR** |
| 10 | Glosario canónico de meta-trabajo en `_vocabulary.yaml` (namespace `harness:`). | ✓ | ✓ | ✓ | ✓ | **ADOPTAR** |
| 11 | Matriz de cobertura conceptual en `docs/knowledge/harness-coverage-matrix.md`. | ✓ | ✓ | ~ | ✓ | **ADOPTAR** |
| 12 | Localización conceptual obligatoria (`## Conceptual Locus`) en specs que tocan `.claude/hooks/` o `workflow-engine.md`. | ✓ | ✓ | ✓ | ✓ | **ADOPTAR (depende de 11)** |

**Conteo:** 9 ADOPTAR · 2 DESCARTAR · 1 APLAZAR.

### 4.1 Justificaciones (1–2 líneas cada una)

- **#1 (ADOPTAR).** Documenta una norma ya enunciada en `CLAUDE.md` ("después de cualquier task que cambie un subsistema, actualizar el módulo") pero no enforced. El gate fuerza la garantía arquitectónica de paridad spec↔implementación que el harness predica para código y la extiende a su propio metadominio — coherencia de boundary.
- **#2 (ADOPTAR con recalibración).** Los thresholds propuestos por Manus (1.500 / 5.000 LOC) ya están excedidos hoy (1.268 cerca del techo; 12.362 hooks LOC, 2.5× sobre 5.000). Recalibrar a "no crecer >X% sin sesión de poda" y disparar **inmediatamente** la sesión de poda como prerequisito del resto del plan. El gate inicial debe ser warning, no HARD, hasta medir falsos positivos durante 4 semanas.
- **#3 (ADOPTAR).** Especialización del patrón B3 (session-cut gate) ya endorsed. P2 (recursividad agente↔agente) es exactamente el sesgo que B3 mitiga; restringirlo al sub-dominio harness ataca P2 en su origen sin inventar mecánica nueva.
- **#4 (DESCARTAR).** No existe ataque observado contra la aprobación verbal (gates `user_approved` y `retrospective_shown` con scrub de `<system-reminder>` ya cubren autenticidad). El token criptográfico introduce fricción UX alta para defender contra un riesgo no materializado — falla T1 y T3.
- **#5 (APLAZAR).** Granularidad razonable, pero captura de decisión estructurada añade fricción al usuario por interacción. Re-evaluar si 3+ retrospectivas documentan "no recordábamos por qué se eligió A". Hasta entonces, las **alternativas descartadas** documentadas en spec/decision-log cumplen el rol con coste cero.
- **#6 (DESCARTAR).** El feedback de calidad ya ocurre vía retrospective (estimate accuracy, process gap, emergent patterns); 10% de interacciones interrumpidas duplica un canal existente con coste UX desproporcionado. T1 débil, T3 falla.
- **#7 (ADOPTAR).** Mecaniza una regla de `CLAUDE.md` § Context Hygiene ya endorsed pero meramente prescriptiva. Sin enforcement, P9 (stream idle timeout) recurre. Garantía arquitectónica del boundary "context is a scarce resource".
- **#8 (ADOPTAR).** Reduce coste de atención efectivo de 1.268 líneas/turno a ~30 contextualizadas. Coherente con la jerarquía CLAUDE.md/sub-CLAUDE.md ya existente, que es exactamente attention budgeting por directorio; la estrategia lo extiende a por fase. T3 ~ porque el diseño de qué se inyecta requiere su propia interacción.
- **#9 (ADOPTAR).** Cierra la brecha entre "Evidence Before Claims" declarado en CLAUDE.md y enforcement real. Sin esto, un `tests_passed=true` sin ejecución es indistinguible de uno verificado — el invariante "claim ↔ evidence" se sostiene solo por buena fe del modelo, lo cual T1 explícitamente rechaza como suficiente.
- **#10 (ADOPTAR).** Extensión natural del sistema ULS Phase A endorsed. Sin normalización, cada execution log re-inventa términos para el mismo concepto (compactación / autocompact / context refresh). Coste cero (datos YAML), valor: alimenta directamente las estrategias 11 y 12.
- **#11 (ADOPTAR).** Meta-instrumento que evalúa los siguientes informes externos. Sin matriz, el repo decide adoptar/descartar gates de forma local; con matriz, las decisiones quedan ancladas en un mapa explícito de qué nodo conceptual cubre qué gate, dando garantía arquitectónica de no-solapamiento.
- **#12 (ADOPTAR, depende de 11).** Sin matriz no hay nodo al que apuntar; con matriz, la sección `## Conceptual Locus` en specs nuevos previene la proliferación local que produjo K/N/S/Sync/Agent en una semana. Coherencia con boundary documental establecido por #11.

---

## 5. Plan de adopción

### 5.1 Hito 0 — Gate-keeper y poda previa

Antes de adoptar cualquier estrategia que añada complejidad nueva al harness,
ejecutar dos pasos no negociables:

- **(0.a) Activar #2 con thresholds calibrados a la realidad actual.** Threshold inicial: hooks LOC delta ≤ 0% sobre baseline 12.362 hasta completar 0.b; CLAUDE.md líneas delta ≤ 0% sobre baseline 1.268. El gate es WARN durante 4 semanas para medir falsos positivos antes de pasar a HARD. Esto bloquea la auto-justificación de "una capa más no duele".
- **(0.b) Sesión de poda dedicada.** Auditar los 14 validators y los hooks (12.362 LOC) buscando: (i) layers solapados (¿K + N + S pueden colapsar?), (ii) bypasses con uso < 3 veces que pueden volverse default, (iii) tests baseline rotos (P8) reparados o eliminados, (iv) cierre de la brecha P1 actualizando `workflow-engine.md` exhaustivamente. Salida esperada: reducción neta ≥ 15% LOC + paridad doc completa antes de continuar al Hito 1.

### 5.2 Hito 1 — Documentación-paridad enforced

- **#1** sobre el harness ya podado en 0.b. Pre-push hook cuenta validators y compara con secciones documentadas en `workflow-engine.md`; falla si hay validator sin sección o sección huérfana. Una interacción full.

### 5.3 Hito 2 — Mapa conceptual

- **#11** primero: crear `docs/knowledge/harness-coverage-matrix.md` con fila por nodo del grafo conceptual (Agent, Model, Context window, Autocompact, Attention degradation, Parametric knowledge, Hallucination, Human review, Handoff, etc.) y columnas atendido-por / decisión / justificación. Una interacción full.
- **#12** después: `brainstorm-validator.sh` exige `## Conceptual Locus` cuando spec toca `.claude/hooks/` o `workflow-engine.md`. La sección debe apuntar a nodos de la matriz creada en #11. **Dependencia HARD de #11**; sin matriz, #12 no tiene referente. Una interacción full.

### 5.4 Hito 3 — Vocabulario meta-trabajo

- **#10**: extender `_vocabulary.yaml` con namespace `harness:` poblado desde nodos del grafo (mismos nodos que #11 — coherencia de boundary). Cada término con definición operativa breve y archivo del repo que lo encarna si aplica. Una interacción full pequeña (datos puros, sin código).

### 5.5 Hito 4 — Anti-recursividad y atención

- **#3**: `session-cut-validator.sh` reconoce sub-dominio `harness` y bloquea `planning → implementation` si los archivos del plan tocan `.claude/hooks/` o `workflow-engine.md` y `plan_session_date == today`. Una interacción full.
- **#8**: hook `UserPromptSubmit` inyecta 3–5 reglas por fase activa. Diseño previo de catálogo de reglas (qué inyectar en consult vs brainstorming vs implementation vs verification) — una sub-interacción de spec separada. Una interacción full grande total.

### 5.6 Hito 5 — Robustez factual

- **#7**: campo `evidence.last_compaction_checkpoint` con archivos modificados desde el último checkpoint, escrito automáticamente al iniciar cada wave. Lectura: el modelo consulta este campo después de cualquier sospecha de compactación. Una interacción full.
- **#9**: extender Layer Sync a Grounding Validator. Captura exit-codes de Bash (`make lint`, `phpunit`, `npm run build`) en un buffer auditable; `verification → capture` exige que el último exit-code de cada herramienta declarada en `tests_passed=true` sea 0. Una interacción full.

### 5.7 Resumen de orden y dependencias

```
Hito 0 (gate-keeper #2 + poda)  ──┐
                                  ├──>  Hito 1 (#1)  ──>  Hito 2 (#11 → #12)
                                  │                              │
                                  │                              v
                                  │                       Hito 3 (#10)
                                  │                              │
                                  │                              v
                                  └──────────────────────>  Hito 4 (#3, #8)
                                                                 │
                                                                 v
                                                          Hito 5 (#7, #9)
```

**Dependencias HARD:** 0 → todo; 11 → 12; 10 idealmente antes de 11/12 (vocabulario alimenta nombres de nodos); 8 después de 1+11 (no inyectar reglas que no estén documentadas y mapeadas).

---

## 6. Estrategias DESCARTADAS

Documentadas para que un próximo análisis externo que las re-proponga vea el
razonamiento previo de rechazo y no las re-adopte por inercia.

### #4 — Aprobación humana fuera de banda con token criptográfico

- **Test 1 falla.** No hay ataque observado contra la aprobación verbal. Los gates `user_approved` (con scrub de `<system-reminder>` antes de aplicar regex de approval/rejection — decision log 2026-04-07) y `retrospective_shown` ya defienden la autenticidad de la aprobación humana. Añadir cripto defiende contra un vector no materializado.
- **Test 3 falla.** Coste UX alto (`make approve-harness-change` por cada cambio al engine) para un riesgo no observado. Ratio coste/valor desfavorable.
- **Test 4 falla.** Sin precedente en el repo, sin patrón análogo endorsed.
- **Re-adoptar si:** se documenta ≥1 caso real de aprobación verbal automatizada/falsificada por el modelo. Hasta entonces, la práctica no fuerza nada que los gates verbales no fuercen ya.

### #6 — Auditorías aleatorias de calidad (chaos monkey)

- **Test 1 débil.** La retrospective ya pide reflexión sobre estimate accuracy, process gap, emergent patterns. Sampling 1/10 duplica un canal existente.
- **Test 3 falla.** 10% de interacciones interrumpidas para datos que la retrospective ya genera con coste cero adicional. La fricción al usuario (parar antes de implementación, calificar 1–5) es alta.
- **Test 4 falla.** Sin precedente; el patrón "feedback estructurado periódico" ya está cubierto por Skill 15 (Learning Review mensual) que agrega evidencia de múltiples logs en lugar de muestrear puntualmente.
- **Re-adoptar si:** Skill 15 deja de producir aprendizaje accionable durante 3 meses consecutivos. Hasta entonces, mecanismo redundante.

---

## 7. Estrategias APLAZADAS

### #5 — Aprobación granular de decisiones (`evidence.alternative_chosen`)

- **Estado.** Razonable estructuralmente; falla T3 hoy por fricción incremental sobre el usuario por cada brainstorming.
- **Criterio de re-evaluación.** Si en 3+ retrospectivas (o decision-log entries) aparece la frase "no recordábamos por qué se eligió A" o equivalente, graduar a ADOPTAR. Métrica concreta: `consult.sh tag missing-rationale` ≥ 3.
- **Mecanismo de checkpoint.** Re-auditar en review trimestral (próximo: 2026-08).
- **Mientras tanto.** Las "Alternativas descartadas" en specs y decision-log ya capturan la decisión con coste cero — la práctica está cubierta a nivel de documentación, falta solo el gate enforced.

---

## 8. Norms

> Reglas imperativas sobre el propio spec y sus consumidores futuros.

- **Toda interacción que ADOPTE una de las 9 estrategias DEBE referenciar este spec en su sección Consult.** No se permite re-derivar el 4-test para una estrategia ya evaluada aquí.
- **Ninguna estrategia ADOPTADA SHALL ejecutarse antes de Hito 0** (gate-keeper + poda). Saltar Hito 0 invalida el plan.
- **Cualquier futuro informe externo que proponga una estrategia ya DESCARTADA aquí MUST justificar explícitamente por qué la decisión previa quedó obsoleta** (cambio en evidencia empírica, no preferencia estética).
- **El plan DEBE re-evaluarse al completar cada hito**, antes de pasar al siguiente — no se permite ejecución encadenada sin checkpoint humano.
- **Ninguna estrategia DEBE implementarse en la misma sesión que su spec** (pre-condición universal #3 aplicada al meta-nivel: el spec en sí MUST conmutarse con su impl, regla que este documento respeta — solo se escribe el spec, no se modifica el harness).
- **Jamás se permitirá ampliar el alcance de las 12 estrategias en este documento sin reabrir brainstorming.** Si emerge una estrategia nueva, requiere su propio spec o un addendum aprobado.

---

## 9. Safeguards

| Risk | Mitigation |
|---|---|
| **Adoptar las 9 estrategias agrava la hipertrofia que intentan resolver.** | Hito 0 (poda previa) es prerrequisito HARD; sin reducir antes, ninguna ADOPCIÓN ejecuta. La § Observación estructural (sección 11) lo refuerza explícitamente. |
| **Estrategia #2 con thresholds Manus retroactivamente bloquea trabajo legítimo.** | Recalibrar thresholds a baseline actual (1.268 / 12.362) con delta ≤ 0% como warning durante 4 semanas; HARD solo después de medir falsos positivos. |
| **Dependencia #11 → #12 se rompe si #11 produce una matriz de baja calidad.** | #12 NO ejecuta hasta que #11 incluya cobertura de los nodos del grafo IA estándar (mínimo: Autocompact, Attention degradation, Hallucination, Human review, Handoff, Parametric knowledge). |
| **Falsos positivos del Grounding Validator (#9) bloquean verification cuando el comando no produjo exit code (timeout, kill).** | Diferenciar exit absent (sin evidencia) de exit ≠ 0 (evidencia de fallo); el primero produce warning y permite override con justificación, el segundo es HARD. |
| **Inyección dinámica (#8) puede contradecir reglas globales de CLAUDE.md.** | Catálogo de reglas inyectables se aprueba en spec aparte (sub-interacción); no se permite que el hook reinterprete CLAUDE.md, solo que extraiga sub-secciones literales. |
| **Glosario harness (#10) fork del namespace dominio puede causar colisión de nombres.** | Namespace explícito `harness:` en YAML; `consult.sh vocab <term>` ya soporta scoping. |
| **El spec mismo cae en P3 (estructura sin rigor): cumple Norms/Safeguards/Adversarial Review por regex pero el análisis es superficial.** | El usuario revisa el contenido manualmente antes de aprobación final; el flujo de retrospective de esta interacción debe nominar como "process gap" cualquier sección que detecte como ceremonial. |
| **Auto-commits del manifiesto (P7) interfieren con la sesión de poda.** | Documentar contrato de auto-commit como prerrequisito de Hito 0; si la automatización desconocida persiste, suspenderla durante poda. |
| **Re-evaluación de #5 nunca ocurre (aplazada → olvidada).** | Criterio de re-evaluación queda atado a `consult.sh tag missing-rationale` ≥ 3 + revisión trimestral programada (próxima: 2026-08); follow-up registrado en decision log con esta interacción. |

---

## 10. Architectural Adversarial Review

Mínimo 3 preguntas Q/A. Al menos una con keyword arquitectónica (endorsed,
boundary, DDD, tech-debt, architecture, coupling, pattern, tradeoff). El
usuario exigió explícitamente la pregunta 1.

**Q1. ¿Aplicar el 4-test retrospectivamente a las capas existentes
K/N/S/Sync/Agent/F/H también está en scope, o solo prospectivamente?**

A: Prospectivamente para este spec. Aplicar el 4-test retroactivamente a Layers
K/N/S/Sync/Agent/F/H requiere su propia interacción full y aterriza
naturalmente en **Hito 0 (poda previa)** — la sesión de poda DEBE incluir
re-evaluación de cada capa existente con el mismo criterio. Si una capa no
sobrevive el 4-test retroactivo, candidata a remoción (precedente: I/J ya
fueron removidos 2026-04-26). Sin retro-aplicación durante Hito 0, el sesgo de
status quo (las capas existentes son endorsed por defecto) contradice el
**boundary** "ceremonia, no flujo — descártala" del propio 4-test. Por tanto:
spec actual define la metodología prospectiva; Hito 0 ejecuta la retrospectiva
sobre las capas existentes. Tradeoff aceptado: la retrospectiva podría
descubrir que 2–3 layers existentes ya fallan el test, lo cual es resultado
deseable (validación del proceso), no fallo del spec.

**Q2. ¿La adopción de #11 (matriz de cobertura conceptual) no genera ella
misma una nueva forma de hipertrofia documental — un nuevo módulo de
conocimiento que requiere mantenimiento, paridad, y se vuelve obsoleto si los
nodos del grafo IA evolucionan?**

A: Riesgo real. Mitigación de tres patas: (a) la matriz se ancla al **patrón**
ya endorsed `_graduations.yaml` y `_ddd-boundaries.yaml` (single source of
truth con maintenance contract documentado), no inventa convención nueva;
(b) la matriz tiene un **límite explícito de tamaño** definido en su Hito 2 —
si crece más de un orden de magnitud sobre los nodos iniciales, dispara
revisión por presupuesto (#2); (c) acepta el **tech-debt** de mantenimiento
como precio del beneficio: sin matriz, cada decisión arquitectónica del harness
se toma en vacío y la deuda se distribuye implícitamente sobre todos los
specs futuros. Concentrarla en un documento auditable es preferible a
diseminarla, aun a coste de un módulo más.

**Q3. ¿La estrategia #8 (inyección dinámica) introduce coupling entre el hook
`UserPromptSubmit` y CLAUDE.md que dificulta editar CLAUDE.md sin romper la
inyección?**

A: Sí, y es coupling deseable si se diseña como referencia, no como copia.
Patrón endorsed: el hook **extrae sub-secciones literales** de CLAUDE.md (por
heading) en lugar de duplicar el texto. Editar CLAUDE.md actualiza
automáticamente lo inyectado. Esto convierte el coupling en **alineamiento
arquitectónico** — el hook no inventa interpretación. Tradeoff: si una heading
de CLAUDE.md se renombra, el hook falla loudly (no silently) — patrón
preferible al coupling implícito actual donde el modelo "supuestamente lee"
todo CLAUDE.md y nadie verifica qué retuvo. La interacción de #8 deberá
declarar explícitamente este contrato de extracción (heading → fase) en su
spec, sujeto al Conceptual Locus de #12.

**Q4. ¿Por qué adoptar 9 de 12 si la observación estructural (sección 11)
sugiere que el harness ya está hipertrofiado y la respuesta correcta es PODA
antes que ADOPCIÓN?**

A: Porque el plan de adopción **incorpora la poda como Hito 0 prerrequisito
HARD**. La adopción nominal de 9 estrategias no es contradictoria con poda
previa; al contrario, las 9 fueron diseñadas precisamente para problemas que
la poda no resuelve por sí sola (paridad doc, vocabulario, matriz, grounding).
La alternativa "no adoptar nada hasta podar" se descarta porque la poda sin
disciplina prospectiva (=#1, #11, #12 al menos) reduce LOC sin prevenir su
recurrencia — re-emergeríamos en 6 semanas a la misma posición. El tradeoff
aceptado: 9 ADOPTAR pueden parecer muchas, pero contadas como hitos son 5
sesiones secuenciales tras Hito 0; volumen manejable si Hito 0 efectivamente
reduce baseline.

---

## 11. Observación estructural

Tras analizar las 12 propuestas con el 4-test, el patrón claro es que **el
harness está hipertrofiado y la respuesta más urgente NO es adoptar
estrategias adicionales, sino podar las existentes**. Evidencia:

- 12.362 LOC en hooks (3× la cifra subestimada por Manus).
- 14 validators activos; layers I y J ya fueron removidos 2026-04-26 sin
  consecuencias negativas reportadas — precedente fuerte de que la poda es
  factible y no destruye función.
- Brecha de documentación de 5 días sin cerrar (P1) sugiere que el ritmo de
  adopción excede el ritmo de mantenimiento.
- 6 baseline test failures persistentes (P8) — incluso el sub-sistema de
  auto-test del harness está acumulando deuda silenciosa.
- CLAUDE.md a 1.268 líneas, prácticamente en el techo del threshold de
  attention degradation (≈1.500 según literatura "lost in the middle").

Por eso este plan **NO** ejecuta las 9 ADOPTAR de forma encadenada; las
condiciona a un Hito 0 que es **explícitamente reductor**, no aditivo. Si Hito
0 no produce reducción neta ≥ 15% LOC y paridad de documentación completa,
**el plan completo se aborta** y se reescribe como spec de poda exclusiva. La
honestidad estructural exige nombrar este caveat: la primera acción tras
aprobar este spec NO es escribir un nuevo gate, es eliminar capas redundantes.

Conviene también nombrar la meta-ironía sin disfraz: este spec consume el
flujo full (consult → brainstorm → planning → impl → verify → capture →
retrospect → finalize) para diagnosticar que el flujo full produce
hipertrofia. Se justifica porque el output ES un artefacto de poda en sí
(condicional al cumplimiento de Hito 0); pero la meta-ironía debe quedar
registrada — si el patrón se repite (specs sobre el harness que cuestan más
de lo que ahorran), graduar a regla de CLAUDE.md "ningún spec del harness sin
plan de reducción neta cuantificada".

---

## 12. Anexo — Referencias cruzadas

- `CLAUDE.md` § The 4-Test for Workflow Changes (criterio aplicado).
- `CLAUDE.md` § Session-cut gates (B3) (precedente de #3).
- `CLAUDE.md` § Context Hygiene (precedente de #7).
- `CLAUDE.md` § Evidence Before Claims (precedente de #9).
- `docs/superpowers/specs/2026-04-29-ubiquitous-language-system-phase-a-design.md` (precedente de #10).
- `docs/superpowers/execution-logs/2026-04-28-layer-k-anti-reduction-validator.md` (caso paradigmático P2).
- `docs/superpowers/execution-logs/2026-04-30-cross-session-resume-hardening.md` (P6, P7, P9).
- `docs/knowledge/_graduations.yaml`, `docs/knowledge/_ddd-boundaries.yaml` (patrones single-source-of-truth para #11).
- `docs/decisions/log.md` (registro vivo de decisiones; este spec añade entrada).
