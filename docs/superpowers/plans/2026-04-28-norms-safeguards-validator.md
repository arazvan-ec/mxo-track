# Plan — Hito 1: Norms & Safeguards Validator (Layers N + S)

**Spec:** `docs/superpowers/specs/2026-04-28-norms-safeguards-validator-design.md`
**Approach:** A — Universal HARD with structured content validation.

## Phase 1 (v0): TDD red → green → verify

### Wave 1: Tests TDD red
- **1a:** Añadir 4 fixtures + helper `run_ns_scenario` a `test-brainstorm-validator.sh`
  → produces: 4 nuevos casos failing
  → files: `.claude/hooks/test-brainstorm-validator.sh`

### Wave 2: Implementación green (depende de 1a)
- **2:** Añadir Layer N + Layer S a `brainstorm-validator.sh` tras Anti-Omision (línea 64), antes de Layer H (línea 66).
  - Layer N: presence + awk state machine para extraer sección Norms + grep imperativo
  - Layer S: presence + grep tolerante de tabla con tokens "Risk" y "Mitigation"
  → produces: validator que hace pasar los 4 tests
  → files: `.claude/hooks/validators/brainstorm-validator.sh`

### Wave 3: Verificación + doc (depende de 2)
- **3a:** `test-brainstorm-validator.sh` completo → 19/19 pass.
- **3b:** `bash -n` syntax check.
- **3c:** Smoke test: validator contra este spec → exit 0 (o 1 SOFT por turnos, pero no Layer N/S).
- **3d:** Documentar en CLAUDE.md "Enforcement gates" table.
  → files: `CLAUDE.md`

## Estimación

| Métrica | Estimación |
|---|---|
| Files | 3 fuente + 2 artefactos (spec, plan, log) = 5 total |
| Validator lines | +25 |
| Test lines | +90 (4 casos × 20 + helper + fixtures) |
| CLAUDE.md lines | +1 |
| Total net lines | ~120 |
| Waves | 3 |

## Done criteria

- [ ] 4 nuevos tests TDD pasan
- [ ] Tests existentes (15) siguen pasando — total 19/19
- [ ] `bash -n` clean
- [ ] CLAUDE.md actualizado
- [ ] Smoke test contra este spec: pass
- [ ] Commit + push
