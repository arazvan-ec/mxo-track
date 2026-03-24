# Subagent Output: Harness Design Patterns Review

**Fecha:** 2026-03-24
**Tipo de agente:** Explore
**Objetivo:** Analizar el artículo de Anthropic "Harness Design for Long-Running Application Development" y extraer ideas aplicables para mejorar el flujo de trabajo descrito en CLAUDE.md

**Fuente:** https://www.anthropic.com/engineering/harness-design-long-running-apps

---

## Resumen del artículo

El artículo presenta un sistema multi-agente inspirado en GANs con tres agentes especializados:
- **Planner:** convierte prompts breves en especificaciones completas
- **Generator:** implementa iterativamente en chunks descompuestos
- **Evaluator:** QA externo usando herramientas como Playwright para evaluar contra criterios explícitos

Hallazgos clave del artículo:
1. Los agentes que evalúan su propio trabajo tienden a alabarlo aunque la calidad sea mediocre
2. La compactación de contexto no elimina "context anxiety" (el modelo apura el trabajo conforme se llena el contexto)
3. Los context resets completos con handoffs estructurados dan mejores resultados que la compactación sola
4. Cada componente del harness codifica asunciones sobre limitaciones del modelo que deben stress-testearse periódicamente
5. Sprint contracts (criterios de aceptación negociados) mejoran la calidad de los iteration loops

---

## Hallazgos clave (análisis contra nuestro CLAUDE.md)

### 1. El problema de la auto-evaluación — parcialmente cubierto

**Artículo:** "When asked to evaluate work they've produced, agents tend to respond by confidently praising the work — even when, to a human observer, the quality is obviously mediocre." Solución: separar evaluación de generación.

**Estado actual:** Skill 5 (Subagent-Driven Development) ya separa implementación de review con dos etapas: spec compliance reviewer + code quality reviewer. Skill 11 también ofrece review externo. Sin embargo, Skill 9 (Verification) se limita a evidencia mecánica: `tests pass + lint clean`.

**Gap:** No hay evaluación subjetiva de calidad arquitectónica. Un código puede pasar tests y lint pero ser over-engineered, violar YAGNI, o no seguir los patrones del codebase. Actualmente dependemos del reviewer humano para esto.

**Recomendación:** Añadir al reviewer de Skill 5 un checklist de calidad arquitectónica explícito:
- ¿La solución es la más simple posible? (YAGNI)
- ¿Respeta los principios SOLID documentados en CLAUDE.md?
- ¿Sigue los patrones existentes del codebase o los mejora justificadamente?
- ¿La cantidad de abstracción es proporcional al problema?
- ¿Se puede entender el flujo sin leer más de 3-4 archivos?

**Impacto:** Medium — mejoraría calidad pero requiere calibrar el reviewer para evitar false positives.

### 2. Context Management — gap significativo

**Artículo:** Claude Sonnet 4.5 exhibió "context anxiety" (comportamiento de apurar el trabajo cuando el contexto se llena) tan fuerte que la compactación no fue suficiente. Claude Opus 4.5 "largely eliminated this behavior". Los context resets con handoffs estructurados son superiores a la compactación sola.

**Estado actual:** Tenemos `session-state.json` + execution logs + atomic commits como mecanismo de handoff estructurado. El SessionStart hook lee contexto previo. Pero NO tenemos:
- Detección de context anxiety (¿cuándo está el modelo degradando por contexto largo?)
- Estrategia explícita de context hygiene para sesiones largas
- Threshold definido para sugerir nueva sesión

**Recomendación:** Documentar una regla de "context hygiene" en CLAUDE.md:
1. En sesiones largas (>50 tool calls), hacer checkpoint explícito: commit + push + actualizar session-state
2. Si la tarea tiene más de ~8 pasos de implementación, considerar dividir en sesiones
3. Antes de continuar después de una compactación, verificar que el contexto crítico (spec, plan, estado actual) está accesible via archivos, no solo en memoria

**Impacto:** Medium-High — las sesiones largas son donde más se degrada la calidad. Con Opus 4.6 puede que el impacto sea menor, pero es un safety net valioso.

### 3. Sprint Contracts — mejora para Skill 5

**Artículo:** Antes de implementar, generator y evaluator negocian contratos explícitos con deliverables específicos y criterios de éxito testables. Esto cierra el gap entre specs de alto nivel y código concreto.

**Estado actual:** Nuestro flujo brainstorm → spec → plan es similar pero el "contrato" es entre Claude y el usuario, no entre agentes. Los subagentes implementadores en Skill 5 reciben la tarea completa pero sin criterios de aceptación explícitos para el reviewer.

