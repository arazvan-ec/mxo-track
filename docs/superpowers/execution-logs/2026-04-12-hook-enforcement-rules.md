# Execution Log — 2026-04-12 — Enforce CLAUDE.md Rules via Hooks

**Type:** code change (hooks infrastructure)
**Branch:** `claude/add-customer-filters-ev8cG`

## Brainstorming

- **Audit:** 18 gaps found between CLAUDE.md rules and hook enforcement
- **Selected:** 8 rules (4 critical + 4 quick wins), omitted wave concurrency (#1) por complejidad
- **Approach:** Extensiones de hooks existentes (no scripts nuevos excepto pre-agent-check.sh)

## Planning

- **Tasks:** 7 (agrupadas en 2 waves paralelas: quick wins + critical gates)
- **Files affected:** 5 hooks + 1 nuevo + spec + plan

## Implementation

### Quick Wins (agentes paralelos)
- **#5 Manifest check:** SOFT warning en pre-push-gate si `codebase-manifest.md` no en diff
- **#6 Agent uncommitted:** DENY en pre-agent-check.sh si `git status --porcelain` no vacío (excluye Explore)
- **#7 Ephemeral artifacts:** WARNING en auto-evidence si Write a `/tmp/` con keywords de spec/plan
- **#8 Deploy commands:** Track `npm run build`/`make lint` en `verified_commands[]`, warn `tsc --noEmit`

### Critical Gates (flujo principal)
- **#2 Fresh evidence:** Timestamps `tests_ran_at`/`lint_ran_at` en auto-evidence al detectar phpunit/lint
- **#3 Deviation criteria:** post-bash-validator revierte `deviation.active` si ≥30 líneas, nuevos endpoints, o sin file:line
- **#4 TDD isolation:** brainstorm-validator SOFT warn si plan tiene tareas standalone "add tests"

## Verification
- `bash -n` limpio en 5 scripts modificados + 1 nuevo

## Retrospective

**Paralelismo real esta vez:** 3 agentes background (tasks 5, 6, 7+8) corrieron mientras el flujo principal implementaba tasks 2, 3, 4. Los 3 agentes completaron antes de que el flujo principal terminara — wall-clock time reducido vs ejecución secuencial.

**Lecciones:**
- Agentes background funcionan bien para edits aislados a archivos distintos (no conflictos)
- El agente de task 7+8 editó auto-evidence.sh, que el flujo principal también necesitaba editar (task 2). Esto causó un "file modified since read" que se resolvió re-leyendo. Para evitarlo: el flujo principal debe esperar al agente que toca el mismo archivo antes de editarlo.
- La auditoría de gaps (Explore agent) fue el paso más valioso — sin ella, habría implementado las reglas más fáciles, no las más impactantes.
