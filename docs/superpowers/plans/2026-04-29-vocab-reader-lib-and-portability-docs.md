# Plan — I12: vocab-reader lib + portability docs

**Spec:** `docs/superpowers/specs/2026-04-29-vocab-reader-lib-and-portability-docs-design.md`

## Phase 1: extract → migrate → verify

### Wave 1: lib/vocabulary-reader.sh
- **1:** Crear `.claude/hooks/lib/vocabulary-reader.sh` con tres primitivas: `vocab_deprecated_aliases`, `vocab_canonicals_in_text`, `vocab_bounded_context`.
  → files: `.claude/hooks/lib/vocabulary-reader.sh`

### Wave 2: migrate pre-agent-check
- **2:** Reemplazar bloque inline en `pre-agent-check.sh` Gate 4 con llamada a lib.
  → files: `.claude/hooks/pre-agent-check.sh`

### Wave 3: migrate pattern-audit
- **3:** Reemplazar bloque inline en `pattern-audit.sh` deprecated-alias scan.
  → files: `.claude/hooks/pattern-audit.sh`

### Wave 4: migrate ddd-boundary-check
- **4:** Reemplazar bloque inline en `ddd-boundary-check.sh` vocab cross-ref.
  → files: `.claude/hooks/ddd-boundary-check.sh`

### Wave 5: portability docs
- **5:** Añadir sección "Shell Portability Constraints" a `.claude/README.md`.
  → files: `.claude/README.md`

### Wave 6: verify
- **6a:** `bash -n` clean en lib + 3 callers.
- **6b:** 31 existing tests pass.
- **6c:** Smoke pattern-audit B-3 con fixture log "tour" → surface canonical "Route".

## Estimación

| Métrica | Estimación |
|---|---|
| lib/vocabulary-reader.sh | +80 lines |
| pre-agent-check.sh | -25 / +8 |
| pattern-audit.sh | -25 / +8 |
| ddd-boundary-check.sh | -30 / +10 |
| .claude/README.md | +30 |
| Total net | ~+50 (libs grow, callers shrink) |
| Files (incl artefactos) | 8 |

## Done criteria

- [ ] Lib creada con 3 primitivas
- [ ] 3 callers migrados
- [ ] Portability docs en README
- [ ] 31/31 tests pass
- [ ] Smoke B-3 pass
- [ ] Commit + push