**Recomendación:** En Skill 5, al despachar cada implementador, incluir:
```
## Acceptance Criteria (para el reviewer)
- [ ] Criterio 1 específico y verificable
- [ ] Criterio 2 específico y verificable
- [ ] No introduce código que no sea directamente necesario para la tarea
```
Estos criterios se extraen del plan (que ya tiene pasos detallados) y se incluyen tanto en el prompt del implementador como del reviewer.

**Impacto:** Medium — mejora la consistencia del review loop sin añadir complejidad significativa.

### 4. Adaptive Model Improvement — LA IDEA CON MÁS IMPACTO

**Artículo:** "Every component in a harness encodes assumptions about model limitations worth stress-testing." Recomiendan eliminar componentes que ya no son load-bearing y re-examinar arquitectura con cada nuevo modelo.

**Estado actual:** Nuestro CLAUDE.md tiene scaffolding significativo que codifica asunciones sobre modelos anteriores. NO tenemos mecanismo para evaluar si cada restricción sigue siendo necesaria.

**Inventario de asunciones codificadas:**

| Componente | Asunción que codifica | Evidencia de necesidad actual | Acción sugerida |
|---|---|---|---|
| Workflow engine HARD gates | "Claude se saltará fases si no lo bloqueas mecánicamente" | Sin datos — nunca se ha testeado sin gates | **Stress-test:** ejecutar 3-5 tareas con SOFT warnings en vez de HARD gates, medir compliance |
| Tablas anti-racionalización (6+ tablas en CLAUDE.md) | "Claude inventará excusas para saltarse pasos" | Parcialmente válido pero la cantidad puede ser excesiva | **Reducir:** consolidar en 1 tabla general + solo las específicas de skills críticos (TDD, Debugging) |
| `evidence.user_turns ≥ 3` en brainstorming | "Claude no conversará suficiente sin mínimo forzado" | A veces 1-2 turnos son suficientes para cambios bien definidos | **Relajar:** cambiar a `≥ 1` con SOFT warning si `< 3`, permitiendo brainstorm corto cuando el scope es claro |
| `session-state.json` con evidencia granular | "Claude no trackea progreso sin estado externo" | Útil entre sesiones, necesario para hooks | **Mantener** — valor real para persistencia cross-session |
| Subagent output limits (300 líneas) | "Los subagentes producen output excesivo" | Problema real con modelos anteriores | **Stress-test:** probar sin límite explícito en 5 tareas, medir si Opus 4.6 es naturalmente conciso |
| Exploración en capas obligatoria | "Claude hará grep masivos si no lo restringes" | Parcialmente válido, pero overhead de la regla puede superar el beneficio | **Simplificar:** mantener como best practice, no como HARD gate |
| Pre-Exploration Gate (leer manifest antes de explorar) | "Claude explorará redundantemente" | Válido — el manifest ahorra tiempo real | **Mantener** — ahorro medible de tool calls |
| Principio de No-Redundancia | "Claude ejecutará acciones innecesarias" | Válido en general | **Mantener** — pero ya es natural para Opus 4.6 |
| Atomic commits cada paso | "Se perderá trabajo en sesiones largas" | Válido por razones de safety, no de limitación del modelo | **Mantener** — pero la razón es safety, no capability |
| Scope Change Detection forzado | "Claude mezclará tareas sin detectar cambio de scope" | Probablemente menos necesario con modelos más capaces | **Relajar a SOFT warning** |

**Recomendación principal:** Crear una sección "Harness Assumptions" en CLAUDE.md que:
1. Documente explícitamente qué limitación codifica cada mecanismo
2. Defina un schedule de review (cada cambio de modelo)
3. Establezca el protocolo de stress-test (N tareas sin el gate, medir compliance)
4. Permita evolucionar el harness de HARD → SOFT → best practice → eliminar

**Impacto:** High — reducir over-engineering del harness directamente mejora velocidad sin necesariamente sacrificar calidad. También reduce la carga cognitiva del CLAUDE.md que ya es muy extenso.

### 5. Evaluator como participante continuo — mejora para features XL

**Artículo:** El evaluator no es un paso final sino un participante continuo que genera iteration targets concretos durante el desarrollo.

**Estado actual:** Los reviewers en Skill 5 se despachan DESPUÉS de completar cada tarea. Para features grandes (XL), esto significa que se puede implementar mucho código antes de recibir feedback.

