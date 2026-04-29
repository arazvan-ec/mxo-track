# Plan — Layer K (Anti-Reduction Validator)

**Spec:** `docs/superpowers/specs/2026-04-28-anti-reduction-validator-design.md`
**Branch:** `claude/review-workflow-improvements-x78Zp`
**Approach:** A — Trigger-based, mirrors Layer H pattern.

## Phase 1 (v0): minimal working validator + TDD coverage

### Wave 1: Tests TDD primero (red)
- **1a:** Añadir 4 fixtures TDD a `test-brainstorm-validator.sh` para Layer K
  → produces: 4 nuevos casos failing (RED)
  → files: `.claude/hooks/test-brainstorm-validator.sh`

### Wave 2: Implementación (green) — depende de 1a
- **2:** Añadir bloque Layer K a `brainstorm-validator.sh` después de Layer H
  - Detección de marcadores fuera de bloques fenced (state machine awk)
  - Extracción de sección `## Maximal Version Considered`
  - Verificación de 4 bullets estructurales
  - Verificación del bullet "Independent superiority" rechazando solo cost-language
  → produces: validator que hace pasar los 4 tests TDD
  → files: `.claude/hooks/validators/brainstorm-validator.sh`

### Wave 3: Verificación + documentación (depende de 2)
- **3a:** Ejecutar `test-brainstorm-validator.sh` completo (existentes + nuevos) → todos pasan
  → produces: evidencia tests_passed
- **3b:** `bash -n` syntax check + `make lint-shell` si shellcheck disponible
  → produces: evidencia lint_clean
- **3c:** Smoke test del validator contra el propio spec (no debe disparar Layer K)
  → produces: confirmación que el spec mismo no se autobloquea
- **3d:** Documentar Layer K en CLAUDE.md sección "Enforcement gates — shortcuts they catch"
  → files: `CLAUDE.md`

## Phase 2 (Mature): no aplica

El cambio es pequeño y self-contained. No requiere refactor posterior.

## Estimación

| Métrica | Estimación |
|---|---|
| Files | 3 (validator, tests, CLAUDE.md) |
| Net lines validator | +35 |
| Net lines tests | +110 (calibrado del log 2026-04-22: `30 + 20·N`) |
| Net lines CLAUDE.md | +6 |
| Total net lines | ~150 |
| Waves | 3 |

## TDD cycle (Wave 1 + Wave 2)

1. Wave 1a — escribir 4 fixtures + asserts esperando outputs:
   - **TC-K1:** spec sin marcadores → exit 0, no menciona Layer K.
   - **TC-K2:** spec con marker "MVP" sin sección → exit 2, error contiene "Maximal Version Considered".
   - **TC-K3:** spec con marker + sección + bullet de superioridad solo en lenguaje de coste → exit 2, error contiene "independent superiority".
   - **TC-K4:** spec con marker + sección + bullet con argumento no-coste → exit 0.
2. Ejecutar test harness → RED esperado (4 fallos).
3. Wave 2 — implementar Layer K hasta que los 4 pasen → GREEN.
4. Re-ejecutar harness completo → todos pasan, sin regresiones.

## Risk register

| Risk | Mitigation |
|---|---|
| Marker regex matchea texto dentro de bloques fenced | State machine awk para skip de líneas dentro de \`\`\` |
| `cost-language` regex deja pasar racionalizaciones | Aceptado en spec — backstop, no defensa única |
| Test harness `set -euo pipefail` colisiona con validator que exita 2 | Capturar output con `\|\| true` antes de grep (precedente log 2026-04-22) |
| Falso positivo en CLAUDE.md mismo (contiene "MVP") | Validator solo aplica a `docs/superpowers/specs/`, no a CLAUDE.md |

## Done criteria

- [ ] 4 nuevos tests TDD añadidos y pasan
- [ ] Tests existentes siguen pasando
- [ ] `bash -n` clean en validator y test harness
- [ ] Layer K documentado en CLAUDE.md
- [ ] Smoke test contra el spec propio: pass
- [ ] Commit + push
