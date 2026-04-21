---
type: process
tags: [workflow, memory, harness, graduation, curation, registry, pattern-audit, knowledge-modules]
files_touched: [docs/knowledge/_graduations.yaml, scripts/graduate.sh, scripts/test-graduate.sh, scripts/validate-graduations.sh, scripts/test-validate-graduations.sh, .claude/hooks/pattern-audit.sh, .claude/hooks/test-pattern-audit.sh, scripts/suggest-tags.sh, scripts/test-suggest-tags.sh, docs/knowledge/ui-frontend.md, docs/knowledge/superpowers-skills.md, docs/knowledge/domain-model.md, docs/knowledge/api-surface.md]
patterns: [harness-memory-separation, workflow-script-conventions]
outcome: success
outcome_verified_at: null
regressions_later: []
pr_number: null
estimated_lines: 720
actual_lines: 1376
duration_minutes: 60
consulted_in_future: []
---

# Execution Log — 2026-04-21 — Memory/Harness PR4 (Strict Graduation Registry + Curation)

**Type:** process (workflow infrastructure + knowledge curation)
**Branch:** `claude/view-plan-progress-ddWZc`
**Spec:** `docs/superpowers/specs/2026-04-21-memory-harness-pr4.md`
**Plan:** `docs/superpowers/plans/2026-04-21-memory-harness-pr4.md`
**Context:** Cuarta PR de la serie memory-harness. Cierra el gap principal
identificado en mi análisis de la serie: pattern-audit tenía falsos negativos
silenciosos por substring-match en knowledge docs. Reemplaza detección
heurística por registro explícito (`_graduations.yaml`) + curación de 13
tags/patterns pendientes.

## Summary

Tres cambios principales en 3 waves:
1. **Foundation**: registro YAML + `graduate.sh` helper + `validate-graduations.sh`
   drift detector, con 16+6 tests.
2. **Consumers**: refactor de `pattern-audit.sh` y `suggest-tags.sh` para leer
   del registro YAML. Cierra la ironía PR3 de `suggest-tags.sh` violando su
   propia graduated convention. 7+17 tests.
3. **Curation**: 8 secciones nuevas en 4 knowledge modules — todas las 13 tags
   y 1 pattern pendientes graduadas. `pattern-audit` ahora silencioso sobre
   el corpus real (detecta correctamente "0 candidatos").

118/118 tests verdes (39 consult + 7 pattern-audit + 17 suggest-tags +
16 graduate + 6 validator + 9 mark-verified + 10 link-regression + 14
phase-advance). workflow-engine 23/29 (6 pre-existentes desde PR1).

### Phase: Brainstorming
- **Alternatives evaluated per item:**
  - Matching semantics: A (heading strict) vs **B (YAML registry, elegido)** vs C (híbrido). Ventaja decisiva: curación O(1) por tag.
  - Format: **A (un solo file, elegido)** vs B (dos files) vs C (tres files). Ventaja: unifica pattern-audit + suggest-tags.
  - Curación: **A (full, elegido)** vs B (solo pointer-only) vs C (graduación pasiva). Trade-off: +200 líneas de doc vs audit silent post-PR.
  - Anti-drift: **A (combinación 4 controles, elegido)** vs B (solo disciplina). Ventaja: operación atómica vía graduate.sh elimina "olvido".
- **Past decisions consulted:** spec+log de PR1, PR2, PR3 de memory-harness
- **Complexity estimate:** M (4 waves, 13 tareas, ~720 líneas)
- **Confidence:** high — patrones establecidos en PR1-3

### Phase: Planning
- **Task count:** 13 tareas en 5 waves, 3 commits lógicos + 1 manifest
- **Files affected:** 5 new + 8 edit
- **Time estimate:** 60-90 min
- **Risk:** low-medium (YAML parsing en bash awk)

### Phase: Implementation
- **Actual time:** ~60 min
- **Blockers hit:**
  - Primera versión del parser awk usaba `getline` dentro del block, que
    consumía líneas y saltaba entradas consecutivas en el YAML. El test T2c
    falló (missing-module no reportado). Fix: state machine con accumulator
    (entry_name, entry_module, entry_section) + flush() function. Solución
    predictible sin getline.
  - Test T9 (graduate.sh) asumía que consult.sh devolvía count para
    `glass-overlay` pero el fake consult.sh no lo incluía → exit 2 en vez de
    exit 1. Añadí glass-overlay al fake stats. Trivial.
- **Plan deviations:**
  - El plan mencionaba "test-graduations-validator.sh" (1 script). Separé en
    `validate-graduations.sh` (reutilizable, llamable desde pre-push/CI) +
    `test-validate-graduations.sh` (tests). Arquitectura más limpia sin coste.

### Phase: Verification
- **Tests nuevos:** 16 (graduate) + 6 (validator) = 22 tests
- **Tests refactorizados:** pattern-audit 4→7, suggest-tags 14→17 (+6)
- **Regression:** 39 consult + 9 mark-verified + 10 link-regression + 14 phase-advance = 72 unchanged (verdes)
- **Workflow-engine:** 23/29 (6 pre-existentes desde PR1, sin cambio)
- **Smoke tests:**
  - `suggest-tags.sh --dry-run` sobre 89 logs reales: 0 cambios (backwards compat OK)
  - `pattern-audit.sh` sobre corpus real: silencioso (0 candidatos; antes 2 reportados → 13+ reales ocultos por substring fail)
  - `validate-graduations.sh` sobre registro real: valid

