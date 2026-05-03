# Harness Pruning — Hito 0.b Design

**Fecha:** 2026-05-03
**Tipo:** Spec ejecutivo (Hito 0.b del plan maestro)
**Branch:** `claude/prune-harness-poda-xEdZG`
**Input spec:** `docs/superpowers/specs/2026-05-03-harness-critique-and-mitigations-design.md`
**Baseline:** hooks 12,362 LOC · CLAUDE.md 1,268 líneas · workflow-engine.md 313 líneas
**Target:** ≥15% reducción LOC en `.claude/hooks/` + paridad doc completa

---

## 1. Contexto

El spec maestro (2026-05-03-harness-critique-and-mitigations) condicionó la
adopción de 9 estrategias a un Hito 0.b reductor previo. Sin reducción neta
≥15% el plan completo aborta. Esta interacción ejecuta esa poda y cierra la
brecha P1 (workflow-engine.md desactualizado 5+ días respecto a layers
K/N/S/Sync/Agent/B3).

---

## 2. Approach evaluado

Trade-off entre tres alternativas:

- **Alternativa A — Poda agresiva (RECOMENDADA y APROBADA):** elimina dead
  code confirmado (test-self-gating.sh huérfano), Layer K (1 falso positivo
  recursivo, 1 origin log, ceremonia sin rigor), refactoriza section-gates
  table-driven, audita tests con paths obsoletos, cierra paridad doc.
- **Alternativa B — Poda quirúrgica:** solo Layer K + test huérfano, sin
  auditar test-workflow-engine. Estimado −500-800 LOC, no alcanza target
  ≥15%. Descartada.
- **Alternativa C — Solo doc:** 0% reducción → trigger del abort clause del
  spec maestro. Descartada.

**Ventaja A:** los items de mayor LOC están en código ya roto o ceremonial;
la "agresividad" es nominal. **Desventaja A:** auditar test-workflow-engine
(513 LOC) requiere cuidado para no eliminar cobertura legítima.

---

## 3. Existing Functionality Inventory

| Elemento | LOC | Decisión | Justificación |
|---|---|---|---|
| `.claude/hooks/test-self-gating.sh` | 340 | **Eliminar** | Testea `full-flow-gate.sh` que NO existe; 7/14 fail; dead code |
| Layer K (anti-reduction) en `brainstorm-validator.sh` | ~30 | **Eliminar** | T1 falla — verifica sección, no calidad; 1 falso positivo recursivo (su propio spec); origin log único |
| Lógica `positive-signal` y `extract_bullet` en `section-validator.sh` (lib) | ~30 | **Eliminar (parcial)** | Sólo soportan Layer K; sin K son dead code |
| Tests de Layer K en `test-brainstorm-validator.sh` | ~50 | **Eliminar** | Cobertura de la layer eliminada |
| `test-workflow-engine.sh` | 513 | **Auditar** | 14/33 fail, paths obsoletos a `full-flow-gate.sh`. Por test fallando: si camino existe → mover/reparar; si no → eliminar |
| `test-pre-commit-deprecated-alias.sh` | 56 | **Verificar** | Si la deprecación cumplió ciclo → eliminar; si activa → mantener |
| Layer N (Norms) | 8 | **Mantener + consolidar en tabla** | Universal, cheap, único enforcer de invariantes explícitos |
| Layer S (Safeguards) | 8 | **Mantener + consolidar en tabla** | Idem N, pairing risk/mitigation |
| Layer H (Prior Art Audit) | ~20 | **Mantener + consolidar en tabla** | Pareja con F, lib compartida |
| Layer C (Adversarial Review) | ~20 | **Mantener + consolidar en tabla** | Endorsed; relocada de post-verification 2026-04-24 |
| Layer Sync (130 LOC) | 130 | **Mantener** | Único detector de drift plan↔código; alta complejidad pero alto valor |
| Layer Agent (`pre-agent-check.sh`, 118 LOC) | 118 | **Mantener** | Endorsed AGENTS.md hito 4 |
| Layer F (`ddd-boundary-check.sh`) | — | **Mantener** | Endorsed con bypass |
| Layer B3 (`session-cut-validator.sh`, 67 LOC) | 67 | **Mantener** | 2 transitions enforced; reciente abr-30 |
| Bypasses 0-uso (DDD/SYNC/SESSION_CUT/CLASSIFY) | — | **Mantener** | Recientes; 0 usos refleja calibración correcta, no muerte; documentar |
| `workflow-engine.md` (313 líneas) | 313 | **Reescribir** | Falta K/N/S/Sync/Agent/B3/spec-compliance + 4 bypasses |

