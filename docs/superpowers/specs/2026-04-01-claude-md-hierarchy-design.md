# Spec: Reestructuración CLAUDE.md → Jerarquía por Directorio

**Fecha:** 2026-04-01
**Approach:** B+ (Narrative Flow + Skills Semi-comprimidas + Jerarquía por Directorio)
**Objetivo:** Reducir tokens en contexto ~60%, mejorar comprensión con las 4 transformaciones (filosofía, mecánica, flujo, reglas integradas), y aprovechar la carga jerárquica nativa de Claude Code.

---

## Contexto: Cómo Claude Code carga instrucciones

Claude Code carga archivos `CLAUDE.md` de forma jerárquica:
- `CLAUDE.md` en raíz → se carga **siempre** en toda conversación
- `CLAUDE.md` en subdirectorios → se carga **solo cuando se trabaja en archivos de ese directorio**
- `AGENTS.md` → instrucciones específicas para **subagentes** (Agent tool)

Esto permite distribuir instrucciones según contexto, reduciendo tokens cargados por tarea.

## Principios de diseño (las 4 transformaciones)

### 1. Filosofía (por qué)
Cada sección empieza con 1-2 frases de **por qué existe** el mecanismo. No "mandatory" sin razón.

### 2. Mecánica (cómo funciona)
Explicar la mecánica interna: "session-state.json persiste porque la ventana de contexto se compacta y el modelo pierde el estado de qué fases completó."

### 3. Flujo (qué alimenta qué)
Mostrar conexiones: "el spec → alimenta el plan → alimenta los acceptance criteria → el reviewer los verifica."

### 4. Reglas integradas
SOLID, DDD, Anti-Omission dejan de ser secciones separadas y se integran donde aplican.

---

## Existing Functionality Inventory

### Contenido actual del CLAUDE.md (1993 líneas)

| Bloque | Líneas aprox | Destino propuesto |
|--------|-------------|-------------------|
| Notas personales (optimizar ruta, demo cliente, resume) | 12 | Eliminar o mover a docs/notes.md |
| Project Overview + Tech Stack + Commands | 28 | `CLAUDE.md` raíz |
| SOLID Principles (5 principios con ejemplos) | 60 | `backend/CLAUDE.md` (integrado en narrativa de diseño) |
| DDD Architecture | 48 | `backend/CLAUDE.md` (integrado en narrativa de arquitectura) |
| Design Patterns | 55 | `backend/CLAUDE.md` (integrado en narrativa de diseño) |
| Conventions | 20 | `backend/CLAUDE.md` |
| Atomic Commits & Push | 50 | `CLAUDE.md` raíz (aplica a todo) |
| No-Redundancia | 10 | `CLAUDE.md` raíz (meta-principio) |
| Pre-Exploration Gate + tablas mapeo | 100 | `CLAUDE.md` raíz (comprimido) + tabla completa a `docs/knowledge/` |
| Escalabilidad en Decisiones | 20 | `CLAUDE.md` raíz |
| Flujo Obligatorio (clasificación + 5 flows) | 110 | `CLAUDE.md` raíz |
| Workflow Engine (session-state, gates, validators) | 150 | `CLAUDE.md` raíz (comprimido) + referencia completa a `.claude/README.md` |
| Harness Assumptions | 40 | `.claude/README.md` |
| Session Context | 28 | `.claude/README.md` |
| Context Hygiene | 10 | `CLAUDE.md` raíz |
| Status Line | 45 | `.claude/README.md` |
| Feedback Capture | 30 | `CLAUDE.md` raíz (comprimido) |
| Learning Loop | 38 | `CLAUDE.md` raíz (integrado en flujo) |
| Critical Patterns | 30 | `backend/src/CLAUDE.md` |
| Anti-Omission Rule | 32 | `CLAUDE.md` raíz (integrado en brainstorming) |
| Knowledge Modules table | 35 | `CLAUDE.md` raíz (comprimida) |
| Governance rule | 15 | `CLAUDE.md` raíz |
| Features Document | 4 | `docs/CLAUDE.md` |
| Backlog Arquitectónico | 48 | `docs/backlog.md` |
| **Skills inline (14 skills, ~900 líneas)** | 900 | Semi-comprimidas en archivo correspondiente |
| Problemas conocidos (3 bloques) | 80 | `.claude/README.md` |

### Skills — Decisión por skill

