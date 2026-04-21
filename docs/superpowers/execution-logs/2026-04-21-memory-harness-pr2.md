---
type: process
tags: [workflow, memory, harness, surfacing, pattern-audit, outcome-tracking, regressions, session-start]
files_touched: [.claude/hooks/pattern-audit.sh, .claude/hooks/session-start.sh, .claude/hooks/phase-advance.sh, .claude/hooks/post-commit-validator.sh, .claude/hooks/test-pattern-audit.sh, scripts/mark-verified.sh, scripts/link-regression.sh, scripts/test-mark-verified.sh, scripts/test-link-regression.sh, docs/superpowers/templates/execution-log-template.md]
patterns: [harness-memory-separation, proactive-surfacing, schema-first-indexing]
outcome: success
outcome_verified_at: null
regressions_later: []
pr_number: null
estimated_lines: 400
actual_lines: 717
duration_minutes: 90
consulted_in_future: []
---

# Execution Log — 2026-04-21 — Memory/Harness PR2 (Surfacing + Audit + Outcome + Regressions)

**Type:** process (workflow infrastructure)
**Branch:** `claude/improve-keyboard-shortcuts-Pnrqv`
**Spec:** `docs/superpowers/specs/2026-04-21-memory-harness-pr2.md`
**Plan:** `docs/superpowers/plans/2026-04-21-memory-harness-pr2.md`
**Context:** Segunda de dos PRs a partir del análisis del tweet de Harrison Chase.
PR1 estableció schema + consult.sh. PR2 conecta los consumidores: surfacing
proactivo, pattern-audit automatizado, outcome tracking (manual + auto), regressions
link bidireccional, y cierra la brecha de user_approved en new-day resume.

## Summary

Cinco items implementados (D1a, D2a+b, D3b, D4a, D5b aprobados en brainstorm).
3 scripts nuevos (`pattern-audit.sh`, `mark-verified.sh`, `link-regression.sh`),
4 tests (23 cases totales, verdes), 3 hooks modificados (session-start con 4
extensiones, phase-advance con audit trigger, post-commit con regression link).

### Phase: Brainstorming
- **Alternatives evaluated:** en PR1 spec, items #3-#5 fueron deferred; aquí se
  confirmaron decisiones específicas via 5 preguntas (D1..D5) con 2-3 opciones cada
- **Chosen approach:** D1a (surfacing ≤5 file threshold), D2a+b (manual + auto),
  D3b (audit non-blocking), D4a (post-commit auto-link), D5b (función refactor)
- **Past decisions consulted:** PR1 spec + retrospective (identificó el gap de
  user_approved new-day resume)
- **Complexity estimate:** M — 5 items semi-independientes
- **Confidence:** high — patrones ya establecidos en PR1

### Phase: Planning
- **Task count:** 13 tareas en 5 waves
- **Files affected:** 7 nuevos + 4 modificados
- **Time estimate:** 90-120 min
- **Risk assessment:** low-medium — session-start.sh crítico, tests cubren ramas nuevas
- **Paralelismo:** Wave 1 dispatches 3 scripts independientes (archivos disjuntos)

### Phase: Implementation
- **Actual time:** ~90 min
- **Blockers hit:**
  - `is_resumable()` originally returned 1 (not 0) for "is resumable" — reordered to
    match bash convention (0 = success/true), caught by smoke test before commit.
  - Test `pattern-audit.sh` requires overriding `KNOWLEDGE_DIR` which is hardcoded in
    the script. Solved with wrapper fixture that inlines the logic with env overrides —
    acceptable trade-off vs. parametrizing the production script.
  - Regex for `**Fixes previously:** \`...\``  in post-commit-validator uses grep -oE;
    bash's backtick handling in sed required escaping sequence carefully. Tested
    manually by grepping real patterns.
- **Plan deviations:**
  - Wave 2 (session-start) no dependía técnicamente de Wave 1 — pude haber
    paralelizado. Mantuve secuencial para commits más legibles.
  - `surface_related_logs()` dedupes por filename (4ª columna) en AWK en vez de
    requerir nueva flag en consult.sh — menos invasivo.

### Phase: Verification
- **Tests:** 23/23 new tests pass (pattern-audit 4/4, mark-verified 9/9,
  link-regression 10/10)
- **Regression:** test-consult.sh (PR1) 39/39 green, test-phase-advance 14/14,
  test-workflow-engine 23/29 (6 failures pre-existentes, sin cambio)
