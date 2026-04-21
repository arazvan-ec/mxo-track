---
type: process
tags: [workflow, memory, harness, frontmatter, consult-sh, execution-logs]
files_touched: [.claude/hooks/consult.sh, .claude/hooks/test-consult.sh, scripts/backfill-exec-logs.sh, .claude/README.md, CLAUDE.md, docs/superpowers/templates/execution-log-template.md]
patterns: [yaml-frontmatter-indexing, harness-memory-separation]
outcome: success
outcome_verified_at: null
regressions_later: []
pr_number: null
estimated_lines: 320
actual_lines: 2349
duration_minutes: 180
consulted_in_future: []
---

# Execution Log — 2026-04-19 — Memory/Harness PR1 (Schema + Consult Foundation)

**Type:** process (workflow infrastructure)
**Branch:** `claude/improve-keyboard-shortcuts-Pnrqv`
**Spec:** `docs/superpowers/specs/2026-04-19-memory-harness-improvements.md`
**Plan:** `docs/superpowers/plans/2026-04-19-memory-harness-pr1.md`
**Context:** Respuesta a preguntas de Harrison Chase sobre harness-as-memory.
Primera de dos PRs; ésta construye la foundation (schema + query tool + backfill).
PR2 construirá surfacing proactivo, pattern-audit automatizado, y outcome tracking.

## Summary

Introducido YAML frontmatter en execution logs, script `consult.sh` para query
sobre ese frontmatter, y backfill retroactivo sobre 86 logs existentes. El corpus
ahora es consultable por `tag`, `file`, `pattern`, `type`, `outcome`, con coste
O(n) sobre 86 logs (<100ms medido).

### Phase: Brainstorming
- **Alternatives evaluated:**
  1. YAML frontmatter (A) — estándar markdown, parseable con awk, se mantiene junto al contenido
  2. JSON index derivado (B) — más rápido query, pero frágil (se desincroniza si alguien edita un log sin actualizar índice)
  3. Nombres de archivo estructurados (C) — zero tooling, pero rígido (max tags en filename, no re-tagueable)
- **Chosen approach:** A — trivial tooling, imposible desincronizar, formato estándar
- **Past decisions consulted:** [2026-04-14 widget phased roadmap] validó que PRs ~400 líneas pasan mientras >800 hit rate limits; informó la decisión de fasear en 2 PRs
- **Complexity estimate:** M — schema + tooling + backfill + docs
- **Confidence:** high — bash/awk local sin integraciones externas

### Phase: Planning
- **Task count:** 12 tareas en 5 waves, 4 commits lógicos
- **Files affected:** 6 creados + 86 modificados (backfill auto) + 3 docs tocados
- **Time estimate:** 60-90 min
- **Risk assessment:** low — git es safety net, dry-run obligatorio antes de write

### Phase: Implementation
- **Actual time:** ~180 min (sesión rota por rate limit a mitad, resumida día siguiente)
- **Blockers hit:**
  - Session rate-limited después de Wave 2. Resume al día siguiente requirió restaurar session-state (flow, phase, user_approved, spec_path) manualmente. Solución: hook `session-start.sh` auto-restaura `user_approved` solo en same-day resume; new-day no lo preserva. Esto es la brecha que este PR trata de sistematizar (contrato de compaction).
  - Primer diseño del `extract_files` en backfill era demasiado estricto (buscaba solo `## Files changed` section). 79/86 logs quedaban con `files_touched: []`. Relajé a "escanear documento completo, filtrar backticked paths con `/` separator" → redujo a 38 warnings.
  - Test `recent 2 includes second-newest` usaba fixture sin frontmatter como segunda-newest esperada; consult.sh correctamente la skippea. Ajusté expectativa del test.
- **Plan deviations:**
  - Añadí `--quiet` flag a consult.sh antes de lo planeado (anticipando su uso en PR2 surfacing hook)
  - El comando `show` se implementó aunque no estaba explícito en el plan — trivial de añadir y útil para debugging

### Phase: Verification
- **Tests:** 39/39 pasan en test-consult.sh. 23/29 en test-workflow-engine (6 failures pre-existentes en HEAD, no introducidos por este PR — verified vía stash).
- **Lint:** skipped (no shellcheck en pipeline; bash scripts leen cleanly)
- **Coverage:** consult.sh tiene 10 subcomandos, cada uno con ≥2 assertions (found results, excluded non-matches, exit codes). Edge cases: log sin frontmatter, outcome null, invalid subcommand, --quiet flag.

