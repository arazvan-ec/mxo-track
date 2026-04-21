---
type: process
tags: [workflow, memory, harness, approval-regex, knowledge-dir, tags, backfill, convention]
files_touched: [.claude/hooks/user-prompt-state.sh, .claude/hooks/pattern-audit.sh, .claude/hooks/test-pattern-audit.sh, docs/knowledge/superpowers-skills.md, scripts/suggest-tags.sh, scripts/test-suggest-tags.sh]
patterns: [harness-memory-separation, workflow-script-conventions]
outcome: success
outcome_verified_at: null
regressions_later: []
pr_number: null
estimated_lines: 380
actual_lines: 520
duration_minutes: 60
consulted_in_future: []
---

# Execution Log — 2026-04-21 — Memory/Harness PR3 (Regex + KDIR + Doc + Tag Backfill)

**Type:** process (workflow infrastructure)
**Branch:** `claude/improve-keyboard-shortcuts-Pnrqv`
**Spec:** `docs/superpowers/specs/2026-04-21-memory-harness-pr3.md`
**Plan:** `docs/superpowers/plans/2026-04-21-memory-harness-pr3.md`
**Context:** Tercera y última PR de la serie memory-harness. Cierra 4 follow-ups
identificados en retrospectivas de PR1/PR2: regex flexible, env var KNOWLEDGE_DIR,
documentación de convención emergente, tag backfill oportunístico.

## Summary

4 items en 1 wave paralelo (archivos disjuntos): regex de aprobación por decision-ID,
env var para tests aislados, sección "Workflow Script Conventions" en knowledge
module, script de tag backfill por keyword-matching. 14 nuevos tests, todos verdes.
61 de 88 execution logs adquirieron tags automáticamente.

### Phase: Brainstorming
- **Alternatives evaluated:** 3 approaches por cada item (ver spec)
- **Chosen approach:** decision-ID threshold ≥2, env var consistent naming,
  inline keyword table, filename-based inference
- **Past decisions consulted:** PR1 + PR2 execution logs y retrospectivas —
  identifican los gaps específicos que este PR cierra
- **Complexity estimate:** M — 4 items semi-independientes
- **Confidence:** high — patrones ya establecidos

### Phase: Planning
- **Task count:** 8 tareas en 3 waves
- **Files affected:** 2 nuevos + 4 modificados + 61 backfilled
- **Time estimate:** 60 min
- **Risk assessment:** low — append-only backfill, tests cover nuevos paths

### Phase: Implementation
- **Actual time:** ~60 min
- **Blockers hit:**
  - Brainstorm validator rechazó el primer spec: faltaban keywords canónicos
    ("Approach", "Alternativa", "Trade-off"). Ajusté la sección de alternativas
    evaluadas a formato explícito con los 3 headers esperados. **Process insight:**
    el validator refuerza formato pero no contenido; si sabes los keywords puedes
    pasar con spec mediocre. No es fallo del validator — es por diseño (HARD gate
    debe ser determinístico), pero vale la pena documentarlo.
  - Plan validator rechazó advance a implementation porque el archivo del plan
    no existía aún (Write del plan estaba bloqueado por el mismo issue del spec).
    Re-ordenado: fix spec → advance brainstorm→planning → write plan → advance
    planning→implementation.
- **Plan deviations:** ninguna significativa. El tag backfill actualizó 61 logs
  vs. estimación de ~50, diff manejable.

### Phase: Verification
- **Tests:** 14/14 new (suggest-tags), 4/4 refactored (pattern-audit using env
  var), 39/39 regression (consult), 23/23 regression (mark-verified +
  link-regression), 14/14 regression (phase-advance)
- **Regression:** workflow-engine 23/29 (6 failures pre-existentes desde PR1,
  sin cambio)
- **Lint:** skipped
- **Smoke:** consult.sh stats muestra 11 tags con ≥3 ocurrencias post-backfill;
  pattern-audit silent (todos ya mencionados en algún knowledge module)

### Phase: Retrospective

#### Estimate accuracy

Estimado 380 líneas, actual 520 (+37%). Gap principalmente en tests (+80 líneas
por edge cases adicionales) y en la sección de Workflow Script Conventions
(+40 líneas con template de ejemplo). Drift aceptable.

#### Process gap

El brainstorm validator requiere keywords específicos en el spec
("Approach|Alternativa|Trade-off|Problema|Ventaja|Desventaja|Opcion"). Sin
estos, HARD gate bloquea. Dos implicaciones:

