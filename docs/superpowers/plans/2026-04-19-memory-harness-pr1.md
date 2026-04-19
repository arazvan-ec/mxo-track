# Plan — 2026-04-19 — Memory/Harness PR1 (Schema + Consult Foundation)

**Spec:** `docs/superpowers/specs/2026-04-19-memory-harness-improvements.md`
**Branch:** `claude/improve-keyboard-shortcuts-Pnrqv`
**Phase:** v0 (directo a producción — no requiere "Mature" phase, scope acotado)

---

## Phase 1: v0 (única fase — scope acotado)

### [parallel] Wave 1: Foundation

- **1a: Template frontmatter**
  - Archivo: `docs/superpowers/templates/execution-log-template.md`
  - Añadir bloque YAML frontmatter al principio con los 12 campos
  - TDD: no aplica (cambio de doc)
  - → produces: schema canónico que consult.sh y backfill consumen

- **1b: Compaction contract section**
  - Archivo: `.claude/README.md`
  - Añadir sección nueva "## Compaction Contract" con tabla de 8 filas
  - → produces: referencia explícita de qué sobrevive

- **1c: CLAUDE.md mention de consult.sh**
  - Archivo: `CLAUDE.md` — sección "Closing the Cycle" o "Knowledge Modules"
  - Añadir 1 línea mencionando `.claude/hooks/consult.sh <tag|file|pattern>` como herramienta de Step 0 brainstorming
  - → produces: discoverability

**Commit 1:** `feat: add frontmatter schema to execution log template`
- Files: template.md, .claude/README.md, CLAUDE.md
- ~60 líneas

### Wave 2: consult.sh (needs Wave 1)

- **2a: Write test-consult.sh FIRST (TDD)**
  - Archivo: `.claude/hooks/test-consult.sh`
  - Crear fixtures en `/tmp/test-consult-logs/` con 5 logs sintéticos (mezcla de types, tags, outcomes)
  - Test cases: tag, file, file-glob, pattern, type, recent, by-outcome, stats, show, unverified
  - Verify exit codes (0/1/2)
  - → produces: failing tests (consult.sh doesn't exist yet)

- **2b: Implement consult.sh**
  - Archivo: `.claude/hooks/consult.sh`
  - awk-based YAML frontmatter parser
  - 10 subcomandos del diseño
  - `--quiet` flag
  - Output format: `date | type | outcome | filename | title`
  - → produces: tests green

**Commit 2:** `feat: add consult.sh for execution log queries`
- Files: consult.sh, test-consult.sh
- ~180 líneas

### Wave 3: Backfill script (needs Wave 2)

- **3a: Write backfill-exec-logs.sh with --dry-run first**
  - Archivo: `scripts/backfill-exec-logs.sh`
  - Parser para type, files_touched, outcome, pr_number, actual_lines
  - Idempotente (skip si ya tiene frontmatter)
  - Modo `--dry-run` imprime sin escribir
  - Reporta summary: "N procesados, M skipped, K warnings"
  - → produces: script ejecutable en dry-run

- **3b: Run dry-run sobre 5 logs, inspección visual**
  - Verificar que type/files_touched/outcome parseen correctamente
  - Ajustar regex si hay casos problemáticos
  - → produces: confianza en parser

**Commit 3:** `chore: add backfill script for execution log frontmatter`
- Files: backfill-exec-logs.sh
- ~80 líneas

### Wave 4: Execute backfill (needs Wave 3)

- **4a: Run backfill sobre los 86 logs**
  - `bash scripts/backfill-exec-logs.sh` (no dry-run)
  - → produces: 86 logs con frontmatter
  - Verificación: `consult.sh stats` y smoke-check algunos logs manualmente

**Commit 4:** `chore: backfill frontmatter into 86 execution logs`
- Files: 86 × execution-logs/*.md
- ~860 líneas (auto-generadas)

### Wave 5: Verification + push

- **5a: Run full test suite**
  - `bash .claude/hooks/test-consult.sh`
  - `bash .claude/hooks/test-workflow-engine.sh` (regression check)
- **5b: make manifest** (actualizar codebase manifest)
- **5c: make lint** si aplica
- **5d: Push**

---

## Task count: 9 tareas, 5 waves, 4 commits

## Files affected
- **Nuevos:** `.claude/hooks/consult.sh`, `.claude/hooks/test-consult.sh`, `scripts/backfill-exec-logs.sh`
- **Modificados:** `docs/superpowers/templates/execution-log-template.md`, `.claude/README.md`, `CLAUDE.md`, 86× `docs/superpowers/execution-logs/*.md`

## Time estimate: 60-90 min

## Risk: Low
- Bash/awk local, sin integraciones externas
- Git es safety net (revert trivial si backfill corrompe)
- Tests antes de producción
- Dry-run obligatorio antes de write