### Phase: Retrospective

#### Estimate accuracy

Estimado: 320 líneas netas nuevas + ~860 backfill = ~1180 total. Actual: ~2349 lines (549 consult+tests, 193 backfill, 1290 backfilled frontmatter, ~300 docs).
Gap en backfill: estimaba 10 líneas/log (solo fields extraídos), actualicé a 15 (todos los 12 campos presentes aunque sean null). Acceptable drift.

#### Process gaps

1. **Session resume después de rate limit perdió `user_approved`.** El hook `session-start.sh` auto-restaura `user_approved` solo en same-day resume; new-day reset no lo preserva. Cuando el usuario continuó al día siguiente, tuve que pedirle re-aprobación explícita para que el regex del hook disparara. **Fix propuesto:** extender `session-start.sh` new-day path para preservar `user_approved` cuando spec+plan+phase_history indican trabajo avanzado. Candidato para PR2 o PR follow-up.

2. **Pre-push gate bloqueó mi primer intento de push.** Llegué a verification sin haber pasado por capture/retrospective/finalize — el gate correctamente bloqueó. Cumplió su función.

#### Emergent patterns

- **Pattern: schema-first for queryable artifacts.** Antes de este PR, los execution logs eran markdown libre — legibles pero no consultables. Añadir frontmatter estructurado + tool de query separa "storage" de "index". Esta misma técnica aplica a `docs/decisions/log.md` si algún día crece lo suficiente. Por ahora es 30 entradas, no justifica.
- **Pattern: backfill con fallback degradado.** El extractor intenta varias heurísticas (sección explícita → paths backticked → prose mentions) y deja el campo vacío si ninguna da resultado. 38 warnings sobre 86 (44%) indican que el corpus antiguo no estandarizó file references. **Lesson:** standardizar formato desde el inicio ahorra backfill.

## Lessons

1. **Contracts de artefactos deben incluir su formato de referencia** — si los execution logs hubieran tenido `files_touched` como convención desde el inicio, backfill no existiría. Mi `execution-log-template.md` actualizado ahora incluye el frontmatter, estableciendo el contrato hacia adelante.

2. **Session-state después de compaction / rate-limit / resume requiere explicit restoration.** Los hooks auto-preservan algunas cosas (`last_work_summary`) pero no otras (`user_approved`, `user_turns`). Esto es la brecha que el Compaction Contract (añadido en .claude/README.md) ahora documenta explícitamente. PR2 podría automatizar la restauración del approval flag cuando spec+plan existen.

3. **Regex de aprobación del hook es el único path para `user_approved = true`.** El phase-transition-controller revierte writes directos via `jq`. Esto es correcto por diseño (prevenir auto-aprobación del modelo) pero fricciona cuando hay continuidad legítima entre sesiones. Tradeoff aceptado.

## Files changed

- `.claude/hooks/consult.sh` (+331, new)
- `.claude/hooks/test-consult.sh` (+218, new)
- `scripts/backfill-exec-logs.sh` (+193, new)
- `docs/superpowers/templates/execution-log-template.md` (+21)
- `.claude/README.md` (+45, compaction contract section)
- `CLAUDE.md` (+16, consult.sh mention)
- `docs/superpowers/execution-logs/*.md` (+1290 total across 86 files, auto-backfill)
- `docs/superpowers/specs/2026-04-19-memory-harness-improvements.md` (+107, new)
- `docs/superpowers/plans/2026-04-19-memory-harness-pr1.md` (+97, new)
- `docs/codebase-manifest.md` (regen)

## PR2 follow-ups (tracked for next iteration)

1. Surfacing proactivo en `session-start.sh`: usar `consult.sh file` sobre los archivos tocados en el branch para mostrar "Related past logs" en el contexto inicial
2. `pattern-audit.sh` dedicado con hook a phase-advance finalize (alerta de 3+ ocurrencias)
3. Outcome tracking post-merge: hook que setea `outcome_verified_at` al push/merge
4. Restauración automática de `user_approved` en new-day resume cuando spec+plan+phase_history>=implementation existen
5. Completar `tags`/`patterns` oportunísticamente en logs consultados (quizás con prompt al modelo al abrir un log)
