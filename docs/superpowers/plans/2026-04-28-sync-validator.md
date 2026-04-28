# Plan — Hito 2: Sync Validator

**Spec:** `docs/superpowers/specs/2026-04-28-sync-validator-design.md`

## Phase 1: TDD red → green → integrate → verify

### Wave 1: TDD red — fixtures + harness
- **1a:** Crear `test-sync-validator.sh` con 5 fixtures TDD.
  - Construye repo git temporal en `$TEST_TMPDIR` con commits sintéticos para producir diffs predecibles.
  - Fixtures: pass baseline, drift detected, workflow-only diff, plan + workflow mix, parenthesized payload.
  → produces: harness con 5 RED.
  → files: `.claude/hooks/test-sync-validator.sh`

### Wave 2: Implementación green (depende de 1a)
- **2:** Crear `sync-validator.sh`.
  - Parsea `→ files:` del plan (mismo idiom que brainstorm-validator:228+).
  - Ejecuta `git diff --name-only origin/main...HEAD` (fail-open con SOFT warn si falla).
  - Computa drift = touched − declared − workflow_paths.
  - BLOCK con lista si drift no vacío.
  → produces: 5/5 GREEN en test-sync-validator.
  → files: `.claude/hooks/validators/sync-validator.sh`

### Wave 3: Integración (depende de 2)
- **3:** Sub-invocar `sync-validator.sh` desde `verification-validator.sh`.
  - Tras los checks de tests_passed + lint_clean.
  - Captura output con `|| true`, propaga exit 2, preserva semántica.
  → produces: gate activo en phase advance verification → capture.
  → files: `.claude/hooks/validators/verification-validator.sh`

### Wave 4: Verificación + documentación (depende de 3)
- **4a:** `bash test-sync-validator.sh` → 5/5 pass.
- **4b:** `bash test-brainstorm-validator.sh` → 19/19 pass (no regresión).
- **4c:** `bash -n` syntax checks.
- **4d:** Smoke test contra el plan real al final de la interacción.
- **4e:** Documentar en CLAUDE.md "Enforcement gates" + sección "Bypass env vars" si añade `SKIP_SYNC_GATE=1`.
  → files: `CLAUDE.md`

## Estimación

| Métrica | Estimación |
|---|---|
| Validator (nuevo) | +70 lines |
| Test harness (nuevo) | +120 lines |
| verification-validator.sh integration | +6 lines |
| CLAUDE.md | +3 lines |
| Total net | ~200 |
| Files (incl artefactos) | 7 (3 nuevos + verification-validator + CLAUDE.md + spec + plan + log) |

## Done criteria

- [ ] 5 nuevos tests TDD pasan
- [ ] Tests existentes (19) siguen pasando — total 24
- [ ] `bash -n` clean
- [ ] CLAUDE.md actualizado con fila de Layer Sync
- [ ] Smoke test real-plan ↔ real-diff: pass
- [ ] Commit + push
