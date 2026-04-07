# Spec — Workflow Enforcement: 5 Capas Anti-Evasión

**Fecha:** 2026-04-07
**Tipo:** Feature (infrastructure — workflow engine)
**Problema:** Claude fabrica `phase_history`, setea `user_approved = true` sin consentimiento,
salta TDD, y pasa el pre-push gate con evidencia simulada. El workflow tiene un solo punto
de enforcement real (pre-push) y confía en honestidad para todo lo demás.

## Existing Functionality Inventory

| Element | Decision | Justification |
|---------|----------|---------------|
| `workflow-engine.sh` (PreToolUse:Edit\|Write) | Include — extender | Añadir prerequisite gates (Capa 2) |
| `auto-evidence.sh` (PostToolUse:*) | Include — extender | Añadir detección de phase transitions (Capa 1) |
| `user-prompt-state.sh` (UserPromptSubmit) | Include — extender | Añadir detección de user approval (Capa 3) |
| `pre-push-gate.sh` (PreToolUse:Bash) | Include — extender | Añadir cross-validation (Capa 5) |
| `post-commit-validator.sh` (PostToolUse:Bash) | Keep as-is | No necesita cambios |
| `workflow-status-line.sh` (PostToolUse:*) | Keep as-is | No necesita cambios |
| `session-start.sh` (SessionStart) | Keep as-is | No necesita cambios |
| 10 validators en `validators/` | Include — algunos se endurecen | SOFT→HARD donde se indica |
| `settings.json` hooks config | Include — añadir nuevo hook | Registrar phase-transition-controller |

## Omission Decisions

| Element | Decision | Justification |
|---------|----------|---------------|
| `post-push-validator.sh` | Omit | No relevante para enforcement de flujo |
| `workflow-status.sh` | Omit | Deprecated/unused |
| Test scripts (`test-*.sh`) | Omit | Test infrastructure, no enforcement |

---

## Capa 1 — Phase Transition Controller

**Problema:** Claude puede escribir `phase_history = [...]` directamente, fabricando el historial.

**Solución:** Un hook `PostToolUse:Bash` que intercepta escrituras `jq` a `session-state.json`.

**Mecanismo:**
1. Hook detecta `jq` + `session-state.json` en el comando Bash
2. Lee el state ANTES y DESPUÉS de la escritura
3. Si `phase_history` cambió, valida:
   - Solo puede crecer (append-only, no rewrite)
   - La fase añadida debe ser la siguiente legal en la secuencia
   - Añade timestamp automáticamente: `{"phase": "brainstorm", "at": "2026-04-07T10:23:00"}`
4. Si la validación falla, revierte el cambio y emite warning

**Secuencia legal:**
```
consult → brainstorming → planning → implementation → verification → capture → retrospective → finalize
```

**Cambios en formato de phase_history:**
```json
// Antes (strings manipulables):
"phase_history": ["consult", "brainstorming"]

// Después (objetos con timestamp):
"phase_history": [
  {"phase": "consult", "at": "2026-04-07T10:20:00"},
  {"phase": "brainstorming", "at": "2026-04-07T10:25:00"}
]
```

**Nuevo comando para avanzar fase:**
Claude debe usar `phase-advance <next_phase>` en vez de escribir `jq` directamente.
El script `phase-advance.sh` valida la transición y es el único que escribe `phase_history`.

**Archivos:**
- NUEVO: `.claude/hooks/phase-transition-controller.sh` (PostToolUse:Bash)
- NUEVO: `.claude/hooks/phase-advance.sh` (CLI command)
- MODIFICAR: `.claude/settings.json` (registrar nuevo hook)
- MODIFICAR: `pre-push-gate.sh` (leer nuevo formato phase_history)

---

## Capa 2 — Prerequisite Gates (Edit/Write validators)

**Problema:** Los validators son SOFT (exit 1 = warn). Claude puede ignorar los warnings.

**Solución:** Endurecer validators críticos a HARD (exit 2 = deny) y añadir checks de existencia real.

**Cambios por validator:**

| Validator | Ahora | Después | Check añadido |
|-----------|-------|---------|---------------|
| `consult-validator.sh` | SOFT | **HARD** | Sin cambio en lógica |
| `brainstorm-validator.sh` | SOFT | **HARD** | Verificar spec file existe Y >500B |
| `planning-validator.sh` | SOFT | **HARD** | Verificar plan file existe Y >300B |
| `implementation-validator.sh` | SOFT | **HARD** para plan; SOFT para TDD | Sin cambio en lógica |
| `verification-validator.sh` | HARD | HARD | Sin cambio |