| Skill | Líneas actuales | Destino | Formato inline |
|-------|----------------|---------|----------------|
| 1. Using Superpowers | 55 | `CLAUDE.md` raíz | Trigger + principio (~10 líneas) |
| 2. Brainstorming | 90 | `CLAUDE.md` raíz | Trigger + checklist compacto (~25 líneas) |
| 3. Writing Plans | 70 | `CLAUDE.md` raíz | Trigger + estructura key (~15 líneas) |
| 4. Executing Plans | 40 | `backend/src/CLAUDE.md` | Trigger + proceso core (~10 líneas) |
| 5. Subagent-Driven Dev | 80 | Trigger en raíz (~5 líneas) + `AGENTS.md` completo | Raíz sabe cuándo usarla, AGENTS.md tiene el cómo |
| 6. Dispatching Parallel | 40 | Trigger en raíz (~5 líneas) + `AGENTS.md` completo | Raíz sabe cuándo usarla, AGENTS.md tiene el cómo |
| 7. TDD | 70 | `backend/src/CLAUDE.md` | Iron law + red-green-refactor (~20 líneas) |
| 8. Systematic Debugging | 80 | `backend/src/CLAUDE.md` | 4 fases + pattern-wide (~25 líneas) |
| 9. Verification | 40 | `CLAUDE.md` raíz | Iron law + gate function (~15 líneas) |
| 10. Receiving Code Review | 35 | Proceso core en raíz (~15 líneas) + `AGENTS.md` completo | Hilo principal recibe reviews, no solo subagentes |
| 11. Requesting Code Review | 25 | Proceso core en raíz (~15 líneas) + `AGENTS.md` completo | Hilo principal solicita reviews |
| 12. Finishing Branch | 50 | `CLAUDE.md` raíz | Proceso core (~15 líneas) |
| 13. Git Worktrees | 25 | Eliminar (no se usa activamente) |
| 14. Writing Skills | 60 | Eliminar (meta-skill, no operativo) |
| 15. Learning Review | 35 | `docs/CLAUDE.md` | Trigger + proceso (~10 líneas) |

**Referencia completa de todas las skills:** permanece en `docs/knowledge/superpowers-skills.md` (ya existe, 778 líneas).

---

## Omission Decisions

| Element | Decision | Justification |
|---------|----------|---------------|
| Notas personales (líneas 1-12) | Move | Mover a `docs/notes.md` para conservar |
| Skill 13 (Git Worktrees) | Omit | No se usa activamente en el proyecto |
| Skill 14 (Writing Skills) | Omit | Meta-skill para crear skills, no operativa |
| Tablas anti-racionalización (5 tablas) | Transform | Consolidar en 1 tabla en raíz, eliminar las de skills individuales (la filosofía las reemplaza) |
| Tabla completa Pre-Exploration Gate | Transform | Versión compacta inline, tabla completa en knowledge module |
| session-state.json schema completo | Transform | Referencia en `.claude/README.md`, solo campos key inline |
| Gates tables (3 tablas) | Transform | 1 tabla resumen inline, detalle en `.claude/README.md` |
| Problemas conocidos (tool_use ids, prefill, subagent infra) | Move | A `.claude/README.md` (son problemas del tooling, no del proyecto) |

---

## Estructura de archivos propuesta

### 1. `CLAUDE.md` (raíz) — ~400 líneas

**Se carga:** SIEMPRE

**Contenido narrativo (flujo natural de trabajo):**

```markdown
# mxo-track — Guía para Claude Code

## Qué es este proyecto
[Project overview, tech stack, commands — 30 líneas]

## Cómo piensa Claude en este repo

### El contexto como recurso escaso
[Filosofía: por qué fresh context importa. Mecánica: Claude Code carga 
CLAUDE.md jerárquicamente — raíz siempre, subdirectorio solo cuando 
trabajas ahí. Flujo: manifest → respuesta directa, si no está → buscar 
→ actualizar manifest. Regla integrada: Pre-Exploration Gate comprimido]

### Session-state: memoria que sobrevive a la compactación  
[Filosofía: por qué existe. Mecánica: Claude Code compacta mensajes 
antiguos, el modelo pierde estado. session-state.json es la memoria 
externa. Flujo: hooks lo resetean al inicio de sesión, Claude lo actualiza 
por fase, los gates lo leen para validar]

### El flujo de toda interacción
[Clasificación: micro/light/debug/full/explore — tabla compacta.
Cada flow descrito con: por qué existe, qué produce, qué alimenta.
Full-flow detallado: consult → brainstorm → plan → implement → verify → 
capture → retrospective → finalize. 
Scope change detection integrado aquí.]

### El QA loop: por qué brainstorming no es burocracia
[Filosofía: brainstorming es QA preventivo — más barato que debug.
Checklist compacto de Skill 2 (~25 líneas).
Anti-Omission integrada aquí: inventariar antes de diseñar.]

### Planificación: v0 → Mature
[Filosofía: validar concepto antes de arquitectura.
Skill 3 comprimida: estructura del plan, dos fases.]

### Verificación: evidencia antes de claims
[Skill 9 comprimida: iron law + gate function]

### Cerrar el ciclo: branch → capture → learn
[Skill 12 comprimida: verify → merge → retrospective.
Learning loop integrado: execution log → decisions → próximo brainstorm.
Feedback capture integrado.]

## Workflow engine (resumen)
[Cómo funciona el enforcement mecánico — 20 líneas.
Tabla resumen de gates (1 tabla compacta).
Referencia: "Detalle completo en .claude/README.md"]

## Commits y push
[Atomic commits con filosofía: por qué push frecuente 
(resiliencia ante compactación). Formato. Anti-patterns.]

## Gobernanza
[Regla de gobernanza de CLAUDE.md adaptada a jerarquía.
Knowledge modules table (comprimida).]
```