### Phase: Retrospective

#### Estimate accuracy

Estimado 720 líneas, actual 1376 (+91%). Gap principalmente en:
- **Tests adicionales**: plan mencionaba "test-graduate ~10, validator ~5"; real fue 16+6 con edge cases añadidos durante TDD
- **Curación**: plan estimó "8 secciones × 5-10 líneas = ~70 líneas"; real ~130 con headers + pointers + log refs
- **graduate.sh**: estimado 50 líneas, real 115 (validaciones robustas)

Drift aceptable dado que la calidad mejoró (no solo más líneas, más cobertura).

#### Process gap

1. **El plan auto-parseado por phase-advance reportó "2 waves, 17 tareas"**
   cuando el plan real tiene 5 waves, 13 tareas (usando headers `### Wave N:`).
   El parser probablemente mira `[parallel]` markers y cuenta tareas como
   bullets (`- **1a:`). Inspeccionar `plan-progress.sh init` en futuro PR
   si afecta la tracking. No crítico hoy.

2. **Pre-push gate de workflow-engine bloqueó push anterior** (no este PR,
   observado antes). Gate cumplió su función de forzar capture+retrospective.

#### Emergent patterns

- **Pattern: state-machine YAML parser en awk.** Segunda ocurrencia
  (primera fue en `consult.sh`). Patrón: tracking section + accumulator de
  entry + flush() function. Si aparece 3ª vez, graduar convención "bash-yaml
  parsing idiom" a `superpowers-skills.md`. Actualmente no vale la pena
  abstraer — los 2 casos son lo suficientemente distintos.
- **Pattern: single-source-of-truth registry para scripts independientes.**
  Nueva ocurrencia: `_graduations.yaml` sirve a `pattern-audit.sh`,
  `suggest-tags.sh`, `graduate.sh`, `validate-graduations.sh`. El pattern
  "multiple scripts share a single declarative source" es distinto del
  "single script with many CLI flags". Primera ocurrencia consciente.

## Lessons

1. **Silent false negatives son peor que ruidosos.** El pattern-audit pre-PR4
   reportaba 2 candidatos; parecía "working". La realidad era 14 candidatos
   reales con 11 ocultos por substring-match fail. Sistemas que dan signal
   positiva cuando están rotos son los más difíciles de detectar. **Generalización:**
   cuando un detector marque "0 problemas" persistentemente en un corpus que
   sabes que crece, auditar el detector mismo — el silencio puede ser avería.

2. **Curación es trabajo real que activa infra latente.** La inversión en
   pattern-audit + consult.sh en PR1-3 quedaba inerte sin contenido que
   apuntara. Escribir 8 secciones de 5-10 líneas (~130 líneas total) es
   lineal y el contenido tiene valor documentario independiente de la
   graduación. **La inversión en infra se amortiza con curación, no con más
   infra.**

3. **Registry explícito > heurística substring.** Tres alternativas evaluadas
   (heading strict, YAML registry, híbrido). La decisión a favor del registry
   se apoya en la observación de que **hallar la respuesta por regex sobre
   docs libres sufrirá siempre de falsos positivos**. Un registry explícito
   separa "¿es graduado?" (lookup O(1) en YAML) de "¿dónde está documentado?"
   (pointer dentro del registry). Acoplar esas dos preguntas en un grep era
   la raíz del gap.

## Files changed

- `docs/knowledge/_graduations.yaml` (+100, new)
- `scripts/graduate.sh` (+115, new)
- `scripts/test-graduate.sh` (+175, new)
- `scripts/validate-graduations.sh` (+85, new)
- `scripts/test-validate-graduations.sh` (+80, new)
- `.claude/hooks/pattern-audit.sh` (+60/-35, refactor)
- `.claude/hooks/test-pattern-audit.sh` (+70/-40, refactor)
- `scripts/suggest-tags.sh` (+15/-40, remove inline KEYWORD_TAGS)
- `scripts/test-suggest-tags.sh` (+30, new cases)
- `docs/knowledge/ui-frontend.md` (+24, 2 new sections)
- `docs/knowledge/superpowers-skills.md` (+67, 3 new sections)
- `docs/knowledge/domain-model.md` (+14, 1 new section)
- `docs/knowledge/api-surface.md` (+19, 1 new section)
- `docs/superpowers/specs/2026-04-21-memory-harness-pr4.md` (+230, new)
- `docs/superpowers/plans/2026-04-21-memory-harness-pr4.md` (+120, new)

## Serie memory-harness post-PR4

- ✅ PR1: schema + consult.sh + backfill
- ✅ PR2: surfacing + outcome tracking + regressions + user_approved fix
- ✅ PR3: approval regex + KNOWLEDGE_DIR env + workflow-script-conventions graduada + tag backfill
- ✅ **PR4: graduation registry + curación completa**

Sistema en steady state: pattern-audit silent (0 candidatos), corpus con
13/14 tags/patterns dominantes documentados, drift auto-detectado por
validator. Próximo candidato hipotético (PR5): shellcheck en pipeline
(skip crónico identificado en PR1-4 retros).