**Recomendación:** Para features clasificadas como XL en el plan:
- Añadir "checkpoint reviews" mid-implementation (ej: después de completar 50% de las tareas)
- El checkpoint review verifica: ¿la dirección es correcta? ¿hay desvíos del spec? ¿la calidad se mantiene?
- Esto no reemplaza el review por tarea, sino que añade una verificación de coherencia global

**Impacto:** Medium — solo aplica a features XL pero previene divergencia costosa.

### 6. Comunicación entre agentes via archivos — ya implementado

**Artículo:** Los agentes intercambian información via archivos estructurados, no via historial de conversación.

**Estado actual:** Ya lo hacemos: specs en `docs/superpowers/specs/`, plans en `docs/superpowers/plans/`, execution logs, session-state.json. Para subagentes, el contexto va en el prompt (correcto para tareas pequeñas).

**Recomendación:** Sin cambios necesarios. Nuestro approach ya se alinea con esta práctica.

---

## Archivos relevantes

| Archivo | Relevancia |
|---------|------------|
| `CLAUDE.md` | Contiene todo el harness actual — target principal de las mejoras propuestas |
| `.claude/hooks/workflow-engine.sh` | Implementa los HARD/SOFT gates que se propone stress-testear |
| `.claude/session-state.json` | Estado del workflow engine que se propone mantener |
| `docs/knowledge/superpowers-skills.md` | Skills detallados donde aplicarían mejoras (5, 7, 9) |

## Decisiones / Recomendaciones priorizadas

### Prioridad 1 (alto impacto, bajo riesgo)
1. **Crear sección "Harness Assumptions"** en CLAUDE.md documentando qué limitación codifica cada mecanismo y cuándo fue última vez validado
2. **Añadir acceptance criteria explícitos** en el dispatch de subagentes (Skill 5) — mejora review loop sin costo

### Prioridad 2 (alto impacto, requiere experimentación)
3. **Stress-testear HARD gates** — ejecutar 3-5 tareas reales con SOFT warnings en vez de HARD gates, documentar compliance
4. **Relajar `user_turns ≥ 3`** a `≥ 1` con SOFT warning — permitir brainstorm corto cuando scope es claro

### Prioridad 3 (mejora incremental)
5. **Añadir regla de context hygiene** — checkpoints explícitos en sesiones largas
6. **Consolidar tablas anti-racionalización** — reducir de 6+ a 2-3 bien focalizadas
7. **Checkpoint reviews para features XL** — review de coherencia global mid-implementation

### No actuar por ahora
8. **Evaluación subjetiva de calidad** — requiere calibración significativa del reviewer, posponer hasta tener datos de qué tipo de issues se escapan actualmente
9. **Eliminar scaffolding** — esperar resultados del stress-test (Prioridad 2) antes de eliminar cualquier gate

## Gaps detectados

| Documento | Sección | Gap | Severidad |
|-----------|---------|-----|-----------|
| `CLAUDE.md` | Workflow Engine | No documenta qué asunción de modelo codifica cada gate | Medium |
| `CLAUDE.md` | Entire | No hay schedule de review del harness cuando cambia el modelo base | Medium |
| `CLAUDE.md` | Skill 9 Verification | Solo verifica evidencia mecánica (tests/lint), no calidad arquitectónica | Low |
| `CLAUDE.md` | Skill 5 Subagent-Driven Dev | No incluye acceptance criteria en dispatch de subagentes | Low |
| `CLAUDE.md` | Context Management | No hay estrategia de context hygiene para sesiones largas | Medium |

## Contexto adicional

### Sobre el costo del artículo vs nuestro contexto

El artículo reporta costos significativos: $200 / 6 horas para el harness completo vs $9 / 20 minutos para single-agent. Nuestro harness (CLAUDE.md + hooks + session-state) añade overhead pero mucho menor porque:
- No tenemos 3 agentes permanentes corriendo en paralelo
- Nuestros subagentes son on-demand (solo en Skill 5 y reviews)
- El overhead principal es cognitivo (CLAUDE.md extenso) y de latencia (fases obligatorias), no de tokens

### Modelo de evolución propuesto

```
Harness component lifecycle:

HARD gate → (stress-test OK) → SOFT warning → (validated) → Best practice → (obsolete) → Remove

Each transition requires:
- N=5 real tasks without the gate
- Compliance rate ≥ 90% → safe to relax
- Compliance rate < 70% → keep current level
```

Este modelo permite evolucionar el harness de forma data-driven en vez de especulativa.
