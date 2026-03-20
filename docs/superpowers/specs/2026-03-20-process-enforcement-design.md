# Spec: Enforcement Mecánico del Flujo CLAUDE.md

**Fecha:** 2026-03-20
**Approach:** C — SessionStart + Gate mejorado + Preflight
**Problema:** 13 de 15 reglas mandatorias de proceso en CLAUDE.md no tienen enforcement mecánico. Solo spec+plan están gateados vía `full-flow-gate.sh`. Las demás dependen de disciplina del agente, que se degrada bajo presión de ejecución.

---

## Evidencia del problema

- Session B (2026-03-20): saltó brainstorming, learning loop, execution log, retrospectiva, finishing skill — aun teniendo CLAUDE.md con anti-racionalizaciones explícitas
- El `full-flow-gate.sh` existente no bloqueó porque `.claude/active-spec` no existía (Check 1 debería haber denegado, pero posiblemente el hook no se ejecutó en el entorno web)

## Diseño: 3 puntos de control

### 1. SessionStart Hook

**Trigger:** Al inicio de cada sesión de Claude Code
**Propósito:** Forzar que Claude clasifique la interacción y declare el flujo ANTES de hacer cualquier cosa.

**Mecanismo:** Un archivo `.claude/hooks/session-start.sh` que:
- Crea/resetea `.claude/session-state.json` con campos requeridos
- El campo `flow_declared` empieza en `false`
- El `full-flow-gate.sh` (mejorado) verificará que `flow_declared` sea `true` antes de permitir edits

**session-state.json schema:**
```json
{
  "session_date": "2026-03-20",
  "flow_type": null,
  "flow_declared": false,
  "learning_loop_done": false,
  "brainstorm_done": false,
  "active_spec": null,
  "active_plan": null,
  "execution_log": null
}
```

**Claude debe:** Antes de cualquier Edit/Write a src/:
1. Leer CLAUDE.md si no lo ha hecho
2. Clasificar la interacción (micro/light/debug/full)
3. Declarar el flujo escribiendo a `.claude/session-state.json`
4. Para full-flow: marcar learning_loop_done, brainstorm_done antes de implementar

### 2. full-flow-gate.sh mejorado

**Checks actuales (mantener):**
1. `.claude/active-spec` existe
2. El spec referenciado existe
3. Plan del día existe en `docs/superpowers/plans/`

**Checks nuevos a añadir:**
4. `.claude/session-state.json` existe y `flow_declared` es `true`
5. Si `flow_type` es `full`: verificar `learning_loop_done` y `brainstorm_done` son `true`
6. Si `flow_type` es `debug`: verificar `learning_loop_done` es `true` (debug-flow requiere consultar retrospectives)

**Bypasses legítimos:**
- Archivos en `docs/`, `tests/`, `config/`, `migrations/`, `.claude/` — no requieren gate (son artefactos de proceso, no código de producción)
- `flow_type` es `micro` o `light` — no requieren spec+plan

### 3. `make preflight` (pre-push validation)

**Script:** `backend/bin/preflight.sh` invocado por `make preflight`
**Momento:** Antes de push (manual o recordado por hook)

**Checks:**
1. `make lint` pasa (0 errores de sintaxis)
2. `php vendor/bin/phpunit --testsuite Unit` no tiene nuevos fallos
3. `docs/codebase-manifest.md` generado hoy (o < 24h)
4. Si hay commits con `feat:` o `fix:` hoy → debe existir `docs/superpowers/execution-logs/YYYY-MM-DD-*.md`
5. Si hay cambios en `src/` → `.claude/session-state.json` debe tener `flow_declared: true`

**Output:** Lista de checks con PASS/FAIL. Exit code 0 si todo pasa, 1 si algo falla.

### 4. PostToolUse en git commit — recordatorio de execution log

**Trigger:** Después de cada `git commit` exitoso
**Acción:** Si no existe `docs/superpowers/execution-logs/YYYY-MM-DD-*.md`, emitir un recordatorio (no bloquear, solo avisar).

---

## Archivos a crear/modificar

| Archivo | Acción |
|---------|--------|
| `.claude/hooks/session-start.sh` | CREAR |
| `.claude/hooks/full-flow-gate.sh` | MODIFICAR (añadir checks 4-6) |
| `.claude/hooks/post-commit-reminder.sh` | CREAR |
| `backend/bin/preflight.sh` | CREAR |
| `Makefile` | MODIFICAR (añadir target `preflight`) |
| `.claude/settings.local.json` | MODIFICAR (añadir SessionStart hook + post-commit) |

## Decisiones de diseño

1. **No bloquear, avisar** en post-commit — bloquear commits sería demasiado agresivo y podría impedir progreso legítimo
2. **Gate solo en src/** — documentación, tests, config son artefactos de proceso que no deben ser bloqueados
3. **session-state.json es efímero** — se resetea cada sesión, no se commitea al repo
4. **Preflight es manual** — `make preflight` se ejecuta antes de push, no es un git hook automático (los hooks de git no están en scope de Claude Code hooks)
