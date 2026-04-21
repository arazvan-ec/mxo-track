---
type: process
tags: []
files_touched: []
patterns: []
outcome: null
outcome_verified_at: null
regressions_later: []
pr_number: null
estimated_lines: null
actual_lines: null
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-04-07 — Análisis de Validators Dormidos + Deuda Oculta

**Type:** exploration
**Branch:** `claude/unify-menu-implementation-Yo8np`

## Contexto

Al implementar autodiscovery en `phase-advance.sh`, se activaron 5 validators que existían pero NUNCA se ejecutaban. Esto reveló deuda oculta en el sistema de enforcement.

## Inventario Completo de Validators

### Validators ACTIVOS (previamente hardcoded, ahora autodiscovery)
| Validator | Gate | Qué valida | Estado |
|-----------|------|-----------|--------|
| consult | HARD (E2) | `decisions_read` OR `logs_scanned` | ✅ Correcto |
| brainstorm | HARD (E2) | turns, alternatives, approval, spec ≥500B, anti-omission | ✅ Correcto |
| planning | HARD (E2) | plan exists, ≥300B, keywords Task/Tarea/etc | ✅ Correcto |
| retrospective | HARD (E2) | execution log con sección Lessons ≥100 chars | ✅ Correcto |

### Validators RECIÉN ACTIVADOS (dormidos hasta autodiscovery)
| Validator | Gate | Qué valida | Problemas |
|-----------|------|-----------|-----------|
| implementation | SOFT (E1) | Plan existe (HARD full-flow) + TDD check | ⚠️ TDD warnings nuevos que antes no existían |
| verification | HARD (E2) | `tests_passed` + `lint_clean` | ✅ Correcto y esencial |
| capture | SOFT (E1) | `execution_log_path` existe | ✅ Correcto |
| finalize | SOFT (E1) | `branch_strategy` + knowledge modules | ⚠️ False positives en knowledge mapping |

### Validators CÓDIGO MUERTO
| Validator | Por qué es dead code | Recomendación |
|-----------|---------------------|---------------|
| debug-validator.sh | No existe fase "debug" en la secuencia de phase-advance.sh. El flujo debug usa `flow_type = "debug"` pero NO pasa por phase-advance | **Integrar**: se invoca desde workflow-engine.sh, no desde phase-advance. Dejar como está — funciona en su contexto original (PreToolUse gate para edits en debug flow) |
| spec-compliance-validator.sh | Nombre no matchea ninguna fase. Autodiscovery busca `${phase}-validator.sh` | **Integrar**: llamarlo desde `brainstorm-validator.sh` o `planning-validator.sh` como sub-check |

## Deuda Oculta Descubierta

### 1. implementation-validator TDD es ahora visible
**Antes:** Nunca se ejecutaba como gate de transición. Solo corría como PreToolUse en workflow-engine.sh cuando se editaba un archivo.
**Ahora:** También se ejecuta al intentar `phase-advance.sh verification`. Usuarios que escriban código sin tests primero verán warnings SOFT al avanzar.
**Impacto:** Bajo (exit 1, no bloquea). Pero es un cambio de comportamiento.

### 2. finalize-validator tiene false positives
**Problema:** Mapea cambios en `frontend/src/` → sugiere actualizar `ui-frontend.md`. Pero un cambio de 1 línea en un import no necesita actualización de knowledge.
**Impacto:** Warnings innecesarios en CADA feature que toque frontend. SOFT gate, no bloquea, pero genera ruido.
**Fix propuesto:** Hacer threshold — solo sugerir si >5 archivos cambiaron en un patrón, o si se añadieron archivos nuevos.

### 3. spec-compliance-validator es bueno pero invisible
**Problema:** Valida que el plan referencie todos los items del inventory del spec. Excelente para anti-omisión. Pero NUNCA se ejecuta porque su nombre no matchea ninguna fase.
**Fix propuesto:** Llamarlo como sub-check dentro de `planning-validator.sh` antes de permitir avanzar a implementation.

