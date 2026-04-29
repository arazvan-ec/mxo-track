# Plan — Hito 5: Refactor Classification

**Spec:** `docs/superpowers/specs/2026-04-29-refactor-classification-design.md`

## Phase 1: edit + verify

### Wave 1: classify-validator extension
- **1:** Extend the `full|debug)` arm in
  `.claude/hooks/validators/classify-validator.sh` line 61 to also
  accept `refactor`.
  → files: `.claude/hooks/validators/classify-validator.sh`

### Wave 2: CLAUDE.md table row
- **2:** Add row for `refactor` in the "Classify First" table.
  → files: `CLAUDE.md`

### Wave 3: Verification
- **3a:** `bash -n` clean.
- **3b:** 31 existing tests pass.
- **3c:** Smoke: set classification to `refactor`, attempt
  framework-path edit; expect pass.
- **3d:** Smoke: leave at `code change`, attempt edit; expect pass
  (regression check).

## Estimación

| Métrica | Estimación |
|---|---|
| classify-validator.sh | +1 line (regex extension) |
| CLAUDE.md | +1 line (table row) |
| Total net | ~2 lines code + spec/plan/log |
| Files (incl artefactos) | 5 |

## Done criteria

- [ ] classify-validator accepts `refactor`
- [ ] CLAUDE.md documents `refactor` row
- [ ] 31/31 tests pass
- [ ] Smoke validates both directions
- [ ] Commit + push
