# Plan — Harness Consolidation (lib extractions)

**Spec:** `docs/superpowers/specs/2026-04-28-harness-consolidation-design.md`

## Phase 1: extract → migrate → verify (no behavior change)

### Wave 1: Extract section-validator lib
- **1:** Crear `.claude/hooks/lib/section-validator.sh` con primitivas:
  `section_present`, `section_body`, `section_satisfied_inline_or_ref` con 5 modos
  (`imperative`, `risk-mitigation-table`, `classified-rows`, `positive-signal`,
  `multiline-bullet`).
  → files: `.claude/hooks/lib/section-validator.sh`

### Wave 2: Extract files-decl-parser lib (independiente de Wave 1)
- **2:** Crear `.claude/hooks/lib/files-decl-parser.sh` con `parse_files_decl`,
  backtick stripping baked in.
  → files: `.claude/hooks/lib/files-decl-parser.sh`

### Wave 3: Migrate sync-validator (depende de 2)
- **3:** Reemplazar parser inline en `sync-validator.sh` con llamada a lib.
  Verificar: `test-sync-validator.sh` → 6/6.
  → files: `.claude/hooks/validators/sync-validator.sh`

### Wave 4: Migrate brainstorm-validator (depende de 1 + 2)
- **4a:** Reemplazar Layers H, K, N, S con llamadas a `section-validator.sh`.
- **4b:** Reemplazar parser parallel-conflict con `files-decl-parser.sh`.
- Verificar: `test-brainstorm-validator.sh` → 19/19.
  → files: `.claude/hooks/validators/brainstorm-validator.sh`

### Wave 5: Migrate pre-agent-check Gate 3 (depende de 1)
- **5:** Reemplazar Gate 3 con llamada a `section_satisfied_inline_or_ref`.
  Verificar: `test-pre-agent-check.sh` → 6/6.
  → files: `.claude/hooks/pre-agent-check.sh`

### Wave 6: Verificación final
- **6a:** Total tests: 31/31 (19+6+6).
- **6b:** `bash -n` en libs + 3 callers.
- **6c:** Smoke verification → capture en este propio plan.
  → files: (no nuevos)

## Estimación

| Métrica | Estimación |
|---|---|
| `lib/section-validator.sh` | +180 lines (3 funcs × 5 modos + helpers) |
| `lib/files-decl-parser.sh` | +30 lines |
| brainstorm-validator deletions | -120 lines (5 inline blocks) |
| brainstorm-validator additions | +30 lines (5 lib calls) |
| pre-agent-check deletions | -60 lines (Gate 3 inline) |
| pre-agent-check additions | +10 lines (lib call) |
| sync-validator deletions | -15 lines (inline parser) |
| sync-validator additions | +5 lines (lib call) |
| Net delta | +60 (libs grow more than callers shrink, but consolidated) |
| Files (incl artefactos) | 8 |

## Done criteria

- [ ] `lib/section-validator.sh` y `lib/files-decl-parser.sh` creados
- [ ] 5 callers migrados (4 en brainstorm-validator, 1 en pre-agent-check, 1 en sync-validator)
- [ ] Tests 31/31 pasan
- [ ] `bash -n` clean
- [ ] Smoke verification → capture pass
- [ ] Commit + push (incluirá execution log de Hito 4 pendiente)
