# Plan — Hito 3 Phase A: Ubiquitous Language System Foundation

**Spec:** `docs/superpowers/specs/2026-04-29-ubiquitous-language-system-phase-a-design.md`

## Phase 1: schema → bootstrap → curate → integrate → render → verify

### Wave 1: Schema + 5 anchor entries
- **1:** Crear `docs/knowledge/_vocabulary.yaml` con header documentando esquema + 5 entradas curadas (Route, RouteStop, Driver, Vehicle, RouteSnapshot).
  → files: `docs/knowledge/_vocabulary.yaml`

### Wave 2: Bootstrap script (independiente de 1, puede leer 1 si existe)
- **2:** Crear `scripts/bootstrap-vocabulary.sh`. Idempotent merge.
  → files: `scripts/bootstrap-vocabulary.sh`

### Wave 3: Run bootstrap + curate ~45 más (depende de 1+2)
- **3:** Ejecutar bootstrap, revisar output, curar aliases para los ~45 conceptos high-authority listados en spec.
  → files: `docs/knowledge/_vocabulary.yaml` (extended)

### Wave 4: `consult.sh vocab` subcommand (depende de 1)
- **4:** Añadir bloque `vocab` a `.claude/hooks/consult.sh`. Lookup, --all, --context.
  → files: `.claude/hooks/consult.sh`

### Wave 5: Render script + Makefile integration (depende de 1)
- **5a:** Crear `scripts/render-vocabulary.sh` que emite markdown agrupado por `bounded_context`.
  → files: `scripts/render-vocabulary.sh`
- **5b:** Extender `Makefile` para invocar render desde `make manifest`.
  → files: `Makefile`

### Wave 6: CLAUDE.md Step 0 update (independiente)
- **6:** Añadir instrucción de consulta de vocabulario en Step 0 del checklist de brainstorming.
  → files: `CLAUDE.md`

### Wave 7: Verificación (depende de 1-6)
- **7a:** Existing tests 31/31 pass.
- **7b:** `bash -n` en scripts nuevos + consult.sh modificado.
- **7c:** Smoke: `consult.sh vocab Route` → canonical entry; `consult.sh vocab ruta` → mismo entry; `consult.sh vocab nonexistent_xyz` → exit 1.
- **7d:** `make manifest` regenera `docs/knowledge/ubiquitous-language.md`.
- **7e:** Smoke forzó descubrir que el render auto-generado debe estar en `WORKFLOW_ARTIFACTS_PATHS` del sync-validator (mismo patrón que `codebase-manifest.md`). Adjustment in scope.
  → files: `.claude/hooks/validators/sync-validator.sh`

## Estimación

| Métrica | Estimación |
|---|---|
| `_vocabulary.yaml` | +600 lines (50 entries × ~12 líneas) |
| `bootstrap-vocabulary.sh` | +120 lines |
| `render-vocabulary.sh` | +60 lines |
| `consult.sh vocab` block | +50 lines |
| `Makefile` | +3 lines |
| `CLAUDE.md` Step 0 | +5 lines |
| Total net | ~840 |
| Files (incl artefactos) | 9 |

## Done criteria

- [ ] `_vocabulary.yaml` creado con ≥50 entradas curadas
- [ ] `bootstrap-vocabulary.sh` idempotent (re-run preserva curación)
- [ ] `consult.sh vocab` funcional (canonical, alias, --all, --context)
- [ ] `make manifest` renderiza `ubiquitous-language.md`
- [ ] CLAUDE.md Step 0 actualizado
- [ ] Tests 31/31 pasan (no regresión)
- [ ] Commit + push
