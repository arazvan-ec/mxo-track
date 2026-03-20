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

---

## 5. Mejora de Self-Gating (eliminar el problema de "prisionero = carcelero")

### Problema

El sistema actual tiene un defecto fundamental: Claude establece los flags (`brainstorm_done=true`, `learning_loop_done=true`) que gatean su propio comportamiento. No hay verificación independiente de que esos flags reflejen trabajo real.

### Solución: 3 niveles combinados

#### Nivel 1 — Evidencia verificable (reemplaza flags booleanos)

En vez de confiar en `brainstorm_done: true`, el gate verifica que los artefactos existan y tengan contenido mínimo real.

**Cambios en `full-flow-gate.sh`:**

```bash
# Para brainstorm_done: verificar que el spec tenga contenido real
SPEC_PATH=$(tr -d '[:space:]' < "$ACTIVE_SPEC_FILE")
SPEC_FULL="$REPO/$SPEC_PATH"

# Mínimo 500 bytes (un spec vacío o stub no pasa)
if [ -f "$SPEC_FULL" ]; then
  SPEC_SIZE=$(wc -c < "$SPEC_FULL")
  if [ "$SPEC_SIZE" -lt 500 ]; then
    deny "SELF-GATE: Spec file is only ${SPEC_SIZE} bytes. A real brainstorm produces >500 bytes."
  fi
fi

# Debe contener secciones clave de un brainstorming real
if ! grep -qi "approach\|alternativa\|trade-off\|problema" "$SPEC_FULL"; then
  deny "SELF-GATE: Spec missing required brainstorming sections (Approach/Alternativas/Trade-offs/Problema)."
fi

# Para plan: verificar contenido mínimo y estructura
PLAN_FILE=$(ls "$PLANS_DIR"/${TODAY}-*.md 2>/dev/null | head -1)
if [ -n "$PLAN_FILE" ]; then
  PLAN_SIZE=$(wc -c < "$PLAN_FILE")
  if [ "$PLAN_SIZE" -lt 300 ]; then
    deny "SELF-GATE: Plan file is only ${PLAN_SIZE} bytes. A real plan produces >300 bytes."
  fi
  if ! grep -qi "task\|step\|archivo\|file" "$PLAN_FILE"; then
    deny "SELF-GATE: Plan missing required structure (Task/Step/File references)."
  fi
fi
```

**Qué resuelve:** Claude no puede crear stubs vacíos y pasar el gate.
**Qué NO resuelve:** Claude podría escribir contenido basura de 500+ bytes (pero esto ya es mucho más esfuerzo que saltarse el proceso, así que el incentivo se invierte).

#### Nivel 2 — Validación temporal anti-rush

Verifica que pasó tiempo suficiente entre pasos — no se puede hacer brainstorming + spec + plan + code en 30 segundos.

**Cambios en `session-state.json` schema:**

```json
{
  "session_date": "2026-03-20",
  "flow_type": null,
  "flow_declared": false,
  "flow_declared_at": null,
  "learning_loop_done": false,
  "learning_loop_done_at": null,
  "brainstorm_done": false,
  "brainstorm_done_at": null,
  "active_spec": null,
  "active_plan": null,
  "execution_log": null
}
```

**Cambios en `full-flow-gate.sh`:**

```bash
# Brainstorm debe haberse completado hace >120 segundos
BRAINSTORM_TS=$(jq -r '.brainstorm_done_at // empty' "$STATE_FILE")
if [ -n "$BRAINSTORM_TS" ]; then
  BRAINSTORM_EPOCH=$(date -d "$BRAINSTORM_TS" +%s 2>/dev/null || echo 0)
  NOW=$(date +%s)
  ELAPSED=$(( NOW - BRAINSTORM_EPOCH ))
  if [ "$ELAPSED" -lt 120 ]; then
    deny "SELF-GATE: Brainstorm completed only ${ELAPSED}s ago. Minimum 2 minutes between brainstorm completion and code edit (ensures real deliberation)."
  fi
fi
```

**Qué resuelve:** Claude no puede rushear todos los flags en un solo turno.

#### Nivel 3 — Coherencia plan↔edit

El hook verifica que el archivo que se quiere editar esté mencionado en el plan activo. No se puede editar archivos que no fueron planificados.

**Cambios en `full-flow-gate.sh`:**

```bash
# El archivo que se edita debe estar mencionado en el plan
PLAN_FILE=$(ls "$PLANS_DIR"/${TODAY}-*.md 2>/dev/null | head -1)
if [ -n "$PLAN_FILE" ] && [ "$FLOW_TYPE" = "full" ]; then
  BASENAME=$(basename "$FILE_PATH")
  # Buscar el nombre del archivo o su path relativo en el plan
  if ! grep -qi "$BASENAME" "$PLAN_FILE"; then
    deny "SELF-GATE: File '${BASENAME}' not mentioned in today's plan (${PLAN_FILE}). Update the plan first to include this file, or edit a planned file."
  fi
fi
```

**Qué resuelve:** El código editado debe ser coherente con lo planificado. Fuerza a actualizar el plan antes de desviarse.

### **[DESCARTADO]** Nivel 4 — Human-in-the-loop gate

Evaluamos un nivel donde `brainstorm_approved` solo puede ser escrito por el usuario (con un guard hook que bloquea a Claude de modificar `human_state`). **Descartado por ahora** porque añade fricción manual al usuario sin proporcional beneficio — los niveles 1-3 ya eliminan los atajos triviales. Se puede añadir en el futuro si los niveles 1-3 resultan insuficientes.

### **[DESCARTADO]** Nivel 5 — Hash chain de artefactos

Hash de cada artefacto para detectar modificaciones post-aprobación. **Descartado** por over-engineering — Claude genera el hash original, así que no añade seguridad real sobre los niveles 1-3.

### Resumen de cambios

| Archivo | Cambio |
|---------|--------|
| `.claude/hooks/full-flow-gate.sh` | Añadir checks de evidencia (tamaño, secciones), anti-rush (timestamps), y coherencia plan↔edit |
| `.claude/hooks/session-start.sh` | Añadir campos `*_at` (timestamps) al schema de session-state.json |

### Impacto esperado

- **Self-gating eliminado para el path crítico:** ya no basta con escribir `true` — hay que producir artefactos reales con contenido verificable
- **Incentivo invertido:** Es más fácil seguir el proceso que intentar engañar los 3 niveles de verificación
- **Zero friction adicional para el usuario:** todos los checks son automáticos en el hook