1. **Observación:** Cualquier spec que quiera pasar el gate debe usar estos
   exact keywords aunque la sección se llame de otra forma. Genera disonancia
   entre naming preferido y naming aceptado por el gate.
2. **No es bug:** determinístico por diseño. La alternativa (LLM-validated
   spec quality) sería no-determinística y contradice el propósito del HARD
   gate (mechanical enforcement).

**Lesson:** al escribir specs, incluir "Alternativas evaluadas" con "Approach A/B",
"Ventaja/Desventaja", "Trade-off" explícitos. Es convención, no restricción.

#### Emergent patterns

- **Pattern: keyword-table inference (PR3).** El `suggest-tags.sh` usa un
  diccionario inline para mapear filename keywords a tags. Primera ocurrencia
  consciente del patrón "diccionario inline + substring match" en este repo.
  Si aparece 2×+ (ej. si algún día mapeamos commit types a categorías, o
  branch names a teams), refactor a tabla externa JSON/YAML.
- **Pattern: spec-validator keyword compliance (PR3).** Segunda ocurrencia
  (primera fue en PR2 con retrospective-validator requiriendo `## Lessons` o
  `## Retrospectiva`). El pattern emergente: **validators que buscan keywords
  específicos en markdown**. Esto es una convención implícita de documentación
  del workflow engine; si aparece 3ª vez, documentar en el mismo superpowers-
  skills.md la lista de keywords-expected por validator.

## Lessons

1. **Filename es suficiente señal para taggeo básico.** No se necesita parsear
   el body de los logs para obtener tags útiles. El keyword matching en el
   slug del filename produjo 61 de 88 logs taggueados — 69% de cobertura con
   heurística trivial. El 31% restante son logs con nombres genéricos
   (e.g., "2026-03-20-business-decisions-implementation.md") donde el body
   sería más útil, pero el costo de parsearlo no vale el retorno marginal.

2. **pattern-audit vs. substring grep.** Actualmente pattern-audit considera
   "tag graduado" si la palabra aparece como substring en ALGUNA línea de
   ALGÚN knowledge module. Esto es demasiado permisivo — "route" aparece en
   route-optimization.md (esperado), pero "filter" aparece como "filter-based"
   en api-surface.md y cuenta como graduado aunque no esté documentado como
   pattern específico. **Fix potencial PR4:** grep más estricto (heading exacto,
   o frontmatter-tag reference). No crítico ahora.

3. **Decision-ID regex es backwards-compatible.** El nuevo check solo AGREGA
   casos de aprobación; el regex original sigue corriendo antes, y el rejection
   regex sigue corriendo después. Cero cambios observables para flujos que no
   usan IDs.

## Files changed

- `.claude/hooks/user-prompt-state.sh` (+8)
- `.claude/hooks/pattern-audit.sh` (+1 / -1)
- `.claude/hooks/test-pattern-audit.sh` (-75 / +85, refactored to use env var)
- `docs/knowledge/superpowers-skills.md` (+76)
- `scripts/suggest-tags.sh` (+140, new)
- `scripts/test-suggest-tags.sh` (+140, new)
- `docs/superpowers/specs/2026-04-21-memory-harness-pr3.md` (+110, new)
- `docs/superpowers/plans/2026-04-21-memory-harness-pr3.md` (+55, new)
- `docs/superpowers/execution-logs/*.md` (+61 / -61, auto-backfilled tags)

## Serie memory-harness completa (PR1 + PR2 + PR3)

Los 6 items del análisis del tweet de Harrison Chase están cerrados:
1. ✅ Compaction contract (PR1) — `.claude/README.md` sección
2. ✅ Frontmatter + consult.sh (PR1) — 86 logs queryables
3. ✅ Surfacing proactivo (PR2) — session-start extension con ≤5 threshold
4. ✅ Pattern-audit automático (PR2+PR3) — hook + env var + graduado a doc
5. ✅ Outcome tracking (PR2) — manual + auto-detect en session-start
6. ✅ Portabilidad (ya resuelta por arquitectura markdown)

Plus follow-ups completos:
- ✅ user_approved survive new-day resume (PR2)
- ✅ Regressions bidireccional (PR2)
- ✅ Decision-ID approval regex (PR3)
- ✅ KNOWLEDGE_DIR env var (PR3)
- ✅ Workflow Script Conventions graduado (PR3)
- ✅ Tag backfill automático (PR3)

**Pendiente opcional:** mergear el branch `claude/improve-keyboard-shortcuts-Pnrqv`
a main vía PR.