### 2. `backend/CLAUDE.md` — ~200 líneas

**Se carga:** Al trabajar en `backend/`

**Contenido:**

```markdown
# Backend — Convenciones y Arquitectura

## Por qué estas reglas existen
[Filosofía: este backend tiene dos mundos — contextos críticos (DDD puro)
y pragmáticos (Symfony). Las reglas aseguran que el código nuevo en 
contextos críticos no acumule deuda técnica.]

## Arquitectura: dos mundos
[DDD Architecture integrado con filosofía.
Bounded contexts: cuáles son críticos, cuáles pragmáticos.
Reglas de placement: dónde va cada cosa.
Flujo: "código nuevo en contexto crítico → Domain/ como POPO, 
interface en Domain/, implementación en Infrastructure/"]

## Principios de diseño
[SOLID integrado con filosofía y ejemplos del proyecto.
No como lista de 5 principios — sino como guía: "cuando diseñes una 
clase, pregúntate..." con los principios como respuestas.
Design patterns: proceso de decisión, patterns existentes en el codebase.]

## Convenciones PHP
[strict_types, attribute mapping, naming_strategy, routing, DTOs, 
ApiErrorResponder — compacto]

## Documentación honesta
[Regla de estado actual vs aspiracional]
```

### 3. `backend/src/CLAUDE.md` — ~150 líneas

**Se carga:** Al editar código en `backend/src/`

**Contenido:**

```markdown
# Escribir código — Reglas de implementación

## El ciclo TDD
[Filosofía: si no viste el test fallar, no sabes si testea lo correcto.
Skill 7 comprimida: iron law, red-green-refactor, racionalizaciones clave.
Flujo: test rojo → código mínimo → test verde → refactor → commit]

## Debugging sistemático
[Filosofía: fix de síntoma = fracaso. Root cause primero.
Skill 8 comprimida: 4 fases + pattern-wide investigation.
Flujo: reproduce → trace → hypothesis → fix → test]

## Ejecutar planes
[Skill 4 comprimida: load → review → execute → capture]

## Critical Patterns
[Entity identity (BIGINT + ULID), multi-tenancy, role hierarchy,
constructor signature changes — tal cual están hoy]

## Trampas comunes
[Anti-patterns DDD, EntityManager directo, lifecycle callbacks]
```

### 4. `backend/tests/CLAUDE.md` — ~50 líneas

**Se carga:** Al editar tests en `backend/tests/`

**Contenido:**

```markdown
# Tests — Convenciones y Patterns

## Filosofía
[Los tests son la especificación ejecutable. Si un test pasa sin que 
hayas implementado nada, no testea lo correcto.]

## Estructura
[Unit vs Functional vs Domain. Dónde va cada tipo.]

## Fixtures y Factories
[Patterns de fixtures del proyecto. Factory approach.]

## Verificación
[Siempre correr suite completa antes de claim. Lint + tests.]
```

### 5. `docs/CLAUDE.md` — ~80 líneas

**Se carga:** Al editar documentación en `docs/`

**Contenido:**

```markdown
# Documentación — Reglas

## Filosofía
[La documentación describe lo que ES, no lo que debería ser.]

## Knowledge modules
[Tabla de referencia: qué module leer según subsistema.
Freshness protocol.]

## Features document
[FEATURES.md: qué es, cuándo actualizar]

## Learning review
[Skill 15 comprimida: cuándo hacer review, proceso, output]

## Execution logs y retrospectivas
[Templates, cuándo capturar, formato]
```

### 6. `AGENTS.md` — ~150 líneas

**Se carga:** Por el hilo principal cuando despacha subagentes, y por los subagentes mismos.

**Contenido:**

```markdown
# Instrucciones para subagentes

## Output limits
[300 líneas máximo. Preferir escribir a archivo.]

## Subagent-Driven Development
[Skill 5 completa: process, model selection, handling status, sprint contract]

## Dispatching Parallel Agents
[Skill 6 completa: when to use, pattern, agent prompt structure]

## Receiving Code Review (para reviewers)
[Skill 10 completa: response pattern, forbidden responses, pushback]

## Requesting Code Review
[Skill 11 completa: when, how, acting on feedback]

## Problemas conocidos de infraestructura
[Los 3 bloques de problemas conocidos (tool_use ids, prefill, subagent infra)]
```