---

## 4. Omission Decisions

| Elemento | Decisión | Justificación |
|---|---|---|
| `user-prompt-state.sh` (579 LOC) y `workflow-status-line.sh` (608 LOC) | Omit | Endorsed; refactor de status-lines fuera de scope. Inconsistencia `pattern_wide`/`pattern_search` documentada como follow-up — no se ataca aquí |
| Status line / progress hooks | Omit | Operacionales, fuera de patrón "validator" |
| Layers F/H/Sync/Agent/B3 | Omit (de la eliminación) | Pasan retroactivamente el 4-test |
| CLAUDE.md (1,268 líneas) | Omit (reducción) | El spec maestro asigna esto a estrategia #8 (inyección dinámica), Hito 4 — no Hito 0.b |
| `_vocabulary.yaml`, `_graduations.yaml`, `_ddd-boundaries.yaml` | Omit | Datos endorsed, no se modifican |
| Pre-existing tests pasando | Omit | El criterio es "no eliminar tests legítimos"; sólo se eliminan tests con path-target inexistente |

---

## 5. Plan de poda — pasos atómicos

### Wave 1 (paralelo): eliminaciones triviales independientes

- **1a:** Eliminar `test-self-gating.sh` (340 LOC dead code).
  → produces: -340 LOC; 1 archivo eliminado.
  → files: .claude/hooks/test-self-gating.sh
- **1b:** Auditar `test-pre-commit-deprecated-alias.sh` (56 LOC). Si la deprecación cumplió ciclo (alias eliminado del codebase), eliminar. Si activa, mantener.
  → produces: decisión documentada; 0–56 LOC eliminadas.
  → files: .claude/hooks/test-pre-commit-deprecated-alias.sh
- **1c:** Auditar `test-workflow-engine.sh` (513 LOC). Para cada uno de los 14 tests fallidos: ¿el código que prueba existe? Si no → eliminar test. Si sí pero el test está mal escrito → reparar o mover a test-suite vivo equivalente. Documentar línea por línea.
  → produces: −X LOC (estimado 200-400); test-workflow-engine reducido o eliminado completo si redundante con test-full-flow-e2e.
  → files: .claude/hooks/test-workflow-engine.sh

### Wave 2: refactor brainstorm-validator (depende de Wave 1 sólo por LOC counting)

- **2a:** Eliminar Layer K en `brainstorm-validator.sh` (líneas 165–193) **+** lógica `extract_bullet` y rama `positive-signal` en `section-validator.sh` que sólo soportan K.
  → produces: −~40 LOC en validators.
  → files: .claude/hooks/validators/brainstorm-validator.sh, .claude/hooks/lib/section-validator.sh
- **2b:** Eliminar tests específicos de Layer K en `test-brainstorm-validator.sh`.
  → produces: −~50 LOC.
  → files: .claude/hooks/test-brainstorm-validator.sh
- **2c:** Refactor section-gates como tabla declarativa: para layers N/S/H/C, parametrizar (section_name, trigger_condition, content_classifier) en una loop. Code path actual ~80 LOC → ~40 LOC.
  → produces: −~40 LOC; brainstorm-validator más mantenible.
  → files: .claude/hooks/validators/brainstorm-validator.sh

### Wave 3 (paralelo con Wave 2): documentación

- **3a:** Reescribir `docs/knowledge/workflow-engine.md` exhaustivamente. Cubre A/B/C/D/F/H + K (REMOVED 2026-05-03) + N + S + Sync + Agent + B3 + spec-compliance. Tabla de bypasses completa. Mantiene "[REMOVED]" para K (precedente: I/J).
  → produces: workflow-engine.md actualizado, paridad cerrada.
  → files: docs/knowledge/workflow-engine.md

