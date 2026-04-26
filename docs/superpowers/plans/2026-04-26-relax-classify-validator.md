# Plan — Relax classify-validator for Pure-Config Edits

**Spec:** `docs/superpowers/specs/2026-04-26-relax-classify-validator-design.md`
**Date:** 2026-04-26

## Phase 1 (v0)

### Wave 1: Implementación + Test (paralelizable, archivos disjuntos)

- **1a:** Agregar 4 carve-outs al `case "$REL_PATH"` en
  `.claude/hooks/validators/classify-validator.sh` (después de la
  entrada `session-state.json`).
  → produces: validator que permite `.gitignore`, `.editorconfig`,
  `.gitattributes`, `.claude/settings.local.json`.

- **1b:** Agregar 2 casos a `.claude/hooks/test-enforcement-layers.sh`:
  uno positivo (`.gitignore` → permitido sin clasificación), uno
  negativo (`.claude/settings.json` → sigue bloqueado).
  → produces: cobertura de regresión.

### Wave 2: Doc (depende de 1a)

- **2:** Actualizar la tabla "Enforcement gates" en `CLAUDE.md` para
  mencionar el allowlist de config-only en la fila de
  `classify-validator.sh`.
  → produces: documentación alineada con el comportamiento.

## Phase 2 (Mature)

No hay refactor planeado. v0 ES la forma final del cambio (4 líneas
literales). Si en el futuro el allowlist crece más allá de 8-10
entradas, considerar extracción a `lib/config-only-paths.sh` siguiendo
el patrón de `lib/ddd-boundaries.sh`.

## Validation

1. `bash .claude/hooks/test-enforcement-layers.sh` — debe pasar todos
   los casos previos + los 2 nuevos.
2. Smoke manual:
   - Edit `.gitignore` con clasificación nula → permitido
   - Edit `backend/src/Foo.php` con clasificación nula → bloqueado
3. `make lint-shell` verde.

## Estimate

- Líneas: ~25 totales
- Archivos: 3 (validator, test, CLAUDE.md)
- Wall time: ~10 min