### 4. debug-validator NO es dead code realmente
**Aclaración:** Al investigar más, `debug-validator.sh` SÍ se invoca — pero desde `workflow-engine.sh` como PreToolUse gate, no desde phase-advance. Es el gate que bloquea edits de código en debug flow hasta tener root cause + pattern-wide search. Funciona correctamente en su contexto.

### 5. Validators con interfaces inconsistentes
- `debug-validator.sh` y `implementation-validator.sh` aceptan `$2` (FILE_PATH) — son para PreToolUse
- El resto acepta solo `$1` (STATE_FILE) — son para phase transitions
- `phase-advance.sh` llama a TODOS con solo `$1`. Los que esperan `$2` reciben string vacío en `FILE_PATH` y pueden tener comportamiento inesperado

## Retrospectiva de la Sesión Completa (2 interacciones)

### Estimación vs realidad
- Sesión 1 (menú): 50% del tiempo en hook bugs, no en el feature
- Sesión 2 (tests): 3 iteraciones para full-walk porque autodiscovery activó validators que requerían artefactos que los tests no creaban
- La retrospectiva se saltó 2 veces — la 1ra sin gate mecánico, la 2da con gate pero escribí el contenido durante capture (satisfice el gate sin satisfacer el espíritu)

### Qué funcionó bien
- Autodiscovery por convención es el patrón correcto — 6 líneas vs case statement creciente
- Los tests atraparon los problemas de los validators dormidos ANTES de que llegaran a producción
- El subagente en paralelo para análisis de hooks fue eficiente

### Qué falló
- **El retrospective-validator valida contenido pero no timing** — no puede distinguir si se escribió en fase 6 (capture) o fase 7 (retrospective). Fix: comparar mtime del archivo vs timestamp de entrada a fase retrospective
- **Tamaños mínimos (500B spec, 300B plan, 100 chars retro) son fáciles de satisfacer con padding** — validación semántica sería mejor pero es difícil en bash
- **`user_approved` sigue siendo frágil** — funciona cuando el usuario dice "Apruebo" exacto, falla con "Prefiero opción A"

### Patrón recurrente (4ta vez)
Validators que existen pero no se ejecutan es el mismo patrón que "declarar X sin que exista". La solución es autodiscovery, ya aplicada. Pero queda el patrón inverso: validators que se ejecutan en contexto incorrecto (debug-validator llamado desde phase-advance sin FILE_PATH).

## Propuestas de Mejora

### Lección 2: Tests end-to-end
1. **test-full-flow-e2e.sh** — simula consult→...→finalize creando TODOS los artefactos reales que cada validator requiere. Verifica que el flujo completo pasa sin errores.
2. **test-finalize-validator.sh** — tests dedicados para la lógica de knowledge module mapping (false positives).
3. Actualizar `test-phase-advance.sh` Test 8 para crear artefactos completos (spec, plan, log con retrospectiva).

### Lección 3: Autodiscovery + integraciones
1. **Integrar spec-compliance-validator** como sub-check dentro de `planning-validator.sh`
2. **Separar interfaces** — validators para phase-advance (solo `$1`) vs validators para PreToolUse (con `$2`). Convención de nombres: `{phase}-validator.sh` para transitions, `{phase}-gate.sh` para PreToolUse.
3. **Ampliar keywords de aprobación** en `user-prompt-state.sh` — añadir "prefiero", "vamos con", "me gusta"
4. **Threshold en finalize-validator** — solo sugerir knowledge update si >5 archivos cambiaron en un patrón

## Lessons

- Autodiscovery reveló que 5 validators eran "alarmas desconectadas" — encenderlas descubrió deuda oculta (artefactos incompletos, interfaces inconsistentes)
- Los gates mecánicos previenen saltarse pasos, pero no garantizan calidad del contenido. La retrospectiva se puede satisfacer con 100 chars de relleno
- La distinción entre validators de transición (phase-advance) y gates de edición (workflow-engine PreToolUse) necesita formalizarse con convención de nombres
