# Plan — Layer Agent (Gate 3 in pre-agent-check.sh)

**Spec:** `docs/superpowers/specs/2026-04-28-agent-prompt-validator-design.md`

## Phase 1: TDD red → green → document → verify

### Wave 1: TDD red
- **1a:** Crear `test-pre-agent-check.sh` con 6 fixtures (incluyendo regression para Gates 1+2 existentes).
  → produces: harness con tests RED para Gate 3.
  → files: `.claude/hooks/test-pre-agent-check.sh`

### Wave 2: Implementación Gate 3 (depende de 1a)
- **2:** Añadir Gate 3 en `pre-agent-check.sh` tras Gate 2.
  - Skip si subagent_type es read-only (`Explore`).
  - Extraer secciones Norms/Safeguards del prompt vía awk.
  - Aceptar inline (imperativo en Norms / Risk|Mitigation table en Safeguards) OR spec-reference (path + token dentro de ~200 chars).
  - Bloquear con `permissionDecision: deny` si falla.
  → files: `.claude/hooks/pre-agent-check.sh`

### Wave 3: Documentación (depende de 2)
- **3a:** Añadir sección "Norms & Safeguards (mandatory)" a `AGENTS.md` con dos ejemplos.
  → files: `AGENTS.md`
- **3b:** Fila en `CLAUDE.md` "Enforcement gates" table.
  → files: `CLAUDE.md`

### Wave 4: Verificación
- **4a:** `bash test-pre-agent-check.sh` → 6/6 pass.
- **4b:** Regression: `bash test-brainstorm-validator.sh` → 19/19 pass.
- **4c:** Regression: `bash test-sync-validator.sh` → 6/6 pass.
- **4d:** `bash -n` syntax checks.
- **4e:** Smoke test: fabricar Agent invocation con prompt que referencia este spec → exit 0.

## Estimación

| Métrica | Estimación |
|---|---|
| pre-agent-check.sh | +50 lines (Gate 3 block) |
| test-pre-agent-check.sh | +130 lines (6 fixtures + helper + assertions) |
| AGENTS.md | +25 lines (new section + examples) |
| CLAUDE.md | +1 line (row) |
| Total | ~210 |
| Files | 7 (2 modificados + 1 nuevo + 4 artefactos) |

## Done criteria

- [ ] 6 nuevos tests pasan
- [ ] Tests existentes (19+6=25) siguen pasando
- [ ] AGENTS.md documenta nueva sección con ambas formas (inline + reference)
- [ ] CLAUDE.md actualizado
- [ ] Smoke test pass
- [ ] Commit + push