- **Lint:** skipped (no shellcheck en pipeline)
- **Smoke tests:** session-start.sh ejecuta clean; surface_related_logs con branch
  sintético de 3 archivos retorna 4 logs relevantes correctamente deduplicados

### Phase: Retrospective

#### Estimate accuracy

Estimado: 400 líneas. Actual: 717 líneas (+79%). El gap viene principalmente de
los tests (~300 líneas estimadas, ~450 reales con más edge cases agregados durante
implementación — valor agregado, no scope creep).

#### Process gap

El problema del regex de aprobación que vimos en la sesión de hoy (el hook no
reconoció "1. D1a 2. D2a y D2b completos" como aprobación porque no contiene
"sí"/"ok"/"aprueba") es la próxima iteración obvia. El hook debería detectar
patrones de aprobación más flexibles, o aceptar explícitamente confirmación de
decisiones con ID (D1a, etc.). **Candidato para PR3 si se materializa.**

#### Emergent patterns

- **Pattern: wrapper-based test isolation para scripts con paths hardcoded.**
  `test-pattern-audit.sh` creó un wrapper inline porque la producción `pattern-audit.sh`
  hardcodea `KNOWLEDGE_DIR` al repo root. Si necesito este mismo patrón otra vez,
  considero parametrizar via env var (como ya hice con `CONSULT_LOGS_DIR`,
  `MARK_VERIFIED_LOGS_DIR`, `LINK_REGRESSION_LOGS_DIR`). Segunda ocurrencia →
  graduar a convención de hooks.
- **Pattern: idempotent scripts via "skip if already done" + --force flag.**
  `mark-verified.sh` y `link-regression.sh` ambos siguen el mismo shape:
  detect-state → skip-if-done → do-or-force. Tercera ocurrencia (contando
  `backfill-exec-logs.sh` de PR1) → **gradúa a convención implícita para workflow
  scripts**. Candidato para documentación breve en `docs/knowledge/superpowers-skills.md`.

## Lessons

1. **Cuando dos items tocan el mismo archivo, combínalos en un solo wave.** El plan
   originalmente separaba "surfacing" de "user_approved fix" pero ambos modifican
   `session-start.sh`. Fusionarlos en Wave 2 evitó merge conflicts y produjo un
   commit coherente "extend session-start with X+Y+Z".

2. **Tests con fixtures env-var-overrideable son trivialmente isolados.** Los 3
   scripts nuevos aceptan env vars (`MARK_VERIFIED_LOGS_DIR`, `LINK_REGRESSION_LOGS_DIR`)
   para apuntar a fixtures temporales. Sin eso, los tests tendrían que modificar el
   corpus real — frágil y contaminante. **Convención para futuros workflow scripts:
   acepta `<NAME>_LOGS_DIR` o equivalente.**

3. **La brecha de user_approved en new-day resume era una simple preservación
   incompleta.** El fix no requiere nueva lógica — solo mover el reset de `user_approved`
   del "always reset" al "reset only if not resumable". 5 líneas efectivas, elimina
   fricción real en interacciones largas. **Lesson: antes de añadir complejidad,
   verifica si el fix es "dejar de hacer algo".**

## Files changed

- `.claude/hooks/pattern-audit.sh` (+51, new)
- `.claude/hooks/test-pattern-audit.sh` (+110, new)
- `.claude/hooks/session-start.sh` (+141/-17, refactor + 3 extensiones)
- `.claude/hooks/phase-advance.sh` (+5, pattern-audit hook)
- `.claude/hooks/post-commit-validator.sh` (+20, regression-link hook)
- `scripts/mark-verified.sh` (+82, new)
- `scripts/link-regression.sh` (+82, new)
- `scripts/test-mark-verified.sh` (+120, new)
- `scripts/test-link-regression.sh` (+140, new)
- `docs/superpowers/templates/execution-log-template.md` (+1, convention)
- `docs/superpowers/specs/2026-04-21-memory-harness-pr2.md` (+113, new)
- `docs/superpowers/plans/2026-04-21-memory-harness-pr2.md` (+130, new)

## PR3 follow-ups (candidatos, no planeados)

1. Hook de aprobación más flexible (reconocer "D1a", "Ok 1-5", etc., no solo "sí")
2. Parametrizar `pattern-audit.sh` para aceptar `KNOWLEDGE_DIR` env var (2ª ocurrencia del pattern)
3. Documentar en `superpowers-skills.md` la convención "workflow scripts son idempotent + env-overrideable para tests"