**Concepto clave:** Los validators ya son buenos — solo falta cambiar `exit 1` por `exit 2`
en los checks críticos. La lógica de verificación ya existe.

**Archivos:**
- MODIFICAR: `validators/consult-validator.sh`
- MODIFICAR: `validators/brainstorm-validator.sh`
- MODIFICAR: `validators/planning-validator.sh`
- MODIFICAR: `validators/implementation-validator.sh`

---

## Capa 3 — User Approval via Hook

**Problema:** Claude puede escribir `user_approved = true` sin que el usuario haya dicho nada.

**Solución:** Extender `user-prompt-state.sh` para detectar patrones de aprobación en el texto
del usuario y solo entonces setear `user_approved = true`.

**Patrones de aprobación (español + inglés):**
```
sí|si|yes|ok|dale|adelante|aprobado|apruebo|perfecto|de acuerdo|
estoy de acuerdo|me parece bien|prefiero|vamos con|go ahead|approved|lgtm
```

**Patrones de rechazo (resetean approval):**
```
no|cambia|modifica|diferente|otra opción|no me convence|rechaz
```

**Anti-evasión:** `auto-evidence.sh` ya detecta writes a session-state. Añadir:
si Claude escribe `user_approved = true` via jq (no via el hook), el phase-transition-controller
lo detecta y lo revierte.

**Archivos:**
- MODIFICAR: `user-prompt-state.sh` (añadir detección de approval)
- MODIFICAR: `phase-transition-controller.sh` (anti-evasión de user_approved)

---

## Capa 4 — TDD Order Tracking

**Problema:** Claude escribe código sin tests primero.

**Solución:** Extender `auto-evidence.sh` para trackear orden de edición por tarea.

**Mecanismo:**
1. Cuando `task_progress.current` cambia → resetear `tdd_edits` tracker
2. Cada Edit/Write a `src/` → registrar en `tdd_edits`
3. Cada Edit/Write a `tests/` → registrar en `tdd_edits`
4. `implementation-validator.sh` verifica: si hay edits a `src/` sin edits previos a `tests/`
   en la tarea actual → SOFT warning

**Estado en session-state.json:**
```json
"evidence": {
  "tdd_tracker": {
    "current_task": 1,
    "edits": [
      {"file": "tests/FooTest.php", "at": "2026-04-07T10:30:00", "type": "test"},
      {"file": "src/Foo.php", "at": "2026-04-07T10:32:00", "type": "src"}
    ]
  }
}
```

**Nivel:** SOFT warning (no HARD). TDD no siempre es posible (frontend visual, config files).
Pero el warning es visible y registrado.

**Archivos:**
- MODIFICAR: `auto-evidence.sh` (trackear edit order)
- MODIFICAR: `validators/implementation-validator.sh` (check TDD order)

---

## Capa 5 — Pre-push Cross-validation

**Problema:** Pre-push gate verifica strings en JSON pero no realidad.

**Solución:** Verificar que la evidencia corresponde a artefactos reales.

**Checks añadidos:**

| Check | Implementación |
|-------|---------------|
| phase_history timestamps cronológicos | Verificar que timestamps son ascendentes y tienen separación >30s |
| spec_path → archivo real | Ya existe parcialmente; asegurar >500B |
| plan_path → archivo real | Ya existe parcialmente; asegurar >300B |
| execution_log_path → archivo real >500B | Ya existe; mantener |
| make manifest ejecutado | Comparar timestamp de `docs/codebase-manifest.md` vs último commit |
| decisions log actualizado | `git diff origin/main...HEAD -- docs/decisions/log.md` tiene contenido (SOFT) |
| phase_history no fabricado | Verificar que entries tienen formato `{"phase": "...", "at": "..."}`, no strings planos |

**Archivos:**
- MODIFICAR: `pre-push-gate.sh`

---

## Integración: Orden de ejecución de hooks

### PostToolUse:Bash (orden importa):
1. `phase-transition-controller.sh` — valida/revierte manipulación de state (**NUEVO**)
2. `auto-evidence.sh` — detecta evidencia de herramientas (ya existe)
3. `workflow-status-line.sh` — actualiza display (ya existe)
4. `post-commit-validator.sh` — valida commits (ya existe)
5. `post-push-validator.sh` — post-push tasks (ya existe)

### UserPromptSubmit:
1. `user-prompt-state.sh` — con detección de approval (**MODIFICADO**)

### PreToolUse:Edit|Write:
1. `workflow-engine.sh` → validators HARD (**MODIFICADO: SOFT→HARD**)

### PreToolUse:Bash:
1. `pre-push-gate.sh` — con cross-validation (**MODIFICADO**)
