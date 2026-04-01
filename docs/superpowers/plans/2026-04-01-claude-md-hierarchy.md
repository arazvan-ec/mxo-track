# Plan: Reestructuración CLAUDE.md → Jerarquía por Directorio

**Spec:** `docs/superpowers/specs/2026-04-01-claude-md-hierarchy-design.md`
**Branch:** `claude/improve-claude-me-flow-OoY9D`
**Goal:** Descomponer CLAUDE.md monolítico (1993 líneas) en jerarquía de archivos por directorio (~400 líneas base), aplicando las 4 transformaciones (filosofía, mecánica, flujo, reglas integradas).

**Architecture:** Claude Code hierarchical CLAUDE.md loading — raíz siempre, subdirectorios según contexto.
**Single-phase:** Este es un refactor de documentación sin código PHP. v0 es ya production-quality porque no hay lógica que madurar — solo contenido que reescribir.

---

## File Structure

```
CLAUDE.md                          (rewrite — ~400 líneas narrativas)
AGENTS.md                          (new — ~150 líneas)
backend/CLAUDE.md                  (new — ~200 líneas)
backend/src/CLAUDE.md              (new — ~150 líneas)
backend/tests/CLAUDE.md            (new — ~50 líneas)
docs/CLAUDE.md                     (new — ~80 líneas)
docs/notes.md                      (new — notas migradas)
docs/backlog.md                    (new — backlog migrado)
.claude/README.md                  (new — ~200 líneas referencia técnica)
```

---

## Tasks

### Task 1: Crear archivos de migración (notas + backlog)
- [ ] Crear `docs/notes.md` con el contenido de las líneas 1-12 del CLAUDE.md actual
- [ ] Crear `docs/backlog.md` con el contenido de la sección "Backlog Arquitectónico" del CLAUDE.md actual
- [ ] Commit: `docs: migrate personal notes and architectural backlog from CLAUDE.md`

### Task 2: Crear `.claude/README.md` (referencia técnica del workflow engine)
- [ ] Escribir el archivo con el contenido técnico que sale del CLAUDE.md raíz:
  - session-state.json schema completo (con todos los campos y comentarios)
  - Gates detallados (3 tablas: por flow, full-flow por archivo, debug-flow)
  - Validators (tabla de evidencia por fase)
  - Deviation mode
  - Harness Assumptions & Evolution (inventario, niveles, modelo de evolución)
  - Session Context automático (qué provee el hook, consulta manual)
  - Status Line (formato, reglas, ejemplos)
  - Problemas conocidos del tooling (tool_use ids, prefill, subagent infra)
- [ ] Commit: `docs: create .claude/README.md with workflow engine reference`

### Task 3: Crear `backend/CLAUDE.md` (convenciones y arquitectura)
- [ ] Escribir el archivo con narrativa (filosofía + mecánica + flujo):
  - "Por qué estas reglas existen" — filosofía de los dos mundos (DDD puro vs pragmático)
  - Arquitectura DDD integrada con bounded contexts y reglas de placement
  - SOLID integrado como guía de diseño (no lista de principios)
  - Design Patterns integrado (proceso de decisión, patterns del codebase)
  - Convenciones PHP (strict_types, attribute mapping, etc.)
  - Documentación honesta
- [ ] Usar marcadores `<!-- GENERIC -->` y `<!-- PROJECT-SPECIFIC -->` para preparar extracción a plugin
- [ ] Commit: `docs: create backend/CLAUDE.md with architecture and conventions`

### Task 4: Crear `backend/src/CLAUDE.md` (reglas de implementación)
- [ ] Escribir el archivo con narrativa:
  - TDD (Skill 7 comprimida): iron law, red-green-refactor, racionalizaciones clave
  - Debugging sistemático (Skill 8 comprimida): 4 fases + pattern-wide
  - Ejecutar planes (Skill 4 comprimida): load → review → execute → capture
  - Critical Patterns: entity identity, multi-tenancy, role hierarchy, constructor changes
  - Anti-patterns DDD
- [ ] Commit: `docs: create backend/src/CLAUDE.md with implementation rules`

### Task 5: Crear `backend/tests/CLAUDE.md` (reglas de testing)
- [ ] Escribir el archivo con narrativa:
  - Filosofía: tests como especificación ejecutable
  - Estructura: Unit vs Functional vs Domain
  - Fixtures y factories
  - Verificación: suite completa antes de claims
- [ ] Commit: `docs: create backend/tests/CLAUDE.md with testing conventions`

### Task 6: Crear `docs/CLAUDE.md` (reglas de documentación)
- [ ] Escribir el archivo:
  - Filosofía: documentación describe lo que ES
  - Knowledge modules: tabla de referencia + freshness protocol
  - Features document: qué es, cuándo actualizar
  - Learning review (Skill 15 comprimida)
  - Execution logs y retrospectivas
- [ ] Commit: `docs: create docs/CLAUDE.md with documentation rules`

### Task 7: Crear `AGENTS.md` (instrucciones para subagentes)
- [ ] Escribir el archivo:
  - Output limits (300 líneas)
  - Skill 5 completa (Subagent-Driven Dev)
  - Skill 6 completa (Dispatching Parallel Agents)
  - Skill 10 completa (Receiving Code Review)
  - Skill 11 completa (Requesting Code Review)
  - Problemas conocidos de infraestructura
- [ ] Commit: `docs: create AGENTS.md with subagent instructions`

### Task 8: Reescribir `CLAUDE.md` raíz (~400 líneas narrativas)
- [ ] Reescribir completo siguiendo la estructura narrativa del spec:
  - **Qué es este proyecto** — overview, tech stack, commands
  - **Cómo piensa Claude en este repo** — fresh context, manifest, session-state, flujo de interacción, QA loop (brainstorming con Skill 2 comprimida), planificación (Skill 3 comprimida), verificación (Skill 9 comprimida), cierre (Skill 12 comprimida)
  - **Triggers de skills de subagentes** — Skills 5, 6, 10, 11 (~5-15 líneas cada una)
  - **Skill 1 (Using Superpowers)** — trigger + principio
  - **Workflow engine resumen** — 1 tabla compacta de gates, referencia a `.claude/README.md`
  - **Commits y push** — atomic commits con filosofía
  - **Gobernanza** — regla adaptada a jerarquía
  - **Knowledge modules** — tabla comprimida
- [ ] Verificar que TODA sección del CLAUDE.md original tiene destino (checklist de trazabilidad)
- [ ] Commit: `refactor: rewrite CLAUDE.md as narrative hierarchy (~400 lines from 1993)`

### Task 9: Verificación
- [ ] Verificar que hooks siguen funcionando: `bash .claude/hooks/test-workflow-engine.sh`
- [ ] Verificar status line: `bash .claude/hooks/test-status-line.sh`
- [ ] Revisar que no hay contenido perdido: comparar secciones originales vs destinos
- [ ] Contar líneas de cada archivo nuevo y verificar estimaciones
- [ ] Commit: `chore: verify hierarchy migration integrity`

### Task 10: Push final + manifest
- [ ] `make manifest`
- [ ] Push a branch