### Wave 4: verification + capture

- **4a:** Correr `make lint`, `make lint-shell`, todos los tests del harness. Confirmar 0 regresiones nuevas.
  → produces: evidencia tests_passed + lint_clean.
- **4b:** Recontar baseline. Reportar % reducción en hooks LOC.
  → produces: métrica final.

---

## 6. Test plan

- Cada eliminación corre los tests del harness antes y después; reducción neta de tests **fallando** (no solo total) demuestra calidad ≥ baseline.
- `test-brainstorm-validator.sh` debe seguir pasando todos sus tests no-K.
- `test-full-flow-e2e.sh` debe seguir pasando (smoke test global).

---

## Norms

> Reglas imperativas de esta interacción.

- **No se permite** añadir features nuevas al harness en esta sesión; cualquier
  cambio que no sea eliminación o consolidación está fuera de scope.
- **Se debe** alcanzar reducción neta ≥15% LOC sobre baseline 12,362 antes de
  marcar `tests_passed` o avanzar a `capture`.
- **Jamás** eliminar un test sin verificar que su target (función/archivo
  bajo prueba) ya no existe O que el comportamiento cubierto está cubierto
  por otro test vivo. Toda eliminación de test debe estar documentada en el
  execution log con justificación uno-a-uno.
- **Siempre** preservar la pareja de un layer endorsed: si un layer queda
  (Layer F), su pareja documental en workflow-engine.md también debe quedar.
- **Nunca** eliminar bypass scaffolding sin verificar 0 usos documentados Y
  ≥4 semanas de operación del gate sin disparo legítimo registrado. Esto
  protege contra remover el escape hatch antes de saber si es necesario.
- **Toda** transición de fase debe usar `phase-advance.sh`; no hay edits
  directos a `phase_history` permitidos en esta sesión.

---

## Safeguards

| Risk | Mitigation |
|---|---|
| Eliminar `test-workflow-engine.sh` con tests legítimos camuflados entre los rotos. | Auditoría línea-por-línea con tabla por test indicando: target existente sí/no, redundancia con otro test, decisión final. Si dudoso → reparar, no eliminar. |
| Refactor section-gates table-driven introduce bug en N/S/H/C. | Tests existentes de N/S/H/C corren idénticos antes y después; cualquier delta de pass/fail bloquea el commit. |
| Eliminar Layer K dispara recurrencia del problema que motivó su creación (specs sin Maximal Version Considered). | El spec maestro y este spec demuestran que el patrón ya está internalizado en el flujo de brainstorming (alternativas explícitas + recommendation). La revisión humana en aprobación del spec cumple el rol semántico que K intentaba mecanizar. Si en 3+ retrospectivas siguientes vuelve a aparecer el patrón "MVP-first sin maximal", reabrir K en su propio spec con criterio cualitativo, no regex. |
| Reducción LOC bajo 15 al medir, abortando el plan maestro. | Si la auditoría de test-workflow-engine produce porcentaje insuficiente combinado, escalar a poda adicional documentada caso a caso, no fabricar reducciones cosméticas. Aceptar abort si es la decisión honesta. |
| Doc parity update introduce errores fácticos sobre layers que se mantienen. | Cada sección referencia un archivo con line-number; revisión cruzada al final con `grep -n` contra el código antes de commit. |
| Working tree dirty al finalizar (workflow-artifacts no incluidos en sync). | Sync validator filtra workflow artifacts paths por construcción; no hay drift fabricado. |
| El propio spec cae en P3 (cumple Norms/Safeguards/Adversarial Review por regex pero análisis superficial). | El usuario revisa antes de aprobar; retrospective explícitamente nominará ceremonia detectada como process gap. |

---

## 7. Architectural Adversarial Review

**Q1. ¿Eliminar Layer K no es exactamente el patrón de tech-debt que el harness intenta prevenir — descartar un endorsed por percepción de "ceremonia"?**