### 7. `.claude/README.md` — ~200 líneas

**No se carga automáticamente** — es referencia para consultar cuando se necesita detalle del workflow engine.

**Contenido:**

```markdown
# Workflow Engine — Referencia Técnica

## session-state.json schema completo
[El JSON schema con todos los campos y comentarios]

## Gates detallados
[Las 3 tablas de gates: por flow, full-flow por archivo, debug-flow]

## Validators
[Tabla completa de evidencia por fase]

## Deviation mode
[Cómo activar, qué pasa]

## Harness Assumptions & Evolution
[Inventario de asunciones, niveles, modelo de evolución, schedule]

## Session Context automático
[Qué provee el hook, consulta manual]

## Status Line
[Formato, reglas, ejemplos]

## Problemas conocidos del tooling
[tool_use ids, prefill, subagent infra — referencia]
```

---

## Tokens en contexto por tipo de tarea (estimación)

| Tarea | Archivos cargados | Líneas | vs. actual (1993) |
|-------|-------------------|--------|-------------------|
| Pregunta informativa | raíz (400) | ~400 | **-80%** |
| Editar documentación | raíz (400) + docs/ (80) | ~480 | **-76%** |
| Implementar feature | raíz (400) + backend/ (200) + backend/src/ (150) | ~750 | **-62%** |
| Escribir tests | raíz (400) + backend/ (200) + backend/tests/ (50) | ~650 | **-67%** |
| Subagente implementador | raíz (400) + AGENTS.md (150) + backend/src/ (150) | ~700 | **-65%** |
| Debug bug | raíz (400) + backend/ (200) + backend/src/ (150) | ~750 | **-62%** |

---

## Impacto en hooks existentes

Los hooks en `.claude/hooks/` no necesitan cambios — validan `session-state.json` y paths de archivos, no el contenido de CLAUDE.md. La jerarquía de CLAUDE.md es transparente para ellos.

| Hook | Impacto |
|------|---------|
| `session-start.sh` | Sin cambios |
| `workflow-engine.sh` | Sin cambios (valida session-state, no CLAUDE.md) |
| `workflow-status-line.sh` | Sin cambios |
| `post-commit-validator.sh` | Sin cambios |
| `post-push-validator.sh` | Sin cambios |
| `pre-push-gate.sh` | Sin cambios |

---

## Plan de migración

### Orden de ejecución

1. Crear branch `claude/improve-claude-me-flow-OoY9D` (ya existe)
2. Crear archivos nuevos primero (no romper nada):
   - `backend/CLAUDE.md`
   - `backend/src/CLAUDE.md`
   - `backend/tests/CLAUDE.md`
   - `docs/CLAUDE.md`
   - `AGENTS.md`
   - `.claude/README.md`
3. Reescribir `CLAUDE.md` raíz (reducir a ~400 líneas narrativas)
4. Verificar que hooks siguen funcionando
5. Verificar que el flujo de trabajo funciona (probar con una tarea real)

### Riesgo principal

**Pérdida de reglas en la transición.** Mitigación: 
- Cada sección movida se marca con comentario `<!-- FROM: CLAUDE.md líneas X-Y -->`
- Checklist de verificación post-migración: cada sección del CLAUDE.md original tiene destino asignado
- La tabla de inventario de este spec es el mapa de trazabilidad

### Qué NO cambia

- `docs/knowledge/superpowers-skills.md` — permanece como referencia completa de skills
- `.claude/hooks/*` — sin cambios
- `.claude/session-state.json` — sin cambios en schema
- `.claude/settings.json` — sin cambios
- `docs/knowledge/*.md` — sin cambios (son referencia bajo demanda, ya están bien)

---

## Preparación para plugin futuro

Los CLAUDE.md jerárquicos se escriben con marcadores `<!-- GENERIC -->` y `<!-- PROJECT-SPECIFIC -->` para facilitar la extracción futura como plugin distribuible.

**Repo destino del plugin:** `arazvan-ec/yader`

Después de validar la jerarquía en mxo-track, extraer la capa genérica al repo del plugin será mecánico: copiar estructura, reemplazar PROJECT-SPECIFIC con placeholders, crear skill de generación.

## Archivos adicionales

| Archivo | Contenido |
|---------|-----------|
| `docs/notes.md` | Notas personales migradas de las líneas 1-12 del CLAUDE.md actual |
| `docs/backlog.md` | Backlog arquitectónico migrado del CLAUDE.md actual |
