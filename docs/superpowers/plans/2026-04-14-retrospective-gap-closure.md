# Plan — Cierre de gaps de retrospectiva

**Spec:** `docs/superpowers/specs/2026-04-14-retrospective-gap-closure-design.md`
**Branch:** `claude/enhance-dashboard-widgets-sxseH`
**Estrategia:** una wave con archivos disjuntos.

## Fase 1 (v0)

### Wave 1 — Cierre de los 5 gaps (secuencial por claridad, archivos disjuntos)

1. **Gap 1** — editar `backend/bin/preflight.sh` (añadir check node_modules)
2. **Gap 2** — editar `CLAUDE.md` (añadir regla shared components)
3. **Gap 3** — crear `.claude/test-baseline.txt` con `1`
4. **Gap 4** — editar `docs/knowledge/ui-frontend.md` (guideline collapsible)
5. **Gap 5a** — editar `frontend/src/widgets/types.ts` (campo summaryComponent)
6. **Gap 5b** — crear 6 summary components en cada archivo de widget
7. **Gap 5c** — editar `frontend/src/widgets/registry.ts` (referenciar summaries)
8. **Gap 5d** — editar `frontend/src/components/bottom-sheet/WidgetRenderer.tsx` (renderizar summary)

### Wave 2 — Verificación

- `bash backend/bin/preflight.sh`
- `php vendor/bin/phpunit`
- `cd frontend && npm run build`
- `make manifest`
- Commit + push

## Fase 2 (Mature)

N/A.