A: No. Layer K **falló su propio 4-test retrospectivamente** documentado en este consult: T1 verifica presencia de sección, no rigor del razonamiento (P3 explícito); T3 paga ~40 LOC + maintenance regex + lógica de stripping de fenced code blocks; T4 tiene un solo origin log Y un falso positivo recursivo en su propio spec de implementación. La distinción con tech-debt es que tech-debt tiene valor de uso continuo aunque imperfecto; Layer K nunca capturó un caso real fuera de su origen. Precedente endorsed: Layers I y J fueron removidos 2026-04-26 con la misma metodología sin consecuencias negativas — la práctica de poda con 4-test retroactivo está endorsed.

**Q2. ¿Refactor table-driven de section-gates introduce coupling/abstraction que dificulta debugging cuando un gate específico falla?**

A: Coupling controlado. La tabla declarativa (section_name, trigger, classifier) es endorsed por **patrón existente** en el codebase: `_graduations.yaml`, `_ddd-boundaries.yaml`, y `WORKFLOW_ARTIFACTS_PATHS` en sync-validator todos siguen single-source-of-truth tabular. Tradeoff: en lugar de 4 bloques if-else de ~20 LOC cada uno, una tabla de 4 filas + un loop de 10 LOC. Debugging: cuando un gate falla, el mensaje sigue siendo específico al section_name (igual que hoy). Si un mantenedor futuro quiere entender K, ve la tabla; el shape de cada layer queda más explícito que disperso entre líneas de un script de 259 LOC. Boundary preservado: sigue siendo un solo validator, no se introduce nuevo módulo.

**Q3. ¿Por qué no aprovechar para consolidar también Layer Sync (130 LOC) o Layer Agent (118 LOC), que también son grandes?**

A: Tradeoff de scope vs evidencia. Layer K tiene **evidencia retrospectiva de fallo del 4-test**; Layer Sync y Layer Agent **no** — pasan los 4 tests retroactivos en este consult. Eliminarlos por tamaño y no por evidencia violaría el principio "ceremonia, no flujo — descártala" en su lectura honesta: hay que descartar lo ceremonial, no lo grande. Además, ambos tienen menos de 30 días de operación; aplicar el mismo criterio de evidencia que se aplicó a I/J/K (≥3 ocurrencias o falso positivo documentado) requiere más tiempo de medición. Re-evaluar en revisión trimestral (2026-08).

**Q4. ¿Eliminar `test-self-gating.sh` (340 LOC) sin reemplazo deja un agujero de cobertura?**

A: No. El target del test (`full-flow-gate.sh`) ya no existe — el agujero ya está abierto desde hace meses sin consecuencias detectadas. La cobertura "self-gating" semánticamente equivalente (validators bloquean cuando se autoejecutan sin evidencia) está cubierta por `test-enforcement-layers.sh` (testea phase-transition-controller que es el mecanismo actual de self-gating). Ningún test pasando de test-self-gating verifica algo que otro test vivo no cubra.

---

## 8. Resultado esperado

- **Cuantitativo:** hooks LOC ≤ 10,508 (15% reducción mínima sobre 12,362).
- **Cualitativo:** workflow-engine.md cubre todas las layers en producción +
  todas las documentadas como REMOVED.
- **Habilitador:** Hito 1 del spec maestro queda desbloqueado.
- **Salvaguarda:** si LOC menor a 15 por ciento, plan maestro aborta y se reescribe como
  spec de poda exclusiva (regla del spec § 11).

---

## 9. Anexo — referencias cruzadas

- Spec maestro: `docs/superpowers/specs/2026-05-03-harness-critique-and-mitigations-design.md` § 5.1 Hito 0.b, § 11 Observación estructural.
- Precedente de poda con 4-test: `docs/superpowers/execution-logs/2026-04-26-4test-applied-FIJ.md` (Layers I, J).
- Origen Layer K: `docs/superpowers/execution-logs/2026-04-28-layer-k-anti-reduction-validator.md`.
- CLAUDE.md § The 4-Test for Workflow Changes (criterio aplicado).
